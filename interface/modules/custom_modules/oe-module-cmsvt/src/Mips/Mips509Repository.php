<?php

/**
 * Queries behind the MIPS 509 melanoma report.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Margaret Haywood <openemr@cmsvt.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Mips;

use OpenEMR\Common\Database\QueryUtils;

final readonly class Mips509Repository
{
    /** Melanoma (C43) or melanoma in situ (D03) on an active fee sheet line. */
    private const MELANOMA_DX = "b.code_type = 'ICD10' AND (b.code LIKE 'C43%' OR b.code LIKE 'D03%')";

    /**
     * Living patients with a melanoma diagnosis billed in the lookback, with every
     * melanoma / Z85.820 (history of melanoma) diagnosis in that window for reference.
     * A zero deceased_date counts as living, as in is_patient_deceased().
     *
     * @return list<array<mixed>>
     */
    public function patients(Mips509Measure $measure): array
    {
        $from = $measure->lookbackStart() . ' 00:00:00';
        $to = $measure->periodEnd() . ' 23:59:59';
        return QueryUtils::fetchRecords(
            "SELECT p.pid, p.lname, p.fname, p.mname, p.sex, p.DOB,
                    (SELECT GROUP_CONCAT(DISTINCT CONCAT(b.code, ' (', DATE_FORMAT(fe.date, '%Y-%m-%d'), ')')
                                         ORDER BY fe.date SEPARATOR ', ')
                       FROM billing AS b
                       JOIN form_encounter AS fe ON fe.encounter = b.encounter AND fe.pid = b.pid
                      WHERE b.pid = p.pid AND b.activity = 1 AND fe.date BETWEEN ? AND ?
                        AND (" . self::MELANOMA_DX . " OR (b.code_type = 'ICD10' AND b.code LIKE 'Z85.820%'))
                    ) AS dx_history
               FROM patient_data AS p
              WHERE (p.deceased_date IS NULL OR p.deceased_date = '0000-00-00 00:00:00')
                AND EXISTS (SELECT 1
                              FROM billing AS b
                              JOIN form_encounter AS fe ON fe.encounter = b.encounter AND fe.pid = b.pid
                             WHERE b.pid = p.pid AND b.activity = 1 AND fe.date BETWEEN ? AND ?
                               AND " . self::MELANOMA_DX . ")
           ORDER BY p.lname, p.fname, p.pid",
            [$from, $to, $from, $to]
        );
    }

    /**
     * Performance-year encounters with a qualifying visit code, one row per encounter.
     *
     * @param list<int> $pids
     * @return list<array<mixed>>
     */
    public function qualifyingEncounters(Mips509Measure $measure, array $pids): array
    {
        if ($pids === []) {
            return [];
        }
        return QueryUtils::fetchRecords(
            "SELECT fe.pid, fe.encounter, fe.date,
                    TIMESTAMPDIFF(YEAR, p.DOB, fe.date) AS age,
                    GROUP_CONCAT(DISTINCT b.code ORDER BY b.code SEPARATOR ', ') AS visit_codes
               FROM form_encounter AS fe
               JOIN patient_data AS p ON p.pid = fe.pid
               JOIN billing AS b ON b.encounter = fe.encounter AND b.pid = fe.pid AND b.activity = 1
                AND b.code_type = 'CPT4' AND b.code IN (" . self::placeholders(Mips509Measure::ENCOUNTER_CODES) . ")
              WHERE fe.pid IN (" . self::placeholders($pids) . ")
                AND fe.date BETWEEN ? AND ?
           GROUP BY fe.pid, fe.encounter, fe.date, p.DOB
           ORDER BY fe.date DESC",
            [
                ...Mips509Measure::ENCOUNTER_CODES,
                ...$pids,
                $measure->periodStart() . ' 00:00:00',
                $measure->periodEnd() . ' 23:59:59',
            ]
        );
    }

    /**
     * Every quality data code and excision CPT on these patients' fee sheets in the lookback.
     *
     * @param list<int> $pids
     * @return list<array<mixed>>
     */
    public function trackedCodes(Mips509Measure $measure, array $pids): array
    {
        if ($pids === []) {
            return [];
        }
        $codes = [...array_keys(Mips509Measure::MEASURE_CODES), ...Mips509Measure::EXCISION_CODES];
        return QueryUtils::fetchRecords(
            "SELECT b.pid, b.encounter, b.code, fe.date AS encounter_date, b.date AS entered_date
               FROM billing AS b
               JOIN form_encounter AS fe ON fe.encounter = b.encounter AND fe.pid = b.pid
              WHERE b.activity = 1
                AND b.pid IN (" . self::placeholders($pids) . ")
                AND b.code IN (" . self::placeholders($codes) . ")
                AND fe.date BETWEEN ? AND ?
           ORDER BY fe.date, b.date",
            [
                ...$pids,
                ...$codes,
                $measure->lookbackStart() . ' 00:00:00',
                $measure->periodEnd() . ' 23:59:59',
            ]
        );
    }

    /**
     * @param list<int|string> $values
     */
    private static function placeholders(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }
}
