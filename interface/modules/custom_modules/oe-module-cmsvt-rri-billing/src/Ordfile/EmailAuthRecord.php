<?php

/**
 * The patient's email, prior authorization and guarantor SSN for one visit
 * (the old EA file, cmsvt_emailauthfile).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Ordfile;

use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeFileAccount;

final readonly class EmailAuthRecord
{
    /**
     * @param string $medrec the visit number (7.0.2 called it medrec)
     * @param ?string $date the last charge's date of service, YYYYMMDD
     */
    public function __construct(
        public string $medrec,
        public string $name,
        public string $ssn,
        public string $email,
        public string $authorization,
        public ?string $date,
    ) {
    }

    /**
     * The record for an account, or null when there is nothing to keep (no
     * email, no authorization, no guarantor SSN). 7.0.2 compared padded fields
     * against blanks and so saved a row for every account.
     */
    public static function forAccount(ChargeFileAccount $file, ?OrdfileLine $lastLine): ?self
    {
        $ssn = $file->guarantor->ssn;
        if ($file->patient->email === '' && $file->visit->authorization === '' && trim($ssn, '0') === '') {
            return null;
        }
        return new self(
            $file->visitNumber,
            $file->guarantor->name->full,
            $ssn,
            $file->patient->email,
            $file->visit->authorization,
            $lastLine?->dateT->format('Ymd'),
        );
    }
}
