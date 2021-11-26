<?php

/**
 * Helper class for RRMC tape
 * Represents an encounter or accession per RRMC
 * which can include multiple radiology charges
 *
 * @package OpenEMR
 * @author Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2021 Stephen Waite <stephen.waite@cmsvt.com>
 * @link https://github.com/openemr/openemr/tree/master
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Billing;

class Accession
{
    protected $acc_no;
    protected $mrn; // medical record number
    protected $charges; // array of charges
    
    /**
     *
     * @var string $acc_no RRMC accession number
     * @var string $mrn medical record number
     * @var array $charges array of CPT/HCPCS with modifiers
     */
    public function __construct($acc_no, $mrn, $charges)
    {
        $this->acc_no = $acc_no;
        $this->mrn = $mrn;
        $this->charges = $charges;
    }



    /**
     * getters for properties
     *
     */
    public function getAcc_No()
    {
        return $this->acc_no;
    }

    /**
     * setters for properties
     *
     */

    /**
     * @param string $acc_no
     */
    public function setId($acc_no): void
    {
        $this->acc_no = $acc_no;
    }

}
