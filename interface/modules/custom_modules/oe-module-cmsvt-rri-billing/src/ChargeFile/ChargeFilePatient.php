<?php

/**
 * The patient part of a charge file account (## and ++ records).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile;

final readonly class ChargeFilePatient
{
    /**
     * @param string $email kept in the case RRMC sent it; every other field is uppercase
     * @param string $gender M or F as sent (may be blank)
     * @param string $dobRaw YYMMDD as sent (may be blank)
     */
    public function __construct(
        public PersonName $name,
        public Address $address,
        public string $email,
        public string $gender,
        public string $dobRaw,
        public PhoneNumber $phone,
    ) {
    }
}
