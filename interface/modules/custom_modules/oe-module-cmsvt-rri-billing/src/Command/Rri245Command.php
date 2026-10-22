<?php

/**
 * cmsvt:rri-245, step 4: checks the ordfile step 3 made and writes what the
 * biller should look at: file222 (dangling credits), file224 (CDMs to review)
 * and file245 (referring doctors, unread studies, undefined procedures, the
 * same charge twice). It changes nothing.
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

use Cmsvt\OpenEMR\Modules\RriBilling\Globals\RriSettings;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\DatabaseAccountFile;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\DatabaseReferringDoctors;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\ProcFileChargeMaster;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\DatabaseOrdfileSource;
use Cmsvt\OpenEMR\Modules\RriBilling\Review\ChargeChecks;
use Cmsvt\OpenEMR\Modules\RriBilling\Review\DanglingCredits;
use Cmsvt\OpenEMR\Modules\RriBilling\Review\OrdfileReview;
use Cmsvt\OpenEMR\Modules\RriBilling\Step\ConsolePrompts;
use Cmsvt\OpenEMR\Modules\RriBilling\Step\ReportFiles;
use OpenEMR\BC\ServiceContainer;
use OpenEMR\Core\OEGlobalsBag;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class Rri245Command extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('cmsvt:rri-245')
            ->setDescription('Step 4: checks for dangling credits, CDMs to review, referring doctors and duplicate charges')
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
        $accounts = new DatabaseAccountFile();
        $files = new ReportFiles($io, $settings->errorFileDirectory(), $clock->now()->format('m-d-y-H-i'));

        $io->info('Any dangling credits to delete?');
        $credits = (new DanglingCredits($accounts))->check($ordfile);
        foreach ($credits->warnings as $warning) {
            $io->error($warning);
        }
        if ($credits->file222 !== []) {
            $files->write('file222', $credits->file222, true);
            $io->info('dangling credits');
        }

        $review = OrdfileReview::cdmsToReview($credits->remaining);
        if ($review !== []) {
            $io->info("These are the cdms that we used to delete. We'd like to view them all for now to see what they are."
                . ' Maybe RRMC can help us eliminate the ones we don\'t need?');
            $files->write('file224', $review, true);
        }

        $io->info('Checking data entry from report for errors...');
        $checks = new ChargeChecks($accounts, new DatabaseReferringDoctors(), new ProcFileChargeMaster(), new ConsolePrompts($io));
        $file245 = $checks->check($credits->remaining, OrdfileReview::firstChargePerAccount($credits->remaining));
        if ($file245 !== []) {
            $io->info('these can be the charges that need 76 or 77 mod');
            $files->write('file245', $file245, true);
        }
        $output->writeln('completed successfully');
        return Command::SUCCESS;
    }
}
