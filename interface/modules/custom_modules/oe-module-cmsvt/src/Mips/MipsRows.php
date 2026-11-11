<?php

/**
 * Typed access to database rows, and the per-patient indexes every measure builds on.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Mips;

final readonly class MipsRows
{
    /**
     * @param array<int, list<MipsCodeEntry>> $codesByPid oldest first
     * @param array<int, list<MipsEncounter>> $encountersByPid newest first
     * @param array<int, list<string>> $mentionsByPid distinct days, oldest first
     */
    public function __construct(
        public array $codesByPid,
        public array $encountersByPid,
        public array $mentionsByPid,
    ) {
    }

    public static function index(MipsData $data): self
    {
        $codesByPid = [];
        $codesByEncounter = [];
        foreach ($data->codes as $row) {
            $entry = new MipsCodeEntry(
                strtoupper(trim(self::str($row, 'code'))),
                substr(self::str($row, 'encounter_date'), 0, 10),
                self::int($row, 'encounter'),
            );
            $codesByPid[self::int($row, 'pid')][] = $entry;
            $codesByEncounter[$entry->encounter][] = $entry->label();
        }

        $encountersByPid = [];
        foreach ($data->encounters as $row) {
            $encounter = self::int($row, 'encounter');
            $encountersByPid[self::int($row, 'pid')][] = new MipsEncounter(
                $encounter,
                self::str($row, 'date'),
                self::str($row, 'age'),
                self::str($row, 'visit_codes'),
                $codesByEncounter[$encounter] ?? [],
            );
        }

        $mentionsByPid = [];
        foreach ($data->mentions as $row) {
            $mentionsByPid[self::int($row, 'pid')][] = substr(self::str($row, 'encounter_date'), 0, 10);
        }
        foreach ($mentionsByPid as $pid => $days) {
            $days = array_values(array_unique($days));
            sort($days);
            $mentionsByPid[$pid] = $days;
        }

        return new self($codesByPid, $encountersByPid, $mentionsByPid);
    }

    /**
     * @return list<MipsCodeEntry>
     */
    public function codes(int $pid): array
    {
        return $this->codesByPid[$pid] ?? [];
    }

    /**
     * @return list<MipsEncounter>
     */
    public function encounters(int $pid): array
    {
        return $this->encountersByPid[$pid] ?? [];
    }

    /**
     * @return list<string>
     */
    public function mentions(int $pid): array
    {
        return $this->mentionsByPid[$pid] ?? [];
    }

    /**
     * Patient rows taken from candidate encounter rows (which carry the patient's
     * columns), one per patient, ordered by name.
     *
     * @param list<array<mixed>> $encounterRows
     * @param array<int, string> $dxHistory pid => diagnosis history
     * @return list<array<mixed>>
     */
    public static function patientsFromEncounters(array $encounterRows, array $dxHistory): array
    {
        $patients = [];
        foreach ($encounterRows as $row) {
            $pid = self::int($row, 'pid');
            $patients[$pid] ??= [
                'pid' => $pid,
                'lname' => self::str($row, 'lname'),
                'fname' => self::str($row, 'fname'),
                'mname' => self::str($row, 'mname'),
                'sex' => self::str($row, 'sex'),
                'DOB' => self::str($row, 'DOB'),
                'dx_history' => $dxHistory[$pid] ?? '',
            ];
        }
        usort(
            $patients,
            static fn(array $a, array $b): int => [$a['lname'], $a['fname'], $a['pid']] <=> [$b['lname'], $b['fname'], $b['pid']]
        );
        return $patients;
    }

    /**
     * @param list<array<mixed>> $rows
     * @return list<int>
     */
    public static function pids(array $rows): array
    {
        $pids = [];
        foreach ($rows as $row) {
            $pids[self::int($row, 'pid')] = true;
        }
        unset($pids[0]);
        return array_keys($pids);
    }

    /**
     * @param array<mixed> $row
     */
    public static function str(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        return is_string($value) || is_int($value) || is_float($value) ? (string) $value : '';
    }

    /**
     * @param array<mixed> $row
     */
    public static function int(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        return is_numeric($value) ? (int) $value : 0;
    }
}
