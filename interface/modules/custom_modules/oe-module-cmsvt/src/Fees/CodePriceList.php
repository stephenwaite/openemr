<?php

/**
 * Turns fee file records into one price per code and modifier.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Fees;

/**
 * A fee file has one record per charge master entry (PROC-CDM), so a code often comes
 * several times, usually at one fee. Records are grouped by code and modifier:
 *
 * - $0 records are left out;
 * - a 5-digit code, or 4 digits and F, T or U (CPT categories II and III, PLA), is CPT4;
 *   a letter A-V and 4 digits is HCPCS; anything else is reported and left out;
 * - when the records disagree, the most common fee wins, and of equally common fees the
 *   highest; the others stay on the price so they can be reported;
 * - the title is the one most of the chosen fee's records share, else the first of them.
 */
final readonly class CodePriceList
{
    /**
     * @param list<CodePrice> $prices sorted by code type, code, modifier
     * @param int $zeroFeeRecords records left out for a $0 fee
     * @param list<string> $unknownCodes "line N: code 'X'" for codes neither CPT4 nor HCPCS
     */
    public function __construct(
        public array $prices,
        public int $zeroFeeRecords,
        public array $unknownCodes,
    ) {
    }

    /**
     * @param list<ProcFileRecord> $records
     */
    public static function fromRecords(array $records): self
    {
        /** @var array<string, array{type: string, code: string, modifier: string, records: non-empty-list<ProcFileRecord>}> $groups */
        $groups = [];
        $zero = 0;
        $unknown = [];
        foreach ($records as $record) {
            if ($record->feeCents === 0) {
                $zero++;
                continue;
            }
            $type = self::codeType($record->code);
            if ($type === null) {
                $unknown[] = sprintf("line %d: code '%s'", $record->lineNumber, $record->code);
                continue;
            }
            $key = $type . '|' . $record->code . '|' . $record->modifier;
            if (isset($groups[$key])) {
                $groups[$key]['records'][] = $record;
            } else {
                $groups[$key] = ['type' => $type, 'code' => $record->code, 'modifier' => $record->modifier, 'records' => [$record]];
            }
        }
        ksort($groups, SORT_STRING);

        $prices = [];
        foreach ($groups as $group) {
            $prices[] = self::price($group['type'], $group['code'], $group['modifier'], $group['records']);
        }

        return new self($prices, $zero, $unknown);
    }

    public static function codeType(string $code): ?string
    {
        if (preg_match('/^\d{5}$|^\d{4}[FTU]$/', $code) === 1) {
            return 'CPT4';
        }
        if (preg_match('/^[A-V]\d{4}$/', $code) === 1) {
            return 'HCPCS';
        }

        return null;
    }

    /**
     * @param non-empty-list<ProcFileRecord> $records in file order
     */
    private static function price(string $type, string $code, string $modifier, array $records): CodePrice
    {
        /** @var array<int, int> $counts fee in cents => records */
        $counts = [];
        foreach ($records as $record) {
            $counts[$record->feeCents] = ($counts[$record->feeCents] ?? 0) + 1;
        }
        $fees = array_keys($counts);
        usort($fees, static fn (int $a, int $b): int => [$counts[$b], $b] <=> [$counts[$a], $a]);
        $chosen = $fees[0];

        // Each charge master entry words the code its own way: take the wording most of the
        // chosen fee's records share, else the first of them in the file.
        /** @var array<string, int> $titleCounts title => records, in first-seen order */
        $titleCounts = [];
        foreach ($records as $record) {
            if ($record->feeCents === $chosen) {
                $titleCounts[$record->title] = ($titleCounts[$record->title] ?? 0) + 1;
            }
        }
        $title = '';
        $best = 0;
        foreach ($titleCounts as $candidate => $count) {
            if ($count > $best) {
                $title = (string) $candidate;
                $best = $count;
            }
        }
        $others = array_values(array_filter($fees, static fn (int $fee): bool => $fee !== $chosen));
        rsort($others);

        return new CodePrice($type, $code, $modifier, $title, $chosen, count($records), $others);
    }
}
