<?php

/**
 * Parses one raw Gifford charge row into records, listing every problem found.
 *
 * Nothing here looks at the database: payer mnemonics, the rendering NPI and the
 * location are only checked for presence. Matching them to OpenEMR (and the fee)
 * happens when the batch is reviewed.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Parse;

use Cmsvt\OpenEMR\Modules\GiffordCharges\Model\Address;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Model\ChargeRecord;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Model\EncounterRecord;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Model\ParsedRow;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Model\PartyRecord;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Model\PatientRecord;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Model\PolicyRecord;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Reader\RawRow;

final class ChargeRowParser
{
    /** Payer mnemonics that mean the patient pays: no policy is created. */
    private const SELF_PAY_MNEMONICS = ['SP'];

    public function parse(RawRow $row): ParsedRow
    {
        $problems = [];

        $patient = $this->patient($row, $problems);
        $encounter = $this->encounter($row, $problems);
        $charge = $this->charge($row, $problems);
        $policies = $this->policies($row, $problems);

        return new ParsedRow(
            $row->line,
            $patient,
            $encounter,
            $charge,
            $this->guarantor($row),
            $this->employer($row),
            $policies,
            $problems,
        );
    }

    /**
     * @param list<string> $problems
     */
    private function patient(RawRow $row, array &$problems): ?PatientRecord
    {
        $mrn = $row->get('pat_mrn');
        $last = $row->get('pat_last');
        $first = $row->get('pat_first');
        $dob = GiffordValues::date($row->get('pat_dob'));

        if ($mrn === '') {
            $problems[] = 'pat_mrn is empty';
        }
        if ($last === '' || $first === '') {
            $problems[] = 'patient name is incomplete';
        }
        if ($dob === null) {
            $problems[] = "pat_dob '" . $row->get('pat_dob') . "' is not a date";
        }
        if ($mrn === '' || $last === '' || $first === '' || $dob === null) {
            return null;
        }

        return new PatientRecord(
            $mrn,
            $last,
            $first,
            $dob,
            GiffordValues::sex($row->get('pat_sex')),
            self::address($row, 'pat'),
            $row->get('pat_phone'),
            $row->get('pat_email'),
        );
    }

    /**
     * @param list<string> $problems
     */
    private function encounter(RawRow $row, array &$problems): ?EncounterRecord
    {
        $visit = $row->get('encounter_num');
        // The admit date is the encounter date; a file without it falls back
        // to the charge's date of service.
        $admitText = $row->get('enc_admit_date') !== '' ? $row->get('enc_admit_date') : $row->get('chg_dos');
        $admit = GiffordValues::date($admitText);
        [$diagnoses, $rejected] = GiffordValues::diagnoses($row->get('enc_dx_codes'));
        $renderingNpi = $row->get('prov_rend_npi');

        if ($visit === '') {
            $problems[] = 'encounter_num is empty';
        }
        if ($admit === null) {
            $problems[] = "enc_admit_date '" . $admitText . "' is not a date";
        }
        if ($diagnoses === []) {
            $problems[] = 'enc_dx_codes has no ICD-10 codes';
        }
        if ($rejected !== []) {
            $problems[] = 'enc_dx_codes has codes that are not ICD-10: ' . implode(', ', $rejected);
        }
        if (preg_match('/^\d{10}$/', $renderingNpi) !== 1) {
            $problems[] = "prov_rend_npi '" . $renderingNpi . "' is not an NPI";
        }

        $placeOfService = null;
        if ($row->get('chg_pos') !== '') {
            $placeOfService = GiffordValues::placeOfService($row->get('chg_pos'));
            if ($placeOfService === null) {
                $problems[] = "chg_pos '" . $row->get('chg_pos') . "' is not a place of service code";
            }
        }

        $discharge = GiffordValues::date($row->get('enc_discharge_date'));
        $accident = GiffordValues::date($row->get('enc_accident_date'));

        if ($visit === '' || $admit === null) {
            return null;
        }

        return new EncounterRecord(
            $visit,
            $admit,
            $discharge,
            strtoupper($row->get('enc_workcomp')) === 'Y',
            $accident,
            $row->get('enc_auth_num'),
            $diagnoses,
            $renderingNpi,
            $row->get('prov_rend_name'),
            $row->get('prov_ref_npi'),
            $row->get('prov_ref_name'),
            $row->get('chg_location'),
            $placeOfService,
        );
    }

    /**
     * @param list<string> $problems
     */
    private function charge(RawRow $row, array &$problems): ?ChargeRecord
    {
        $dos = GiffordValues::date($row->get('chg_dos'));
        $cpt = strtoupper($row->get('chg_cpt'));
        $hcpcs = strtoupper($row->get('chg_hcpcs'));
        $unitsText = $row->get('chg_units');
        $accession = $row->get('chg_accession');

        if ($dos === null) {
            $problems[] = "chg_dos '" . $row->get('chg_dos') . "' is not a date";
        }

        [$codeType, $code] = match (true) {
            $cpt !== '' && $hcpcs !== '' && $cpt !== $hcpcs => [null, ''],
            $cpt !== '' => ['CPT4', $cpt],
            $hcpcs !== '' => ['HCPCS', $hcpcs],
            default => [null, ''],
        };
        if ($cpt !== '' && $hcpcs !== '' && $cpt !== $hcpcs) {
            $problems[] = "chg_cpt '$cpt' and chg_hcpcs '$hcpcs' are both set";
        } elseif ($code === '') {
            $problems[] = 'chg_cpt and chg_hcpcs are both empty';
        } elseif (preg_match('/^[A-Z0-9]{5}$/', $code) !== 1) {
            $problems[] = "procedure code '$code' is not 5 characters";
            $codeType = null;
        }

        if (!ctype_digit($unitsText) || (int) $unitsText < 1) {
            $problems[] = "chg_units '" . $unitsText . "' is not a whole number of units";
        }
        if ($accession === '') {
            $problems[] = 'chg_accession is empty';
        }

        $modifiers = [];
        foreach (['chg_mod1', 'chg_mod2', 'chg_mod3'] as $column) {
            $modifier = strtoupper($row->get($column));
            if ($modifier === '') {
                continue;
            }
            if (preg_match('/^[A-Z0-9]{2}$/', $modifier) !== 1) {
                $problems[] = "$column '$modifier' is not a modifier";
                continue;
            }
            $modifiers[] = $modifier;
        }

        if ($dos === null || $codeType === null || !ctype_digit($unitsText) || (int) $unitsText < 1 || $accession === '') {
            return null;
        }

        return new ChargeRecord(
            $dos,
            $codeType,
            $code,
            $modifiers,
            (int) $unitsText,
            $accession,
            $row->get('chg_dept'),
            $row->get('chg_clinical'),
            $row->get('chg_comment'),
        );
    }

    /**
     * The ins1-ins3 column groups, by rank. A group with no mnemonic, or a
     * self-pay mnemonic, has no policy.
     *
     * @param list<string> $problems
     * @return list<PolicyRecord>
     */
    private function policies(RawRow $row, array &$problems): array
    {
        $policies = [];
        $ranks = [];
        foreach ([1, 2, 3] as $group) {
            $prefix = "ins{$group}_";
            $mnemonic = strtoupper($row->get($prefix . 'mnemonic'));
            if ($mnemonic === '' || in_array($mnemonic, self::SELF_PAY_MNEMONICS, true)) {
                continue;
            }

            $rankText = $row->get($prefix . 'rank');
            $rank = $rankText === '' ? $group : (ctype_digit($rankText) ? (int) $rankText : 0);
            if ($rank < 1 || $rank > 3) {
                $problems[] = "{$prefix}rank '$rankText' is not 1, 2 or 3";
                continue;
            }
            if (in_array($rank, $ranks, true)) {
                $problems[] = "two policies have rank $rank";
                continue;
            }
            if ($row->get($prefix . 'member_id') === '') {
                $problems[] = "{$prefix}member_id is empty for $mnemonic";
            }

            $relationship = GiffordValues::relationship($row->get($prefix . 'relate'));
            [$subscriberLast, $subscriberFirst] = GiffordValues::lastFirst($row->get($prefix . 'sub_name'));
            $subscriberDob = GiffordValues::date($row->get($prefix . 'sub_dob'));
            $subscriberSex = GiffordValues::sex($row->get($prefix . 'sub_gender'));
            if ($relationship === 'self') {
                // The patient is the subscriber: the patient columns win over
                // the subscriber ones, which do not always agree with them.
                $subscriberLast = $row->get('pat_last');
                $subscriberFirst = $row->get('pat_first');
                $subscriberDob = GiffordValues::date($row->get('pat_dob'));
                $subscriberSex = GiffordValues::sex($row->get('pat_sex'));
            } elseif ($subscriberLast === '') {
                $problems[] = "{$prefix}sub_name is empty for a subscriber who is not the patient";
            }

            $ranks[] = $rank;
            $policies[] = new PolicyRecord(
                $rank,
                $mnemonic,
                $row->get($prefix . 'payer_name'),
                $row->get($prefix . 'member_id'),
                $row->get($prefix . 'group_num'),
                $row->get($prefix . 'group_name'),
                $subscriberLast,
                $subscriberFirst,
                $subscriberDob,
                $subscriberSex,
                $relationship,
            );
        }

        usort($policies, static fn(PolicyRecord $a, PolicyRecord $b): int => $a->rank <=> $b->rank);
        return $policies;
    }

    private function guarantor(RawRow $row): ?PartyRecord
    {
        if ($row->get('guar_last') === '' && $row->get('guar_first') === '') {
            return null;
        }
        return new PartyRecord($row->get('guar_last'), $row->get('guar_first'), self::address($row, 'guar'));
    }

    private function employer(RawRow $row): ?PartyRecord
    {
        if ($row->get('emp_name') === '') {
            return null;
        }
        return new PartyRecord($row->get('emp_name'), '', self::address($row, 'emp'));
    }

    private static function address(RawRow $row, string $prefix): Address
    {
        return new Address(
            $row->get($prefix . '_addr1'),
            $row->get($prefix . '_addr2'),
            $row->get($prefix . '_city'),
            $row->get($prefix . '_state'),
            $row->get($prefix . '_zip'),
        );
    }
}
