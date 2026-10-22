<?php

/**
 * Isolated tests for step 1's rules: RRMC's charge rules (rrmc008), the
 * same-patient insurance check and the paper/unknown payer list (rrmc007).
 * All data is invented.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\Modules\RriBilling;

require_once __DIR__ . '/autoload.php';

use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\Address;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeFileAccount;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeFileEmployer;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeFileGuarantor;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeFileInsurance;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeFilePatient;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeFileVisit;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeLine;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\PersonName;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\PhoneNumber;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\ChargeMaster;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\ChargeMasterEntry;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\PayerCompany;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\PayerDirectory;
use Cmsvt\OpenEMR\Modules\RriBilling\Rules\ChargeRules;
use Cmsvt\OpenEMR\Modules\RriBilling\Rules\PayerCheck;
use Cmsvt\OpenEMR\Modules\RriBilling\Rules\VisitInsuranceCheck;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StepOneRulesTest extends TestCase
{
    /**
     * @param array<string, int> $fees "CDM CODE" => fee in cents
     */
    private static function rules(array $fees = ['1285 73562' => 4500, '1285 73560' => 3000, '7000 71046' => 2500]): ChargeRules
    {
        return new ChargeRules(new class ($fees) implements ChargeMaster {
            /** @param array<string, int> $fees */
            public function __construct(private readonly array $fees)
            {
            }

            public function find(string $cdm, string $code): ?ChargeMasterEntry
            {
                $fee = $this->fees["$cdm $code"] ?? null;
                return $fee === null ? null : new ChargeMasterEntry($cdm, $code, '', $fee);
            }
        });
    }

    private static function charge(
        string $cdm = '7000',
        string $cpt = '71046',
        string $hcpcs = '',
        string $modifier1 = '',
        string $modifier2 = '',
        string $glCode = '1',
        string $location = 'MAIN',
    ): ChargeLine {
        return new ChargeLine(
            '',
            '52',
            $glCode,
            $cdm,
            new \DateTimeImmutable('2026-10-20'),
            '102026',
            '001',
            '',
            'OUTP',
            'MITCHELL, EXAMPLE',
            $cpt,
            $hcpcs,
            $modifier1,
            $modifier2,
            '',
            '',
            $location,
            '',
        );
    }

    private static function insurance(string $code = '', string $policy = '', string $city = '', string $zip = '', string $phone = ''): ChargeFileInsurance
    {
        return new ChargeFileInsurance(
            $code,
            $policy,
            '',
            '',
            '',
            PersonName::parse(''),
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            new Address('', '', $city, $city === '' ? '' : 'VT', $zip),
            $phone,
            '',
        );
    }

    /**
     * @param list<ChargeLine> $charges
     * @param list<ChargeFileInsurance> $insurances primary first; missing ones are empty
     */
    private static function account(array $charges = [], string $visit = '10000001', string $mrnRaw = '12-34-56', array $insurances = []): ChargeFileAccount
    {
        $name = PersonName::parse('DOE, JANE');
        $address = new Address('', '', '', '', '');
        $insurances += [self::insurance(), self::insurance(), self::insurance(), self::insurance()];
        return new ChargeFileAccount(
            $visit,
            str_replace('-', '', $mrnRaw),
            $mrnRaw,
            new ChargeFilePatient($name, $address, '', 'F', '', PhoneNumber::parse('')),
            new ChargeFileGuarantor($name, $address, ''),
            new ChargeFileEmployer('', $address),
            new ChargeFileVisit('', '', '', null, '', '', $name, '', '', '', '', '', '', ''),
            $insurances[0],
            $insurances[1],
            $insurances[2],
            $insurances[3],
            $charges,
        );
    }

    /**
     * @param list<ChargeFileAccount> $accounts
     * @return list<list<string>> per account, each kept charge as "CPT MOD1 MOD2"
     */
    private static function summary(array $accounts): array
    {
        return array_map(
            static fn(ChargeFileAccount $account): array => array_map(
                static fn(ChargeLine $charge): string => trim("$charge->cpt $charge->modifier1 $charge->modifier2"),
                $account->charges
            ),
            $accounts
        );
    }

    #[Test]
    public function anUndefinedProcedureIsDroppedWithTheRightInstruction(): void
    {
        $result = self::rules()->apply([self::account([
            self::charge(cdm: '9999'),
            self::charge(cdm: '9998', glCode: '0', location: 'RVOC'),
            self::charge(cdm: '9997', glCode: '0'),
            self::charge(),
        ])]);

        $this->assertSame([['71046']], self::summary($result->accounts));
        $this->assertSame([
            'UNDEFINED PROCEDURE FOR MRN 123456 CDM 9999 CPT 71046 HCPCS  DOS 102026',
            '** STOP! And ADD THIS CDM-CPT IN THE 52 TO CAPTURE THE NEW RRMC CDM, re-run qqq thank you. MAIN **',
            'UNDEFINED PROCEDURE FOR MRN 123456 CDM 9998 CPT 71046 HCPCS  DOS 102026',
            '** STOP and ADD THIS CDM-CPT IN THE 52 TO CAPTURE THE new RVOC CDM, re-run qqq thank you. RVOC **',
            'UNDEFINED PROCEDURE FOR MRN 123456 CDM 9997 CPT 71046 HCPCS  DOS 102026',
            "DON'T WORRY ABOUT IT DUE TO RRMC AUC",
        ], $result->messages);
    }

    #[Test]
    public function theHcpcsIsLookedUpWhenThereIsNoCpt(): void
    {
        $result = self::rules(['7000 G0279' => 1000])->apply([self::account([self::charge(cpt: '', hcpcs: 'G0279')])]);

        $this->assertSame([['']], self::summary($result->accounts));
        $this->assertSame('G0279', $result->accounts[0]->charges[0]->hcpcs);
        $this->assertSame([], $result->messages);
    }

    #[Test]
    public function someHcpcsCodesAreBilledAsTheirCpt(): void
    {
        $result = self::rules(['6250 C8901' => 9000, '6250 74185' => 9000])->apply([self::account([
            self::charge(cdm: '6250', cpt: '', hcpcs: 'C8901'),
            self::charge(cdm: '6250', cpt: '74185'),
        ])]);

        $this->assertSame([['74185', '74185']], self::summary($result->accounts));
        $this->assertSame('C8901', $result->accounts[0]->charges[0]->hcpcs);
        $this->assertSame(['RRMC SENT US  C8901 CHANGING THIS TO CPT 74185'], $result->messages);
    }

    #[Test]
    public function aZeroDollarChargeIsDroppedUnlessItIsAnAucLine(): void
    {
        $result = self::rules(['7000 71046' => 0])->apply([self::account([
            self::charge(),
            self::charge(glCode: '0'),
        ])]);

        $this->assertSame([['71046']], self::summary($result->accounts));
        $this->assertSame('0', $result->accounts[0]->charges[0]->glCode);
        $this->assertSame(
            ['Zero dollar charge found for mrn 123456 CDM 7000 CPT 71046 HCPCS  DOS 102026 safe to ignore, usually is but DBL CK!'],
            $result->messages
        );
    }

    #[Test]
    public function aChargeWithBothCodesIsReportedAndKept(): void
    {
        $result = self::rules()->apply([self::account([self::charge(hcpcs: 'G0279', modifier1: 'TC')])]);

        $this->assertSame([['71046 TC']], self::summary($result->accounts));
        $this->assertSame(['for 10000001 rrmc sent us both a hcpcs G0279 AND a cpt 71046 with mod TC for DOS 102026'], $result->messages);
    }

    #[Test]
    public function rvocLosesItsProfessionalComponentModifierAndRepeatsAreCleared(): void
    {
        $result = self::rules()->apply([self::account([
            self::charge(modifier1: '26', modifier2: 'RT', location: 'RVOC'),
            self::charge(modifier1: '26', location: 'RVOC'),
            self::charge(modifier1: '26', modifier2: 'LT'),
            self::charge(modifier1: 'LT', modifier2: 'LT'),
        ])]);

        $this->assertSame([['71046 RT', '71046', '71046 26 LT', '71046 LT']], self::summary($result->accounts));
        $this->assertSame([], $result->messages);
    }

    #[Test]
    public function bilateralStudiesBecomeAnRtLtPairAcrossAccounts(): void
    {
        $result = self::rules()->apply([
            self::account([self::charge(cdm: '1285', cpt: '73562', modifier1: '50')]),
            self::account([
                self::charge(cdm: '1285', cpt: '73562', modifier1: '50'),
                self::charge(cdm: '1285', cpt: '73562', modifier1: '50'),
                self::charge(cpt: '71046', modifier1: '50'),
            ], '10000002'),
        ]);

        $this->assertSame([['73562 RT'], ['73562 LT', '73562 RT', '71046 50']], self::summary($result->accounts));
        $this->assertSame([], $result->messages);
    }

    #[Test]
    public function rvocsSecondStudyOfAPairBecomesLt(): void
    {
        // RVOC sends the second study of a bilateral pair with 26 only; once 26 is
        // removed it is billed LT. Without an open pair it stays unmodified.
        $result = self::rules()->apply([self::account([
            self::charge(cdm: '1285', cpt: '73562', modifier1: '26', location: 'RVOC'),
            self::charge(cdm: '1285', cpt: '73562', modifier1: '50', location: 'RVOC'),
            self::charge(cdm: '1285', cpt: '73562', modifier1: '26', location: 'RVOC'),
            self::charge(cdm: '1285', cpt: '73562', modifier1: '50', location: 'RVOC'),
        ])]);

        $this->assertSame([['73562', '73562 RT', '73562 LT', '73562 RT']], self::summary($result->accounts));
        $this->assertSame(['CHANGING BILAT STUDY TO RT LT, MEDREC 123456 CDM 1285 CPT 73562 MOD1    DOS 102026'], $result->messages);
    }

    #[Test]
    public function droppedChargesDoNotMoveThePairing(): void
    {
        $result = self::rules()->apply([self::account([
            self::charge(cdm: '1285', cpt: '73562', modifier1: '50'),
            self::charge(cdm: '1285', cpt: '73999', modifier1: '50'),
            self::charge(cdm: '1285', cpt: '73562', modifier1: '50'),
        ])]);

        $this->assertSame([['73562 RT', '73562 LT']], self::summary($result->accounts));
    }

    #[Test]
    public function differentPrimaryPayersForOnePatientAreReported(): void
    {
        $messages = (new VisitInsuranceCheck())->check([
            self::account([], '10000001', '12-34-56', [self::insurance('P0001')]),
            self::account([], '10000001', '12-34-56', [self::insurance('P0002')]),
            self::account([], '10000002', '12-34-56', [self::insurance('P0002')]),
            self::account([], '10000003', '12-34-56', [self::insurance('P0003')]),
            self::account([], '10000004', '99-99-99', [self::insurance('P0004')]),
        ]);

        $this->assertSame([
            '*** uh oh, rrmc ins code P0003 is different from prior record ins code P0002 in this file for mrn 123456',
            '*** which means we were given different insurances for distinct charges for the same person and should be remedied.',
        ], $messages);
    }

    #[Test]
    public function paperAndUnknownPayersAreListedInTheOldLayoutAndOrder(): void
    {
        $directory = new class implements PayerDirectory {
            public function insuranceKeyForRrmcCode(string $rrmcCode): ?string
            {
                return $this->companyForRrmcCode($rrmcCode)?->legacyCode();
            }

            public function companyById(int $id): ?PayerCompany
            {
                return null;
            }

            public function companyForRrmcCode(string $rrmcCode): ?PayerCompany
            {
                return match ($rrmcCode) {
                    'E0001' => new PayerCompany(3, true),
                    'P0002' => new PayerCompany(95, false),
                    'P0003' => new PayerCompany(1, false),
                    'P0004' => new PayerCompany(13924, false),
                    default => null,
                };
            }
        };
        $lines = (new PayerCheck($directory))->check([
            self::account([], '10000001', '12-34-56', [
                self::insurance('E0001'),
                self::insurance('P0002', 'POL2', 'RUTLAND', '05701', '802-555-0100'),
                self::insurance('U0009'),
            ]),
            self::account([], '10000002', '1234567', [
                self::insurance('P0003'),
                self::insurance(),
                self::insurance(),
                self::insurance('P0004'),
            ]),
        ]);

        $this->assertSame([
            'TERTIARY   U0009 XXX ' . str_repeat(' ', 30) . ' ' . str_repeat(' ', 20) . '    ' . str_repeat(' ', 10) . ' '
                . str_repeat(' ', 12) . ' MRN 00123456        ',
            'QUATERNARY P0004 13924 ' . str_repeat(' ', 30) . ' ' . str_repeat(' ', 20) . '    ' . str_repeat(' ', 10) . ' '
                . str_repeat(' ', 12) . ' MRN 1234567         ',
            'SECONDARY  P0002 095 ' . str_pad('POL2', 30) . ' ' . str_pad('RUTLAND', 20) . ' VT ' . str_pad('05701', 10) . ' '
                . '802-555-0100 MRN 00123456        ',
            'PRIMARY    P0003 001 ' . str_repeat(' ', 30) . ' ' . str_repeat(' ', 20) . '    ' . str_repeat(' ', 10) . ' '
                . str_repeat(' ', 12) . ' MRN 1234567         ',
        ], $lines);
    }
}
