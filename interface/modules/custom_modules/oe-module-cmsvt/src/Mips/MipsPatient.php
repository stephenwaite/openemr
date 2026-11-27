<?php

/**
 * One patient on a MIPS report.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Mips;

final readonly class MipsPatient
{
    /**
     * @param list<MipsEncounter> $encounters qualifying visits in the performance year, newest first
     * @param array<string, MipsStatus> $statuses criterion key => status
     * @param array<string, list<string>> $columns the measure's own columns, label => values
     * @param list<string> $flags things to review on the chart
     */
    public function __construct(
        public int $pid,
        public string $lname,
        public string $fname,
        public string $mname,
        public string $sex,
        public string $dob,
        public string $dxHistory,
        public array $encounters,
        public bool $inDenominator,
        public array $statuses,
        public array $columns,
        public array $flags,
    ) {
    }

    /**
     * @param array<mixed> $row a patient row: pid, lname, fname, mname, sex, DOB, dx_history
     * @param list<MipsEncounter> $encounters
     * @param array<string, MipsStatus> $statuses
     * @param array<string, list<string>> $columns
     * @param list<string> $flags
     */
    public static function fromRow(
        array $row,
        array $encounters,
        bool $inDenominator,
        array $statuses,
        array $columns,
        array $flags,
    ): self {
        return new self(
            MipsRows::int($row, 'pid'),
            MipsRows::str($row, 'lname'),
            MipsRows::str($row, 'fname'),
            MipsRows::str($row, 'mname'),
            MipsRows::str($row, 'sex'),
            MipsRows::str($row, 'DOB'),
            MipsRows::str($row, 'dx_history'),
            $encounters,
            $inDenominator,
            $statuses,
            $columns,
            $flags,
        );
    }
}
