<?php

/**
 * What the Gifford review page shows and changes: unmapped payers and held lines.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Review;

use Cmsvt\OpenEMR\Modules\GiffordCharges\Parse\ChargeRowParser;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Payer\PayerMap;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Reader\RawRow;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Stage\LineStatus;
use OpenEMR\Common\Database\QueryUtils;

final readonly class ReviewRepository
{
    /** The start of the problem posting gives a corrected re-send. */
    public const CORRECTION_PREFIX = 'corrected re-send?';

    public function __construct(
        private ChargeRowParser $parser,
        private PayerMap $payers,
    ) {
    }

    /**
     * Held lines, oldest file first.
     *
     * @return list<HeldLine>
     */
    public function heldLines(): array
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT l.`id`, l.`line_number`, l.`visit_number`, l.`mrn`, l.`accession`, l.`date_of_service`, l.`code`,'
            . ' l.`raw_json`, l.`problems_json`, l.`released`, b.`file_name`, b.`received_at`'
            . ' FROM `gifford_charge_line` AS l JOIN `gifford_charge_batch` AS b ON b.`id` = l.`batch_id`'
            . ' WHERE l.`status` = ? ORDER BY l.`batch_id`, l.`line_number`',
            [LineStatus::Held->value]
        );

        $lines = [];
        foreach ($rows as $row) {
            $raw = self::raw($row['raw_json'] ?? null);
            $problems = [];
            $decoded = json_decode(self::text($row['problems_json'] ?? null), true);
            foreach (is_array($decoded) ? $decoded : [] as $problem) {
                if (is_string($problem)) {
                    $problems[] = $problem;
                }
            }
            $lines[] = new HeldLine(
                self::int($row['id'] ?? null),
                self::text($row['file_name'] ?? null),
                self::int($row['line_number'] ?? null),
                self::text($row['received_at'] ?? null),
                self::text($row['visit_number'] ?? null),
                self::text($row['mrn'] ?? null),
                trim(($raw['pat_last'] ?? '') . ', ' . ($raw['pat_first'] ?? ''), ', '),
                self::text($row['accession'] ?? null),
                self::text($row['date_of_service'] ?? null),
                self::text($row['code'] ?? null),
                $problems,
                array_filter($problems, static fn (string $p): bool => str_starts_with($p, self::CORRECTION_PREFIX)) !== [],
                self::int($row['released'] ?? null) === 1,
            );
        }

        return $lines;
    }

    /**
     * Payer mnemonics on held lines that aren't mapped, by mnemonic.
     *
     * @return list<UnmappedPayer>
     */
    public function unmappedPayers(): array
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT l.`line_number`, l.`raw_json`, b.`received_at` FROM `gifford_charge_line` AS l'
            . ' JOIN `gifford_charge_batch` AS b ON b.`id` = l.`batch_id` WHERE l.`status` = ? ORDER BY l.`batch_id`',
            [LineStatus::Held->value]
        );
        /** @var array<string, array{name: string, lines: int, first: string}> $found */
        $found = [];
        $mapped = [];
        foreach ($rows as $row) {
            $parsed = $this->parser->parse(new RawRow(self::int($row['line_number'] ?? null), self::raw($row['raw_json'] ?? null)));
            foreach ($parsed->policies as $policy) {
                $mnemonic = strtoupper($policy->payerMnemonic);
                $mapped[$mnemonic] ??= $this->payers->insuranceCompanyId($mnemonic) !== null;
                if ($mapped[$mnemonic]) {
                    continue;
                }
                $found[$mnemonic] ??= ['name' => $policy->payerName, 'lines' => 0, 'first' => self::text($row['received_at'] ?? null)];
                $found[$mnemonic]['lines']++;
            }
        }
        ksort($found);

        $payers = [];
        foreach ($found as $mnemonic => $f) {
            $payers[] = new UnmappedPayer($mnemonic, $f['name'], $f['lines'], $f['first']);
        }

        return $payers;
    }

    /**
     * Insurance companies to map a mnemonic to: active ones, by name.
     *
     * @return list<array{id: int, label: string}>
     */
    public function insuranceCompanies(): array
    {
        $companies = [];
        foreach (QueryUtils::fetchRecords('SELECT `id`, `name`, `cms_id` FROM `insurance_companies` WHERE `inactive` = 0 ORDER BY `name`, `id`') as $row) {
            $payerId = self::text($row['cms_id'] ?? null);
            $companies[] = [
                'id' => self::int($row['id'] ?? null),
                'label' => self::text($row['name'] ?? null) . ($payerId === '' ? ' (paper)' : ' (' . $payerId . ')'),
            ];
        }

        return $companies;
    }

    public function companyExists(int $id): bool
    {
        return QueryUtils::fetchSingleValue('SELECT `id` FROM `insurance_companies` WHERE `id` = ?', 'id', [$id]) !== null;
    }

    /**
     * Mark a held line skipped: it is never posted. Returns false when it isn't held.
     */
    public function skip(int $lineId, \DateTimeImmutable $at): bool
    {
        QueryUtils::sqlStatementThrowException(
            'UPDATE `gifford_charge_line` SET `status` = ?, `updated_at` = ? WHERE `id` = ? AND `status` = ?',
            [LineStatus::Skipped->value, $at->format('Y-m-d H:i:s'), $lineId, LineStatus::Held->value]
        );

        return QueryUtils::affectedRows() > 0;
    }

    /**
     * Let a line held as a corrected re-send post on the next run. Returns false when it
     * isn't held.
     */
    public function release(int $lineId, \DateTimeImmutable $at): bool
    {
        QueryUtils::sqlStatementThrowException(
            'UPDATE `gifford_charge_line` SET `released` = 1, `updated_at` = ? WHERE `id` = ? AND `status` = ?',
            [$at->format('Y-m-d H:i:s'), $lineId, LineStatus::Held->value]
        );

        return QueryUtils::affectedRows() > 0;
    }

    /**
     * @return array<string, string>
     */
    private static function raw(mixed $json): array
    {
        $decoded = json_decode(self::text($json), true);
        $values = [];
        foreach (is_array($decoded) ? $decoded : [] as $column => $value) {
            if (is_string($column) && is_string($value)) {
                $values[$column] = $value;
            }
        }

        return $values;
    }

    private static function text(mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            is_int($value) => (string) $value,
            default => '',
        };
    }

    private static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
