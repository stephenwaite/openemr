<?php

/**
 * One ordfile charge as step 6 bills it: an encounter of its own and a CPT
 * line, with the accession field's diagnosis.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Billing;

use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\OrdfileLine;

final readonly class BillingCharge
{
    /**
     * @param int $recordId the ordfile line's cmsvt_ordfile_record_id
     * @param ?int $providerId the reading doctor's user, null when the line's doctor code isn't the reading doctor's
     */
    public function __construct(
        public int $recordId,
        public OrdfileLine $line,
        public BillingPatient $patient,
        public PlaceOfService $place,
        public int $facilityId,
        public ?int $providerId,
    ) {
    }

    /** mod2, mod3 and mod4, those not blank. */
    public function modifiers(): string
    {
        $modifiers = array_filter([$this->line->mod2, $this->line->mod3, $this->line->mod4], static fn(string $mod): bool => trim($mod) !== '');
        return implode(':', $modifiers);
    }

    /** RRMC sends the diagnosis in the accession field; '' when there is none. */
    public function diagnosis(): string
    {
        return trim($this->line->accession);
    }
}
