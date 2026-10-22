<?php

/**
 * Step 3 for one charge file account (the per-patient part of the COBOL
 * rrmc006 / 7.0.2 RRMC006Processor::processFile, up to saving): the account
 * file record with its address, relationship, referring doctor and primary
 * and secondary insurance, then the insurance rules.
 *
 * The order, questions and messages are 7.0.2's. Changes, each marked below:
 * - every patient gets a new record (7.0.2 reused one, so legacy columns of
 *   the previous patient's account carried over to new accounts);
 * - fields are compared trimmed, as the COBOL meant. 7.0.2 read the file
 *   untrimmed, so its blank checks never matched: a blank certificate didn't
 *   fall back to the SSN, a "SELF" primary subscriber became "SELF;", a blank
 *   guarantor state or address didn't fall back, and "bad address" never fired;
 * - a blank certificate uses the SSN as the policy (7.0.2 would have thrown a
 *   TypeError here had the check ever matched);
 * - the insurance the biller (or --y-primary / --y-2ndary) gives for one of
 *   RRMC's miscellaneous payers is stored; 7.0.2 looked it up and dropped it.
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

use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeFileAccount;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeFileInsurance;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\PersonName;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\AccountFile;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\PatientInsuranceHistory;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\PayerCompany;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\PayerDirectory;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\PriorPrimary;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\ReferringDoctor;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\ReferringDoctors;
use Psr\Clock\ClockInterface;

final class AccountBuilder
{
    /** RRMC's miscellaneous payer codes: the biller picks the real primary. */
    private const MISC_PRIMARY_CODES = ['00433', '00698', '00699', '00830', '00930'];
    private const MISC_SECONDARY_CODES = ['00698', '00699'];
    private const SELF_SUBSCRIBERS = ['SELF', 'UNK', 'X', 'XX'];

    private const NO_PRIOR_INSURANCE = '095';
    private const SELF_PAY = '001';
    private const MEDICARE = '003';
    private const MEDIGAP = '062';
    private const MEDIGAP_CODE_A = '00058';
    private const MEDIGAP_CODE_B = '00061';
    private const EMPLOYER_GROUP = '091';
    private const REVIEW_CODE = '00951';

    /** @var array<string, ?ReferringDoctor> doctors chosen or added this run, by name and NPI */
    private array $doctors = [];

    /** @var list<string> */
    private array $errors = [];

    /** @var list<string> */
    private array $insuranceErrors = [];

    public function __construct(
        private readonly PayerDirectory $payers,
        private readonly AccountFile $accountFile,
        private readonly PatientInsuranceHistory $insuranceHistory,
        private readonly ReferringDoctors $referringDoctors,
        private readonly InsuranceRules $rules,
        private readonly StepThreePrompts $prompts,
        private readonly StepThreeOptions $options,
        private readonly ClockInterface $clock,
    ) {
    }

    public function build(ChargeFileAccount $file): AccountBuildResult
    {
        $this->errors = [];
        $this->insuranceErrors = [];
        $today = $this->clock->now();
        $patient = $file->patient;

        if ($file->mrn === '00000000') {
            $this->acknowledge('MRN IS ZEROS FOR ' . $patient->name->full);
        }
        $mrn = str_pad($file->mrn, 8, '0', STR_PAD_LEFT);

        // A new record for every patient (7.0.2 reused one across the file).
        $existing = $this->accountFile->find($mrn);
        $account = $existing === null ? new AccountRecord() : clone $existing;
        $prior = null;
        if ($existing !== null) {
            $prior = $this->insuranceHistory->currentPrimary(ltrim($existing->actno, '0'));
        }

        // "LAST;FIRST MIDDLE", as rri-248 wrote it (its untrimmed middle name kept the space).
        $account->garname = $patient->name->last . ';' . $patient->name->first
            . ($patient->name->middle === '' ? '' : ' ' . $patient->name->middle);
        $this->contact($file, $account);
        if ($account->city === '' || $account->state === '' || $account->zip === '') {
            $this->errors[] = $account->garname . ' HAS A BAD ADDRESS, MEDREC # ' . $mrn;
            if (!$this->options->assumeYes) {
                $this->prompts->warning('bad address, will be listed, any key to proceed ' . $account->garname . ' ' . $mrn);
                $this->prompts->ask('Press enter to continue');
            }
        }
        $account->billadd = str_replace('*', ' ', $account->billadd);
        $account->street = str_replace('*', ' ', $account->street);
        $dobReadable = LegacyDates::isReadable($patient->dobRaw);
        $account->dob = $dobReadable ? LegacyDates::yyyymmdd($patient->dobRaw, $today) : '00000000';
        if (!$dobReadable) {
            $this->prompts->warning(LegacyDates::yyyymmdd($patient->dobRaw, $today) . ' BAD DATE');
        }
        $account->garno = null;

        $doctor = $this->referringDoctor($file);
        if ($doctor === null) {
            $this->errors[] = 'Failed to find referring provider NPI ' . $file->visit->referringNpi
                . ' PROVIDER NAME ' . $file->visit->referringName->full . ' MRN ' . $mrn;
            return new AccountBuildResult(null, $existing, null, $mrn, $patient->dobRaw, $patient->gender, $this->errors, $this->insuranceErrors);
        }

        $dob = $patient->dobRaw;
        if (!$dobReadable) {
            $this->prompts->notice($dob . ' ' . $patient->name->full);
            $dob = '000000';
        }
        $sex = $patient->gender === 'F' ? 'F' : 'M';
        $account->sex = $sex;
        $account->relate = LegacyDates::relateCode($sex, $dob, $today);

        $this->selfPay($account);
        $insurances = $file->insurances();
        $secondary = null;
        foreach ($insurances as $index => $insurance) {
            $selected = $this->selectPrimary($file, $account, $insurance, $mrn);
            if ($index === 0 && in_array($insurance->payerCode, self::MISC_PRIMARY_CODES, true)) {
                $this->miscellaneousPrimary($file, $account, $mrn, $prior);
            }
            if ($selected) {
                $secondary = $insurances[$index + 1] ?? null;
                break;
            }
        }
        if ($secondary !== null && !$secondary->isEmpty()) {
            $this->selectSecondary($file, $account, $secondary, $mrn);
            if (in_array($secondary->payerCode, self::MISC_SECONDARY_CODES, true)) {
                $this->miscellaneousSecondary($file, $account);
            }
        }

        foreach ($this->rules->apply($account, $mrn) as $message) {
            $this->errors[] = $message;
        }

        return new AccountBuildResult($account, $existing, $doctor, $mrn, $dob, $sex, $this->errors, $this->insuranceErrors);
    }

    private function acknowledge(string $message): void
    {
        if ($this->options->assumeYes) {
            $this->prompts->notice($message);
        } else {
            $this->prompts->ask($message);
        }
    }

    /**
     * The account's address and phone: the guarantor's, else the patient's.
     */
    private function contact(ChargeFileAccount $file, AccountRecord $account): void
    {
        $guarantor = $file->guarantor->address;
        $account->zip = $guarantor->zip;
        if ($guarantor->state !== '') {
            $account->city = $guarantor->city;
            $account->state = $guarantor->state;
        } else {
            // A city field holding "CITY ST": the state is the last word; one word is a Vermont town.
            $parts = explode(' ', trim($guarantor->city));
            if (count($parts) === 1) {
                $account->state = 'VT';
                $account->city = $parts[0];
            } else {
                $account->state = array_pop($parts);
                $account->city = implode(' ', $parts);
            }
        }
        $phone = $file->patient->phone;
        $account->phone = $phone->area . $phone->exchange . $phone->line;
        $account->billadd = $guarantor->street;
        $account->street = $guarantor->suite;
        if ($guarantor->street === '' && $guarantor->suite === '' && $account->city === '') {
            $address = $file->patient->address;
            $account->billadd = $address->street;
            $account->street = $address->suite;
            $account->city = $address->city;
            $account->state = $address->state;
            $account->zip = $address->zip;
        }
    }

    private function selfPay(AccountRecord $account): void
    {
        $account->prins = self::SELF_PAY;
        $account->seins = self::SELF_PAY;
        $account->trins = self::SELF_PAY;
        $account->pripol = PolicyNumber::none();
        $account->prgroup = '';
        $account->prname = '';
        $account->prrelate = '';
        $account->prmplr = '';
        $account->prgrpname = '';
        $account->secpol = PolicyNumber::none();
        $account->segroup = '';
        $account->sename = '';
        $account->serelate = '';
        $account->semplr = '';
        $account->segrpname = '';
        $account->prassign = 'U';
        $account->seassign = 'U';
    }

    /**
     * SEL-PRINS: makes the insurance the primary when RRMC's code is in the
     * hospital file.
     *
     * @return bool whether it became the primary
     */
    private function selectPrimary(ChargeFileAccount $file, AccountRecord $account, ChargeFileInsurance $insurance, string $mrn): bool
    {
        if ($insurance->isEmpty()) {
            return false;
        }
        $key = $this->payers->insuranceKeyForRrmcCode($insurance->payerCode);
        if ($key === null) {
            $account->pripol = self::assigned($account->pripol, $insurance->certificate);
            $account->prgroup = $insurance->groupNumber;
            $this->insuranceErrors[] = 'HOSP=' . $insurance->payerCode . ' ' . $mrn . ' ' . $account->garname . ' BAD HOSPITAL CODE ';
            return false;
        }
        $company = $this->companyForKey($key);
        if ($company === null) {
            $this->badInsuranceCode($account, $insurance, $key, $mrn);
            $key = self::SELF_PAY;
        } else {
            $account->prassign = $company->assign;
        }
        $account->prins = $key;
        $account->pripol = self::assigned($account->pripol, $insurance->certificate);
        $account->prgroup = $insurance->groupNumber;
        $account->prgrpname = $insurance->groupName;
        $account->prname = self::subscriberName($insurance->subscriber, $account->garname);
        if ($insurance->certificate === '') {
            $account->pripol = PolicyNumber::fromString($insurance->subscriberSsn);
        }
        $gender = $this->subscriberGender($insurance, $account);
        $account->prrelate = self::relation($gender);
        $account->prrelateOriginal = $insurance->relate;
        if (LegacyCode::same($account->prins, self::EMPLOYER_GROUP)) {
            $account->segrpname = $file->primaryInsurance->subscriberEmployer;
        }
        return true;
    }

    /** SEL-SEINS. */
    private function selectSecondary(ChargeFileAccount $file, AccountRecord $account, ChargeFileInsurance $insurance, string $mrn): void
    {
        $key = $this->payers->insuranceKeyForRrmcCode($insurance->payerCode);
        if ($key === null) {
            $account->secpol = self::assigned($account->secpol, $insurance->certificate);
            $account->segroup = $insurance->groupNumber;
            $this->insuranceErrors[] = 'HOSP=' . $insurance->payerCode . ' ' . $mrn . ' ' . $account->garname . ' BAD HOSPITAL CODE ';
            return;
        }
        $company = $this->companyForKey($key);
        if ($company === null) {
            $this->badInsuranceCode($account, $insurance, $key, $mrn);
            $key = self::SELF_PAY;
        } else {
            $account->seassign = $company->assign;
        }
        $account->seins = $key;
        $account->secpol = self::assigned($account->secpol, $insurance->certificate);
        $account->segroup = $insurance->groupNumber;
        $account->segrpname = $insurance->groupName;
        $account->sename = self::subscriberName($insurance->subscriber, $account->garname);
        if ($insurance->certificate === '') {
            $account->secpol = PolicyNumber::fromString($insurance->subscriberSsn);
        }
        $gender = $this->subscriberGender($insurance, $account);
        $account->serelate = self::relation($gender);
        $account->serelateOriginal = $insurance->relate;

        // Two RRMC Medigap codes behind Medicare.
        if (LegacyCode::same($insurance->payerCode, self::MEDIGAP_CODE_A) && LegacyCode::same($account->prins, self::MEDICARE)) {
            $account->prgroup = '0005199     ';
            $account->seins = self::MEDIGAP;
        }
        if (LegacyCode::same($insurance->payerCode, self::MEDIGAP_CODE_B) && LegacyCode::same($account->prins, self::MEDICARE)) {
            $account->prgroup = '0000731     ';
            $account->seins = self::MEDIGAP;
        }
        if (LegacyCode::same($insurance->payerCode, self::EMPLOYER_GROUP)) {
            $account->segrpname = $file->primaryInsurance->subscriberEmployer;
        }
        if (LegacyCode::same($file->primaryInsurance->payerCode, self::REVIEW_CODE)) {
            $this->insuranceErrors[] = 'HAVE TO REVINS IN GP FOR ' . $mrn . ' ' . $account->garname . ' AND CHANGE INS IN AC';
        }
    }

    private function companyForKey(string $key): ?PayerCompany
    {
        return ctype_digit($key) ? $this->payers->companyById((int) $key) : null;
    }

    private function badInsuranceCode(AccountRecord $account, ChargeFileInsurance $insurance, string $key, string $mrn): void
    {
        $this->prompts->warning($key . ' MISSING ' . $insurance->payerCode . ' ' . $mrn . ' ' . $account->garname);
        $this->insuranceErrors[] = 'HOSP=' . $insurance->payerCode . ' ' . $mrn . ' ' . $account->garname . ' BAD INSURANCE CODE ';
    }

    /** The subscriber's F or M, asked for until it is one. */
    private function subscriberGender(ChargeFileInsurance $insurance, AccountRecord $account): string
    {
        $gender = $insurance->subscriberGender;
        while ($gender !== 'F' && $gender !== 'M') {
            $question = $insurance->subscriber->full . ' GENDER F OR M';
            if ($insurance->subscriber->isEmpty()) {
                $question .= ' ' . $account->garname;
            }
            $gender = strtoupper(trim($this->prompts->ask($question)));
        }
        return $gender;
    }

    /**
     * RELATE-1: the old relate code for the subscriber, an adult of their sex (2
     * male, K female). RRMC's relationship codes 03-07, 13, 14 and 17 map to the
     * same, so in effect it is always this; RRMC's code is kept separately.
     */
    private static function relation(string $gender): string
    {
        return $gender === 'F' ? 'K' : '2';
    }

    /** "LAST;FIRST" for the account file: the guarantor for SELF and the like. */
    private static function subscriberName(PersonName $subscriber, string $guarantor): string
    {
        if ($subscriber->isEmpty() || in_array($subscriber->full, self::SELF_SUBSCRIBERS, true)) {
            return $guarantor;
        }
        $full = $subscriber->full;
        $parts = explode(',', $full);
        $last = trim($parts[0]);
        $first = $parts[1] ?? '';
        if ($first === '') {
            $parts = explode(' ', $full);
            $last = trim($parts[0]);
            $first = $parts[1] ?? '';
        }
        return $last . ';' . $first;
    }

    /**
     * REPLACE-1 / REPLACE-2: RRMC sent a miscellaneous payer as the primary. Use
     * the patient's current primary in OpenEMR, or one the biller gives.
     */
    private function miscellaneousPrimary(
        ChargeFileAccount $file,
        AccountRecord $account,
        string $mrn,
        ?PriorPrimary $prior,
    ): void {
        $priorCode = $prior === null ? self::NO_PRIOR_INSURANCE : $prior->provider;
        $question = $file->patient->name->full . ' ' . self::display($file->primaryInsurance)
            . ' IS A MISC INS FROM RRMC FOR ' . $mrn
            . ($prior === null
                ? ' USE 095 SINCE NO RECENT GARNO'
                : ' USE INS ' . $priorCode . ' FROM RECENT GARNO ' . $prior->patientId)
            . '? Y FOR YES, ANYTHING ELSE TO LOOK FOR INS.';
        $usePrior = $this->options->assumeYes || $this->prompts->confirm($question, true);
        if (!ctype_digit($priorCode)) {
            $this->prompts->warning('PRIOR INSURANCE INVALID');
            $usePrior = false;
        }
        if (!$usePrior) {
            // Stored now; 7.0.2 looked the company up and dropped it.
            $company = $this->askForCompany($file->patient->name->full, 'ENTER A PRIMARY INS CODE MAYBE ELECTRONIC?', $this->options->defaultPrimary);
            $account->prins = LegacyCode::forId($company->id);
            $account->prassign = $company->assign;
            return;
        }
        $account->prins = LegacyCode::forId((int) $priorCode);
        $company = $this->payers->companyById((int) $priorCode);
        if ($company === null) {
            $this->prompts->warning('NO INS ON FILE');
            $company = $this->askForCompany($file->patient->name->full, 'ENTER A PRIMARY INS CODE MAYBE ELECTRONIC?', $this->options->defaultPrimary);
            // Stored now; 7.0.2 kept the missing company's code.
            $account->prins = LegacyCode::forId($company->id);
        } else {
            $this->prompts->notice($company->name);
        }
        $account->prassign = 'U';
    }

    /** REPLACE-1 for the secondary. */
    private function miscellaneousSecondary(ChargeFileAccount $file, AccountRecord $account): void
    {
        $question = (LegacyCode::same($account->prins, self::MEDICARE) ? 'MEDICARE PATIENT ' : '')
            . $file->patient->name->full . self::display($file->secondaryInsurance) . ' ENTER 2NDARY INS CODE';
        $company = $this->askForCompany($file->patient->name->full, $question, $this->options->defaultSecondary);
        // Stored now; 7.0.2 looked the company up and dropped it.
        $account->seins = LegacyCode::forId($company->id);
        $account->seassign = $company->assign;
    }

    private function askForCompany(string $patientName, string $question, ?int $default): PayerCompany
    {
        $this->prompts->notice($patientName);
        while (true) {
            $useDefault = $this->options->assumeYes && $default !== null;
            $answer = $useDefault ? (string) $default : trim($this->prompts->ask($question));
            $company = ctype_digit($answer) ? $this->payers->companyById((int) $answer) : null;
            if ($company !== null) {
                $this->prompts->notice($company->name);
                return $company;
            }
            if ($useDefault) {
                // 7.0.2 asked again with the same default forever.
                throw new StepThreeException('The default insurance ' . $answer . ' is not an insurance company on this site.');
            }
            $this->prompts->warning('BAD');
        }
    }

    /**
     * Who referred the patient: the address book user with RRMC's NPI and the
     * same name (or the same last-name initial, asked when there are several);
     * offered to be added when there is none.
     */
    private function referringDoctor(ChargeFileAccount $file): ?ReferringDoctor
    {
        $name = $file->visit->referringName;
        $npi = $file->visit->referringNpi;
        if ($npi === '') {
            return null;
        }
        $key = $name->full . $npi;
        if (array_key_exists($key, $this->doctors)) {
            return $this->doctors[$key];
        }
        $doctor = $this->findReferringDoctor($name, $npi);
        if ($doctor === null && !$name->isEmpty()) {
            $add = $this->options->assumeYes
                || $this->prompts->confirm('WOULD YOU LIKE TO ADD ' . $name->full . ' NPI ' . $npi . ' AUTOMATICALLY?', true);
            if ($add) {
                $doctor = $this->addReferringDoctor($name, $npi);
                $this->doctors[$key] = $doctor;
            }
        }
        return $doctor;
    }

    private function findReferringDoctor(PersonName $name, string $npi): ?ReferringDoctor
    {
        $sameInitial = [];
        foreach ($this->referringDoctors->findByNpi($npi) as $candidate) {
            if (substr($name->last, 0, 1) !== substr($candidate->lastName, 0, 1)) {
                continue;
            }
            if ($name->last === $candidate->lastName && $name->first === $candidate->firstName) {
                return $candidate;
            }
            $sameInitial[] = $candidate;
        }
        $key = $name->full . $npi;
        if (count($sameInitial) === 1) {
            return $this->doctors[$key] = $sameInitial[0];
        }
        if ($sameInitial === []) {
            return null;
        }
        $choices = array_map(static fn(ReferringDoctor $doctor): string => $doctor->label(), $sameInitial);
        $choices[] = 'ADD ' . $name->full . ' NPI ' . $npi . ' AUTOMATICALLY?';
        do {
            $chosen = $this->prompts->choose(
                $name->full . ' NPI ' . $npi . ' had multiple matches. Please select the correct referring provider or add the provider to the system',
                $choices
            );
            $doctor = $sameInitial[$chosen] ?? $this->addReferringDoctor($name, $npi);
        } while ($doctor === null);
        return $this->doctors[$key] = $doctor;
    }

    private function addReferringDoctor(PersonName $name, string $npi): ?ReferringDoctor
    {
        if (!ctype_alnum(substr($name->full, 0, 1))) {
            $this->errors[] = 'Invalid provider name.  First character is not a valid alphanumeric character'
                . ' NPI ' . $npi . ' PROVIDER NAME ' . $name->full;
            return null;
        }
        return $this->referringDoctors->add($name, $npi);
    }

    /** The old record's assignFromString(): an empty value leaves the policy as it was. */
    private static function assigned(PolicyNumber $current, string $value): PolicyNumber
    {
        return $value === '' ? $current : PolicyNumber::fromString($value);
    }

    /** RRMC's payer name and address as the old prompts showed them. */
    private static function display(ChargeFileInsurance $insurance): string
    {
        $address = $insurance->payerAddress;
        return str_pad($insurance->payerName, 25) . str_pad($insurance->payerContact, 25) . str_pad($address->street, 20)
            . str_pad($address->suite, 15) . str_pad($address->city, 20) . str_pad($address->state, 2)
            . str_pad($address->zip, 10) . str_pad($insurance->payerPhone, 12) . str_pad($insurance->authorization, 20);
    }
}
