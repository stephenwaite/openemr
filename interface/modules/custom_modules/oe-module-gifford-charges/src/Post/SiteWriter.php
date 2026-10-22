<?php

/**
 * Finds and writes the site's patients, policies, providers, encounters and billing
 * lines for posted Gifford charges.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Post;

use Cmsvt\OpenEMR\Modules\GiffordCharges\Model\ChargeRecord;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Model\EncounterRecord;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Model\PatientRecord;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Model\PolicyRecord;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Parse\GiffordValues;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Common\Uuid\UuidRegistry;

/**
 * Rows are written directly rather than through the core services, which take the
 * logged-in user from the session; the hourly import runs with no user. Each write
 * matches what the screens save, and every new row gets its uuid as core's would.
 */
final class SiteWriter
{
    /** forms.user for the encounters the import creates. */
    public const IMPORT_USER = 'gifford-import';

    private const POLICY_TYPES = [1 => 'primary', 2 => 'secondary', 3 => 'tertiary'];

    public function findPatientByMrn(string $mrn): ?int
    {
        $pid = QueryUtils::fetchSingleValue('SELECT `pid` FROM `patient_data` WHERE `pubpid` = ? ORDER BY `pid` LIMIT 1', 'pid', [$mrn]);

        return is_numeric($pid) ? (int) $pid : null;
    }

    /**
     * A new patient with the Gifford MRN as the external id (pubpid).
     */
    public function createPatient(PatientRecord $patient): int
    {
        $next = QueryUtils::fetchSingleValue('SELECT COALESCE(MAX(`pid`), 0) + 1 AS `pid` FROM `patient_data`', 'pid');
        $pid = is_numeric($next) ? (int) $next : 1;
        $a = $patient->address;
        QueryUtils::sqlInsert(
            'INSERT INTO `patient_data` (`uuid`, `pid`, `pubpid`, `lname`, `fname`, `DOB`, `sex`, `street`, `street_line_2`,'
            . ' `city`, `state`, `postal_code`, `phone_home`, `email`, `date`, `regdate`)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [
                UuidRegistry::getRegistryForTable('patient_data')->createUuid(),
                $pid,
                $patient->mrn,
                $patient->lastName,
                $patient->firstName,
                $patient->dateOfBirth->format('Y-m-d'),
                $patient->sex,
                $a->street,
                $a->street2,
                $a->city,
                $a->state,
                $a->postalCode,
                $patient->phone,
                $patient->email,
            ]
        );

