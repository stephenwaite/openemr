<?php

/**
 * MIPS 509 report rows: one entry per melanoma patient with their qualifying
 * visits, tracked codes and criteria status, plus the summary counts.
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

final readonly class Mips509Report
{
    /**
     * @param list<Mips509Patient> $patients every living patient with a melanoma diagnosis in the lookback
     * @param array<string, int> $criteria1Counts status => patients, over the denominator only
     * @param array<string, int> $criteria2Counts status => patients, over the denominator only
     */
    public function __construct(
        public Mips509Measure $measure,
        public array $patients,
        public array $criteria1Counts,
        public array $criteria2Counts,
    ) {
    }

    /**
     * @param list<array<mixed>> $patientRows from Mips509Repository::patients()
     * @param list<array<mixed>> $encounterRows from Mips509Repository::qualifyingEncounters()
     * @param list<array<mixed>> $codeRows from Mips509Repository::trackedCodes(), oldest first
     */
    public static function build(Mips509Measure $measure, array $patientRows, array $encounterRows, array $codeRows): self
    {
        /** @var array<int, list<array{code: string, date: string}>> $codesByPid */
        $codesByPid = [];
        /** @var array<int, list<string>> $codesByEncounter */
        $codesByEncounter = [];
        foreach ($codeRows as $row) {
            $code = strtoupper(trim(self::str($row, 'code')));
            $date = substr(self::str($row, 'encounter_date'), 0, 10);
            if (!$measure->counts($code, $date)) {
                continue;
            }
            $codesByPid[self::int($row, 'pid')][] = ['code' => $code, 'date' => $date];
            $codesByEncounter[self::int($row, 'encounter')][] = $code . ' ' . $date;
        }

        /** @var array<int, list<Mips509Encounter>> $encountersByPid */
        $encountersByPid = [];
        foreach ($encounterRows as $row) {
            $encounter = self::int($row, 'encounter');
            $encountersByPid[self::int($row, 'pid')][] = new Mips509Encounter(
                $encounter,
                self::str($row, 'date'),
                self::str($row, 'age'),
                self::str($row, 'visit_codes'),
                $codesByEncounter[$encounter] ?? [],
            );
        }

        $criteria1Counts = array_fill_keys([...array_values(Mips509Measure::CRITERIA_1_STATUSES), Mips509Measure::NOT_REPORTED], 0);
        $criteria2Counts = array_fill_keys([...array_values(Mips509Measure::CRITERIA_2_STATUSES), Mips509Measure::NOT_REPORTED], 0);
        $patients = [];
        foreach ($patientRows as $row) {
            $pid = self::int($row, 'pid');
            $entries = $codesByPid[$pid] ?? [];
            $codes = array_column($entries, 'code');
            $patient = new Mips509Patient(
                $pid,
                self::str($row, 'lname'),
                self::str($row, 'fname'),
                self::str($row, 'mname'),
                self::str($row, 'sex'),
                self::str($row, 'DOB'),
                self::str($row, 'dx_history'),
                $encountersByPid[$pid] ?? [],
                self::entries($entries, Mips509Measure::EXCISION_CODES, '%s (%s)'),
                self::entries($entries, Mips509Measure::CRITERIA_1_CODES, '%s %s'),
                self::entries($entries, Mips509Measure::CRITERIA_2_CODES, '%s %s'),
                Mips509Measure::criteria1Status($codes),
                Mips509Measure::criteria2Status($codes),
            );
            $patients[] = $patient;
            if ($patient->inDenominator()) {
                $criteria1Counts[$patient->criteria1Status]++;
                $criteria2Counts[$patient->criteria2Status]++;
            }
        }

        return new self($measure, $patients, $criteria1Counts, $criteria2Counts);
    }

    public function denominatorCount(): int
    {
        return count(array_filter($this->patients, static fn(Mips509Patient $p): bool => $p->inDenominator()));
    }

    public function encounterCount(): int
    {
        return array_sum(array_map(static fn(Mips509Patient $p): int => count($p->encounters), $this->patients));
    }

    /** Denominator patients with no excision CPT in the lookback. */
    public function missingExcisionCount(): int
    {
        return count(array_filter(
            $this->patients,
            static fn(Mips509Patient $p): bool => $p->inDenominator() && $p->excisions === []
        ));
    }

    /**
     * @param list<array{code: string, date: string}> $entries
     * @param list<string> $codes
     * @return list<string>
     */
    private static function entries(array $entries, array $codes, string $format): array
    {
        $out = [];
        foreach ($entries as $entry) {
            if (in_array($entry['code'], $codes, true)) {
                $out[] = sprintf($format, $entry['code'], $entry['date']);
            }
        }
        return $out;
    }

    /**
     * @param array<mixed> $row
     */
    private static function str(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        return is_string($value) || is_int($value) || is_float($value) ? (string) $value : '';
    }

    /**
     * @param array<mixed> $row
     */
    private static function int(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        return is_numeric($value) ? (int) $value : 0;
    }
}
