<?php

/**
 * Step 1's checks of a charge file (7.0.2's qqq): RRMC's charge rules and
 * the same patient with different primaries (errfile008), and insurances
 * that can't be billed electronically (errfile007). It changes nothing; step
 * 3 applies the charge rules again when it reads the file.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Step;

use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeFileAccount;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\ChargeMaster;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\PayerDirectory;
use Cmsvt\OpenEMR\Modules\RriBilling\Rules\ChargeRules;
use Cmsvt\OpenEMR\Modules\RriBilling\Rules\PayerCheck;
use Cmsvt\OpenEMR\Modules\RriBilling\Rules\VisitInsuranceCheck;

final readonly class StepOne
{
    public function __construct(
        private ChargeMaster $chargeMaster,
        private PayerDirectory $payers,
    ) {
    }

    /**
     * @param list<ChargeFileAccount> $accounts the charge file as read
     * @return array{list<string>, list<string>} errfile008 and errfile007 lines
     */
    public function check(array $accounts): array
    {
        $errfile008 = [
            ...(new ChargeRules($this->chargeMaster))->apply($accounts)->messages,
            ...(new VisitInsuranceCheck())->check($accounts),
        ];
        return [$errfile008, (new PayerCheck($this->payers))->check($accounts)];
    }
}
