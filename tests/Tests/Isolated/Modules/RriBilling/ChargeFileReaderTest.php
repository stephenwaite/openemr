<?php

/**
 * Isolated tests for reading RRMC's charge file: field positions, case,
 * dates, record order and malformed files. All data is invented.
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

use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeFileException;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeFileReader;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\PersonName;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ShortDate;
use Lcobucci\Clock\FrozenClock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ChargeFileReaderTest extends TestCase
{
    private const TODAY = '2026-10-22 10:30:00';

    private static function reader(): ChargeFileReader
    {
        return new ChargeFileReader(new FrozenClock(new \DateTimeImmutable(self::TODAY)));
    }

    /**
     * @param array<string, string> $overrides
     */
    private static function account(array $overrides = []): string
    {
        return ChargeFileFixture::accountLine($overrides + [
            'visit' => '12345678',
            'name' => 'doe, jane q',
            'street' => '1 main st',
            'suite' => 'apt 2',
            'city' => 'rutland',
            'state' => 'vt',
            'zip' => '05701-1234',
            'admitDate' => '10/20/2026',
            'admitTime' => '08:15',
            'workComp' => 'n',
            'guarantor' => 'doe, john',
            'guarantorStreet' => '1 main st',
            'guarantorCity' => 'rutland',
            'guarantorState' => 'vt',
            'guarantorZip' => '05701',
            'email' => 'Jane.Doe@Example.com',
            'p1.code' => 'p0123',
            'p1.policy' => 'xyz123456789',
            'p1.group' => 'grp-1',
            'p1.groupName' => 'example employer',
            'p1.subscriber' => 'doe, john a',
            'p1.gender' => 'm',
            'p1.dob' => '01/02/1960',
            'p1.ssn' => '000000000',
            'p1.relate' => '02',
            'p1.name' => 'example health plan',
            'p1.street' => 'po box 1',
            'p1.city' => 'example',
            'p1.state' => 'vt',
            'p1.zip' => '05601',
            'p1.phone' => '800-555-0100',
            'p1.auth' => 'auth1',
            'p2.code' => 's0456',
            'p2.policy' => 'sec987',
            'p2.subscriber' => 'doe, jane',
            'authorization' => 'acct-auth',
        ]);
    }

    /**
     * @param array<string, string> $overrides
     */
    private static function continuation(array $overrides = []): string
    {
        return ChargeFileFixture::continuationLine($overrides + [
            'accidentDate' => '031598',
            'accidentTime' => '14:00',
            'referring' => 'welby, marcus',
            'diagnosis' => 'chest pain',
            'p3.code' => 't0789',
            'p3.policy' => 'ter555',
            'p4.code' => 'q0999',
            'mrn' => '12-34-56',
            'employer' => 'example corp',
            'employerStreet' => '2 elm st',
            'employerCity' => 'rutland',
            'employerState' => 'vt',
            'employerZip' => '05701',
            'guarantorSsn' => '000000001',
            'phone' => '802-555-0199',
            'doctorCode' => 'ref00042',
            'referringIndicator' => 'y',
            'referringNpi' => '1234567893',
            'admitDateDuplicate' => '102026',
            'gender' => 'f',
            'dob' => '650704',
            'discharge' => '102126',
        ]);
    }

    /**
     * @param array<string, string> $overrides
     */
    private static function charge(array $overrides = []): string
    {
        return ChargeFileFixture::chargeLine($overrides + [
            'department' => '52',
            'glCode' => '1',
            'cdm' => '1285',
            'dos' => '102026',
            'units' => '001',
            'clinical' => 'knee pain',
            'place' => 'emer',
            'doctor' => 'mitchell, example',
            'cpt' => '73562',
            'mod1' => '26',
            'mod2' => 'rt',
            'observation' => 'obs',
            'location' => 'rvoc',
            'accession' => 'c50.912',
        ]);
    }

    #[Test]
    public function readsAnAccountAndItsCharges(): void
    {
        $accounts = self::reader()->read(
            self::account() . self::continuation() . self::charge() . self::charge(['indicator' => 'can', 'cpt' => '73560'])
        );

        $this->assertCount(1, $accounts);
        $account = $accounts[0];
        $this->assertSame('12345678', $account->visitNumber);
        $this->assertSame('123456', $account->mrn);
        $this->assertSame('12-34-56', $account->mrnRaw);

        $patient = $account->patient;
        $this->assertEquals(new PersonName('DOE, JANE Q', 'DOE', 'JANE', 'Q'), $patient->name);
        $this->assertSame('1 MAIN ST', $patient->address->street);
        $this->assertSame('APT 2', $patient->address->suite);
        $this->assertSame('RUTLAND', $patient->address->city);
        $this->assertSame('VT', $patient->address->state);
        $this->assertSame('05701-1234', $patient->address->zip);
        $this->assertSame('F', $patient->gender);
        $this->assertSame('650704', $patient->dobRaw);
        $this->assertSame('802-555-0199', $patient->phone->full);
        $this->assertSame(['802', '555', '0199'], [$patient->phone->area, $patient->phone->exchange, $patient->phone->line]);

        $this->assertSame('DOE', $account->guarantor->name->last);
        $this->assertSame('05701', $account->guarantor->address->zip);
        $this->assertSame('000000001', $account->guarantor->ssn);
        $this->assertSame('EXAMPLE CORP', $account->employer->name);
        $this->assertSame('2 ELM ST', $account->employer->address->street);

        $visit = $account->visit;
        $this->assertSame('10/20/2026', $visit->admitDateRaw);
        $this->assertSame('20261020', $visit->admitDateYmd());
        $this->assertSame('08:15', $visit->admitTime);
        $this->assertSame('N', $visit->workComp);
        $this->assertSame('1998-03-15', $visit->accidentDate?->format('Y-m-d'));
        $this->assertSame('031598', $visit->accidentDateRaw);
        $this->assertSame('WELBY', $visit->referringName->last);
        $this->assertSame('1234567893', $visit->referringNpi);
        $this->assertSame('Y', $visit->referringIndicator);
        $this->assertSame('REF00042', $visit->doctorCode);
        $this->assertSame('CHEST PAIN', $visit->rrmcDiagnosis);
        $this->assertSame('ACCT-AUTH', $visit->authorization);
        $this->assertSame('102026', $visit->admitDateDuplicateRaw);
        $this->assertSame('102126', $visit->dischargeDateRaw);

        $primary = $account->primaryInsurance;
        $this->assertSame('P0123', $primary->payerCode);
        $this->assertSame('XYZ123456789', $primary->policyNumber);
        $this->assertSame('GRP-1', $primary->groupNumber);
        $this->assertSame('EXAMPLE EMPLOYER', $primary->groupName);
        $this->assertEquals(new PersonName('DOE, JOHN A', 'DOE', 'JOHN', 'A'), $primary->subscriber);
        $this->assertSame('M', $primary->subscriberGender);
        $this->assertSame('01/02/1960', $primary->subscriberDob);
        $this->assertSame('02', $primary->relate);
        $this->assertSame('EXAMPLE HEALTH PLAN', $primary->payerName);
        $this->assertSame('PO BOX 1', $primary->payerAddress->street);
        $this->assertSame('05601', $primary->payerAddress->zip);
        $this->assertSame('800-555-0100', $primary->payerPhone);
        $this->assertSame('AUTH1', $primary->authorization);
        $this->assertSame('S0456', $account->secondaryInsurance->payerCode);
        $this->assertSame('', $account->secondaryInsurance->authorization);
        $this->assertSame('T0789', $account->tertiaryInsurance->payerCode);
        $this->assertSame('TER555', $account->tertiaryInsurance->policyNumber);
        $this->assertSame('Q0999', $account->quaternaryInsurance->payerCode);
        $this->assertSame(
            ['P0123', 'S0456', 'T0789', 'Q0999'],
            array_map(static fn($insurance): string => $insurance->payerCode, $account->insurances())
        );

        $this->assertCount(2, $account->charges);
        [$charge, $cancel] = $account->charges;
        $this->assertFalse($charge->isCancel());
        $this->assertSame('52', $charge->department);
        $this->assertSame('1', $charge->glCode);
        $this->assertSame('1285', $charge->cdm);
        $this->assertSame('2026-10-20', $charge->dateOfService?->format('Y-m-d'));
        $this->assertSame('001', $charge->units);
        $this->assertSame('KNEE PAIN', $charge->clinical);
        $this->assertSame('EMER', $charge->placeOfService);
        $this->assertSame('MITC', $charge->doctorPrefix());
        $this->assertSame('73562', $charge->cpt);
        $this->assertSame('', $charge->hcpcs);
        $this->assertSame(['26', 'RT', ''], [$charge->modifier1, $charge->modifier2, $charge->modifier3]);
        $this->assertSame('OBS', $charge->observation);
        $this->assertSame('RVOC', $charge->location);
        $this->assertSame('C50.912', $charge->accession);
        $this->assertTrue($cancel->isCancel());
        $this->assertSame('73560', $cancel->cpt);
    }

    #[Test]
    public function keepsTheEmailCaseAndUppercasesTheFieldsAroundIt(): void
    {
        // The 7.0.2 module's keep-case range stayed where it was when the visit
        // number grew to eight digits, one character early: a 30-character
        // email lost the case of its last character.
        $account = self::reader()->read(
            self::account(['email' => 'jane.doe.example@example.org.x', 'guarantorZip' => '05701-123x'])
            . self::continuation()
        )[0];

        $this->assertSame('jane.doe.example@example.org.x', $account->patient->email);
        $this->assertSame('05701-123X', $account->guarantor->address->zip);
        $this->assertSame('P0123', $account->primaryInsurance->payerCode);
    }

    #[Test]
    public function readsAccountsInFileOrderWithWhitespaceBetweenRecords(): void
    {
        $file = self::account() . "\r\n" . self::continuation() . "\r\n"
            . self::charge() . "\r\n   \n" . self::charge(['cpt' => '73560']) . "\n"
            . self::account(['visit' => '87654321']) . self::continuation()
            . self::account() . self::continuation() . self::charge(['cpt' => '73564']) . "\n\n";

        $accounts = self::reader()->read($file);

        $this->assertSame(['12345678', '87654321', '12345678'], array_map(static fn($a): string => $a->visitNumber, $accounts));
        $this->assertSame(
            [['73562', '73560'], [], ['73564']],
            array_map(static fn($a): array => array_map(static fn($c): string => $c->cpt, $a->charges), $accounts)
        );
    }

    #[Test]
    public function anEmptyFileHasNoAccounts(): void
    {
        $this->assertSame([], self::reader()->read(" \r\n"));
    }

    /**
     * @return array<string, array{string, string}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function malformedFiles(): array
    {
        return [
            'starts with a charge' => [self::charge(), 'Expected a ## record at byte 0 of the charge file but found a $$ record.'],
            'starts with junk' => ['HEADER' . self::account(), 'Expected a ## record at byte 0 of the charge file but found something else.'],
            'account without ++' => [self::account() . self::charge(), 'Expected a ++ record at byte 982 of the charge file but found a $$ record.'],
            'junk after a charge' => [
                self::account() . self::continuation() . self::charge() . 'TRAILER',
                'Expected a ## record at byte 2273 of the charge file but found something else.',
            ],
            'cut-short charge' => [
                self::account() . self::continuation() . substr(self::charge(), 0, 100),
                'The $$ record at byte 2022 of the charge file is cut short (98 of 249 characters).',
            ],
        ];
    }

    #[Test]
    #[DataProvider('malformedFiles')]
    public function rejectsAMalformedFile(string $contents, string $message): void
    {
        $this->expectException(ChargeFileException::class);
        $this->expectExceptionMessage($message);
        self::reader()->read($contents);
    }

    #[Test]
    public function aMissingFileIsAnError(): void
    {
        $this->expectException(ChargeFileException::class);
        self::reader()->readFile('/nonexistent/radb_example990101.txt');
    }

    /**
     * @return array<string, array{string, ?string}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function shortDates(): array
    {
        return [
            'this century' => ['102026', '2026-10-20'],
            'today' => ['102226', '2026-10-22'],
            'tomorrow is last century' => ['102326', '1926-10-23'],
            'last century' => ['070465', '1965-07-04'],
            'leap day' => ['022924', '2024-02-29'],
            'not a leap year' => ['022925', null],
            'month 13' => ['130126', null],
            'blank' => ['      ', null],
            'not digits' => ['10/20/', null],
        ];
    }

    #[Test]
    #[DataProvider('shortDates')]
    public function readsShortDates(string $mmddyy, ?string $expected): void
    {
        $date = ShortDate::parse($mmddyy, new \DateTimeImmutable(self::TODAY));
        $this->assertSame($expected, $date?->format('Y-m-d'));
        if ($date !== null) {
            $this->assertSame('00:00:00', $date->format('H:i:s'));
        }
    }

    #[Test]
    public function aChargeWithABadDateKeepsTheRawValue(): void
    {
        $charge = self::reader()->read(self::account() . self::continuation() . self::charge(['dos' => '133126']))[0]->charges[0];

        $this->assertNull($charge->dateOfService);
        $this->assertSame('133126', $charge->dateOfServiceRaw);
    }

    /**
     * @return array<string, array{string, PersonName}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function names(): array
    {
        return [
            'last, first middle' => ['DOE, JANE Q   ', new PersonName('DOE, JANE Q', 'DOE', 'JANE', 'Q')],
            'two middle names' => ['DOE, JANE ANN MARIE', new PersonName('DOE, JANE ANN MARIE', 'DOE', 'JANE', 'ANN MARIE')],
            'no middle' => ['DOE, JANE      ', new PersonName('DOE, JANE', 'DOE', 'JANE', '')],
            'no space after comma' => ['DOE,JANE', new PersonName('DOE,JANE', 'DOE', 'JANE', '')],
            'no comma' => ['EXAMPLE CLINIC   ', new PersonName('EXAMPLE CLINIC', 'EXAMPLE CLINIC', '', '')],
            'blank' => ['     ', new PersonName('', '', '', '')],
        ];
    }

    #[Test]
    #[DataProvider('names')]
    public function parsesNames(string $field, PersonName $expected): void
    {
        $name = PersonName::parse($field);
        $this->assertEquals($expected, $name);
        $this->assertSame($expected->first !== '', $name->hasFirstAndLast());
    }
}
