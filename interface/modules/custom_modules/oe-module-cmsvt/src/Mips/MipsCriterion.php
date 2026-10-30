<?php

/**
 * One reported criterion of a measure (a numerator): its quality data codes and
 * the rule that turns a patient's codes into one status.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Mips;

final readonly class MipsCriterion
{
    /**
     * @param list<MipsStatus> $statuses one per code; for BestCode, in priority order
     * @param bool $mostRecent true: the latest code counts (conflicting codes on that
     *                         encounter are flagged); false: the first status in
     *                         $statuses that the patient has a code for wins
     * @param bool $inverse lower performance rate is better
     * @param ?MipsStatus $absent the status of a patient with none of the codes,
     *                            when that counts (e.g. as Not Met); null: Not Reported
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $statuses,
        public bool $mostRecent = false,
        public bool $inverse = false,
        public ?MipsStatus $absent = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_map(static fn(MipsStatus $s): string => $s->code, $this->statuses);
    }

    /**
     * Every status this criterion can report, in display order.
     *
     * @return list<MipsStatus>
     */
    public function allStatuses(): array
    {
        $all = $this->statuses;
        if ($this->mostRecent) {
            $all[] = MipsStatus::conflicting();
        }
        $all[] = $this->absent ?? MipsStatus::notReported();
        return $all;
    }

    /**
     * @param list<MipsCodeEntry> $entries the patient's codes in the performance period, oldest first
     */
    public function status(array $entries): MipsStatus
    {
        $codes = $this->codes();
        $mine = array_values(array_filter($entries, static fn(MipsCodeEntry $e): bool => in_array($e->code, $codes, true)));
        if ($mine === []) {
            return $this->absent ?? MipsStatus::notReported();
        }
        if ($this->mostRecent) {
            $last = $mine[count($mine) - 1];
            $onLast = array_unique(array_map(
                static fn(MipsCodeEntry $e): string => $e->code,
                array_filter($mine, static fn(MipsCodeEntry $e): bool => $e->encounter === $last->encounter)
            ));
            return count($onLast) > 1 ? MipsStatus::conflicting() : $this->statusFor($last->code);
        }
        $have = array_map(static fn(MipsCodeEntry $e): string => $e->code, $mine);
        foreach ($this->statuses as $status) {
            if (in_array($status->code, $have, true)) {
                return $status;
            }
        }
        return MipsStatus::notReported();
    }

    private function statusFor(string $code): MipsStatus
    {
        foreach ($this->statuses as $status) {
            if ($status->code === $code) {
                return $status;
            }
        }
        return MipsStatus::notReported();
    }
}
