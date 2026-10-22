<?php

/**
 * A patient's account in the old billing system's account file (cmsvt_actfile),
 * as step 3 builds it from the charge file before saving it.
 *
 * This is the working record the step 3 rules edit in turn, so it is mutable;
 * a new one is made for every patient. Property names are the table's columns.
 * Insurance codes (prins, seins, trins) are the old system's three-digit payer
 * codes; on sites moved from it they are insurance_companies ids.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Account;

final class AccountRecord
{
    /** Account number: the patient's RRMC MRN. */
    public string $actno = '';
    /** "LAST;FIRSTMIDDLE" */
    public string $garname = '';
    public string $billadd = '';
    public string $street = '';
    public string $city = '';
    public string $state = '';
    public string $zip = '';
    public string $collt = '0';
    /** Ten digits, no separators. */
    public string $phone = '';
    public string $sex = '';
    /** Old relate code: 2/4 adult/child male, K/M adult/child female. */
    public string $relate = '';
    public string $mstat = '';
    /** YYYYMMDD */
    public string $dob = '';
    public string $dunning = '1';
    public string $acctstat = '1';
    public string $prmplr = '';
    public string $prins = '';
    /** A assigned, U unassigned. */
    public string $prassign = 'U';
    public string $proffice = '';
    public string $prgroup = '';
    public PolicyNumber $pripol;
    public string $prname = '';
    public string $prrelate = '';
    public string $semplr = '';
    public string $seins = '';
    public string $seassign = '';
    public string $seoffice = '';
    public string $segroup = '';
    public PolicyNumber $secpol;
    public string $sename = '';
    public string $serelate = '';
    public string $trinsind = '0';
    public string $trins = '001';
    public string $inspend = '0000000';
    public string $lastbill = '00000000';
    public string $assignm = '';
    public string $private = '';
    public string $billcycle = '';
    public string $delete = '';
    public ?string $garno = null;
    public string $prgrpname = '';
    public string $segrpname = '';
    public string $namekey = '';
    /** The charge file's relationship codes, kept for the patient update (not saved). */
    public string $prrelateOriginal = '';
    public string $serelateOriginal = '';

    public function __construct()
    {
        $this->pripol = PolicyNumber::none();
        $this->secpol = PolicyNumber::none();
    }
}
