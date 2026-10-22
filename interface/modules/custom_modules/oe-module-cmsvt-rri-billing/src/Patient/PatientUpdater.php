<?php

/**
 * Step 3's patient and insurance update (the 7.0.2 RRIPatientChargeUpdater,
 * from trisquel's running copy): creates or updates the patient by MRN and
 * adds the primary and secondary policies the account ended up with.
 *
 * As on trisquel (eis): every policy's subscriber is the patient ("self"),
 * with the patient's SSN; a new policy starts on January 1 of this year, and
 * a policy that matches the current one (payer, policy and group numbers) is
 * left as it is. A different one ends the current policy and is added, unless
 * the current one also started this January 1, which OpenEMR refuses and is
 * reported.
 *
 * Changes from 7.0.2: a previous name is added only when the subscriber's name
 * differs from the patient's, ignoring case (7.0.2 compared case-sensitively
 * with the title-cased patient name, so each patient got their own name in
 * capitals; its secondary check was inverted); an invalid email is reported
 * in errfile248 (7.0.2 wrote it to billing_notes, which 8.4's patient_data
 * doesn't have, so it was lost); ending a policy no longer prints the policy
 * (a leftover var_dump); save failures list field names, not the record.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Patient;

use Cmsvt\OpenEMR\Modules\RriBilling\Account\AccountRecord;
use Cmsvt\OpenEMR\Modules\RriBilling\Account\LegacyCode;
use Cmsvt\OpenEMR\Modules\RriBilling\Account\LegacyDates;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeFileAccount;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\ReferringDoctor;
use OpenEMR\Billing\InsurancePolicyTypes;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Common\Utils\ValidationUtils;
use OpenEMR\Services\InsuranceService;
use OpenEMR\Services\ListService;
use OpenEMR\Services\PatientNameHistoryService;
use OpenEMR\Services\PatientService;
use OpenEMR\Services\Search\SearchModifier;
use OpenEMR\Services\Search\StringSearchField;
use OpenEMR\Validators\ProcessingResult;
use Psr\Clock\ClockInterface;

final class PatientUpdater implements PatientUpdates
{
    private const SELF = 'self';
    private const SELF_PAY = '001';
    private const WORK_COMP_RRMC_CODES = ['00931', '00932'];
    private const WORK_COMP_CODES = ['075', '076', '095'];

    /** @var array<string, string> sex list option by HL7 code */
    private array $sexes = [];

    /** @var list<string> */
    private array $errors = [];

    public function __construct(
        private readonly PatientService $patients,
        private readonly InsuranceService $insurance,
        private readonly PatientNameHistoryService $nameHistory,
        private readonly ListService $lists,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param string $mrn the MRN zero-filled to eight
     * @param string $patientDob MMDDYY
     * @param string $patientSex F or M
     * @return list<string> errfile248 lines
     */
    public function update(
        ChargeFileAccount $file,
        AccountRecord $account,
        string $mrn,
        string $patientDob,
        string $patientSex,
        ReferringDoctor $doctor,
    ): array {
        $this->errors = [];
        $today = $this->clock->now();
        $name = $file->patient->name;
        $email = $file->patient->email;
        if ($email !== '' && !ValidationUtils::isValidEmail($email)) {
            // 7.0.2 put this in billing_notes, a column 8.4 doesn't have, so it was never saved.
            $this->errors[] = 'Email invalid for MRN ' . $mrn . ': ' . $email;
            $email = null;
        }
        $ssn = $file->primaryInsurance->subscriberSsn;
        $dob = \DateTimeImmutable::createFromFormat('!Ymd', LegacyDates::yyyymmdd($patientDob, $today));
        $pubpid = ltrim($account->actno, '0');
        $record = [
            'pubpid' => $pubpid,
            'ss' => $ssn,
            'fname' => self::titleCase($name->first),
            'lname' => self::titleCase($name->last),
            'mname' => self::titleCase($name->middle),
            'DOB' => $dob === false ? null : $dob->format('Y-m-d'),
            'sex' => $this->sex($patientSex),
            'street' => $file->patient->address->street,
            'street2' => $file->patient->address->suite,
            'city' => $file->patient->address->city,
            'state' => $file->patient->address->state,
            'postal_code' => $file->patient->address->zip,
            'referrer' => $file->visit->referringName->full,
            'ref_providerID' => $doctor->userId,
            'email' => $email,
            'phone_home' => $file->patient->phone->full,
        ];

        $existing = $this->patients->search(['pubpid' => new StringSearchField('pubpid', $pubpid, SearchModifier::EXACT)]);
        $current = $existing->hasData() ? $existing->getFirstDataResult() : null;
        if (is_array($current)) {
            $merged = array_merge($current, $record);
            $result = $this->patients->update(is_string($current['uuid'] ?? null) ? $current['uuid'] : '', $merged);
        } else {
            $result = $this->patients->insert($record);
        }
        if ($result->hasErrors()) {
            $this->errors[] = 'Failed to save patient for MRN ' . $mrn . self::problems($result);
            return $this->errors;
        }
        $saved = $result->getFirstDataResult();
        $pid = is_array($saved) && is_numeric($saved['pid'] ?? null) ? (int) $saved['pid'] : null;
        if ($pid === null && is_array($current) && is_numeric($current['pid'] ?? null)) {
            $pid = (int) $current['pid'];
        }
        if ($pid === null) {
            $this->errors[] = 'Failed to save patient for MRN ' . $mrn . ': no patient id';
            return $this->errors;
        }

        $this->addPreviousNames($pid, $account, $record);
        $policies = $this->insurance->getPoliciesOrganizedByTypeForPatientPid($pid);
        $policies = is_array($policies) ? $policies : [];
        foreach (['primary', 'secondary'] as $type) {
            $currentPolicy = is_array($policies[$type] ?? null) && is_array($policies[$type]['current'] ?? null)
                ? $policies[$type]['current'] : null;
            $this->updatePolicy($file, $account, $mrn, $pid, $ssn, $type, $record, $currentPolicy);
        }
        return $this->errors;
    }

    /**
     * @param array<string, mixed> $patient
     * @param ?array<mixed> $current the patient's current policy of this type
     */
    private function updatePolicy(
        ChargeFileAccount $file,
        AccountRecord $account,
        string $mrn,
        int $pid,
        string $ssn,
        string $type,
        array $patient,
        ?array $current,
    ): void {
        $primary = $type === 'primary';
        $code = $primary ? $account->prins : $account->seins;
        if (LegacyCode::same($code, self::SELF_PAY)) {
            return;
        }
        if ($code === '') {
            $this->errors[] = 'Insurance record code is empty with MRN ' . $mrn;
            return;
        }
        $companyId = ctype_digit($code) ? (int) $code : 0;
        if ($companyId === 0 || !$this->companyExists($companyId)) {
            $this->errors[] = 'Insurance Company not found for code ' . $code . ' with MRN ' . $mrn;
            return;
        }
        $dob = self::isoDate($account->dob);
        if ($dob === null) {
            $this->errors[] = 'Insurance subscriber dob was invalid for ' . $code . ' with MRN ' . $mrn . ' dob was ' . $account->dob;
            return;
        }
        $workComp = $primary
            ? in_array($file->primaryInsurance->payerCode, self::WORK_COMP_RRMC_CODES, true) || LegacyCode::oneOf($account->prins, self::WORK_COMP_CODES)
            : LegacyCode::oneOf($account->seins, self::WORK_COMP_CODES);
        [$lname, $fname, $mname] = $this->subscriberName($primary ? $account->prname : '', $patient);
        $policy = $primary ? $account->pripol : $account->secpol;
        $data = [
            'pid' => $pid,
            'provider' => $companyId,
            'type' => $type,
            'policy_number' => $policy->toString(),
            'group_number' => $primary ? $account->prgroup : $account->segroup,
            'subscriber_relationship' => self::SELF,
            'subscriber_ss' => $ssn,
            'subscriber_DOB' => $dob,
            'subscriber_sex' => $this->sex($account->sex),
            'subscriber_lname' => $lname,
            'subscriber_fname' => $fname,
            'subscriber_mname' => $mname,
            'plan_name' => '',
            'subscriber_street' => $account->billadd,
            'subscriber_street2' => $account->street,
            'subscriber_city' => $account->city,
            'subscriber_state' => $account->state,
            'subscriber_country' => 'USA',
            'subscriber_postal_code' => $account->zip,
            'subscriber_phone' => '',
            'accept_assignment' => ($primary ? $account->prassign : $account->seassign) === 'A' ? 'TRUE' : 'FALSE',
            'copay' => null,
            'date' => $this->clock->now()->format('Y') . '-01-01',
            'policy_type' => $workComp ? InsurancePolicyTypes::POLICY_TYPE_WORKERS_COMPENSATION : InsurancePolicyTypes::POLICY_TYPE_NOT_APPLICABLE,
            'date_end' => null,
            'subscriber_employer' => '',
            'subscriber_employer_street' => '',
            'subscriber_employer_street_2' => '',
            'subscriber_employer_postal_code' => '',
            'subscriber_employer_city' => '',
            'subscriber_employer_state' => '',
            'subscriber_employer_country' => 'USA',
        ];

        if ($current !== null) {
            $same = self::text($current['provider'] ?? null) === (string) $companyId
                && self::text($current['policy_number'] ?? null) === $data['policy_number']
                && self::text($current['group_number'] ?? null) === $data['group_number'];
            if ($same) {
                return;
            }
            if (self::text($current['date'] ?? null) === $data['date']) {
                $this->errors[] = 'Failed to save insurance for patient MRN ' . $mrn . ' for type ' . $type
                    . ' detailed error Insurance has same start date which is not allowed by OpenEMR';
                return;
            }
            $current['date_end'] = $data['date'];
            $ended = $this->insurance->update($current);
            if ($ended instanceof ProcessingResult && $ended->hasErrors()) {
                $this->errors[] = 'Failed to end insurance for patient MRN ' . $mrn . ' for type ' . $type . self::problems($ended);
                return;
            }
        }
        $inserted = $this->insurance->insert($data);
        if ($inserted->hasErrors()) {
            $this->errors[] = 'Failed to save insurance for patient MRN ' . $mrn . ' for type ' . $type . self::problems($inserted);
        }
    }

    /**
     * The policy's subscriber: the account's name for the primary when it has
     * one, the patient otherwise (the secondary is always the patient).
     *
     * @param array<string, mixed> $patient
     * @return array{string, string, string} last, first, middle
     */
    private function subscriberName(string $accountName, array $patient): array
    {
        [$last, $first, $middle] = self::splitAccountName($accountName);
        if ($last === '') {
            return [self::text($patient['lname'] ?? null), self::text($patient['fname'] ?? null), self::text($patient['mname'] ?? null)];
        }
        return [self::titleCase($last), self::titleCase($first), self::titleCase($middle)];
    }

    /**
     * Adds the subscribers' names to the patient's previous names when they
     * differ from the patient's (OpenEMR checks a "self" subscriber's name
     * against them).
     *
     * @param array<string, mixed> $patient
     */
    private function addPreviousNames(int $pid, AccountRecord $account, array $patient): void
    {
        $known = [mb_strtoupper(self::text($patient['lname'] ?? null) . ';' . self::text($patient['fname'] ?? null) . ';' . self::text($patient['mname'] ?? null))];
        $history = $this->nameHistory->getPatientNameHistory($pid);
        foreach (is_array($history) ? $history : [] as $previous) {
            if (is_array($previous)) {
                $known[] = mb_strtoupper(self::text($previous['previous_name_last'] ?? null) . ';' . self::text($previous['previous_name_first'] ?? null)
                    . ';' . self::text($previous['previous_name_middle'] ?? null));
            }
        }
        foreach ([$account->prname, $account->sename] as $accountName) {
            [$last, $first, $middle] = self::splitAccountName($accountName);
            $key = mb_strtoupper($last . ';' . $first . ';' . $middle);
            if ($last === '' || in_array($key, $known, true)) {
                continue;
            }
            $known[] = $key;
            $this->nameHistory->createPatientNameHistory((string) $pid, [
                'previous_name_enddate' => $this->clock->now()->format('Y-m-d'),
                'previous_name_prefix' => '',
                'previous_name_first' => self::titleCase($first),
                'previous_name_middle' => self::titleCase($middle),
                'previous_name_last' => self::titleCase($last),
                'previous_name_suffix' => '',
            ]);
        }
    }

    /**
     * "LAST;FIRST MIDDLE" (the account file's names) as last, first, middle.
     *
     * @return array{string, string, string}
     */
    private static function splitAccountName(string $name): array
    {
        $parts = explode(';', $name, 2);
        $last = trim($parts[0]);
        $rest = ltrim($parts[1] ?? '');
        $space = strpos($rest, ' ');
        return $space === false ? [$last, trim($rest), ''] : [$last, substr($rest, 0, $space), trim(substr($rest, $space))];
    }

    private function sex(string $hl7): string
    {
        if (!isset($this->sexes[$hl7])) {
            $option = $this->lists->getListOptionByCode('sex', 'HL7:' . $hl7);
            $id = is_array($option) ? ($option['option_id'] ?? null) : null;
            $this->sexes[$hl7] = is_string($id) ? $id : 'UNK';
        }
        return $this->sexes[$hl7];
    }

    private function companyExists(int $id): bool
    {
        return QueryUtils::querySingleRow('SELECT 1 FROM `insurance_companies` WHERE `id` = ?', [$id], false) !== false;
    }

    /** YYYYMMDD as Y-m-d when it is a real date. */
    private static function isoDate(string $yyyymmdd): ?string
    {
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $yyyymmdd, $parts) !== 1 || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return null;
        }
        return $parts[1] . '-' . $parts[2] . '-' . $parts[3];
    }

    private static function titleCase(string $value): string
    {
        return ucwords(strtolower($value));
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /** The fields a save refused, without their values. */
    private static function problems(ProcessingResult $result): string
    {
        $messages = $result->getValidationMessages();
        $fields = is_array($messages) ? array_keys($messages) : [];
        return $fields === [] ? ': system error' : ': invalid ' . implode(', ', array_map(strval(...), $fields));
    }
}
