<?php

/**
 * cmsvt:qqq, step 1: fetches the day's charge file from RRMC's SFTP server
 * (or takes --file), then checks it: RRMC's charge rules and the same patient
 * with different primaries (errfile008, asks whether to go on), and
 * insurances that can't be billed electronically (errfile007). The file is
 * touched so step 3 takes it as the newest.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Command;

use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeFileException;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeFileReader;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\FetchOutcome;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\SftpChargeFileFetcher;
use Cmsvt\OpenEMR\Modules\RriBilling\Globals\RriSettings;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\HospFilePayerDirectory;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\ProcFileChargeMaster;
use Cmsvt\OpenEMR\Modules\RriBilling\Step\ReportFiles;
use Cmsvt\OpenEMR\Modules\RriBilling\Step\StepOne;
use OpenEMR\BC\ServiceContainer;
use OpenEMR\Common\Crypto\CryptoGenException;
use OpenEMR\Core\OEGlobalsBag;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class QqqCommand extends Command
{
    private const MAX_FETCH_TRIES = 5;

    protected function configure(): void
    {
        $this
            ->setName('cmsvt:qqq')
            ->setDescription('Step 1: fetches the charge file and checks CDM/CPT/HCPCS and insurance issues')
            ->addUsage('--site=1600')
            ->addUsage('--site=1600 --date=102026')
            ->addOption('site', null, InputOption::VALUE_REQUIRED, 'Name of site', 'default')
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'A charge file already here (in the charges folder, or a path); nothing is fetched')
            ->addOption('date', 'd', InputOption::VALUE_REQUIRED, 'Day of the charge file to fetch, MMDDYY; asked when not given');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $clock = ServiceContainer::getClock();
        $settings = new RriSettings(OEGlobalsBag::getInstance(), ServiceContainer::getCrypto());

        $file = $input->getOption('file');
        if (is_string($file) && $file !== '') {
            $path = str_starts_with($file, '/') || is_file($file) ? $file : $settings->chargeFileDirectory() . $file;
        } else {
            $path = $this->fetch($input, $io, $settings, $clock->now());
            if ($path === null) {
                return Command::FAILURE;
            }
        }
        if (!is_readable($path)) {
            $io->error('Cannot read ' . $path . '. Check it exists and can be read.');
            return Command::INVALID;
        }
        if (!touch($path)) {
            $io->error('Cannot update the time of ' . $path . '; give step 3 its name with --file.');
            return Command::FAILURE;
        }

        try {
            $accounts = (new ChargeFileReader($clock))->readFile($path);
        } catch (ChargeFileException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
        [$errfile008, $errfile007] = (new StepOne(new ProcFileChargeMaster(), new HospFilePayerDirectory()))->check($accounts);
        $files = new ReportFiles($io, $settings->errorFileDirectory(), $clock->now()->format('m-d-y-H-i'));

        $io->info('Checking cdm/cpt/hcpcs issues (rrmc008) and visit charge insurance issues (visitno-load)');
        if ($errfile008 !== []) {
            $io->error('potential cdm to cpt/hcpcs issues. Total errors ' . count($errfile008));
            $files->write('errfile008', $errfile008, true);
            if (!$io->confirm('ok to proceed?', true)) {
                $io->info('exiting until fixed');
                return Command::FAILURE;
            }
        }
        if ($errfile007 !== []) {
            $io->info('Creating errfile007 for paper claim and/or self pays, can check self pays for 004 elig, mrn at end');
            $files->write('errfile007', $errfile007, true);
            return Command::FAILURE;
        }
        $output->writeln('qqq completed successfully');
        return Command::SUCCESS;
    }

    /** The day's charge file in the charges folder, fetched unless it is there already; null after saying why not. */
    private function fetch(InputInterface $input, SymfonyStyle $io, RriSettings $settings, \DateTimeImmutable $today): ?string
    {
        try {
            $sftp = $settings->sftp();
        } catch (CryptoGenException) {
            $io->error('The SFTP password cannot be read. Enter it again in the RRI Billing settings.');
            return null;
        }
        if ($sftp === null) {
            $io->error('Set the SFTP host, username and password in the RRI Billing settings, or give the file with --file.');
            return null;
        }
        $date = $input->getOption('date');
        $day = is_string($date) ? self::day($date) : null;
        if (is_string($date) && $day === null) {
            $io->error('The date must be MMDDYY.');
            return null;
        }
        if ($day === null) {
            $answer = $io->ask(
                'What day would you like from ' . $sftp->host . '? MMDDYY format please, thank you.',
                $today->format('mdy'),
                static fn(mixed $value): \DateTimeImmutable => (is_string($value) ? self::day($value) : null)
                    ?? throw new \RuntimeException('You must type a valid date in the format MMDDYY')
            );
            if (!$answer instanceof \DateTimeImmutable) {
                return null;
            }
            $day = $answer;
        }

        $directory = $settings->chargeFileDirectory();
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            $io->error('Could not create ' . $directory);
            return null;
        }
        $name = $settings->chargeFileName($day);
        $path = $directory . $name;
        if (is_file($path) && !$io->confirm('Local file ' . $name . ' already exists. Do you wish to fetch it again?', false)) {
            return $path;
        }
        $fetcher = new SftpChargeFileFetcher($sftp);
        for ($try = 1; $try <= self::MAX_FETCH_TRIES; $try++) {
            $outcome = $fetcher->fetch($name, $path);
            switch ($outcome) {
                case FetchOutcome::Fetched:
                    $io->info('Fetched file to location ' . $path);
                    return $path;
                case FetchOutcome::NotOnServer:
                    $io->error($sftp->remotePath($name) . ' does not exist on SFTP server ' . $sftp->host . ':' . $sftp->port);
                    return null;
                case FetchOutcome::LoginFailed:
                    $io->error('Invalid SFTP username/password combination');
                    break;
                case FetchOutcome::Failed:
                    $io->error('An error occurred in retrieving the file from the SFTP server. Please check your module configuration');
                    break;
            }
            if (!$io->confirm('Do you want to try and fetch the file again?', true)) {
                return null;
            }
        }
        $io->error('Maximum attempts to retrieve remote file exceeded. Check connection settings and rerun the script');
        return null;
    }

    private static function day(string $mmddyy): ?\DateTimeImmutable
    {
        $day = \DateTimeImmutable::createFromFormat('!mdy', $mmddyy);
        return $day !== false && $day->format('mdy') === $mmddyy ? $day : null;
    }
}
