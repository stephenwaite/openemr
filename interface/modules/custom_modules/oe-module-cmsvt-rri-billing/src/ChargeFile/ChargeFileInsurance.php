<?php

/**
 * One of the four insurance blocks of a charge file account, as RRMC sent it.
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

final readonly class ChargeFileInsurance
{
    /**
     * @param string $payerCode RRMC's five-character payer code ('' when the block is empty)
     * @param string $subscriberGender M or F
     * @param string $subscriberDob as sent, ten characters (MM/DD/YYYY)
     * @param string $relate RRMC's relationship code
     * @param string $payerName the payer's name and address as RRMC has them
     * @param string $authorization prior authorization; only the primary block has one
     */
    public function __construct(
        public string $payerCode,
        public string $policyNumber,
        public string $certificate,
        public string $groupNumber,
        public string $groupName,
        public PersonName $subscriber,
        public string $subscriberEmployer,
        public string $subscriberGender,
        public string $subscriberDob,
        public string $subscriberSsn,
        public string $relate,
        public string $payerName,
        public string $payerContact,
        public Address $payerAddress,
        public string $payerPhone,
        public string $authorization,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->payerCode === '';
    }
}
