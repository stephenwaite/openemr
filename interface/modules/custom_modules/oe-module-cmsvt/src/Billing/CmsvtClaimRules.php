<?php

/**
 * CMS 837P rules: Vermont Medicaid carrier codes for other payers, VA
 * Community Care, USFHP, podiatry routine foot care and x-ray referrals,
 * and extra claim warnings. Per-site parts come from the CMS Vermont globals.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Billing;

use OpenEMR\Billing\Claim;
use OpenEMR\Billing\X12\DefaultClaim837PRules;

final class CmsvtClaimRules extends DefaultClaim837PRules
{
    private const VERMONT_MEDICAID = 'MCDVT';
    private const VA_COMMUNITY_CARE = 'VACCN';
    private const RENDERING_PROVIDER_PER_LINE = '14165';
    private const INVALID_PAYER_ID = '99999';

    /**
     * Vermont Medicaid's carrier code for other payers, by payer ID.
     * BCBS Vermont and payer 60054 are handled separately.
     */
    private const MEDICAID_CARRIER_CODES = [
        '14512' => 'MDB',
        '14212' => 'MDB',
        '14163' => 'MDB',
        '87726' => 'MDB',
        '62308' => 'FB6',
        '14165' => 'Z2',
        '00010' => '42',
        'MPHC1' => '42',
        'EBSRM' => 'AW1',
        '00882' => 'MDB',
        '53275' => 'AE7',
        '39026' => 'S02',
    ];

    private const CLIA_WAIVED_CPTS = ['81002', '81025', '87804', '87880', '87428'];

    private const ROUTINE_FOOT_CARE_CPTS = ['11055', '11056', '11057', '11719', '11720', 'G0127'];
    private const ROUTINE_FOOT_CARE_MODIFIERS = ['Q7', 'Q8', 'Q9'];
    private const ROUTINE_FOOT_CARE_DXS = [
        'E0841', 'E0842', 'E0843', 'E0844', 'E0849', 'E0851', 'E0852', 'E0859', 'E08610', 'E0942', 'E0949',
        'E0951', 'E0952', 'E0959', 'E09610', 'E1041', 'E1042', 'E1043', 'E1044', 'E1049', 'E1051', 'E1052',
        'E1059', 'E10610', 'E1141', 'E1142', 'E1143', 'E1144', 'E1149', 'E1151', 'E1152', 'E1159', 'E11610',
        'E1342', 'E1349', 'E1351', 'E1352', 'E1359', 'E13610', 'E5111', 'E5112', 'E52', 'E531', 'E538',
        'E640', 'G130', 'G131', 'G35', 'G610', 'G611', 'G620', 'G621', 'G622', 'G6282', 'G701', 'G7081',
        'G731', 'G733', 'I8001', 'I8002', 'I8003', 'I8011', 'I8012', 'I8013', 'I80211', 'I80212', 'I80213',
        'I80221', 'I80222', 'I80223', 'I80231', 'I80232', 'I80233', 'I80241', 'I80242', 'I80243', 'I80251',
        'I80252', 'I80253', 'I80291', 'I80292', 'I80293', 'I82541', 'I82542', 'I82543', 'I82811', 'I82812',
        'I82813', 'I82891', 'K902', 'K903', 'K912', 'M05471', 'M05472', 'M05571', 'M05572', 'M05771',
        'M05772', 'M05871', 'M05872', 'M06071', 'M06072', 'M06871', 'M06872', 'N181', 'N182', 'N1830',
        'N1831', 'N1832', 'N184', 'N185', 'N186',
    ];
    private const FOOT_XRAY_CPTS = ['73600', '73610', '73620', '73630', '73650', '73660'];

    /** @var list<string> */
    private array $warnings = [];

    /**
     * @param list<string> $routineFootCareNpis billing facility NPIs that follow the podiatry rules
     * @param list<string> $ndcSkipPayerIds payers that must not receive NDCs
     * @param list<string> $closedFacilityNpis service facilities that no longer exist
     */
    public function __construct(
        private readonly array $routineFootCareNpis,
        private readonly array $ndcSkipPayerIds,
        private readonly array $closedFacilityNpis,
    ) {
    }

    public function submitterIdentifier(Claim $claim): string
    {
        // CMS submits as a third party under its own sender ID.
        return trim(self::str($claim->x12_sender_id()));
    }

    public function requiresLastSeenDate(Claim $claim): bool
    {
        return $this->isRoutineFootCarePractice($claim)
            && array_intersect(self::cptCodes($claim), self::ROUTINE_FOOT_CARE_CPTS) !== []
            && array_intersect(self::diagnoses($claim), self::ROUTINE_FOOT_CARE_DXS) !== [];
    }

    public function requiresReferringProvider(Claim $claim): bool
    {
        // Medicare wants the referring provider for routine foot care and foot x-rays.
        if ($claim->claimType() !== 'MB') {
            return false;
        }
        return $this->requiresLastSeenDate($claim)
            || ($this->isRoutineFootCarePractice($claim)
                && array_intersect(self::cptCodes($claim), self::FOOT_XRAY_CPTS) !== []);
    }

    public function reportsPriorPayerAdjustments(Claim $claim): bool
    {
        // As production has always sent them: secondary claims carry only the
        // remaining patient responsibility (PR-3), no CO/OA or itemized PR.
        return false;
    }

    public function sendsClia(Claim $claim): bool
    {
        return parent::sendsClia($claim) || array_intersect(self::cptCodes($claim), self::CLIA_WAIVED_CPTS) !== [];
    }

    public function sendsEpsdtAsNote(Claim $claim): bool
    {
        return true;
    }

    public function sendsServiceFacility(Claim $claim, bool $default): bool
    {
        // Sent whenever the service facility isn't the billing provider, home visits included.
        return self::str($claim->facilityNPI()) !== self::str($claim->billingFacilityNPI());
    }

    public function sendsOtherPayer(Claim $claim, int $ins): bool
    {
        if ($claim->claimType($ins) === 'MB' && $claim->claimType(0) === 'MB') {
            // Two Medicare policies: send the claim and let the demographics be fixed.
            $this->warn('Skipping other insco loop since primary is also type MB.');
            return false;
        }
        // VA Community Care claims carry no other insurance.
        return self::str($claim->payerID(0)) !== self::VA_COMMUNITY_CARE;
    }

    public function otherPayerIdentifier(Claim $claim, int $ins): ?string
    {
        if (self::str($claim->payerID()) !== self::VERMONT_MEDICAID) {
            return null;
        }
        if ($claim->claimType($ins) === 'MB') {
            return 'MDB';
        }
        $payerId = self::str($claim->payerID($ins));
        if ($payerId === 'BCSVT' || $payerId === 'BCBSVT') {
            $policy = self::str($claim->policyNumber($ins));
            return match (true) {
                self::str($claim->payerName($ins)) === 'BCBS NJ' => 'H6',
                str_starts_with($policy, 'V4BV') => 'MDB',
                str_starts_with($policy, 'PEX') => 'BV',
                default => 'EE',
            };
        }
        if (isset(self::MEDICAID_CARRIER_CODES[$payerId])) {
            return self::MEDICAID_CARRIER_CODES[$payerId];
        }
        if ($payerId === '60054') {
            $group = self::str($claim->groupNumber());
            return $group !== '' ? $group : '92';
        }
        $otherGroup = self::str($claim->groupNumber($ins));
        if ($otherGroup !== '') {
            return substr($otherGroup, 0, 3);
        }
        $this->warn('Missing carrier code for medicaid? Enter in previous ins group # then?');
        return '';
    }

    public function sendsLineRenderingProvider(Claim $claim, int $prockey, bool $default): bool
    {
        return $default || self::str($claim->payerID()) === self::RENDERING_PROVIDER_PER_LINE;
    }

    public function sendsNdc(Claim $claim, int $prockey): bool
    {
        return !in_array(self::str($claim->payerID()), $this->ndcSkipPayerIds, true);
    }

    public function lineAdjustmentReason(Claim $claim, int $ins, string $groupCode, string $reasonCode): string
    {
        // Vermont Medicaid takes a Medicare copay as coinsurance.
        if (
            self::str($claim->payerID()) === self::VERMONT_MEDICAID
            && $claim->claimType($ins) === 'MB'
            && $groupCode === 'PR'
            && $reasonCode === '3'
        ) {
            return '2';
        }
        return $reasonCode;
    }

    public function extraLineAdjustments(Claim $claim, int $ins, int $prockey): array
    {
        // On a tertiary claim, what the primary paid and adjusted is reported on
        // the secondary's line as OA-23 (impact of prior payer adjudication).
        if ($claim->payerSequence() !== 'T' || $claim->payerSequence($ins) !== 'S') {
            return [];
        }
        $primary = $claim->payerTotals(1, $claim->cptKey($prockey));
        $paid = is_array($primary) && is_numeric($primary[1] ?? null) ? (float) $primary[1] : 0.0;
        $adjusted = is_array($primary) && is_numeric($primary[2] ?? null) ? (float) $primary[2] : 0.0;
        if ($paid == 0 && $adjusted == 0) {
            return [];
        }
        return [['group' => 'OA', 'reason' => '23', 'amount' => $paid + $adjusted]];
    }

    public function warnings(Claim $claim): array
    {
        $warnings = $this->warnings;
        if (self::str($claim->facilityPOS()) === '1') {
            $warnings[] = 'Place of service code is 01 Pharmacy!';
        }
        $payerId = self::str($claim->payerID());
        if ($payerId === self::INVALID_PAYER_ID) {
            $warnings[] = "Claim's payer ID is invalid.";
        }
        if ($payerId === self::VA_COMMUNITY_CARE && self::str($claim->priorAuth()) === '') {
            $warnings[] = 'Missing prior auth for ' . $payerId;
        }
        $facilityNpi = self::str($claim->facilityNPI());
        if ($facilityNpi !== '' && in_array($facilityNpi, $this->closedFacilityNpis, true)) {
            $warnings[] = "Service facility NPI $facilityNpi is closed; fix the encounter's facility.";
        }
        if ($this->requiresLastSeenDate($claim)) {
            for ($prockey = 0; $prockey < $claim->procCount(); $prockey++) {
                $code = self::str($claim->cptCode($prockey));
                if (
                    in_array($code, self::ROUTINE_FOOT_CARE_CPTS, true)
                    && !in_array(self::str($claim->cptModifier($prockey)), self::ROUTINE_FOOT_CARE_MODIFIERS, true)
                ) {
                    $warnings[] = "Procedure '" . self::str($claim->cptKey($prockey)) . "' is missing Class findings modifier!";
                }
            }
        }
        return $warnings;
    }

    private function warn(string $warning): void
    {
        if (!in_array($warning, $this->warnings, true)) {
            $this->warnings[] = $warning;
        }
    }

    private function isRoutineFootCarePractice(Claim $claim): bool
    {
        return in_array(self::str($claim->billingFacilityNPI()), $this->routineFootCareNpis, true);
    }

    /**
     * @return list<string>
     */
    private static function cptCodes(Claim $claim): array
    {
        $codes = [];
        for ($prockey = 0; $prockey < $claim->procCount(); $prockey++) {
            $codes[] = self::str($claim->cptCode($prockey));
        }
        return $codes;
    }

    /**
     * @return list<string>
     */
    private static function diagnoses(Claim $claim): array
    {
        $diagnoses = $claim->diagArray();
        return is_array($diagnoses) ? array_values(array_map(self::str(...), $diagnoses)) : [];
    }

    private static function str(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
