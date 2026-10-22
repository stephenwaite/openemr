<?php

/**
 * cmsvt:rri-250, step 6: gives Medicare patients' Medigap secondaries their
 * Medigap id (file242), then an encounter and billing line for each charge in
 * the ordfile, ready for the coding page. Charges it can't bill go to
 * errfile240. Running it again on the same ordfile bills only what wasn't.
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

use Cmsvt\OpenEMR\Modules\RriBilling\Billing\DatabaseChargeBilling;
use Cmsvt\OpenEMR\Modules\RriBilling\Billing\PlaceOfService;
use Cmsvt\OpenEMR\Modules\RriBilling\Globals\RriSettings;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\DatabaseAccountFile;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\DatabaseOrdfileSource;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\OrdfileLine;
use Cmsvt\OpenEMR\Modules\RriBilling\Step\ReportFiles;
use Cmsvt\OpenEMR\Modules\RriBilling\Step\StepSix;
use OpenEMR\BC\ServiceContainer;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\Services\EncounterService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class Rri250Command extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('cmsvt:rri-250')
            ->setDescription('Step 6: creates the encounters and billing lines for the coding page')
            ->addUsage('--site=1600')
            ->addOption('site', null, InputOption::VALUE_REQUIRED, 'Name of site', 'default')
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Ordfile name; without it (or --date) the newest ordfile')
            ->addOption('date', 'd', InputOption::VALUE_REQUIRED, 'The newest ordfile made on this day, Y-m-d');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $clock = ServiceContainer::getClock();
        $settings = new RriSettings(OEGlobalsBag::getInstance(), ServiceContainer::getCrypto());
        $ordfile = OrdfileOption::select($input, $io, new DatabaseOrdfileSource($clock));
        if ($ordfile === null) {
            return Command::INVALID;
        }

        $step = new StepSix(
            new DatabaseAccountFile(),
            new DatabaseChargeBilling(new EncounterService()),
            $settings->readingDoctorCode(),
            $settings->readingDoctorUser(),
            $settings->facility(...),
        );
        $askPlace = static function (OrdfileLine $line) use ($io): PlaceOfService {
            $io->error('Unknown Place of Service for MRN ' . $line->ord8 . ' with IOPAT of ' . $line->iopat);
            $answer = $io->choice('Place of Service to use', ['21', '22', '23'], '22');
            return PlaceOfService::tryFrom(is_numeric($answer) ? (int) $answer : 0) ?? PlaceOfService::OutpatientHospital;
        };
        $io->progressStart(count($ordfile->lines));
        $result = $step->run($ordfile, $askPlace, static function () use ($io): void {
            $io->progressAdvance();
        });
        $io->progressFinish();

        $files = new ReportFiles($io, $settings->errorFileDirectory(), $clock->now()->format('m-d-y-H-i'));
        if ($result->file242 !== []) {
            $io->info('Medigap secondaries');
            $files->write('file242', $result->file242, true);
        }
        if ($result->errors !== []) {
            $io->warning('Charges not billed');
            $files->write('errfile240', $result->errors, true);
        }
        if ($result->paired > 0) {
            $io->info($result->paired . ' charges and cancels left out in pairs (see step 4\'s file222)');
        }
        if ($result->billedBefore > 0) {
            $io->info($result->billedBefore . ' charges were billed by an earlier run');
        }
        $io->info('Billing records created. Coding can now be performed on the website');
        $io->info('congrats, you are ready to code ' . $result->billed . ' charges!');
        $output->writeln('completed successfully');
        return Command::SUCCESS;
    }
}
