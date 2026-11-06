<?php

/**
 * Dispatched once per 837P professional claim so a listener can supply the
 * payer- and practice-specific rules the generator should follow.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Events\Billing;

use OpenEMR\Billing\Claim;
use OpenEMR\Billing\X12\Claim837PRules;
use OpenEMR\Billing\X12\DefaultClaim837PRules;
use Symfony\Contracts\EventDispatcher\Event;

final class Claim837PRulesEvent extends Event
{
    public const EVENT_NAME = 'billing.claim_837p.rules';

    private Claim837PRules $rules;

    public function __construct(public readonly Claim $claim)
    {
        $this->rules = new DefaultClaim837PRules();
    }

    public function getRules(): Claim837PRules
    {
        return $this->rules;
    }

    public function setRules(Claim837PRules $rules): void
    {
        $this->rules = $rules;
    }
}
