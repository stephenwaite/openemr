<?php

/**
 * MIPS quality measure 509 (Melanoma: Tracking and Evaluation of Recurrence):
 * code lists, reporting windows and the per-patient status rules.
 *
 * Codes follow the 2026 specification (v10.0). M1386 itself isn't tracked; its
 * reference coding (the excision CPTs) is used in its place.
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

final readonly class Mips509Measure
{
    /** Years of melanoma diagnosis and excision history searched before the performance year. */
    public const LOOKBACK_YEARS = 5;

    /** Qualifying office / consult visits. */
    public const ENCOUNTER_CODES = [
        '99202', '99203', '99204', '99205', '99211', '99212', '99213', '99214', '99215',
        '99242', '99243', '99244', '99245',
    ];

    /** M1386 reference coding (excisional surgery), searched across the lookback. */
    public const EXCISION_CODES = [
        '11600', '11601', '11602', '11603', '11604', '11606',
        '11620', '11621', '11622', '11623', '11624', '11626',
        '11640', '11641', '11642', '11643', '11644', '11646',
        '17311', '17312', '17313', '17314', '17315',
    ];

    /** Quality data codes, counted only during the performance period. */
    public const MEASURE_CODES = [
        'M1388' => 'Crit 1 Met: exam for recurrence performed',
        'M1392' => 'Crit 1 & 2 Exception: refusal / lost to follow-up',
        'M1390' => 'Crit 1 Not Met: no documented exam',
        'M1391' => 'Crit 2 Met (inverse): recurrent melanoma diagnosed',
        'M1393' => 'Crit 2 Not Met (inverse): no recurrence',
    ];

    public const CRITERIA_1_CODES = ['M1388', 'M1392', 'M1390'];
    public const CRITERIA_2_CODES = ['M1391', 'M1392', 'M1393'];

    public const NOT_REPORTED = 'Not Reported';

    /** Criteria 1: the most advantageous code wins (Met > Exception > Not Met). */
    public const CRITERIA_1_STATUSES = [
        'M1388' => 'Met (M1388)',
        'M1392' => 'Exception (M1392)',
        'M1390' => 'Not Met (M1390)',
    ];

    /** Criteria 2 (inverse): the recurrence result first, then the exception. */
    public const CRITERIA_2_STATUSES = [
        'M1391' => 'Recurrence (M1391)',
        'M1393' => 'No Recurrence (M1393)',
        'M1392' => 'Exception (M1392)',
    ];

    public function __construct(public int $year)
    {
    }

    public function periodStart(): string
    {
        return $this->year . '-01-01';
    }

    public function periodEnd(): string
    {
        return $this->year . '-12-31';
    }

    public function lookbackStart(): string
    {
        return ($this->year - self::LOOKBACK_YEARS) . '-01-01';
    }

    /**
     * Whether a tracked code entered on an encounter of this date counts:
     * quality data codes only inside the performance year, excision CPTs across the lookback.
     */
    public function counts(string $code, string $encounterDate): bool
    {
        $day = substr($encounterDate, 0, 10);
        if ($day < $this->lookbackStart() || $day > $this->periodEnd()) {
            return false;
        }
        if (isset(self::MEASURE_CODES[$code])) {
            return $day >= $this->periodStart();
        }
        return in_array($code, self::EXCISION_CODES, true);
    }

    /**
     * @param list<string> $codes the patient's counted codes
     */
    public static function criteria1Status(array $codes): string
    {
        return self::firstStatus(self::CRITERIA_1_STATUSES, $codes);
    }

    /**
     * @param list<string> $codes the patient's counted codes
     */
    public static function criteria2Status(array $codes): string
    {
        return self::firstStatus(self::CRITERIA_2_STATUSES, $codes);
    }

    /**
     * @param array<string, string> $statuses code => status, in priority order
     * @param list<string> $codes
     */
    private static function firstStatus(array $statuses, array $codes): string
    {
        foreach ($statuses as $code => $status) {
            if (in_array($code, $codes, true)) {
                return $status;
            }
        }
        return self::NOT_REPORTED;
    }
}
