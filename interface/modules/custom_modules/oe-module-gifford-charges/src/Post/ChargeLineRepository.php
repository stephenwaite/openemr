<?php

/**
 * Reads staged charge lines for posting and records what posting did with each.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Post;

use Cmsvt\OpenEMR\Modules\GiffordCharges\Reader\RawRow;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Stage\LineStatus;
use OpenEMR\Common\Database\QueryUtils;

final class ChargeLineRepository
{
    private const DATETIME = 'Y-m-d H:i:s';

    /**
     * Lines not yet finished (staged, or held for any reason), oldest file first. A line
     * whose code a person changed comes with that code in its row.
     *
     * @return list<PendingLine>
     */
    public function pendingLines(): array
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT `id`, `batch_id`, `line_number`, `raw_json`, `released`, `added_modifier`, `changed_code` FROM `gifford_charge_line`'
            . ' WHERE `status` IN (?, ?) ORDER BY `batch_id`, `line_number`',
            [LineStatus::Staged->value, LineStatus::Held->value]
        );

        $lines = [];
        foreach ($rows as $row) {
            $values = self::withCode(self::rawValues($row['raw_json'] ?? null), self::text($row['changed_code'] ?? null));
            $lines[] = new PendingLine(
                self::int($row['id'] ?? null),
                self::int($row['batch_id'] ?? null),
                new RawRow(self::int($row['line_number'] ?? null), $values),
                self::int($row['released'] ?? null) === 1,
                self::text($row['added_modifier'] ?? null),
            );
        }

        return $lines;
    }

    /**
     * Charges already posted for this accession: from earlier files, and from lines
     * before $lineNumber in the same file. `raw` is the row as Gifford sent it and
     * `posted` the row as it posted: they differ when a person changed the code.
     *
     * @return list<array{line_id: int, same_file: bool, raw: array<string, string>, posted: array<string, string>}>
     */
    public function postedEarlier(string $accession, int $batchId, int $lineNumber): array
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT `id`, `batch_id`, `raw_json`, `changed_code` FROM `gifford_charge_line`'
            . ' WHERE `accession` = ? AND `status` = ? AND (`batch_id` < ? OR (`batch_id` = ? AND `line_number` < ?))'
            . ' ORDER BY `batch_id`, `line_number`',
            [$accession, LineStatus::Posted->value, $batchId, $batchId, $lineNumber]
        );
        $posted = [];
        foreach ($rows as $row) {
            $values = self::rawValues($row['raw_json'] ?? null);
            $posted[] = [
                'line_id' => self::int($row['id'] ?? null),
                'same_file' => self::int($row['batch_id'] ?? null) === $batchId,
                'raw' => $values,
                'posted' => self::withCode($values, self::text($row['changed_code'] ?? null)),
            ];
        }

        return $posted;
    }

    /**
     * The latest date of service posted for this MRN, or null when none is.
     */
    public function latestPostedDate(string $mrn): ?\DateTimeImmutable
    {
        $date = QueryUtils::fetchSingleValue(
            'SELECT MAX(`date_of_service`) AS d FROM `gifford_charge_line` WHERE `mrn` = ? AND `status` = ?',
            'd',
            [$mrn, LineStatus::Posted->value]
        );

        return is_string($date) ? new \DateTimeImmutable($date) : null;
    }

    /**
     * The patient's posted lines with this code on this date, other than $lineId: lines
     * that may make this one a repeat of the same procedure.
     *
     * @return list<array{line_id: int, batch_id: int, accession: string, raw: array<string, string>}>
     */
    public function postedSameCode(string $mrn, \DateTimeImmutable $dateOfService, string $code, int $lineId): array
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT `id`, `batch_id`, `accession`, `raw_json` FROM `gifford_charge_line`'
            . ' WHERE `mrn` = ? AND `date_of_service` = ? AND `code` = ? AND `status` = ? AND `id` <> ?'
            . ' ORDER BY `batch_id`, `line_number`',
            [$mrn, $dateOfService->format('Y-m-d'), $code, LineStatus::Posted->value, $lineId]
        );

        return array_map(static fn (array $row): array => [
            'line_id' => self::int($row['id'] ?? null),
            'batch_id' => self::int($row['batch_id'] ?? null),
            'accession' => self::text($row['accession'] ?? null),
            'raw' => self::rawValues($row['raw_json'] ?? null),
        ], $rows);
    }

    /**
     * The patient's charges posted for this date and still billed, other than $lineId's:
     * each code with the modifiers on its billing line now (a person may have changed them
     * in the fee sheet since it posted).
     *
     * @return list<array{code: string, modifiers: list<string>}>
     */
    public function postedThatDay(string $mrn, \DateTimeImmutable $dateOfService, int $lineId): array
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT l.`code`, b.`modifier` FROM `gifford_charge_line` AS l JOIN `billing` AS b ON b.`id` = l.`billing_id`'
            . ' WHERE l.`mrn` = ? AND l.`date_of_service` = ? AND l.`status` = ? AND l.`id` <> ? AND b.`activity` = 1'
            . ' ORDER BY l.`id`',
            [$mrn, $dateOfService->format('Y-m-d'), LineStatus::Posted->value, $lineId]
        );

        return array_map(static fn (array $row): array => [
            'code' => strtoupper(self::text($row['code'] ?? null)),
            'modifiers' => array_values(array_filter(explode(':', strtoupper(self::text($row['modifier'] ?? null))), static fn (string $m): bool => $m !== '')),
        ], $rows);
    }

    /**
     * Whether one of $codes is posted for the patient on this date.
     *
     * @param non-empty-list<string> $codes
     */
    public function hasPostedCode(string $mrn, \DateTimeImmutable $dateOfService, array $codes): bool
    {
        $found = QueryUtils::fetchSingleValue(
            'SELECT COUNT(*) AS n FROM `gifford_charge_line` WHERE `mrn` = ? AND `date_of_service` = ? AND `status` = ?'
            . ' AND `code` IN (' . implode(', ', array_fill(0, count($codes), '?')) . ')',
            'n',
            [$mrn, $dateOfService->format('Y-m-d'), LineStatus::Posted->value, ...$codes]
        );

        return self::int($found) > 0;
    }

    /**
     * @param list<string> $problems
     */
    public function hold(int $lineId, array $problems, \DateTimeImmutable $at): void
    {
        // Held lines are checked every run; updated_at only moves when the reasons change.
        $json = json_encode($problems, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        QueryUtils::sqlStatementThrowException(
            'UPDATE `gifford_charge_line` SET `status` = ?, `problems_json` = ?, `updated_at` = ?'
            . ' WHERE `id` = ? AND NOT (`status` = ? AND `problems_json` = ?)',
            [LineStatus::Held->value, $json, $at->format(self::DATETIME), $lineId, LineStatus::Held->value, $json]
        );
    }

    /**
     * @param int $originalLineId the line (id) this one repeats, posted or posting in this run
     */
    public function markDuplicate(int $lineId, int $originalLineId, \DateTimeImmutable $at): void
    {
        $this->setStatus($lineId, LineStatus::Duplicate, ['same charge as line id ' . $originalLineId . ', which is posted'], $at);
    }

    public function markPosted(int $lineId, int $pid, int $encounter, int $billingId, \DateTimeImmutable $at): void
    {
        QueryUtils::sqlStatementThrowException(
            'UPDATE `gifford_charge_line` SET `status` = ?, `problems_json` = \'[]\', `pid` = ?, `encounter` = ?,'
            . ' `billing_id` = ?, `posted_at` = ?, `updated_at` = ? WHERE `id` = ?',
            [LineStatus::Posted->value, $pid, $encounter, $billingId, $at->format(self::DATETIME), $at->format(self::DATETIME), $lineId]
        );
    }

    /**
     * @param list<string> $problems
     */
    private function setStatus(int $lineId, LineStatus $status, array $problems, \DateTimeImmutable $at): void
    {
        QueryUtils::sqlStatementThrowException(
            'UPDATE `gifford_charge_line` SET `status` = ?, `problems_json` = ?, `updated_at` = ? WHERE `id` = ?',
            [$status->value, json_encode($problems, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $at->format(self::DATETIME), $lineId]
        );
    }

    /**
     * A line's saved row (raw_json) as column => value.
     *
     * @return array<string, string>
     */
    private static function rawValues(mixed $json): array
    {
        $raw = json_decode(self::text($json), true, 512, JSON_THROW_ON_ERROR);
        $values = [];
        foreach (is_array($raw) ? $raw : [] as $column => $value) {
            if (is_string($column) && is_string($value)) {
                $values[$column] = $value;
            }
        }

        return $values;
    }

    /**
     * The row with the code a person changed it to, in the column Gifford's code came in.
     *
     * @param array<string, string> $values
     * @return array<string, string>
     */
    public static function withCode(array $values, string $changedCode): array
    {
        if ($changedCode === '') {
            return $values;
        }
        // Gifford may send the same code in both columns.
        foreach (['chg_cpt', 'chg_hcpcs'] as $column) {
            if (($values[$column] ?? '') !== '') {
                $values[$column] = $changedCode;
            }
        }

        return $values;
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
