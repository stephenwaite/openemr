<?php

/**
 * Loads the NCCI edit file (the billing system's CCIFILE, unloaded) into gifford_cci_edit.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Command;

use Cmsvt\OpenEMR\Modules\GiffordCharges\Cci\CciEdits;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Cci\CciFileException;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Cci\CciFileReader;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Cci\CciIndicator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ImportCciCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('gifford:cci-import')
            ->setDescription('Replace the NCCI edits the Gifford charge import checks with those in the unloaded CCIFILE')
            ->addUsage('--site=2400 --file=/tmp/ccifile-unload --dry-run')
            ->addOption('site', null, InputOption::VALUE_REQUIRED, 'Name of site', 'default')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Path of the unloaded CCIFILE, as the container sees it')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Read the file and report without writing anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getOption('file');
        if (!is_string($path) || $path === '') {
            $output->writeln('<error>--file must name the unloaded CCIFILE.</error>');
            return Command::INVALID;
        }
        $dryRun = $input->getOption('dry-run') === true;

        try {
            $edits = (new CciFileReader())->read($path);
        } catch (CciFileException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $counts = array_fill_keys(array_map(static fn (CciIndicator $i): string => $i->value, CciIndicator::cases()), 0);
        foreach ($edits as $edit) {
            $counts[$edit->indicator->value]++;
        }
        $table = new CciEdits();
        $before = $table->count();
        if (!$dryRun) {
            $table->replaceAll($edits);
        }
        $output->writeln(sprintf(
            '%s %d edits (%d not billed together, %d with modifier 59, %d deleted); the table had %d.',
            $dryRun ? 'Dry run, nothing written:' : 'Done:',
            count($edits),
            $counts[CciIndicator::NotAllowed->value],
            $counts[CciIndicator::WithModifier->value],
            $counts[CciIndicator::NotApplicable->value],
            $before
        ));

        return Command::SUCCESS;
    }
}
