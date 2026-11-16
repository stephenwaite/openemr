<?php

/**
 * The diagnoses each accession posts with: the suggestion read from its report, and the
 * codes a coder confirmed.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Dx;

use Cmsvt\OpenEMR\Modules\GiffordCharges\Parse\GiffordValues;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Review\ReportView;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Stage\LineStatus;
use OpenEMR\Common\Database\QueryUtils;

/**
 * One row per accession in gifford_dx. A suggestion that passes DxRule is saved as agreed
 * and its lines post with it; any other is pending until a coder confirms the codes on
 * Fees > Gifford Diagnoses. A coder can also confirm an accession that has no suggestion.
 * While an accession has neither, ChargePoster holds its lines with PROBLEM.
 */
final readonly class DxRepository
{
    /** The problem a held line carries while its accession's diagnoses are not settled. */
    public const PROBLEM = 'diagnosis: confirm it on Fees > Gifford Diagnoses';

    /** A claim line points to this many diagnoses. */
    public const MAX_CODES = 4;

    public const STATUS_PENDING = 'pending';
    public const STATUS_AGREED = 'agreed';
    public const STATUS_CONFIRMED = 'confirmed';

    /**
     * The codes the accession's lines post with, in claim order; null while they are not
     * settled.
     *
     * @return ?list<string>
     */
    public function codesFor(string $accession): ?array
    {
        $codes = QueryUtils::fetchSingleValue(
            'SELECT `codes` FROM `gifford_dx` WHERE `accession` = ? AND `status` IN (?, ?)',
            'codes',
            [$accession, self::STATUS_AGREED, self::STATUS_CONFIRMED]
        );
        if (!is_string($codes) || $codes === '') {
            return null;
        }

        return explode(',', $codes);
    }

    /**
     * What the staged lines say about an accession, whatever their status; null when no
     * line has it.
     *
     * @return ?array{gifford: list<string>, cpts: list<string>, payer: string}
     */
    public function staged(string $accession): ?array
    {
        $rows = QueryUtils::fetchRecords('SELECT `code`, `raw_json` FROM `gifford_charge_line` WHERE `accession` = ? ORDER BY `id`', [$accession]);
        if ($rows === []) {
            return null;
        }
        $gifford = [];
        $cpts = [];
        $payer = '';
        foreach ($rows as $row) {
            $raw = self::raw($row['raw_json'] ?? null);
            foreach (GiffordValues::diagnoses($raw['enc_dx_codes'] ?? '')[0] as $code) {
                if (!in_array($code, $gifford, true)) {
                    $gifford[] = $code;
                }
            }
            $cpt = strtoupper(self::text($row['code'] ?? null));
            if ($cpt !== '' && !in_array($cpt, $cpts, true)) {
                $cpts[] = $cpt;
            }
            if ($payer === '') {
                $payer = $raw['ins1_mnemonic'] ?? '';
            }
        }

        return ['gifford' => $gifford, 'cpts' => $cpts, 'payer' => $payer];
    }

    /**
     * The codes that are not active and valid for coding in the site's ICD-10 set.
     *
     * @param list<string> $codes with the dot
     * @return list<string>
     */
    public function invalidCodes(array $codes): array
    {
        if ($codes === []) {
            return [];
        }
        $plain = array_map(static fn (string $c): string => str_replace('.', '', $c), $codes);
        $valid = QueryUtils::fetchTableColumn(
            'SELECT `dx_code` FROM `icd10_dx_order_code` WHERE `active` = 1 AND `valid_for_coding` = \'1\' AND `dx_code` IN ('
            . implode(', ', array_fill(0, count($plain), '?')) . ')',
            'dx_code',
            $plain
        );
        $valid = array_map(static fn (mixed $c): string => is_string($c) ? trim($c) : '', $valid);

        return array_values(array_filter($codes, static fn (string $c): bool => !in_array(str_replace('.', '', $c), $valid, true)));
    }

    /**
     * Save the suggestion read from an accession's report: agreed when there is no reason
     * for a coder to confirm it, pending otherwise. A suggestion replaces an earlier one;
     * returns false, changing nothing, when a coder has already confirmed the accession.
     *
     * @param list<array{code: string, confidence: string, rationale: string}> $suggested in claim order
     * @param list<string> $reasons from DxRule::reasons()
     * @param string $source where the suggestion came from (a run's name)
     */
    public function saveSuggestion(string $accession, array $suggested, array $reasons, string $source, \DateTimeImmutable $now): bool
    {
        $status = QueryUtils::fetchSingleValue('SELECT `status` FROM `gifford_dx` WHERE `accession` = ?', 'status', [$accession]);
        if ($status === self::STATUS_CONFIRMED) {
            return false;
        }
        $agreed = $reasons === [] && $suggested !== [];
        $values = [
            json_encode($suggested, JSON_THROW_ON_ERROR),
            json_encode($reasons, JSON_THROW_ON_ERROR),
            $agreed ? self::STATUS_AGREED : self::STATUS_PENDING,
            $agreed ? implode(',', array_slice(array_column($suggested, 'code'), 0, self::MAX_CODES)) : '',
            $source,
        ];
        if (is_string($status)) {
            QueryUtils::sqlStatementThrowException(
                'UPDATE `gifford_dx` SET `suggested_json` = ?, `reasons_json` = ?, `status` = ?, `codes` = ?, `source` = ? WHERE `accession` = ?',
                [...$values, $accession]
            );
        } else {
            QueryUtils::sqlInsert(
                'INSERT INTO `gifford_dx` (`suggested_json`, `reasons_json`, `status`, `codes`, `source`, `accession`, `created_at`)'
                . ' VALUES (?, ?, ?, ?, ?, ?, ?)',
                [...$values, $accession, $now->format('Y-m-d H:i:s')]
            );
        }

        return true;
    }

    /**
     * Record the codes a coder chose for an accession; its lines post with them on the next
     * run. Codes are separated by spaces or commas, with or without the dot.
     *
     * @return list<string> what is wrong with the codes; [] when they were saved
     */
    public function confirm(string $accession, string $entered, int $userId, \DateTimeImmutable $now): array
    {
        $codes = [];
        $errors = [];
        foreach (preg_split('/[\s,;]+/', trim($entered), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            $code = DxRule::normalize($part);
            if ($code === '') {
                $errors[] = $part . ' is not an ICD-10 code';
            } elseif (!in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }
        if ($accession === '' || ($codes === [] && $errors === [])) {
            $errors[] = 'enter at least one code';
        }
        if (count($codes) > self::MAX_CODES) {
            $errors[] = sprintf('a claim line points to %d diagnoses at most', self::MAX_CODES);
        }
        $invalid = $this->invalidCodes($codes);
        if ($invalid !== []) {
            $errors[] = 'not valid for coding: ' . implode(', ', $invalid);
        }
        if ($errors !== []) {
            return $errors;
        }

        $at = $now->format('Y-m-d H:i:s');
        $exists = QueryUtils::fetchSingleValue('SELECT `id` FROM `gifford_dx` WHERE `accession` = ?', 'id', [$accession]) !== null;
        if ($exists) {
            QueryUtils::sqlStatementThrowException(
                'UPDATE `gifford_dx` SET `status` = ?, `codes` = ?, `confirmed_at` = ?, `confirmed_by` = ? WHERE `accession` = ?',
                [self::STATUS_CONFIRMED, implode(',', $codes), $at, $userId, $accession]
            );
        } else {
            QueryUtils::sqlInsert(
                'INSERT INTO `gifford_dx` (`accession`, `suggested_json`, `reasons_json`, `status`, `codes`, `source`, `created_at`,'
                . ' `confirmed_at`, `confirmed_by`) VALUES (?, \'[]\', \'[]\', ?, ?, \'\', ?, ?, ?)',
                [$accession, self::STATUS_CONFIRMED, implode(',', $codes), $at, $at, $userId]
            );
        }

        return [];
    }

    /**
     * Accessions with lines held for their diagnoses: those held for nothing else first,
     * then oldest date of service first.
     *
     * @return list<PendingDx>
     */
    public function pending(?int $limit = null): array
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT l.`accession`, l.`date_of_service`, l.`mrn`, l.`code`, l.`raw_json`, l.`problems_json`, b.`file_name`,'
            . ' d.`suggested_json`, d.`reasons_json`'
            . ' FROM `gifford_charge_line` AS l'
            . ' JOIN `gifford_charge_batch` AS b ON b.`id` = l.`batch_id`'
            . ' LEFT JOIN `gifford_dx` AS d ON d.`accession` = l.`accession`'
            . ' WHERE l.`status` = ? AND l.`problems_json` LIKE ? ORDER BY l.`date_of_service`, l.`mrn`, l.`accession`, l.`id`',
            [LineStatus::Held->value, '%diagnosis: confirm it on Fees%']
        );
        /** @var array<string, list<array<string, mixed>>> $groups */
        $groups = [];
        foreach ($rows as $row) {
            $groups['a' . self::text($row['accession'] ?? null)][] = $row;
        }
        // Accessions nothing else holds come first: confirming them lets them post.
        $postable = [];
        $alsoHeld = [];
        foreach ($groups as $key => $group) {
            $only = true;
            foreach ($group as $row) {
                $only = $only && self::strings($row['problems_json'] ?? null) === [self::PROBLEM];
            }
            if ($only) {
                $postable[$key] = $group;
            } else {
                $alsoHeld[$key] = $group;
            }
        }
        $groups = $postable + $alsoHeld;
        if ($limit !== null) {
            $groups = array_slice($groups, 0, $limit, true);
        }

        $wanted = [];
        $built = [];
        foreach ($groups as $group) {
            $first = $group[0];
            $raw = self::raw($first['raw_json'] ?? null);
            $charges = [];
            $gifford = [];
            $others = [];
            foreach ($group as $row) {
                $lineRaw = self::raw($row['raw_json'] ?? null);
                $modifiers = array_filter([$lineRaw['chg_mod1'] ?? '', $lineRaw['chg_mod2'] ?? '', $lineRaw['chg_mod3'] ?? '']);
                $charges[] = self::text($row['code'] ?? null) . ($modifiers === [] ? '' : '-' . implode(':', $modifiers));
                foreach (GiffordValues::diagnoses($lineRaw['enc_dx_codes'] ?? '')[0] as $code) {
                    if (!in_array($code, $gifford, true)) {
                        $gifford[] = $code;
                    }
                }
                foreach (self::strings($row['problems_json'] ?? null) as $problem) {
                    if ($problem !== self::PROBLEM && !in_array($problem, $others, true)) {
                        $others[] = $problem;
                    }
                }
            }
            $suggested = self::suggestion($first['suggested_json'] ?? null);
            array_push($wanted, ...$gifford, ...array_column($suggested, 'code'));
            $built[] = [
                'first' => $first, 'raw' => $raw, 'charges' => $charges, 'gifford' => $gifford, 'others' => $others,
                'suggested' => $suggested,
            ];
        }
        $texts = $this->descriptions(array_values(array_unique($wanted)));

        $pending = [];
        foreach ($built as $b) {
            $first = $b['first'];
            $raw = $b['raw'];
            $pending[] = new PendingDx(
                self::text($first['accession'] ?? null),
                self::text($first['file_name'] ?? null),
                self::text($first['date_of_service'] ?? null),
                trim(($raw['pat_last'] ?? '') . ', ' . ($raw['pat_first'] ?? ''), ', '),
                self::text($first['mrn'] ?? null),
                $b['charges'],
                $raw['chg_location'] ?? '',
                $raw['chg_clinical'] ?? '',
                array_map(static fn (string $c): array => ['code' => $c, 'text' => $texts[$c] ?? ''], $b['gifford']),
                array_map(static fn (array $s): array => $s + ['text' => $texts[$s['code']] ?? ''], $b['suggested']),
                self::strings($first['reasons_json'] ?? null),
                $b['others'],
                is_string($first['suggested_json'] ?? null),
                ReportView::fromRaw($raw),
            );
        }

        return $pending;
    }

    public function pendingCount(): int
    {
        $count = QueryUtils::fetchSingleValue(
            'SELECT COUNT(DISTINCT `accession`) AS `n` FROM `gifford_charge_line` WHERE `status` = ? AND `problems_json` LIKE ?',
            'n',
            [LineStatus::Held->value, '%diagnosis: confirm it on Fees%']
        );

        return is_numeric($count) ? (int) $count : 0;
    }

    /**
     * How the coders' confirmed codes compare with the suggestions, for grading them.
     *
     * @return array{agreed: int, confirmed: int, as_suggested: int, first_kept: int, first_changed: int, no_suggestion: int}
     */
    public function tally(): array
    {
        $tally = ['agreed' => 0, 'confirmed' => 0, 'as_suggested' => 0, 'first_kept' => 0, 'first_changed' => 0, 'no_suggestion' => 0];
        $rows = QueryUtils::fetchRecords(
            'SELECT `status`, `codes`, `suggested_json` FROM `gifford_dx` WHERE `status` IN (?, ?)',
            [self::STATUS_AGREED, self::STATUS_CONFIRMED]
        );
        foreach ($rows as $row) {
            if (($row['status'] ?? null) === self::STATUS_AGREED) {
                $tally['agreed']++;
                continue;
            }
            $tally['confirmed']++;
            $suggested = array_slice(array_column(self::suggestion($row['suggested_json'] ?? null), 'code'), 0, self::MAX_CODES);
            $codes = explode(',', self::text($row['codes'] ?? null));
            $tally[match (true) {
                $suggested === [] => 'no_suggestion',
                $suggested === $codes => 'as_suggested',
                $suggested[0] === $codes[0] => 'first_kept',
                default => 'first_changed',
            }]++;
        }

        return $tally;
    }

    /**
     * The code set's descriptions.
     *
     * @param list<string> $codes with the dot
     * @return array<string, string> by code, with the dot
     */
    private function descriptions(array $codes): array
    {
        if ($codes === []) {
            return [];
        }
        $byPlain = [];
        foreach ($codes as $code) {
            $byPlain[str_replace('.', '', $code)] = $code;
        }
        $rows = QueryUtils::fetchRecords(
            'SELECT `dx_code`, `long_desc` FROM `icd10_dx_order_code` WHERE `active` = 1 AND `dx_code` IN ('
            . implode(', ', array_fill(0, count($byPlain), '?')) . ') ORDER BY `revision`',
            array_map(strval(...), array_keys($byPlain))
        );
        $texts = [];
        foreach ($rows as $row) {
            $code = $byPlain[trim(self::text($row['dx_code'] ?? null))] ?? null;
            if ($code !== null) {
                $texts[$code] = trim(self::text($row['long_desc'] ?? null));
            }
        }

        return $texts;
    }

    /**
     * @return list<array{code: string, confidence: string, rationale: string}>
     */
    private static function suggestion(mixed $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : null;
        $suggested = [];
        foreach (is_array($decoded) ? $decoded : [] as $item) {
            if (is_array($item) && is_string($item['code'] ?? null) && $item['code'] !== '') {
                $suggested[] = [
                    'code' => $item['code'],
                    'confidence' => is_string($item['confidence'] ?? null) ? $item['confidence'] : '',
                    'rationale' => is_string($item['rationale'] ?? null) ? $item['rationale'] : '',
                ];
            }
        }

        return $suggested;
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : null;

        return array_values(array_filter(is_array($decoded) ? $decoded : [], is_string(...)));
    }

    /**
     * @return array<string, string>
     */
    private static function raw(mixed $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : null;
        $raw = [];
        foreach (is_array($decoded) ? $decoded : [] as $column => $value) {
            if (is_string($column) && is_string($value)) {
                $raw[$column] = $value;
            }
        }

        return $raw;
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : (is_int($value) ? (string) $value : '');
    }
}
