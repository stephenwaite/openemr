<?php

/**
 * Raises every code price by a percentage, rounded to whole dollars.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Command;

use OpenEMR\Common\Database\QueryUtils;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class IncreaseFeesCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('cmsvt:fees-increase')
            ->setDescription('Raise every code price (all price levels) by a percentage, rounded to whole dollars')
            ->addUsage('--site=default --percent=5 --dry-run')
            ->addOption('site', null, InputOption::VALUE_REQUIRED, 'Name of site', 'default')
            ->addOption('percent', null, InputOption::VALUE_REQUIRED, 'Percentage increase, e.g. 5 or 2.5')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show the changes without making them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $percent = $input->getOption('percent');
        if (!is_string($percent) || !is_numeric($percent) || (float) $percent <= 0) {
            $output->writeln('<error>--percent must be a positive number.</error>');
            return Command::INVALID;
        }
        $factor = 1 + ((float) $percent / 100);
        $dryRun = $input->getOption('dry-run') === true;

        $rows = QueryUtils::fetchRecords(
            "SELECT `codes`.`id` AS code_id, `codes`.`code`, `codes`.`modifier`,
                    `prices`.`pr_level`, `prices`.`pr_selector`, `prices`.`pr_price`
               FROM `codes`
               JOIN `prices` ON `prices`.`pr_id` = `codes`.`id`
              WHERE `prices`.`pr_price` > 0
           ORDER BY `codes`.`code`, `codes`.`modifier`, `prices`.`pr_level`"
        );

        $changes = [];
        foreach ($rows as $row) {
            if (!is_numeric($row['pr_price'] ?? null)) {
                continue;
            }
            $old = (float) $row['pr_price'];
            $new = number_format(round($old * $factor), 2, '.', '');
            $changes[] = [$row, $old, $new];
            $output->writeln(sprintf(
                '%s %s:%s [%s] %.2f -> %s',
                $dryRun ? 'Would raise' : 'Raising',
                is_scalar($row['code'] ?? null) ? (string) $row['code'] : '?',
                is_string($row['modifier'] ?? null) && $row['modifier'] !== '' ? $row['modifier'] : 'none',
                is_scalar($row['pr_level'] ?? null) ? (string) $row['pr_level'] : '?',
                $old,
                $new
            ));
        }

        if (!$dryRun) {
            // All or nothing: a failure part-way through must not leave prices half raised.
            QueryUtils::inTransaction(static function () use ($changes): void {
                foreach ($changes as [$row, , $new]) {
                    QueryUtils::sqlStatementThrowException(
                        'UPDATE `prices` SET `pr_price` = ? WHERE `pr_id` = ? AND `pr_level` = ? AND `pr_selector` = ?',
                        [$new, $row['code_id'], $row['pr_level'], $row['pr_selector']]
                    );
                    // codes.fee mirrors the standard price level only.
                    if (($row['pr_level'] ?? null) === 'standard') {
                        QueryUtils::sqlStatementThrowException(
                            'UPDATE `codes` SET `fee` = ? WHERE `id` = ?',
                            [$new, $row['code_id']]
                        );
                    }
                }
            });
        }

        $output->writeln(sprintf('%s %d prices.', $dryRun ? 'Dry run: would raise' : 'Raised', count($changes)));
        return Command::SUCCESS;
    }
}
