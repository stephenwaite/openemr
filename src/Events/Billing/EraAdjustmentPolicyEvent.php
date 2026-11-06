<?php

/**
 * Lets listeners decide how ERA posting (sl_eob_process.php) treats one claim
 * adjustment from an 835, for payer-specific rules such as a Medicaid payer of
 * last resort whose secondary adjustments are real write-offs.
 *
 * The defaults are the stock rules, so with no listener posting is unchanged:
 * - a secondary payer's adjustments are recorded as zero-dollar notes;
 * - CO-45 and CO-59 count as contractual write-offs, so a line the payer paid
 *   nothing on is still posted rather than flagged as an error;
 * - the memo reads "Adjust code <reason>".
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Events\Billing;

use Symfony\Contracts\EventDispatcher\Event;

final class EraAdjustmentPolicyEvent extends Event
{
    public const EVENT_NAME = 'billing.era.adjustment_policy';

    /** CO reasons the stock posting treats as contractual write-offs. */
    private const CONTRACTUAL_CO_REASONS = ['45', '59'];

    private bool $secondaryWriteOff = false;

    private bool $contractual;

    private string $memo;

    /**
     * @param string $payerId the payer's ID from the 835 (N1*PR or REF*2U)
     * @param bool $primary whether this remittance is posted as the primary payer's
     * @param string $procedureCode the service line's procedure code, without modifiers
     */
    public function __construct(
        public readonly string $payerId,
        public readonly bool $primary,
        public readonly string $groupCode,
        public readonly string $reasonCode,
        public readonly string $procedureCode,
    ) {
        $this->contractual = $groupCode === 'CO' && in_array($reasonCode, self::CONTRACTUAL_CO_REASONS, true);
        $this->memo = 'Adjust code ' . $reasonCode;
    }

    /**
     * Post a non-primary payer's adjustment as a write-off that credits the
     * claim, instead of the zero-dollar note non-primary adjustments get.
     * Patient responsibility (PR) is always a note.
     */
    public function postSecondaryAsWriteOff(): void
    {
        $this->secondaryWriteOff = true;
    }

    public function isSecondaryWriteOff(): bool
    {
        return $this->secondaryWriteOff && !$this->primary && $this->groupCode !== 'PR';
    }

    /**
     * Treat the adjustment as a contractual write-off: posted even when the
     * payer paid nothing on the line.
     */
    public function markContractual(): void
    {
        $this->contractual = true;
    }

    public function isContractual(): bool
    {
        return $this->contractual;
    }

    /**
     * The memo stored with the posted adjustment. Kept to 25 characters, the
     * width statements print.
     */
    public function setMemo(string $memo): void
    {
        $this->memo = mb_substr($memo, 0, 25);
    }

    public function getMemo(): string
    {
        return $this->memo;
    }
}
