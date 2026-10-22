<?php

/**
 * The charge file after RRMC's charge rules, and what the rules reported.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Rules;

use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeFileAccount;

final readonly class ChargeRulesResult
{
    /**
     * @param list<ChargeFileAccount> $accounts in file order, without the charges
     *                                          the rules dropped
     * @param list<string> $messages errfile008 lines, in the order they arose
     */
    public function __construct(
        public array $accounts,
        public array $messages,
    ) {
    }
}
