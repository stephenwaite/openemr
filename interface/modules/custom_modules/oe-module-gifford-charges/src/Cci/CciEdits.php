<?php

/**
 * The site's NCCI procedure-to-procedure edits (gifford_cci_edit), loaded by gifford:cci-import.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Cci;

use OpenEMR\Common\Database\QueryUtils;

final class CciEdits
{
    private const INSERT_ROWS = 500;

    /**
     * The edit between two codes, whichever of them is column 1, or null when there is none.
     */
    public function between(string $code, string $otherCode): ?CciEdit
    {
        $row = QueryUtils::querySingleRow(
            'SELECT `column1`, `column2`, `indicator` FROM `gifford_cci_edit`'
            . ' WHERE (`column1` = ? AND `column2` = ?) OR (`column1` = ? AND `column2` = ?) ORDER BY `column1` = ? DESC LIMIT 1',
            [$code, $otherCode, $otherCode, $code, $code]
        );
        if (!is_array($row)) {
            return null;
        }
        $column1 = $row['column1'] ?? null;
        $column2 = $row['column2'] ?? null;
        $indicator = is_string($row['indicator'] ?? null) ? CciIndicator::tryFrom($row['indicator']) : null;
        if (!is_string($column1) || !is_string($column2) || $indicator === null) {
            return null;
        }

        return new CciEdit($column1, $column2, $indicator);
    }

    public function count(): int
    {
        $count = QueryUtils::fetchSingleValue('SELECT COUNT(*) AS n FROM `gifford_cci_edit`', 'n');

        return is_numeric($count) ? (int) $count : 0;
    }

    /**
     * Replace every edit with these: the file is the whole table.
     *
     * @param list<CciEdit> $edits
     */
    public function replaceAll(array $edits): void
    {
        QueryUtils::inTransaction(function () use ($edits): void {
            QueryUtils::sqlStatementThrowException('DELETE FROM `gifford_cci_edit`');
            foreach (array_chunk($edits, self::INSERT_ROWS) as $chunk) {
                $binds = [];
                foreach ($chunk as $edit) {
                    array_push($binds, $edit->column1, $edit->column2, $edit->indicator->value);
                }
                QueryUtils::sqlStatementThrowException(
                    'INSERT INTO `gifford_cci_edit` (`column1`, `column2`, `indicator`) VALUES '
                    . implode(', ', array_fill(0, count($chunk), '(?, ?, ?)')),
                    $binds
                );
            }
        });
    }
}
