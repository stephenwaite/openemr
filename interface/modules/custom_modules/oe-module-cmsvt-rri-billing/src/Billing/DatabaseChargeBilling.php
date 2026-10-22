<?php

/**
 * Step 6's encounters and billing lines in OpenEMR (7.0.2's
 * RRIBillingRecordsProcessor and CMSVTBillingService).
 *
 * Each ordfile charge gets a new encounter (the place of service's category
 * and class, the patient's referring doctor, the admit date as onset), a CPT4
 * line with the fee for the code and its first modifier, and, when RRMC sent a
 * diagnosis in the accession field, an ICD10 line it is justified by. The CPT4
 * line is linked to the ordfile line in cmsvt_ordfile_record_billing, which the
 * coding page reads, and which tells a second run the line is billed.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Billing;

use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Common\Uuid\UuidRegistry;
use OpenEMR\Services\EncounterService;

final class DatabaseChargeBilling implements ChargeBilling
{
    /** @var array<string, int> pc_catid by pc_constant_id */
    private array $categories = [];

    public function __construct(private readonly EncounterService $encounters)
    {
    }

    public function findPatient(string $actno): ?BillingPatient
    {
        $row = QueryUtils::querySingleRow(
            'SELECT `pid`, `uuid`, `ref_providerID` FROM `patient_data` WHERE `pubpid` = ? ORDER BY `pid` LIMIT 1',
            [ltrim($actno, '0')],
            false
        );
        if (!is_array($row) || !is_numeric($row['pid'] ?? null) || !is_string($row['uuid'] ?? null) || $row['uuid'] === '') {
            return null;
        }
        $referring = $row['ref_providerID'] ?? null;
        return new BillingPatient(
            (int) $row['pid'],
            UuidRegistry::uuidToString($row['uuid']),
            is_numeric($referring) && (int) $referring > 0 ? (int) $referring : null,
        );
    }

    public function isBilled(int $recordId): bool
    {
        return QueryUtils::fetchSingleValue(
            "SELECT `billing_id` FROM `cmsvt_ordfile_record_billing` WHERE `ordfile_record_id` = ? AND `record_type` = 'source' LIMIT 1",
            'billing_id',
            [$recordId]
        ) !== null;
    }

    public function bill(BillingCharge $charge): int
    {
        $category = $this->category($charge->place);
        return QueryUtils::inTransaction(function () use ($charge, $category): int {
            $encounter = $this->encounter($charge, $category);
            $diagnosis = $charge->diagnosis();
            $line = $charge->line;
            $modifiers = $charge->modifiers();
            $billingId = QueryUtils::sqlInsert(
                'INSERT INTO `billing` (`date`, `bill_date`, `code_type`, `code`, `pid`, `provider_id`, `authorized`, `encounter`,'
                . ' `billed`, `activity`, `bill_process`, `modifier`, `units`, `fee`, `justify`, `notecodes`, `external_id`,'
                . ' `chargecat`, `revenue_code`)'
                . " VALUES (NOW(), NOW(), 'CPT4', ?, ?, ?, 1, ?, 0, 1, 0, ?, 1, ?, ?, '', ?, '', '')",
                [
                    $line->cpt,
                    $charge->patient->pid,
                    $charge->providerId,
                    $encounter,
                    $modifiers === '' ? null : $modifiers,
                    $this->fee($line->cpt, explode(':', $modifiers)[0]),
                    $diagnosis === '' ? null : 'ICD10|' . $diagnosis . ':',
                    $line->accession,
                ]
            );
            QueryUtils::sqlStatementThrowException(
                "INSERT INTO `cmsvt_ordfile_record_billing` (`ordfile_record_id`, `billing_id`, `record_type`) VALUES (?, ?, 'source')",
                [$charge->recordId, $billingId]
            );
            if ($diagnosis !== '') {
                QueryUtils::sqlInsert(
                    'INSERT INTO `billing` (`date`, `code_type`, `code`, `pid`, `provider_id`, `authorized`, `encounter`, `code_text`, `activity`)'
                    . " VALUES (NOW(), 'ICD10', ?, ?, ?, 1, ?, ?, 1)",
                    [$diagnosis, $charge->patient->pid, $charge->providerId, $encounter, $this->diagnosisText($diagnosis)]
                );
            }
            return $encounter;
        });
    }

    private function encounter(BillingCharge $charge, int $category): int
    {
        $line = $charge->line;
        $admit = \DateTimeImmutable::createFromFormat('!Ymd', trim($line->dateAdmit));
        $result = $this->encounters->insertEncounter($charge->patient->uuid, [
            'external_id' => $line->visitNo,
            'date' => $line->dateT->format('Y-m-d'),
            'onset_date' => $admit === false ? null : $admit->format('Y-m-d'),
            'user' => null,
            'provider_id' => $charge->providerId,
            'group' => 'Default',
            'referring_provider_id' => $charge->patient->referringProviderId,
            'pc_catid' => $category,
            'class_code' => $charge->place->classCode(),
            'pos_code' => $charge->place->value,
            'facility_id' => $charge->facilityId,
        ]);
        $saved = $result->hasErrors() ? null : $result->getFirstDataResult();
        $encounter = is_array($saved) ? ($saved['encounter'] ?? null) : null;
        if (!is_numeric($encounter)) {
            throw new BillingException('ENCOUNTER NOT CREATED ' . json_encode([$result->getValidationMessages(), $result->getInternalErrors()]));
        }
        return (int) $encounter;
    }

    private function category(PlaceOfService $place): int
    {
        if ($this->categories === []) {
            foreach (QueryUtils::fetchRecords('SELECT `pc_catid`, `pc_constant_id` FROM `openemr_postcalendar_categories`') as $row) {
                if (is_string($row['pc_constant_id'] ?? null) && is_numeric($row['pc_catid'] ?? null)) {
                    $this->categories[$row['pc_constant_id']] = (int) $row['pc_catid'];
                }
            }
        }
        $category = $this->categories[$place->category()] ?? null;
        if ($category === null) {
            throw new \RuntimeException('Encounter visit categories are missing; check the module installation');
        }
        return $category;
    }

    /** The code's price (the first one on file), as 7.0.2 looked it up; null when there is none. */
    private function fee(string $code, string $modifier): ?string
    {
        $fee = QueryUtils::fetchSingleValue(
            'SELECT `prices`.`pr_price` AS fee FROM `codes` LEFT JOIN `prices` ON `prices`.`pr_id` = `codes`.`id`'
            . ' WHERE `codes`.`code` = ? AND `codes`.`modifier` = ? LIMIT 1',
            'fee',
            [$code, $modifier]
        );
        return is_numeric($fee) ? (string) $fee : null;
    }

    private function diagnosisText(string $code): string
    {
        $text = QueryUtils::fetchSingleValue(
            'SELECT `short_desc` FROM `icd10_dx_order_code` WHERE `formatted_dx_code` = ? ORDER BY `active` DESC, `revision` DESC LIMIT 1',
            'short_desc',
            [$code]
        );
        return is_string($text) ? $text : '';
    }
}
