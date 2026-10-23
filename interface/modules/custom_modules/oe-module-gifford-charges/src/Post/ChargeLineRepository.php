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
     * Lines not yet finished (staged, or held for any reason), oldest file first.
     *
     * @return list<PendingLine>
     */
    public function pendingLines(): array
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT `id`, `batch_id`, `line_number`, `raw_json`, `released`, `added_modifier` FROM `gifford_charge_line`'
            . ' WHERE `status` IN (?, ?) ORDER BY `batch_id`, `line_number`',
            [LineStatus::Staged->value, LineStatus::Held->value]
        );

        $lines = [];
        foreach ($rows as $row) {
            $raw = json_decode(self::text($row['raw_json'] ?? null), true, 512, JSON_THROW_ON_ERROR);
            $values = [];
            foreach (is_array($raw) ? $raw : [] as $column => $value) {
                if (is_string($column) && is_string($value)) {
                    $values[$column] = $value;
                }
            }
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
     * before $lineNumber in the same file.
     *
     * @return list<array{line_id: int, same_file: bool, raw: array<string, string>}>
     */
    public function postedEarlier(string $accession, int $batchId, int $lineNumber): array
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT `id`, `batch_id`, `raw_json` FROM `gifford_charge_line`'
            . ' WHERE `accession` = ? AND `status` = ? AND (`batch_id` < ? OR (`batch_id` = ? AND `line_number` < ?))'
            . ' ORDER BY `batch_id`, `line_number`',
            [$accession, LineStatus::Posted->value, $batchId, $batchId, $lineNumber]
        );
        $posted = [];
        foreach ($rows as $row) {
            $raw = json_decode(self::text($row['raw_json'] ?? null), true, 512, JSON_THROW_ON_ERROR);
            $values = [];
            foreach (is_array($raw) ? $raw : [] as $column => $value) {
                if (is_string($column) && is_string($value)) {
                    $values[$column] = $value;
                }
            }
            $posted[] = [
                'line_id' => self::int($row['id'] ?? null),
                'same_file' => self::int($row['batch_id'] ?? null) === $batchId,
                'raw' => $values,
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
     * Accessions of the patient's posted lines with this code on this date, other than
     * $lineId: lines that make this one a repeat of the same procedure.
     *
     * @return list<array{line_id: int, batch_id: int, accession: string}>
     */
    public function postedSameCode(string $mrn, \DateTimeImmutable $dateOfService, string $code, int $lineId): array
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT `id`, `batch_id`, `accession` FROM `gifford_charge_line`'
            . ' WHERE `mrn` = ? AND `date_of_service` = ? AND `code` = ? AND `status` = ? AND `id` <> ?'
            . ' ORDER BY `batch_id`, `line_number`',
            [$mrn, $dateOfService->format('Y-m-d'), $code, LineStatus::Posted->value, $lineId]
        );

        return array_map(static fn (array $row): array => [
            'line_id' => self::int($row['id'] ?? null),
            'batch_id' => self::int($row['batch_id'] ?? null),
            'accession' => self::text($row['accession'] ?? null),
        ], $rows);
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

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