        return $pid;
    }

    /**
     * Make the policy the patient's policy of its rank from $dateOfService on. Nothing is
     * written when the policy in effect then is already this payer and member id. Otherwise
     * a policy dated $dateOfService is added (or the one already dated that day is
     * replaced), and an earlier open-ended one of that rank ends the day before.
     */
    public function ensurePolicy(int $pid, PolicyRecord $policy, int $insuranceCompanyId, PatientRecord $patient, \DateTimeImmutable $dateOfService): void
    {
        $type = self::POLICY_TYPES[$policy->rank] ?? null;
        if ($type === null) {
            throw new \LogicException('Policy rank must be 1, 2 or 3');
        }
        $dos = $dateOfService->format('Y-m-d');
        $current = QueryUtils::querySingleRow(
            'SELECT `id`, `provider`, `policy_number`, `date` FROM `insurance_data` WHERE `pid` = ? AND `type` = ?'
            . ' AND (`date` IS NULL OR `date` <= ?) AND (`date_end` IS NULL OR `date_end` >= ?)'
            . ' ORDER BY `date` DESC LIMIT 1',
            [$pid, $type, $dos, $dos]
        );
        if (
            is_array($current)
            && is_numeric($current['provider'] ?? null) && (int) $current['provider'] === $insuranceCompanyId
            && ($current['policy_number'] ?? null) === $policy->memberId
        ) {
            return;
        }

        $self = $policy->relationship === 'self';
        $a = $patient->address;
        $values = [
            'provider' => $insuranceCompanyId,
            'policy_number' => $policy->memberId,
            'group_number' => $policy->groupNumber,
            'plan_name' => $policy->groupName,
            'subscriber_lname' => $self ? $patient->lastName : $policy->subscriberLastName,
            'subscriber_fname' => $self ? $patient->firstName : $policy->subscriberFirstName,
            'subscriber_relationship' => $policy->relationship,
            'subscriber_DOB' => ($self ? $patient->dateOfBirth : $policy->subscriberDateOfBirth)?->format('Y-m-d'),
            'subscriber_sex' => $self ? $patient->sex : $policy->subscriberSex,
            // Gifford sends no subscriber address; the patient's is the best there is.
            'subscriber_street' => $a->street,
            'subscriber_city' => $a->city,
            'subscriber_state' => $a->state,
            'subscriber_postal_code' => $a->postalCode,
            'subscriber_country' => 'USA',
            'accept_assignment' => 'TRUE',
        ];

        $sameDay = QueryUtils::fetchSingleValue(
            'SELECT `id` FROM `insurance_data` WHERE `pid` = ? AND `type` = ? AND `date` = ?',
            'id',
            [$pid, $type, $dos]
        );
        if (is_numeric($sameDay)) {
            QueryUtils::sqlStatementThrowException(
                'UPDATE `insurance_data` SET ' . implode(', ', array_map(static fn (string $c): string => '`' . $c . '` = ?', array_keys($values)))
                . ' WHERE `id` = ?',
                [...array_values($values), (int) $sameDay]
            );
            return;
        }

        QueryUtils::sqlStatementThrowException(
            'UPDATE `insurance_data` SET `date_end` = DATE_SUB(?, INTERVAL 1 DAY)'
            . ' WHERE `pid` = ? AND `type` = ? AND `date_end` IS NULL AND `date` < ?',
            [$dos, $pid, $type, $dos]
        );
        $columns = ['uuid', 'pid', 'type', 'date', ...array_keys($values)];
        QueryUtils::sqlInsert(
            'INSERT INTO `insurance_data` (' . implode(', ', array_map(static fn (string $c): string => '`' . $c . '`', $columns)) . ')'
            . ' VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')',
            [UuidRegistry::getRegistryForTable('insurance_data')->createUuid(), $pid, $type, $dos, ...array_values($values)]
        );
    }

    /**
     * The site's rendering provider with this NPI: an active, authorized user.
     */
    public function findRenderingProvider(string $npi): ?int
    {
        $id = QueryUtils::fetchSingleValue(
            'SELECT `id` FROM `users` WHERE `npi` = ? AND `active` = 1 AND `authorized` = 1 ORDER BY `id` LIMIT 1',
            'id',
            [$npi]
        );

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * The user to name as referring provider: any active user with the NPI, else a new
     * address book entry (external provider) with the file's name and NPI, which is all
     * the claim's referring provider loop needs. Billers complete it with the address
     * book's NPI lookup.
     */
    public function referringProvider(string $npi, string $name): int
    {
        $id = QueryUtils::fetchSingleValue(
            'SELECT `id` FROM `users` WHERE `npi` = ? AND `active` = 1 ORDER BY `authorized` DESC, `id` LIMIT 1',
            'id',
            [$npi]
        );
        if (is_numeric($id)) {
            return (int) $id;
        }
        [$last, $first] = GiffordValues::lastFirst($name);

        return QueryUtils::sqlInsert(
            'INSERT INTO `users` (`uuid`, `username`, `password`, `authorized`, `info`, `source`, `fname`, `lname`, `npi`,'
            . ' `facility`, `see_auth`, `active`, `abook_type`) VALUES (?, \'\', \'\', 0, ?, NULL, ?, ?, ?, \'\', 0, 1, ?)',
            [
                UuidRegistry::getRegistryForTable('users')->createUuid(),
                'Added by the Gifford charge import',
                $first,
                $last,
                $npi,
                'external_provider',
            ]
        );
    }

    /**
     * The encounter (form_encounter.encounter) for this visit and date of service, or null.
     *
     * @return ?array{encounter: int, pos_code: ?string}
     */
    public function findEncounter(int $pid, string $visitNumber, \DateTimeImmutable $dateOfService): ?array
    {
        $row = QueryUtils::querySingleRow(
            'SELECT `encounter`, `pos_code` FROM `form_encounter` WHERE `pid` = ? AND `external_id` = ? AND DATE(`date`) = ?'
            . ' ORDER BY `id` LIMIT 1',
            [$pid, $visitNumber, $dateOfService->format('Y-m-d')]
        );
        if (!is_array($row) || !is_numeric($row['encounter'] ?? null)) {
            return null;
        }
        $pos = $row['pos_code'] ?? null;

        return [
            'encounter' => (int) $row['encounter'],
            'pos_code' => is_numeric($pos) ? sprintf('%02d', (int) $pos) : null,
        ];
    }

    /**
     * A new encounter dated $dateOfService, with its forms row, as the New Encounter form saves it.
     */
    public function createEncounter(
        int $pid,
        EncounterRecord $visit,
        \DateTimeImmutable $dateOfService,
        int $providerId,
        int $referringProviderId,
        ?string $placeOfService,
        PostSettings $settings,
    ): int {
        $encounter = QueryUtils::generateId();
        $date = $dateOfService->format('Y-m-d 00:00:00');
        $formId = QueryUtils::sqlInsert(
            'INSERT INTO `form_encounter` (`uuid`, `date`, `reason`, `facility`, `facility_id`, `pid`, `encounter`,'
            . ' `sensitivity`, `pc_catid`, `provider_id`, `billing_facility`, `external_id`, `pos_code`, `class_code`,'
            . ' `referring_provider_id`) VALUES (?, ?, ?, ?, ?, ?, ?, \'normal\', ?, ?, ?, ?, ?, \'AMB\', ?)',
            [
                UuidRegistry::getRegistryForTable('form_encounter')->createUuid(),
                $date,
                'Gifford radiology, ' . $visit->location,
                $settings->facilityName,
                $settings->facilityId,
                $pid,
                $encounter,
                $settings->visitCategoryId,
                $providerId,
                $settings->billingFacilityId,
                $visit->visitNumber,
                $placeOfService === null ? null : (int) $placeOfService,
                $referringProviderId,
            ]
        );
        QueryUtils::sqlInsert(
            'INSERT INTO `forms` (`date`, `encounter`, `form_name`, `form_id`, `pid`, `user`, `groupname`, `authorized`,'
            . ' `formdir`) VALUES (?, ?, \'New Patient Encounter\', ?, ?, ?, \'Default\', 1, \'newpatient\')',
            [$date, $encounter, $formId, $pid, self::IMPORT_USER]
        );

        return $encounter;
    }

    /**
     * Add the diagnoses the encounter doesn't have yet, in order, as the fee sheet does.
     *
     * @param list<string> $diagnoses ICD-10-CM codes with the dot
     */
    public function addDiagnoses(int $pid, int $encounter, array $diagnoses, int $providerId): void
    {
        $have = QueryUtils::fetchTableColumn(
            "SELECT `code` FROM `billing` WHERE `pid` = ? AND `encounter` = ? AND `code_type` = 'ICD10' AND `activity` = 1",
            'code',
            [$pid, $encounter]
        );
        foreach ($diagnoses as $code) {
            if (in_array($code, $have, true)) {
                continue;
            }
            $text = QueryUtils::fetchSingleValue(
                'SELECT `long_desc` FROM `icd10_dx_order_code` WHERE `formatted_dx_code` = ? AND `active` = 1 ORDER BY `revision` DESC LIMIT 1',
                'long_desc',
                [$code]
            );
            $this->insertBilling($pid, $encounter, 'ICD10', $code, is_string($text) ? $text : '', $providerId, '', 1, '0.00', '');
            $have[] = $code;
        }
    }

    /**
     * The procedure line, unbilled, so it shows in the Billing Manager.
     *
     * @param list<string> $diagnoses pointed to by the line, at most four
     * @return int billing.id
     */
    public function addCharge(int $pid, int $encounter, ChargeRecord $charge, PricedCharge $priced, int $providerId, array $diagnoses): int
    {
        $justify = implode('', array_map(static fn (string $dx): string => 'ICD10|' . $dx . ':', array_slice($diagnoses, 0, 4)));

        return $this->insertBilling(
            $pid,
            $encounter,
            $charge->codeType,
            $charge->code,
            $priced->codeText,
            $providerId,
            $priced->modifier,
            $charge->units,
            $priced->fee($charge->units),
            $justify,
        );
    }

    private function insertBilling(
        int $pid,
        int $encounter,
        string $codeType,
        string $code,
        string $codeText,
        int $providerId,
        string $modifier,
        int $units,
        string $fee,
        string $justify,
    ): int {
        return QueryUtils::sqlInsert(
            'INSERT INTO `billing` (`date`, `encounter`, `code_type`, `code`, `code_text`, `pid`, `authorized`, `user`,'
            . ' `groupname`, `activity`, `billed`, `provider_id`, `modifier`, `units`, `fee`, `justify`, `pricelevel`)'
            . ' VALUES (NOW(), ?, ?, ?, ?, ?, 1, NULL, \'Default\', 1, 0, ?, ?, ?, ?, ?, ?)',
            [$encounter, $codeType, $code, $codeText, $pid, $providerId, $modifier, $units, $fee, $justify, PriceBook::PRICE_LEVEL]
        );
    }
}
