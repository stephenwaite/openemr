<?php

/**
 * Isolated tests for step 3's account building: the account's address and
 * relationship, choosing the primary and secondary insurance, RRMC's
 * miscellaneous payers and the referring doctor. Lookups are in memory and the
 * biller's answers scripted; all data is invented.
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

use Cmsvt\OpenEMR\Modules\RriBilling\Account\AccountBuilder;
use Cmsvt\OpenEMR\Modules\RriBilling\Account\AccountBuildResult;
use Cmsvt\OpenEMR\Modules\RriBilling\Account\AccountRecord;
use Cmsvt\OpenEMR\Modules\RriBilling\Account\InsuranceRules;
use Cmsvt\OpenEMR\Modules\RriBilling\Account\StepThreeException;
use Cmsvt\OpenEMR\Modules\RriBilling\Account\StepThreeOptions;
use Cmsvt\OpenEMR\Modules\RriBilling\Account\StepThreePrompts;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeFileAccount;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeFileReader;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\PersonName;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\AccountFile;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\PatientInsuranceHistory;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\PayerCompany;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\PayerDirectory;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\PriorPrimary;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\ReferringDoctor;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\ReferringDoctors;
use Lcobucci\Clock\FrozenClock;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AccountBuilderTest extends TestCase
{
    private const TODAY = '2026-10-22 10:30:00';
    private const NPI = '1234567893';

    /** @var list<string> answers the biller gives, in order */
    private array $answers = [];

    /** @var list<string> every question and message shown */
    private array $shown = [];

    /** @var list<ReferringDoctor> address book */
    private array $doctors = [];

    /** @var array<string, AccountRecord> account file by actno */
    private array $accounts = [];

    /** @var array<array-key, PriorPrimary> by pubpid (a numeric MRN becomes an int key) */
    private array $history = [];

    /** @var list<string> doctors added */
    private array $added = [];

    protected function setUp(): void
    {
        $this->doctors = [new ReferringDoctor(5001, 'W00', 'WELBY', 'MARCUS', self::NPI)];
    }

    /**
     * @param array<string, string> $account ## fields
     * @param array<string, string> $continuation ++ fields
     */
    private static function file(array $account = [], array $continuation = []): ChargeFileAccount
    {
        $contents = ChargeFileFixture::accountLine($account + [
            'visit' => '10000001',
            'name' => 'DOE, JANE Q',
            'street' => '9 PATIENT RD',
            'city' => 'RUTLAND',
            'state' => 'VT',
            'zip' => '05701',
            'guarantor' => 'DOE, JOHN',
            'guarantorStreet' => '1 MAIN ST',
            'guarantorSuite' => 'APT 2',
            'guarantorCity' => 'RUTLAND',
            'guarantorState' => 'VT',
            'guarantorZip' => '05701-1234',
            'p1.code' => 'MCARE',
            'p1.cert' => '1EG4TE5MK72',
            'p1.group' => 'G1',
            'p1.subscriber' => 'DOE, JANE Q',
            'p1.gender' => 'F',
            'p1.ssn' => '000000000',
            'p1.relate' => '01',
            'p2.code' => 'MGAP',
            'p2.cert' => 'MG555',
            'p2.subscriber' => 'DOE, JANE Q',
            'p2.gender' => 'F',
            'p2.relate' => '01',
        ]) . ChargeFileFixture::continuationLine($continuation + [
            'referring' => 'WELBY, MARCUS',
            'referringNpi' => self::NPI,
            'mrn' => '12-34-56',
            'phone' => '802-555-0199',
            'gender' => 'F',
            'dob' => '070450',
        ]);
        return (new ChargeFileReader(new FrozenClock(new \DateTimeImmutable(self::TODAY))))->read($contents)[0];
    }

    private function builder(StepThreeOptions $options = new StepThreeOptions()): AccountBuilder
    {
        $test = $this;
        $payers = new class implements PayerDirectory {
            private const KEYS = ['MCARE' => '003', 'MGAP' => '062', 'BCBS' => '268', 'GHOST' => '777', 'MISC1' => '095', 'MISC2' => '095', 'ONE' => '091'];
            private const COMPANIES = [3 => 'A', 62 => 'A', 268 => 'U', 95 => 'U', 91 => '', 7 => 'A', 44 => 'A'];

            public function insuranceKeyForRrmcCode(string $rrmcCode): ?string
            {
                $code = match ($rrmcCode) {
                    '00433' => 'MISC1',
                    '00698' => 'MISC2',
                    default => $rrmcCode,
                };
                return self::KEYS[$code] ?? null;
            }

            public function companyById(int $id): ?PayerCompany
            {
                return isset(self::COMPANIES[$id]) ? new PayerCompany($id, true, self::COMPANIES[$id], 'COMPANY ' . $id) : null;
            }

            public function companyForRrmcCode(string $rrmcCode): ?PayerCompany
            {
                $key = $this->insuranceKeyForRrmcCode($rrmcCode);
                return $key === null ? null : $this->companyById((int) $key);
            }
        };
        $accounts = new class ($test) implements AccountFile {
            public function __construct(private readonly AccountBuilderTest $test)
            {
            }

            public function find(string $actno): ?AccountRecord
            {
                return $this->test->account($actno);
            }

            public function save(AccountRecord $account): void
            {
            }
        };
        $history = new class ($test) implements PatientInsuranceHistory {
            public function __construct(private readonly AccountBuilderTest $test)
            {
            }

            public function currentPrimary(string $pubpid): ?PriorPrimary
            {
                return $this->test->prior($pubpid);
            }
        };
        $doctors = new class ($test) implements ReferringDoctors {
            public function __construct(private readonly AccountBuilderTest $test)
            {
            }

            public function findByNpi(string $npi): array
            {
                return $this->test->doctorsWithNpi($npi);
            }

            public function add(PersonName $name, string $npi): ReferringDoctor
            {
                return $this->test->addDoctor($name, $npi);
            }
        };
        $prompts = new class ($test) implements StepThreePrompts {
            public function __construct(private readonly AccountBuilderTest $test)
            {
            }

            public function confirm(string $question, bool $default): bool
            {
                return in_array($this->test->answer($question), ['y', 'Y', ''], true);
            }

            public function ask(string $question): string
            {
                return $this->test->answer($question);
            }

            public function choose(string $question, array $choices): int
            {
                return (int) $this->test->answer($question . ' [' . implode(' | ', $choices) . ']');
            }

            public function notice(string $message): void
            {
                $this->test->show('notice: ' . $message);
            }

            public function warning(string $message): void
            {
                $this->test->show('warning: ' . $message);
            }
        };
        return new AccountBuilder(
            $payers,
            $accounts,
            $history,
            $doctors,
            new InsuranceRules(),
            $prompts,
            $options,
            new FrozenClock(new \DateTimeImmutable(self::TODAY))
        );
    }

    public function account(string $actno): ?AccountRecord
    {
        return $this->accounts[$actno] ?? null;
    }

    public function prior(string $pubpid): ?PriorPrimary
    {
        return $this->history[$pubpid] ?? null;
    }

    /**
     * @return list<ReferringDoctor>
     */
    public function doctorsWithNpi(string $npi): array
    {
        return array_values(array_filter($this->doctors, static fn(ReferringDoctor $doctor): bool => $doctor->npi === $npi));
    }

    public function addDoctor(PersonName $name, string $npi): ReferringDoctor
    {
        $this->added[] = $name->full . ' ' . $npi;
        $doctor = new ReferringDoctor(6000 + count($this->added), $name->last[0] . '00', $name->last, $name->first, $npi);
        $this->doctors[] = $doctor;
        return $doctor;
    }

    public function answer(string $question): string
    {
        $this->shown[] = 'ask: ' . $question;
        if ($this->answers === []) {
            self::fail('Unexpected question: ' . $question);
        }
        return array_shift($this->answers);
    }

    public function show(string $message): void
    {
        $this->shown[] = $message;
    }

    private static function built(AccountBuildResult $result): AccountRecord
    {
        self::assertNotNull($result->account);
        return $result->account;
    }

    #[Test]
    public function aPatientWithMedicareAndMedigap(): void
    {
        $result = $this->builder()->build(self::file());
        $account = self::built($result);

        $this->assertSame('00123456', $result->mrn);
        $this->assertSame('W00', $result->referringDoctor?->refkey);
        $this->assertSame(['00123456', 'DOE;JANEQ', 'DOE'], [$account->actno, $account->garname, $account->namekey]);
        $this->assertSame(['1 MAIN ST', 'APT 2', 'RUTLAND', 'VT', '05701-1234'], [$account->billadd, $account->street, $account->city, $account->state, $account->zip]);
        $this->assertSame('8025550199', $account->phone);
        $this->assertSame(['F', 'K'], [$account->sex, $account->relate]);
        // Medicare: 2050 is read as 1950.
        $this->assertSame('19500704', $account->dob);
        $this->assertSame(['003', 'A', '1EG4TE5MK72', 'G1'], [$account->prins, $account->prassign, $account->pripol->toString(), $account->prgroup]);
        // 7.0.2 splits "DOE, JANE Q" on the comma untrimmed; later steps parse it the same.
        $this->assertSame(['DOE; JANE Q', 'K', '01'], [$account->prname, $account->prrelate, $account->prrelateOriginal]);
        $this->assertSame(['062', 'A', 'MG555', 'DOE; JANE Q'], [$account->seins, $account->seassign, $account->secpol->toString(), $account->sename]);
        $this->assertSame([], $result->errors);
        $this->assertSame([], $result->insuranceErrors);
        $this->assertSame([], $this->shown);
    }

    #[Test]
    public function anUnknownHospitalCodeIsSkippedForTheNextInsurance(): void
    {
        $result = $this->builder()->build(self::file(['p1.code' => 'NOPE', 'p1.cert' => 'OLD1', 'p2.code' => 'BCBS', 'p2.cert' => 'QRS123456789']));
        $account = self::built($result);

        $this->assertSame(['268', 'QRS123456789'], [$account->prins, $account->pripol->toString()]);
        $this->assertSame('001', $account->seins, 'nothing after the chosen primary');
        $this->assertSame(['HOSP=NOPE 00123456 DOE;JANEQ BAD HOSPITAL CODE '], $result->insuranceErrors);
    }

    #[Test]
    public function aHospitalKeyWithoutACompanyIsSelfPay(): void
    {
        $result = $this->builder()->build(self::file(['p1.code' => 'GHOST']));
        $account = self::built($result);

        $this->assertSame('001', $account->prins);
        $this->assertSame(['HOSP=GHOST 00123456 DOE;JANEQ BAD INSURANCE CODE '], $result->insuranceErrors);
        $this->assertSame(['warning: 777 MISSING GHOST 00123456 DOE;JANEQ'], $this->shown);
    }

    #[Test]
    public function aBlankCertificateUsesTheSubscribersSsn(): void
    {
        $account = self::built($this->builder()->build(self::file(['p1.code' => 'BCBS', 'p1.cert' => '', 'p1.ssn' => '123456789'])));

        $this->assertSame('123456789', $account->pripol->toString());
    }

    #[Test]
    public function aSelfSubscriberIsTheGuarantor(): void
    {
        $account = self::built($this->builder()->build(self::file(['p1.subscriber' => 'SELF'])));

        $this->assertSame('DOE;JANEQ', $account->prname);
    }

    #[Test]
    public function aMissingSubscriberSexIsAskedFor(): void
    {
        $this->answers = ['x', 'm'];
        $account = self::built($this->builder()->build(self::file(['p1.gender' => ''])));

        $this->assertSame('2', $account->prrelate);
        $this->assertSame(['ask: DOE, JANE Q GENDER F OR M', 'ask: DOE, JANE Q GENDER F OR M'], $this->shown);
    }

    #[Test]
    public function aMiscellaneousPrimaryWithYesAndNoAccountIs095(): void
    {
        $account = self::built($this->builder(new StepThreeOptions(true, 95, 95))->build(self::file(['p1.code' => '00433'])));

        $this->assertSame(['095', 'U'], [$account->prins, $account->prassign]);
    }

    #[Test]
    public function aMiscellaneousPrimaryUsesThePatientsCurrentPrimary(): void
    {
        $existing = new AccountRecord();
        $existing->actno = '00123456';
        $existing->lastbill = '20260101';
        $this->accounts['00123456'] = $existing;
        $this->history['123456'] = new PriorPrimary(42, '7');
        $this->answers = ['y'];

        $result = $this->builder()->build(self::file(['p1.code' => '00433']));
        $account = self::built($result);

        $this->assertSame(['007', 'U'], [$account->prins, $account->prassign]);
        $this->assertSame('20260101', $account->lastbill, 'an existing account keeps its other columns');
        $this->assertSame($existing, $result->existing);
        $this->assertStringContainsString(' IS A MISC INS FROM RRMC FOR 00123456 USE INS 7 FROM RECENT GARNO 42? Y FOR YES', $this->shown[0]);
        $this->assertSame('notice: COMPANY 7', $this->shown[1]);
    }

    #[Test]
    public function aReplacementPrimaryGivenByTheBillerIsStored(): void
    {
        $this->answers = ['n', 'abc', '44'];
        $account = self::built($this->builder()->build(self::file(['p1.code' => '00433'])));

        $this->assertSame(['044', 'A'], [$account->prins, $account->prassign]);
        $this->assertContains('warning: BAD', $this->shown);
        $this->assertContains('notice: COMPANY 44', $this->shown);
    }

    #[Test]
    public function aReplacementSecondaryGivenByTheBillerIsStored(): void
    {
        $this->answers = ['91'];
        $account = self::built($this->builder()->build(self::file(['p2.code' => '00698'])));

        $this->assertSame(['091', ''], [$account->seins, $account->seassign]);
        $this->assertStringStartsWith('ask: MEDICARE PATIENT DOE, JANE Q', $this->shown[1]);
    }

    #[Test]
    public function anUnknownDefaultInsuranceStopsTheRun(): void
    {
        // The patient has no current primary, so the default is looked up.
        $existing = new AccountRecord();
        $existing->actno = '00123456';
        $this->accounts['00123456'] = $existing;
        $this->history['123456'] = new PriorPrimary(42, '');

        $this->expectException(StepThreeException::class);
        $this->builder(new StepThreeOptions(true, 999, 95))->build(self::file(['p1.code' => '00433']));
    }

    #[Test]
    public function aNewReferringDoctorIsAddedWithYes(): void
    {
        $result = $this->builder(new StepThreeOptions(true))->build(self::file([], ['referring' => 'CASEY, BEN', 'referringNpi' => '1999999999']));

        $this->assertSame(['CASEY, BEN 1999999999'], $this->added);
        $this->assertSame('C00', $result->referringDoctor?->refkey);
    }

    #[Test]
    public function withoutAReferringDoctorThePatientIsSkipped(): void
    {
        $result = $this->builder(new StepThreeOptions(true))->build(self::file([], ['referring' => '*CASEY, BEN', 'referringNpi' => '1999999999']));

        $this->assertNull($result->account);
        $this->assertSame([
            'Invalid provider name.  First character is not a valid alphanumeric character NPI 1999999999 PROVIDER NAME *CASEY, BEN',
            'Failed to find referring provider NPI 1999999999 PROVIDER NAME *CASEY, BEN MRN 00123456',
        ], $result->errors);
    }

    #[Test]
    public function severalDoctorsWithTheNpiAndInitialAreAskedAbout(): void
    {
        $this->doctors[] = new ReferringDoctor(5002, 'W01', 'WELBY', 'MARGARET', self::NPI);
        $this->answers = ['1'];
        $builder = $this->builder();

        $first = $builder->build(self::file([], ['referring' => 'WELBY, M']));
        $second = $builder->build(self::file([], ['referring' => 'WELBY, M']));

        $this->assertSame(['W01', 'W01'], [$first->referringDoctor?->refkey, $second->referringDoctor?->refkey]);
        $this->assertCount(1, $this->shown, 'asked once per run');
    }

    #[Test]
    public function aBlankGuarantorAddressFallsBackToThePatients(): void
    {
        $result = $this->builder(new StepThreeOptions(true))->build(self::file([
            'guarantorStreet' => '', 'guarantorSuite' => '', 'guarantorCity' => '', 'guarantorState' => '', 'guarantorZip' => '',
        ]));
        $account = self::built($result);

        $this->assertSame(['9 PATIENT RD', 'RUTLAND', 'VT', '05701'], [$account->billadd, $account->city, $account->state, $account->zip]);
        $this->assertSame([], $result->errors);
    }

    #[Test]
    public function aCityWithItsStateIsSplit(): void
    {
        $account = self::built($this->builder()->build(self::file(['guarantorCity' => 'WEST RUTLAND VT', 'guarantorState' => ''])));

        $this->assertSame(['WEST RUTLAND', 'VT'], [$account->city, $account->state]);
    }

    #[Test]
    public function anAddressWithoutAZipIsReported(): void
    {
        $this->answers = [''];
        $result = $this->builder()->build(self::file(['guarantorZip' => '']));

        $this->assertSame(['DOE;JANEQ HAS A BAD ADDRESS, MEDREC # 00123456'], $result->errors);
        $this->assertSame('warning: bad address, will be listed, any key to proceed DOE;JANEQ 00123456', $this->shown[0]);
    }

    #[Test]
    public function anUnreadableBirthDateIsZeros(): void
    {
        $result = $this->builder()->build(self::file([], ['dob' => '']));
        $account = self::built($result);

        $this->assertSame(['00000000', '000000'], [$account->dob, $result->patientDob]);
        $this->assertSame('warning: 20 BAD DATE', $this->shown[0]);
    }

    #[Test]
    public function eachPatientStartsFromANewRecord(): void
    {
        $existing = new AccountRecord();
        $existing->actno = '00123456';
        $existing->collt = '1';
        $this->accounts['00123456'] = $existing;
        $builder = $this->builder(new StepThreeOptions(true));

        $builder->build(self::file());
        $next = self::built($builder->build(self::file([], ['mrn' => '654321'])));

        $this->assertSame(['00654321', '0'], [$next->actno, $next->collt]);
    }
}
