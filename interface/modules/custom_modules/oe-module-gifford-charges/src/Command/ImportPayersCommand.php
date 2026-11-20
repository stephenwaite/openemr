<?php

/**
 * Creates insurance companies for Gifford's payer mnemonics from the payer crosswalk CSV.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Command;

use Cmsvt\OpenEMR\Modules\GiffordCharges\Payer\PayerChangeKind;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Payer\PayerCrosswalkException;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Payer\PayerCrosswalkReader;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Payer\PayerImporter;
use OpenEMR\BC\ServiceContainer;
use OpenEMR\Services\InsuranceCompanyService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ImportPayersCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('gifford:payer-import')
            ->setDescription('Create an insurance company for each Gifford payer mnemonic in the payer crosswalk CSV; mapped mnemonics are left alone')
            ->addUsage('--site=2400 --file=/tmp/payer-crosswalk.csv --dry-run')
            ->addOption('site', null, InputOption::VALUE_REQUIRED, 'Name of site', 'default')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Path of the crosswalk CSV, as the container sees it')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would change without writing anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getOption('file');
        if (!is_string($path) || $path === '') {
            $output->writeln('<error>--file must name the payer crosswalk CSV.</error>');
            return Command::INVALID;
        }
        $dryRun = $input->getOption('dry-run') === true;
        $importer = new PayerImporter(new InsuranceCompanyService(), ServiceContainer::getClock());

        try {
            $mappings = (new PayerCrosswalkReader())->read($path);
            $changes = $importer->plan($mappings);
        } catch (PayerCrosswalkException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            foreach ($e->problems as $problem) {
                $output->writeln('  ' . $problem);
            }
            return Command::FAILURE;
        }

        $counts = array_fill_keys(array_map(static fn (PayerChangeKind $k): string => $k->name, PayerChangeKind::cases()), 0);
        foreach ($changes as $change) {
            $counts[$change->kind->name]++;
            if ($change->kind !== PayerChangeKind::AlreadyMapped || $output->isVerbose()) {
                $output->writeln(($dryRun ? 'Would ' : '') . $change->describe());
            }
        }
        if (!$dryRun) {
            $importer->apply($changes);
        }
        $electronic = count(array_filter($mappings, static fn ($m): bool => $m->electronic !== null));
        $output->writeln(sprintf(
            '%s %d rows (%d electronic, %d paper): %d insurance companies created, %d created again, %d already mapped.',
            $dryRun ? 'Dry run, nothing written:' : 'Done:',
            count($mappings),
            $electronic,
            count($mappings) - $electronic,
            $counts[PayerChangeKind::Create->name],
            $counts[PayerChangeKind::Recreate->name],
            $counts[PayerChangeKind::AlreadyMapped->name]
        ));

        return Command::SUCCESS;
    }
}
