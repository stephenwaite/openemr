<?php

/**
 * Queries shared by the MIPS measures.
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

final readonly class MipsRepository
{
    /** A living patient. A zero deceased_date counts as living, as in is_patient_deceased(). */
    public const ALIVE = "(p.deceased_date IS NULL OR p.deceased_date = '0000-00-00 00:00:00')";

    /**
     * @param list<int|string> $binds
     * @return list<array<mixed>>
     */
    public function records(string $sql, array $binds = []): array
    {
        return QueryUtils::fetchRecords($sql, $binds);
    }

    /**
     * Performance-period encounters with a qualifying visit code that also match
     * $where, one row per encounter, with the patient's columns.
     *
     * @param list<string> $visitCodes
     * @param list<int|string> $binds for $where
     * @return list<array<mixed>>
     */
    public function qualifyingEncounters(MipsPeriod $period, array $visitCodes, string $where, array $binds): array
    {
        return $this->records(
            "SELECT fe.pid, fe.encounter, fe.date, p.lname, p.fname, p.mname, p.sex, p.DOB,
                    TIMESTAMPDIFF(YEAR, p.DOB, fe.date) AS age,
                    GROUP_CONCAT(DISTINCT b.code ORDER BY b.code SEPARATOR ', ') AS visit_codes
               FROM form_encounter AS fe
               JOIN patient_data AS p ON p.pid = fe.pid
               JOIN billing AS b ON b.encounter = fe.encounter AND b.pid = fe.pid AND b.activity = 1
                AND b.code IN (" . self::placeholders($visitCodes) . ")
              WHERE fe.date BETWEEN ? AND ? AND " . self::ALIVE . " AND ($where)
           GROUP BY fe.pid, fe.encounter, fe.date, p.lname, p.fname, p.mname, p.sex, p.DOB
           ORDER BY fe.date DESC",
            [...$visitCodes, $period->startTs(), $period->endTs(), ...$binds]
        );
    }

    /**
     * Fee sheet lines with any of $codes for these patients, encounter day between $from and $to.
     *
     * @param list<int> $pids
     * @param list<string> $codes
     * @return list<array<mixed>>
     */
    public function trackedCodes(array $pids, array $codes, string $from, string $to): array
    {
        if ($pids === [] || $codes === []) {
            return [];
        }
        return $this->records(
            "SELECT b.pid, b.encounter, b.code, fe.date AS encounter_date
               FROM billing AS b
               JOIN form_encounter AS fe ON fe.encounter = b.encounter AND fe.pid = b.pid
              WHERE b.activity = 1
                AND b.pid IN (" . self::placeholders($pids) . ")
                AND b.code IN (" . self::placeholders($codes) . ")
                AND fe.date BETWEEN ? AND ?
           ORDER BY fe.date, b.date",
            [...$pids, ...$codes, $from . ' 00:00:00', $to . ' 23:59:59']
        );
    }

    /**
     * Encounters (any kind) whose reason names one of $terms, between $from and $to.
     *
     * @param list<int> $pids
     * @param list<string> $terms
     * @return list<array<mixed>>
     */
    public function reasonMentions(array $pids, array $terms, string $from, string $to): array
    {
        if ($pids === [] || $terms === []) {
            return [];
        }
        [$match, $binds] = self::reasonMatches($terms);
        return $this->records(
            "SELECT fe.pid, fe.date AS encounter_date
               FROM form_encounter AS fe
              WHERE fe.pid IN (" . self::placeholders($pids) . ")
                AND fe.date BETWEEN ? AND ?
                AND $match
           ORDER BY fe.date",
            [...$pids, $from . ' 00:00:00', $to . ' 23:59:59', ...$binds]
        );
    }

    /**
     * Each patient's diagnoses matching $dxCondition (on billing alias b) between
     * $from and $to, as "L40.0 (2026-02-11), ...", newest first.
     *
     * @param list<int> $pids
     * @return array<int, string>
     */
    public function diagnosisHistory(array $pids, string $dxCondition, string $from, string $to): array
    {
        if ($pids === []) {
            return [];
        }
        $rows = $this->records(
            "SELECT b.pid,
                    GROUP_CONCAT(DISTINCT CONCAT(b.code, ' (', DATE_FORMAT(fe.date, '%Y-%m-%d'), ')')
                                 ORDER BY fe.date DESC SEPARATOR ', ') AS dx_history
               FROM billing AS b
               JOIN form_encounter AS fe ON fe.encounter = b.encounter AND fe.pid = b.pid
              WHERE b.activity = 1
                AND b.pid IN (" . self::placeholders($pids) . ")
                AND fe.date BETWEEN ? AND ?
                AND ($dxCondition)
           GROUP BY b.pid",
            [...$pids, $from . ' 00:00:00', $to . ' 23:59:59']
        );
        $history = [];
        foreach ($rows as $row) {
            $history[MipsRows::int($row, 'pid')] = MipsRows::str($row, 'dx_history');
        }
        return $history;
    }

    /**
     * "(LOWER(fe.reason) LIKE ? OR ...)" and its binds, matching any of $terms anywhere.
     *
     * @param list<string> $terms
     * @return array{string, list<string>}
     */
    public static function reasonMatches(array $terms): array
    {
        $binds = array_map(static fn(string $t): string => '%' . strtolower($t) . '%', $terms);
        $match = '(' . implode(' OR ', array_fill(0, count($terms), 'LOWER(fe.reason) LIKE ?')) . ')';
        return [$match, $binds];
    }

    /**
     * @param list<int|string> $values
     */
    public static function placeholders(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }
}
