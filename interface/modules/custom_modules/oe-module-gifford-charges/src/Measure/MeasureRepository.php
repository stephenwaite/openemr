<?php

/**
 * The quality measure rows held charge lines wait on, and the answers that let them post.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Measure;

use Cmsvt\OpenEMR\Modules\GiffordCharges\Stage\LineStatus;
use OpenEMR\Common\Database\QueryUtils;

/**
 * A charge that needs an answer is held (ChargePoster) with a pending row here. The
 * coder answers on Fees > Quality Measures, or releases the line with no answer; the
 * next posting run posts it, with the claims measures' G-codes beside it, and records
 * where it went so the ACR export can find the registry measures' answers.
 */
final readonly class MeasureRepository
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ANSWERED = 'answered';
    public const STATUS_RELEASED = 'released';

    /** The problem a held line carries while its answers are pending. */
    public const PROBLEM = 'quality measures: answer them on Fees > Quality Measures';

    /**
     * The line's measure row, made pending now when it has none.
     *
     * @param list<Measure> $measures
     */
    public function ensurePending(int $lineId, array $measures, \DateTimeImmutable $now): MeasureAnswers
    {
        $row = QueryUtils::querySingleRow('SELECT `status`, `answers_json`, `answered_by` FROM `gifford_measure` WHERE `line_id` = ?', [$lineId]);
        if (is_array($row)) {
            return new MeasureAnswers(
                self::text($row['status'] ?? null),
                self::answers(self::text($row['answers_json'] ?? null)),
                self::int($row['answered_by'] ?? null)
            );
        }
        QueryUtils::sqlInsert(
            'INSERT INTO `gifford_measure` (`line_id`, `measures`, `answers_json`, `status`, `created_at`) VALUES (?, ?, \'{}\', ?, ?)',
            [$lineId, implode(',', array_map(static fn (Measure $m): string => $m->value, $measures)), self::STATUS_PENDING, $now->format('Y-m-d H:i:s')]
        );

        return new MeasureAnswers(self::STATUS_PENDING, [], 0);
    }

    /**
     * Held lines waiting for an answer, oldest date of service first.
     *
     * @return list<PendingMeasure>
     */
    public function pending(): array
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT m.`id`, m.`measures`, l.`id` AS `line_id`, l.`line_number`, l.`raw_json`, l.`problems_json`, l.`date_of_service`,'
            . ' l.`code`, l.`accession`, l.`mrn`, b.`file_name`'
            . ' FROM `gifford_measure` AS m'
            . ' JOIN `gifford_charge_line` AS l ON l.`id` = m.`line_id`'
            . ' JOIN `gifford_charge_batch` AS b ON b.`id` = l.`batch_id`'
            . ' WHERE m.`status` = ? AND l.`status` = ? ORDER BY l.`date_of_service`, l.`mrn`, l.`id`',
            [self::STATUS_PENDING, LineStatus::Held->value]
        );
        $pending = [];
        foreach ($rows as $row) {
            $raw = self::raw($row['raw_json'] ?? null);
            $modifiers = array_values(array_filter([$raw['chg_mod1'] ?? '', $raw['chg_mod2'] ?? '', $raw['chg_mod3'] ?? '']));
            $diagnoses = array_values(array_filter(array_map(trim(...), explode(',', $raw['enc_dx_codes'] ?? ''))));
            $problems = [];
            $decoded = json_decode(self::text($row['problems_json'] ?? null), true);
            foreach (is_array($decoded) ? $decoded : [] as $problem) {
                if (is_string($problem) && $problem !== self::PROBLEM) {
                    $problems[] = $problem;
                }
            }
            $pending[] = new PendingMeasure(
                self::int($row['id'] ?? null),
                self::int($row['line_id'] ?? null),
                self::text($row['file_name'] ?? null),
                self::int($row['line_number'] ?? null),
                trim(($raw['pat_last'] ?? '') . ', ' . ($raw['pat_first'] ?? ''), ', '),
                self::text($row['mrn'] ?? null),
                self::text($row['date_of_service'] ?? null),
                self::text($row['code'] ?? null),
                implode(':', $modifiers),
                self::text($row['accession'] ?? null),
                $raw['prov_rend_name'] ?? '',
                $diagnoses,
                self::measures(self::text($row['measures'] ?? null)),
                $problems,
            );
        }

        return $pending;
    }

    public function pendingCount(): int
    {
        $count = QueryUtils::fetchSingleValue(
            'SELECT COUNT(*) AS `n` FROM `gifford_measure` AS m JOIN `gifford_charge_line` AS l ON l.`id` = m.`line_id`'
            . ' WHERE m.`status` = ? AND l.`status` = ?',
            'n',
            [self::STATUS_PENDING, LineStatus::Held->value]
        );

        return is_numeric($count) ? (int) $count : 0;
    }

    /**
     * Record the coder's answers; the line posts on the next run. Every measure of the row
     * needs an answer.
     *
     * @param array<array-key, int> $answers measure value => option value (numeric measures are int keys, as PHP stores them)
     * @return bool false when the row isn't pending or an answer is missing or unknown
     */
    public function answer(int $id, array $answers, int $userId, \DateTimeImmutable $now): bool
    {
        $row = QueryUtils::querySingleRow('SELECT `measures` FROM `gifford_measure` WHERE `id` = ? AND `status` = ?', [$id, self::STATUS_PENDING]);
        if (!is_array($row)) {
            return false;
        }
        $chosen = [];
        foreach (self::measures(self::text($row['measures'] ?? null)) as $measure) {
            $option = isset($answers[$measure->value]) ? $measure->option($answers[$measure->value]) : null;
            if ($option === null) {
                return false;
            }
            $chosen[$measure->value] = $option->value;
        }
        QueryUtils::sqlStatementThrowException(
            'UPDATE `gifford_measure` SET `answers_json` = ?, `status` = ?, `answered_at` = ?, `answered_by` = ? WHERE `id` = ? AND `status` = ?',
            [json_encode($chosen, JSON_THROW_ON_ERROR), self::STATUS_ANSWERED, $now->format('Y-m-d H:i:s'), $userId, $id, self::STATUS_PENDING]
        );

        return true;
    }

    /**
     * Let the line post with no answer and no codes (the report never came, or the coder's
     * call). Returns false when the row isn't pending.
     */
    public function release(int $id, int $userId, \DateTimeImmutable $now): bool
    {
        $row = QueryUtils::querySingleRow('SELECT `id` FROM `gifford_measure` WHERE `id` = ? AND `status` = ?', [$id, self::STATUS_PENDING]);
        if (!is_array($row)) {
            return false;
        }
        QueryUtils::sqlStatementThrowException(
            'UPDATE `gifford_measure` SET `status` = ?, `answered_at` = ?, `answered_by` = ? WHERE `id` = ?',
            [self::STATUS_RELEASED, $now->format('Y-m-d H:i:s'), $userId, $id]
        );

        return true;
    }

    /** Where the line's charge went when it posted. */
    public function markPosted(int $lineId, int $pid, int $encounter, int $billingId, \DateTimeImmutable $now): void
    {
        QueryUtils::sqlStatementThrowException(
            'UPDATE `gifford_measure` SET `pid` = ?, `encounter` = ?, `billing_id` = ?, `posted_at` = ? WHERE `line_id` = ?',
            [$pid, $encounter, $billingId, $now->format('Y-m-d H:i:s'), $lineId]
        );
    }

    /**
     * Answered and posted registry measures with dates of service in the range, with what
     * the ACR row needs, oldest first.
     *
     * @return list<AcrRow>
     */
    public function acrRows(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT m.`measures`, m.`answers_json`, b.`code`, b.`justify`, p.`pubpid`, p.`DOB`, p.`sex`, e.`date`,'
            . ' l.`accession`, u.`npi`'
            . ' FROM `gifford_measure` AS m'
            . ' JOIN `billing` AS b ON b.`id` = m.`billing_id`'
            . ' JOIN `patient_data` AS p ON p.`pid` = m.`pid`'
            . ' JOIN `form_encounter` AS e ON e.`pid` = m.`pid` AND e.`encounter` = m.`encounter`'
            . ' JOIN `gifford_charge_line` AS l ON l.`id` = m.`line_id`'
            . ' LEFT JOIN `users` AS u ON u.`id` = b.`provider_id`'
            . ' WHERE m.`status` = ? AND e.`date` >= ? AND e.`date` < ? ORDER BY e.`date`, m.`id`',
            [self::STATUS_ANSWERED, $from->format('Y-m-d 00:00:00'), $to->modify('+1 day')->format('Y-m-d 00:00:00')]
        );
        $acr = [];
        foreach ($rows as $row) {
            $answers = self::answers(self::text($row['answers_json'] ?? null));
            $dateOfService = new \DateTimeImmutable(substr(self::text($row['date'] ?? null), 0, 10));
            $dateOfBirth = self::text($row['DOB'] ?? null);
            foreach (self::measures(self::text($row['measures'] ?? null)) as $measure) {
                if ($measure->onClaim()) {
                    continue;
                }
                $value = $answers[$measure->value] ?? null;
                $option = $value === null ? null : $measure->option($value);
                if ($option === null || $option->acrCode === null) {
                    continue;
                }
                $acr[] = new AcrRow(
                    $dateOfService,
                    self::text($row['npi'] ?? null),
                    self::text($row['pubpid'] ?? null),
                    $dateOfBirth === '' ? 0 : MeasureRules::ageOn(new \DateTimeImmutable($dateOfBirth), $dateOfService),
                    self::text($row['sex'] ?? null),
                    $measure,
                    self::text($row['code'] ?? null),
                    self::diagnoses(self::text($row['justify'] ?? null))[0] ?? '',
                    $option->acrCode,
                    self::text($row['accession'] ?? null),
                );
            }
        }

        return $acr;
    }

    /**
     * @return list<Measure>
     */
    private static function measures(string $stored): array
    {
        $measures = [];
        foreach (explode(',', $stored) as $value) {
            $measure = Measure::tryFrom(trim($value));
            if ($measure !== null) {
                $measures[] = $measure;
            }
        }

        return $measures;
    }

    /**
     * @return array<array-key, int>
     */
    private static function answers(string $json): array
    {
        $decoded = json_decode($json, true);
        $answers = [];
        foreach (is_array($decoded) ? $decoded : [] as $measure => $value) {
            if (is_int($value)) {
                $answers[$measure] = $value;
            }
        }

        return $answers;
    }

    /**
     * The diagnoses a billing row's justify points to ("ICD10|I50.20:ICD10|Z95.811:").
     *
     * @return list<string>
     */
    private static function diagnoses(string $justify): array
    {
        $codes = [];
        foreach (explode(':', $justify) as $part) {
            $pieces = explode('|', $part, 2);
            if (count($pieces) === 2 && $pieces[1] !== '') {
                $codes[] = $pieces[1];
            }
        }

        return $codes;
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

    private static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : (is_int($value) ? (string) $value : '');
    }
}
