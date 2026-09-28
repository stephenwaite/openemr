<?php

/**
 * Lets listeners adjust an eligibility (270) request row before it is validated and sent.
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

final class EligibilityRequestFilterEvent extends Event
{
    public const EVENT_NAME = 'billing.eligibility.request.filter';

    /**
     * @param array<mixed> $row the patient/provider/payer row EDI270 builds the request from
     */
    public function __construct(private array $row)
    {
    }

    /**
     * @return array<mixed>
     */
    public function getRow(): array
    {
        return $this->row;
    }

    /**
     * @param array<mixed> $row
     */
    public function setRow(array $row): void
    {
        $this->row = $row;
    }
}
