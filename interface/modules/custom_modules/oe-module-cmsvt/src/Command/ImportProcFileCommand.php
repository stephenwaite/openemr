<?php

/**
 * Loads a site's codes and standard prices from a CMS fee file (COBOL copybook PROCFILE01).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Command;

use Cmsvt\OpenEMR\Modules\Customizations\Fees\CodePrice;
use Cmsvt\OpenEMR\Modules\Customizations\Fees\CodePriceChange;
use Cmsvt\OpenEMR\Modules\Customizations\Fees\CodePriceChangeKind;
use Cmsvt\OpenEMR\Modules\Customizations\Fees\CodePriceImporter;
use Cmsvt\OpenEMR\Modules\Customizations\Fees\CodePriceList;
use Cmsvt\OpenEMR\Modules\Customizations\Fees\ProcFileParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ImportProcFileCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('cmsvt:procfile-import')
            ->setDescription('Add missing codes and set standard prices from a CMS fee file (PROCFILE01 records); $0 records are skipped')
            ->addUsage('--site=2400 --file=/tmp/wste --dry-run')
            ->addOption('site', null, InputOption::VALUE_REQUIRED, 'Name of site', 'default')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Path of the fee file, as the container sees it')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would change without writing anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getOption('file');
        if (!is_string($path) || $path === '' || !is_file($path) || !is_readable($path)) {
            $output->writeln('<error>--file must name a readable fee file.</error>');
            return Command::INVALID;
        }
        $lines = file($path);
        if ($lines === false) {
            $output->writeln('<error>The fee file could not be read.</error>');
            return Command::FAILURE;
        }
        $dryRun = $input->getOption('dry-run') === true;

        $parsed = (new ProcFileParser())->parse($lines);
        $list = CodePriceList::fromRecords($parsed->records);
        $output->writeln(sprintf(
            '%d records read; %d skipped at $0; %d codes and modifiers priced.',
            count($parsed->records),
            $list->zeroFeeRecords,
            count($list->prices)
        ));
        foreach ($parsed->errors as $error) {
            $output->writeln('<comment>Not a record, skipped: ' . $error . '</comment>');
        }
        foreach ($list->unknownCodes as $unknown) {
            $output->writeln('<comment>Neither CPT4 nor HCPCS, skipped: ' . $unknown . '</comment>');
        }
        foreach ($list->prices as $price) {
            if ($price->otherFeesCents !== []) {
                $output->writeln(sprintf(
                    'Fees disagree for %s: using %s (also %s)',
                    $price->label(),
                    $price->fee(),
                    implode(', ', array_map(CodePrice::dollars(...), $price->otherFeesCents))
                ));
            }
        }

        $importer = new CodePriceImporter();
        $changes = $importer->plan($list->prices);
        $counts = array_fill_keys(array_map(static fn (CodePriceChangeKind $k): string => $k->name, CodePriceChangeKind::cases()), 0);
        foreach ($changes as $change) {
            $counts[$change->kind->name]++;
            if ($change->kind !== CodePriceChangeKind::Unchanged || $output->isVerbose()) {
                $output->writeln(($dryRun ? 'Would ' : '') . $change->describe());
            }
        }

        $toWrite = array_values(array_filter(
            $changes,
            static fn (CodePriceChange $c): bool => $c->kind !== CodePriceChangeKind::Unchanged
        ));
        if (!$dryRun && $toWrite !== []) {
            $importer->apply($toWrite);
        }
        $output->writeln(sprintf(
            '%s %d codes added, %d prices added to existing codes, %d prices changed; %d already right.',
            $dryRun ? 'Dry run, nothing written:' : 'Done:',
            $counts[CodePriceChangeKind::AddCode->name],
            $counts[CodePriceChangeKind::AddPrice->name],
            $counts[CodePriceChangeKind::ChangePrice->name],
            $counts[CodePriceChangeKind::Unchanged->name]
        ));

        return Command::SUCCESS;
    }
}
