<?php

/**
 * A built MIPS report: the measure's patients plus the counts and rates shared
 * by every measure.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Mips;

final readonly class MipsReport
{
    /**
     * @param list<MipsCriterion> $criteria
     * @param list<MipsPatient> $patients every patient the measure lists, in display order
     * @param list<string> $columns labels of the measure's own patient columns, in order
     */
    public function __construct(
        public MipsMeasure $measure,
        public MipsPeriod $period,
        public array $criteria,
        public array $patients,
        public array $columns,
    ) {
    }

    public function denominatorCount(): int
    {
        return count(array_filter($this->patients, static fn(MipsPatient $p): bool => $p->inDenominator));
    }

    public function encounterCount(): int
    {
        return array_sum(array_map(static fn(MipsPatient $p): int => count($p->encounters), $this->patients));
    }

    /**
     * How many listed patients carry each flag.
     *
     * @return array<string, int>
     */
    public function flagCounts(): array
    {
        $counts = [];
        foreach ($this->patients as $patient) {
            foreach ($patient->flags as $flag) {
                $counts[$flag] = ($counts[$flag] ?? 0) + 1;
            }
        }
        return $counts;
    }

    /**
     * Patients per status of one criterion: all listed, and in the denominator.
     *
     * @return array<string, array{all: int, denominator: int}>
     */
    public function counts(MipsCriterion $criterion): array
    {
        $counts = [];
        foreach ($criterion->allStatuses() as $status) {
            $counts[$status->label] = ['all' => 0, 'denominator' => 0];
        }
        foreach ($this->patients as $patient) {
            $label = ($patient->statuses[$criterion->key] ?? MipsStatus::notReported())->label;
            $counts[$label] ??= ['all' => 0, 'denominator' => 0];
            $counts[$label]['all']++;
            if ($patient->inDenominator) {
                $counts[$label]['denominator']++;
            }
        }
        return $counts;
    }

    /**
     * The patient's status label for each criterion, in order.
     *
     * @return list<string>
     */
    public function statusLabels(MipsPatient $patient): array
    {
        return array_map(
            static fn(MipsCriterion $c): string => ($patient->statuses[$c->key] ?? MipsStatus::notReported())->label,
            $this->criteria
        );
    }

    /** Reported (Met + Exception + Not Met) over the denominator, as a percentage. */
    public function completeness(MipsCriterion $criterion): ?float
    {
        $denominator = $this->denominatorCount();
        if ($denominator === 0) {
            return null;
        }
        $reported = $this->roleCount($criterion, MipsRole::Met)
            + $this->roleCount($criterion, MipsRole::Exception)
            + $this->roleCount($criterion, MipsRole::NotMet);
        return round(100 * $reported / $denominator, 2);
    }

    /** Met over (Met + Not Met) in the denominator, as a percentage. */
    public function performanceRate(MipsCriterion $criterion): ?float
    {
        $met = $this->roleCount($criterion, MipsRole::Met);
        $scored = $met + $this->roleCount($criterion, MipsRole::NotMet);
        return $scored === 0 ? null : round(100 * $met / $scored, 2);
    }

    private function roleCount(MipsCriterion $criterion, MipsRole $role): int
    {
        $count = 0;
        foreach ($this->patients as $patient) {
            if ($patient->inDenominator && ($patient->statuses[$criterion->key] ?? null)?->role === $role) {
                $count++;
            }
        }
        return $count;
    }
}
