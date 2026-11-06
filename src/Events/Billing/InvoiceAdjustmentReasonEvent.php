<?php

/**
 * Lets listeners choose the text manual posting (sl_eob_invoice.php) stores
 * in the adjustment memo for an adjustment reason picked on the invoice.
 *
 * The default is the stock text, the reason's option_id from the adjreason
 * list, so with no listener the memo is unchanged. A site whose reason ids
 * are codes can store the reason's title instead.
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

final class InvoiceAdjustmentReasonEvent extends Event
{
    public const EVENT_NAME = 'billing.invoice.adjustment_reason';

    private string $reasonText;

    /**
     * @param string $reasonId the reason's option_id in the adjreason list
     * @param string $reasonTitle the reason's title in that list ('' if it has none)
     */
    public function __construct(
        public readonly string $reasonId,
        public readonly string $reasonTitle,
    ) {
        $this->reasonText = $reasonId;
    }

    /**
     * Store the reason's title instead of its id. Does nothing when the
     * reason has no title.
     */
    public function useTitle(): void
    {
        if ($this->reasonTitle !== '') {
            $this->reasonText = $this->reasonTitle;
        }
    }

    public function getReasonText(): string
    {
        return $this->reasonText;
    }
}
