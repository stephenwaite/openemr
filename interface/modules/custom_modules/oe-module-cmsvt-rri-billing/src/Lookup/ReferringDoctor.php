<?php

/**
 * A referring doctor: an address book user (abook_type external_provider)
 * paired with RRMC's three-character key in cmsvt_refphy.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Lookup;

final readonly class ReferringDoctor
{
    /**
     * @param int $userId users.id
     * @param string $refkey RRMC's key ('' when the user has no cmsvt_refphy row)
     */
    public function __construct(
        public int $userId,
        public string $refkey,
        public string $lastName,
        public string $firstName,
        public string $npi,
    ) {
    }

    public function label(): string
    {
        return $this->lastName . ';' . $this->firstName . ' NPI ' . $this->npi . ' (' . ($this->refkey === '' ? 'no key' : $this->refkey) . ')';
    }
}
