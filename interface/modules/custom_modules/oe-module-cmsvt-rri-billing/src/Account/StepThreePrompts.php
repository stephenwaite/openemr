<?php

/**
 * What step 3 asks the biller. The questions are built by the step; this only
 * asks them (a console in the command, a script in tests).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Account;

interface StepThreePrompts
{
    public function confirm(string $question, bool $default): bool;

    public function ask(string $question): string;

    /**
     * @param non-empty-list<string> $choices
     * @return int the index of the chosen one
     */
    public function choose(string $question, array $choices): int;

    /** Shown to the biller, nothing to answer. */
    public function notice(string $message): void;

    /** Shown to the biller as a problem, nothing to answer. */
    public function warning(string $message): void;
}
