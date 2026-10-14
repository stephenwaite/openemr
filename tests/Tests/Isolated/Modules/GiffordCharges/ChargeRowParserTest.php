<?php

/**
 * Isolated tests for parsing Gifford charge rows into records.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\Modules\GiffordCharges;

require_once __DIR__ . '/autoload.php';

use Cmsvt\OpenEMR\Modules\GiffordCharges\Parse\ChargeRowParser;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Reader\RawRow;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ChargeRowParserTest extends TestCase
{
    /**
     * A made-up row shaped like Gifford's sample: patient is the primary
     * subscriber, self pay second, no POS column.
     *
     * @param array<string, string> $overrides
     */
    private static function row(array $overrides = []): RawRow
    {
        return new RawRow(2, array_merge([
            'encounter_num' => 'GH0000000001',
            'pat_mrn' => 'MR0000000001',
            'pat_last' => 'Testpatient',
            'pat_first' => 'Ann',
            'pat_addr1' => '1 Test Lane',
            'pat_addr2' => '',
            'pat_city' => 'Testville',
            'pat_state' => 'VT',
            'pat_zip' => '05675',
            'pat_phone' => '802-555-0100',
            'pat_email' => '',
            'pat_sex' => 'F',
            'pat_dob' => '25917',
            'enc_admit_date' => '08/20/2026',
            'enc_discharge_date' => '08/20/2026',
            'enc_workcomp' => 'N',
            'enc_dx_codes' => 'I50.20,Z94.1,Z95.811',
            'prov_ref_name' => 'Referrer, Rae',
            'prov_ref_npi' => '1234567893',
            'prov_rend_name' => 'Reader, Ray',
            'prov_rend_npi' => '1234567893',
            'chg_dos' => '08/20/2026',
            'chg_cpt' => '93005',
            'chg_hcpcs' => '',
            'chg_mod1' => '',
            'chg_units' => '1',
            'chg_accession' => 'A0000000001',
            'chg_location' => 'GHC.TEST',
            'guar_last' => 'Testpatient',
            'guar_first' => 'Ann',
            'emp_name' => 'Retired',
            'ins1_rank' => '1',
            'ins1_mnemonic' => 'MCDVT',
            'ins1_payer_name' => 'MEDICAID VT',
            'ins1_member_id' => '38097',
            'ins1_group_num' => '',
            'ins1_sub_name' => 'Testpatient, Ann',
            // Gifford's subscriber DOB does not always match the patient's.
            'ins1_sub_dob' => '03/25/1964',
            'ins1_sub_gender' => 'M',
            'ins1_relate' => '18',
            'ins2_rank' => '2',
            'ins2_mnemonic' => 'SP',
            'ins2_payer_name' => 'SELF PAY',
            'ins2_relate' => '01',
            'ins3_rank' => '3',
            'ins3_mnemonic' => '',
            'ins3_relate' => '01',
        ], $overrides));
    }

    #[Test]
    public function aCompleteRowIsImportable(): void
    {
        $parsed = (new ChargeRowParser())->parse(self::row());

        $this->assertSame([], $parsed->problems);
        $this->assertTrue($parsed->isImportable());
        $patient = $parsed->patient;
        $encounter = $parsed->encounter;
        $charge = $parsed->charge;
        $this->assertNotNull($patient);
        $this->assertNotNull($encounter);
        $this->assertNotNull($charge);
        $this->assertSame('MR0000000001', $patient->mrn);
        $this->assertSame('1970-12-15', $patient->dateOfBirth->format('Y-m-d'));
        $this->assertSame('Female', $patient->sex);
        $this->assertSame('GH0000000001', $encounter->visitNumber);
        $this->assertSame('2026-08-20', $encounter->admitDate->format('Y-m-d'));
        $this->assertSame(['I50.20', 'Z94.1', 'Z95.811'], $encounter->diagnoses);
        $this->assertNull($encounter->placeOfService, 'no POS until Gifford sends it');
        $this->assertSame('CPT4', $charge->codeType);
        $this->assertSame('93005', $charge->code);
        $this->assertSame(1, $charge->units);
    }

    #[Test]
    public function selfPayIsNotAPolicyAndASelfSubscriberIsThePatient(): void
    {
        $parsed = (new ChargeRowParser())->parse(self::row());

        $this->assertCount(1, $parsed->policies);
        $policy = $parsed->policies[0];
        $this->assertSame(1, $policy->rank);
        $this->assertSame('MCDVT', $policy->payerMnemonic);
        $this->assertSame('self', $policy->relationship);
        $this->assertSame(['Testpatient', 'Ann'], [$policy->subscriberLastName, $policy->subscriberFirstName]);
        $this->assertSame('1970-12-15', $policy->subscriberDateOfBirth?->format('Y-m-d'));
        $this->assertSame('Female', $policy->subscriberSex);
    }

    #[Test]
    public function aSpouseSubscriberKeepsTheSubscriberColumns(): void
    {
        $parsed = (new ChargeRowParser())->parse(self::row([
            'ins1_relate' => '01',
            'ins1_sub_name' => 'Testspouse, Ed',
            'ins1_sub_dob' => '11/24/1961',
            'ins1_sub_gender' => 'M',
        ]));

        $policy = $parsed->policies[0];
        $this->assertSame('spouse', $policy->relationship);
        $this->assertSame(['Testspouse', 'Ed'], [$policy->subscriberLastName, $policy->subscriberFirstName]);
        $this->assertSame('1961-11-24', $policy->subscriberDateOfBirth?->format('Y-m-d'));
        $this->assertSame('Male', $policy->subscriberSex);
    }

    #[Test]
    public function placeOfServiceIsUsedWhenGiven(): void
    {
        $parser = new ChargeRowParser();

        $this->assertSame('22', $parser->parse(self::row(['chg_pos' => '22']))->encounter?->placeOfService);

        $bad = $parser->parse(self::row(['chg_pos' => 'XX']));
        $this->assertFalse($bad->isImportable());
        $this->assertContains("chg_pos 'XX' is not a place of service code", $bad->problems);
    }

    #[Test]
    public function aHcpcsChargeWithModifiers(): void
    {
        $parsed = (new ChargeRowParser())->parse(self::row([
            'chg_cpt' => '',
            'chg_hcpcs' => 'g0279',
            'chg_mod1' => '26',
            'chg_mod2' => 'rt',
        ]));

        $this->assertSame([], $parsed->problems);
        $charge = $parsed->charge;
        $this->assertNotNull($charge);
        $this->assertSame('HCPCS', $charge->codeType);
        $this->assertSame('G0279', $charge->code);
        $this->assertSame(['26', 'RT'], $charge->modifiers);
    }

    #[Test]
    public function everyProblemInARowIsReported(): void
    {
        $parsed = (new ChargeRowParser())->parse(self::row([
            'pat_dob' => '13/45/1970',
            'enc_dx_codes' => '786.50',
            'prov_rend_npi' => '123',
            'chg_units' => '0',
            'chg_hcpcs' => 'G0279',
            'ins1_member_id' => '',
        ]));

        $this->assertFalse($parsed->isImportable());
        $this->assertNull($parsed->patient);
        $this->assertNull($parsed->charge);
        $this->assertSame([
            "pat_dob '13/45/1970' is not a date",
            'enc_dx_codes has no ICD-10 codes',
            'enc_dx_codes has codes that are not ICD-10: 786.50',
            "prov_rend_npi '123' is not an NPI",
            "chg_cpt '93005' and chg_hcpcs 'G0279' are both set",
            "chg_units '0' is not a whole number of units",
            'ins1_member_id is empty for MCDVT',
        ], $parsed->problems);
    }
}
