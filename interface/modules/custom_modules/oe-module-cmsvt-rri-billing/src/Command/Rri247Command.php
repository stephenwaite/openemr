<?php

/**
 * cmsvt:rri-247, step 5: shows each account's insurances and relate codes,
 * twenty to a page, writes the w2 listing, then shows the accounts whose
 * subscriber or relate code doesn't match the guarantor (rri247x).
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
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\DatabaseOrdfileSource;
use Cmsvt\OpenEMR\Modules\RriBilling\Review\OrdfileReview;
use Cmsvt\OpenEMR\Modules\RriBilling\Review\RelateReview;
use Cmsvt\OpenEMR\Modules\RriBilling\Step\ReportFiles;
use OpenEMR\BC\ServiceContainer;
use OpenEMR\Core\OEGlobalsBag;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class Rri247Command extends Command
{
    private const PAGE = 20;

    protected function configure(): void
    {
        $this
            ->setName('cmsvt:rri-247')
            ->setDescription('Step 5: shows insurance information and relate codes, and names that don\'t match')
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

        $io->info('RRI-247 Displaying medical records, insurance information and relate codes');
        $result = (new RelateReview(new DatabaseAccountFile()))->review(OrdfileReview::firstChargePerAccount($ordfile->lines));
        foreach ($result->missing as $actno) {
            $io->info('BAD ' . $actno);
        }
        foreach (array_chunk($result->rows, self::PAGE) as $page) {
            $table = [];
            $previous = null;
            foreach ($page as $row) {
                if ($previous !== null && $previous !== $row[3]) {
                    $table[] = new TableSeparator();
                }
                $table[] = $row;
                $previous = $row[3];
            }
            $io->table(['Type', 'Relate', 'Name', 'Act No'], $table);
            $io->ask('Press any key to continue');
        }
        $io->info('FIX RELATE CODES OF INSURANCES');
        if ($result->w2 !== []) {
            (new ReportFiles($io, $settings->errorFileDirectory(), $clock->now()->format('m-d-y-H-i')))->write('w2', $result->w2, false);
        }

        $io->info('RRI-247x Checking for mismatched names and relate codes');
        foreach ($result->mismatches as [$guarantor, $subscriber]) {
            $io->info($guarantor);
            $io->info($subscriber);
            $io->ask('Press any key to continue');
        }
        $io->info('FIX RELATE CODES OF INSURANCES');
        $output->writeln('completed successfully');
        return Command::SUCCESS;
    }
}
