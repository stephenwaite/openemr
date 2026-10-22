<?php

/**
 * The coding page's data (7.0.2's CMSVTBillingService::getCmsvtBillingRecords
 * and the page's save): the unbilled charges of an ordfile with what the
 * biller needs to code them, and saving the provider and diagnoses.
 *
 * Changes from 7.0.2: a charge's provider changes its own encounter (7.0.2
 * changed every encounter of the visit, so other charges' encounters no
 * longer matched their billing lines); a diagnosis box emptied removes its
 * ICD10 line (7.0.2 left a line with no code); the description shown for Dx 1
 * is Dx 1's (7.0.2 showed Dx 2's); and lines already billed are not changed.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Coding;

use Cmsvt\OpenEMR\Modules\RriBilling\Account\LegacyCode;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Services\CodeTypesService;

final readonly class CodingRepository
{
    private const RECENT_ORDFILES = 5;

    /** Users who can be a charge's provider (7.0.2's CMSVTLocalProviderListType). */
    private const PROVIDERS = "SELECT `id`, `fname`, `lname` FROM `users` WHERE `active` = 1"
        . " AND (`info` IS NULL OR `info` NOT LIKE '%Inactive%')"
        . " AND (`authorized` = 1 OR ((`username` = '' OR `username` IS NULL) AND `npi` != ''))"
        . " AND `abook_type` != 'external_provider' ORDER BY `lname`, `fname`";

    public function __construct(private CodeTypesService $codeTypes)
    {
    }

    /**
     * @return list<array{id: int, created: string}> the newest ordfiles, newest first
     */
    public function recentOrdfiles(): array
    {
        $ordfiles = [];
        foreach (QueryUtils::fetchRecords('SELECT `ordfile_id`, `date_created` FROM `cmsvt_ordfile` ORDER BY `ordfile_id` DESC LIMIT ' . self::RECENT_ORDFILES) as $row) {
            if (is_numeric($row['ordfile_id'] ?? null)) {
                $ordfiles[] = ['id' => (int) $row['ordfile_id'], 'created' => self::text($row['date_created'] ?? null)];
            }
        }
        return $ordfiles;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function providers(): array
    {
        $providers = [];
        foreach (QueryUtils::fetchRecords(self::PROVIDERS) as $row) {
            if (is_numeric($row['id'] ?? null)) {
                $providers[] = ['id' => (int) $row['id'], 'name' => trim(self::text($row['lname'] ?? null) . ', ' . self::text($row['fname'] ?? null), ', ')];
            }
        }
        return $providers;
    }

    /**
     * @return list<CodingRow> the ordfile's charges whose billing line isn't billed yet, in ordfile order
     */
    public function rows(int $ordfileId): array
    {
        $rows = [];
        $sql = 'SELECT o.`ordfile_record_id`, o.`ord8`, o.`proc`, o.`cpt`, o.`dateT`, o.`dateAdmit`, o.`mod2`, o.`mod3`, o.`iopat`,'
            . ' o.`clinical`, o.`admitDiag`, b.`pid`, b.`encounter`, b.`provider_id`, b.`external_id`,'
            . ' pd.`fname`, pd.`mname`, pd.`lname`, pd.`DOB`, act.`garno`, u.`fname` AS provider_fname, u.`lname` AS provider_lname'
            . ' FROM `cmsvt_ordfile_record` o'
            . " JOIN `cmsvt_ordfile_record_billing` c ON c.`ordfile_record_id` = o.`ordfile_record_id` AND c.`record_type` = 'source'"
            . ' JOIN `billing` b ON b.`id` = c.`billing_id` AND b.`billed` = 0'
            . ' LEFT JOIN `patient_data` pd ON pd.`pid` = b.`pid`'
            . ' LEFT JOIN `cmsvt_actfile` act ON act.`actno` = o.`ord8`'
            . ' LEFT JOIN `users` u ON u.`id` = b.`provider_id`'
            . ' WHERE o.`ordfile_id` = ? ORDER BY o.`ordfile_record_id`';
        foreach (QueryUtils::fetchRecords($sql, [$ordfileId]) as $row) {
            $recordId = self::number($row['ordfile_record_id'] ?? null);
            $pid = self::number($row['pid'] ?? null);
            if ($recordId === null || $pid === null) {
                continue;
            }
            $providerId = self::number($row['provider_id'] ?? null);
            $providerId = $providerId !== null && $providerId > 0 ? $providerId : null;
            [$payer, $subscriberDob] = $this->primaryInsurance($pid, self::text($row['DOB'] ?? null));
            $cpt = self::text($row['cpt'] ?? null);
            [$title, $mod] = $this->procedure(self::text($row['proc'] ?? null), $cpt);
            $dateT = \DateTimeImmutable::createFromFormat('!Ymd', self::text($row['dateT'] ?? null));
            $rows[] = new CodingRow(
                $recordId,
                $pid,
                self::number($row['encounter'] ?? null) ?? 0,
                $providerId,
                $providerId === null ? '' : trim(self::text($row['provider_fname'] ?? null) . ' ' . self::text($row['provider_lname'] ?? null)),
                self::text($row['ord8'] ?? null),
                trim(self::text($row['lname'] ?? null) . ', ' . self::text($row['fname'] ?? null) . ' ' . self::text($row['mname'] ?? null), ', '),
                $dateT === false ? '' : $dateT->format('m-d-Y'),
                self::text($row['dateAdmit'] ?? null),
                self::text($row['garno'] ?? null),
                $title,
                str_pad($cpt . $mod, 7),
                self::text($row['mod2'] ?? null),
                self::text($row['mod3'] ?? null),
                $payer,
                self::text($row['iopat'] ?? null),
                $subscriberDob,
                self::text($row['clinical'] ?? null),
                self::text($row['admitDiag'] ?? null),
                self::text($row['external_id'] ?? null),
                $this->diagnosis($recordId, DiagnosisSlot::First),
                $this->diagnosis($recordId, DiagnosisSlot::Second),
            );
        }
        return $rows;
    }

    /**
     * Saves the biller's provider and diagnoses, all or nothing.
     *
     * @param list<CodingUpdate> $updates
     * @return int charges saved (lines already billed, or not step 6's, are skipped)
     */
    public function save(array $updates): int
    {
        return QueryUtils::inTransaction(function () use ($updates): int {
            $saved = 0;
            foreach ($updates as $update) {
                if ($this->saveOne($update)) {
                    $saved++;
                }
            }
            return $saved;
        });
    }

    private function saveOne(CodingUpdate $update): bool
    {
        $source = QueryUtils::querySingleRow(
            'SELECT b.`id`, b.`pid`, b.`encounter`, b.`provider_id`, b.`billed` FROM `billing` b'
            . " JOIN `cmsvt_ordfile_record_billing` c ON c.`billing_id` = b.`id` AND c.`record_type` = 'source'"
            . ' WHERE c.`ordfile_record_id` = ? FOR UPDATE',
            [$update->recordId],
            false
        );
        $billingId = is_array($source) ? self::number($source['id'] ?? null) : null;
        $pid = is_array($source) ? self::number($source['pid'] ?? null) : null;
        $encounter = is_array($source) ? self::number($source['encounter'] ?? null) : null;
        if ($billingId === null || $pid === null || $encounter === null || self::number($source['billed'] ?? null) !== 0) {
            return false;
        }
        $providerId = self::number($source['provider_id'] ?? null);
        if (($providerId === null || $providerId === 0) && $update->providerId !== null && $this->isProvider($update->providerId)) {
            $providerId = $update->providerId;
            QueryUtils::sqlStatementThrowException(
                'UPDATE `billing` SET `provider_id` = ? WHERE `id` IN (SELECT `billing_id` FROM `cmsvt_ordfile_record_billing` WHERE `ordfile_record_id` = ?)',
                [$providerId, $update->recordId]
            );
            QueryUtils::sqlStatementThrowException('UPDATE `form_encounter` SET `provider_id` = ? WHERE `encounter` = ? AND `pid` = ?', [$providerId, $encounter, $pid]);
        }
        $this->saveDiagnosis($update->recordId, DiagnosisSlot::First, $update->dx1, $pid, $encounter, $providerId);
        $this->saveDiagnosis($update->recordId, DiagnosisSlot::Second, $update->dx2, $pid, $encounter, $providerId);
        QueryUtils::sqlStatementThrowException('UPDATE `billing` SET `justify` = ? WHERE `id` = ?', [$update->justify(), $billingId]);
        return true;
    }

    private function saveDiagnosis(int $recordId, DiagnosisSlot $slot, string $code, int $pid, int $encounter, ?int $providerId): void
    {
        $existing = $this->diagnosisLine($recordId, $slot);
        if ($code === '') {
            if ($existing !== null) {
                QueryUtils::sqlStatementThrowException('DELETE FROM `cmsvt_ordfile_record_billing` WHERE `ordfile_record_id` = ? AND `billing_id` = ?', [$recordId, $existing['id']]);
                QueryUtils::sqlStatementThrowException('DELETE FROM `billing` WHERE `id` = ?', [$existing['id']]);
            }
            return;
        }
        if ($existing !== null && $existing['code'] === $code) {
            return;
        }
        $text = $this->codeTypes->lookup_code_description('ICD10:' . $code);
        if ($existing !== null) {
            QueryUtils::sqlStatementThrowException(
                'UPDATE `billing` SET `code` = ?, `code_text` = ?, `date` = NOW() WHERE `id` = ?',
                [$code, $text, $existing['id']]
            );
            return;
        }
        $id = QueryUtils::sqlInsert(
            'INSERT INTO `billing` (`date`, `code_type`, `code`, `pid`, `provider_id`, `authorized`, `encounter`, `code_text`, `billed`, `activity`)'
            . " VALUES (NOW(), 'ICD10', ?, ?, ?, 1, ?, ?, 0, 1)",
            [$code, $pid, $providerId, $encounter, $text]
        );
        QueryUtils::sqlStatementThrowException(
            'INSERT INTO `cmsvt_ordfile_record_billing` (`ordfile_record_id`, `billing_id`, `record_type`) VALUES (?, ?, ?)',
            [$recordId, $id, $slot->value]
        );
    }

    /**
     * @return ?array{id: int, code: string, text: string}
     */
    private function diagnosisLine(int $recordId, DiagnosisSlot $slot): ?array
    {
        $row = QueryUtils::querySingleRow(
            'SELECT b.`id`, b.`code`, b.`code_text` FROM `billing` b JOIN `cmsvt_ordfile_record_billing` c ON c.`billing_id` = b.`id`'
            . ' WHERE c.`ordfile_record_id` = ? AND c.`record_type` = ? LIMIT 1',
            [$recordId, $slot->value],
            false
        );
        $id = is_array($row) ? self::number($row['id'] ?? null) : null;
        if (!is_array($row) || $id === null) {
            return null;
        }
        return ['id' => $id, 'code' => self::text($row['code'] ?? null), 'text' => self::text($row['code_text'] ?? null)];
    }

    private function diagnosis(int $recordId, DiagnosisSlot $slot): CodingDiagnosis
    {
        $line = $this->diagnosisLine($recordId, $slot);
        return new CodingDiagnosis($line['code'] ?? '', $line['text'] ?? '');
    }

    private function isProvider(int $userId): bool
    {
        foreach ($this->providers() as $provider) {
            if ($provider['id'] === $userId) {
                return true;
            }
        }
        return false;
    }

    /**
     * The current primary's company as the old three-digit code (001, self
     * pay, when none) and the subscriber's DOB (the patient's for self pay).
     *
     * @return array{string, string}
     */
    private function primaryInsurance(int $pid, string $patientDob): array
    {
        $row = QueryUtils::querySingleRow(
            "SELECT `provider`, `subscriber_DOB` FROM `insurance_data` WHERE `pid` = ? AND `type` = 'primary' AND `date_end` IS NULL"
            . ' ORDER BY `date` DESC LIMIT 1',
            [$pid],
            false
        );
        $provider = is_array($row) ? self::number($row['provider'] ?? null) : null;
        if (!is_array($row) || $provider === null || $provider <= 0 || $provider === 1) {
            return [LegacyCode::forId(1), $patientDob];
        }
        return [LegacyCode::forId($provider), self::text($row['subscriber_DOB'] ?? null)];
    }

    /**
     * The charge master's title and modifier for the CDM and CPT (the professional, 26, or blank row).
     *
     * @return array{string, string}
     */
    private function procedure(string $cdm, string $cpt): array
    {
        $row = QueryUtils::querySingleRow(
            "SELECT `proc_title`, `proc_mod` FROM `cmsvt_procfile` WHERE `proc_cdm` = ? AND `proc_cpt` = ? AND `proc_mod` IN ('26', ' ', '')"
            . " ORDER BY `proc_mod` = '26' DESC LIMIT 1",
            [$cdm, $cpt],
            false
        );
        return is_array($row) ? [self::text($row['proc_title'] ?? null), self::text($row['proc_mod'] ?? null)] : ['', ''];
    }

    private static function number(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
