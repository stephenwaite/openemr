<?php

/**
 * A policy number as the old billing system kept it: the first nine characters
 * (POL1) and the rest (POL2), which some rules handle separately.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Account;

final readonly class PolicyNumber
{
    private const PART1_LENGTH = 9;

    private function __construct(
        public string $part1,
        public string $part2,
    ) {
    }

    public static function fromString(string $policy): self
    {
        return new self(substr($policy, 0, self::PART1_LENGTH), substr($policy, self::PART1_LENGTH));
    }

    public static function none(): self
    {
        return new self('', '');
    }

    public function toString(): string
    {
        return $this->part1 . $this->part2;
    }

    public function length(): int
    {
        return strlen($this->part1) + strlen($this->part2);
    }

    public function isEmpty(): bool
    {
        return $this->length() === 0;
    }

    /**
     * Spaces, hyphens and slashes removed from each part. The parts are not
     * re-split, as in the old system, so part 1 can end up shorter than nine.
     */
    public function packed(): self
    {
        return new self(
            str_replace([' ', '-', '/'], '', $this->part1),
            str_replace([' ', '-', '/'], '', $this->part2)
        );
    }

    public function withPart2(string $part2): self
    {
        return new self($this->part1, $part2);
    }
}
