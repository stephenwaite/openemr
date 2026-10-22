<?php

/**
 * cmsvt:rri-248, step 3: reads the charge file, sets up each patient's account
 * and insurances, writes the ordfile, and updates the patients.
 *
 * Writes ordfiles/ordfile-<time> (and its database rows), and errors/
 * errfile248-<time> (smart edits: POS, policy numbers ...) and err248-<time>
 * (bad insurance information from RRMC) when there is anything to report.
 * 7.0.2 stopped after writing the first error file; both are written here.
 *
 * --dry-run makes the ordfile and error files (named ...-dry-run; the ordfile
 * not in the database) without saving anything; 7.0.2's --dry-run saved
 * everything anyway.
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

use Cmsvt\OpenEMR\Modules\RriBilling\Account\AccountBuilder;
use Cmsvt\OpenEMR\Modules\RriBilling\Account\InsuranceRules;
use Cmsvt\OpenEMR\Modules\RriBilling\Account\StepThreeException;
use Cmsvt\OpenEMR\Modules\RriBilling\Account\StepThreeOptions;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeFileException;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeFileReader;
use Cmsvt\OpenEMR\Modules\RriBilling\Globals\RriSettings;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\DatabaseAccountFile;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\DatabasePatientInsuranceHistory;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\DatabaseReferringDoctors;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\HospFilePayerDirectory;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\ProcFileChargeMaster;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\DatabaseStepThreeFiles;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\OrdfileBuilder;
use Cmsvt\OpenEMR\Modules\RriBilling\Patient\PatientUpdater;
use Cmsvt\OpenEMR\Modules\RriBilling\Rules\ChargeRules;
use Cmsvt\OpenEMR\Modules\RriBilling\Step\ConsolePrompts;
use Cmsvt\OpenEMR\Modules\RriBilling\Step\DryRunReferringDoctors;
use Cmsvt\OpenEMR\Modules\RriBilling\Step\StepThree;
use OpenEMR\BC\ServiceContainer;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\Services\InsuranceService;
use OpenEMR\Services\ListService;
use OpenEMR\Services\PatientNameHistoryService;
use OpenEMR\Services\PatientService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class Rri248Command extends Command
{
    private const MISC_DEFAULT = '95';

    protected function configure(): void
    {
        $this
            ->setName('cmsvt:rri-248')
            ->setDescription('Step 3: sets up patients, insurance and referring doctors from the charge file and writes the ordfile')
            ->addUsage('--site=1600')
            ->addUsage('--site=1600 --file=radb_eisemannbilling261020.txt --date=10212026 --dry-run')
            ->addOption('site', null, InputOption::VALUE_REQUIRED, 'Name of site', 'default')
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Charge file; without it the newest file in the charges folder')
            ->addOption('date', 'd', InputOption::VALUE_REQUIRED, 'Report date, MMDDYYYY (default today)')
            ->addOption('resume', 'r', InputOption::VALUE_REQUIRED, 'MRN of the patient to start at; patients before it are skipped')
            ->addOption('y-primary', null, InputOption::VALUE_REQUIRED, 'With --yes: insurance company id for a miscellaneous primary (default 95)')
            ->addOption('y-2ndary', null, InputOption::VALUE_REQUIRED, 'With --yes: insurance company id for a miscellaneous secondary (default 95)')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Answer confirmations with yes and use the defaults above')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Make the ordfile and error lists without saving anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $clock = ServiceContainer::getClock();
        $settings = new RriSettings(OEGlobalsBag::getInstance(), ServiceContainer::getCrypto());
        $payers = new HospFilePayerDirectory();
        $dryRun = $input->getOption('dry-run') === true;
        $assumeYes = $input->getOption('yes') === true;

        $file = $this->chargeFile($input, $settings);
        if ($file === null) {
            $io->error('No charge file: give --file, or fetch one with step 1 (cmsvt:qqq).');
            return Command::INVALID;
        }
        $dateOption = $input->getOption('date');
        $reportDate = is_string($dateOption) ? \DateTimeImmutable::createFromFormat('!mdY', $dateOption) : $clock->now();
        if ($reportDate === false || (is_string($dateOption) && $reportDate->format('mdY') !== $dateOption)) {
            $io->error('The report date must be MMDDYYYY.');
            return Command::INVALID;
        }
        $defaults = [];
        foreach (['y-primary', 'y-2ndary'] as $option) {
            $value = $input->getOption($option);
            $value = is_string($value) ? $value : ($assumeYes ? self::MISC_DEFAULT : null);
            if ($value !== null && (!ctype_digit($value) || $payers->companyById((int) $value) === null)) {
                $io->error('--' . $option . ' ' . $value . ' is not an insurance company on this site.');
                return Command::INVALID;
            }
            $defaults[] = $value === null ? null : (int) $value;
        }
        $resume = $input->getOption('resume');

        try {
            $accounts = (new ChargeFileReader($clock))->readFile($file);
        } catch (ChargeFileException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
        $accounts = (new ChargeRules(new ProcFileChargeMaster()))->apply($accounts)->accounts;

        $insurance = new InsuranceService();
        $doctors = new DatabaseReferringDoctors();
        $builder = new AccountBuilder(
            $payers,
            new DatabaseAccountFile(),
            new DatabasePatientInsuranceHistory($insurance),
            $dryRun ? new DryRunReferringDoctors($doctors) : $doctors,
            new InsuranceRules(),
            new ConsolePrompts($io),
            new StepThreeOptions($assumeYes, $defaults[0], $defaults[1]),
            $clock
        );
        $step = new StepThree(
            $builder,
            new DatabaseAccountFile(),
            new OrdfileBuilder($settings->readingDoctorCode()),
            new DatabaseStepThreeFiles(),
            new PatientUpdater(new PatientService(), $insurance, new PatientNameHistoryService(), new ListService(), $clock),
            $clock,
            $dryRun
        );

        $io->info(sprintf('Step 3 for %s (%d accounts), report date %s%s', basename($file), count($accounts), $reportDate->format('m/d/Y'), $dryRun ? ', dry run: nothing is saved' : ''));
        $io->progressStart(count($accounts));
        try {
            $result = $step->run($accounts, $reportDate, is_string($resume) ? $resume : null, static function () use ($io): void {
                $io->progressAdvance();
            });
        } catch (StepThreeException $e) {
            $io->progressFinish();
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
        $io->progressFinish();
        $io->info('RRMC DATA FILE LOAD HAS ENDED');

        $stamp = $clock->now()->format('m-d-y-H-i') . ($dryRun ? '-dry-run' : '');
        $ordfileName = 'ordfile-' . $stamp;
        $lines = $result->sortedLines();
        $ordfilePath = $this->write($io, $settings->ordFileDirectory(), $ordfileName, array_map(static fn($line): string => $line->toText(), $lines));
        if ($ordfilePath !== null && !$dryRun) {
            $files = new DatabaseStepThreeFiles();
            $ordfileId = $files->createOrdfile($ordfilePath);
            foreach ($lines as $line) {
                $files->saveOrdfileLine($ordfileId, $line);
            }
        }
        $io->info(sprintf('%d patients, %d ordfile lines in %s', $result->patients, count($lines), $ordfileName));

        $failed = false;
        foreach ([['errfile248-', $result->errors, 'our smart edits like POS and policy #s'], ['err248-', $result->insuranceErrors, 'bad insurance info from rrmc']] as [$prefix, $lines, $title]) {
            if ($lines === []) {
                $io->info($prefix . $stamp . ' was not created as there were no errors');
                continue;
            }
            $failed = true;
            $io->section($title);
            $io->writeln($lines);
            $this->write($io, $settings->errorFileDirectory(), $prefix . $stamp, $lines);
        }
        return $failed || $ordfilePath === null ? Command::FAILURE : Command::SUCCESS;
    }

    /** The --file path, or the newest file in the charges folder. */
    private function chargeFile(InputInterface $input, RriSettings $settings): ?string
    {
        $option = $input->getOption('file');
        if (is_string($option) && $option !== '') {
            if (str_starts_with($option, '/') || is_file($option)) {
                return $option;
            }
            return $settings->chargeFileDirectory() . $option;
        }
        $newest = null;
        $newestTime = -1;
        foreach (glob($settings->chargeFileDirectory() . '*') ?: [] as $path) {
            $time = is_file($path) ? filemtime($path) : false;
            if ($time !== false && $time > $newestTime) {
                $newest = $path;
                $newestTime = $time;
            }
        }
        return $newest;
    }

    /**
     * @param list<string> $lines
     * @return ?string the file's path, or null when it couldn't be written
     */
    private function write(SymfonyStyle $io, string $directory, string $name, array $lines): ?string
    {
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            $io->error('Could not create ' . $directory);
            return null;
        }
        $path = $directory . $name;
        if (file_put_contents($path, $lines === [] ? '' : implode("\n", $lines) . "\n") === false) {
            $io->error('Could not write ' . $path);
            return null;
        }
        $io->info('Created ' . $path);
        return $path;
    }
}
