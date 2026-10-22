<?php

/**
 * Isolated tests for step 6: what is billed (not pairs, not cancels, not what
 * a previous run billed), the place of service, facility and provider of each
 * charge, what goes to errfile240, and the Medigap secondaries. All data is
 * invented.
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

use Cmsvt\OpenEMR\Modules\RriBilling\Account\AccountRecord;
use Cmsvt\OpenEMR\Modules\RriBilling\Account\PolicyNumber;
use Cmsvt\OpenEMR\Modules\RriBilling\Billing\BillingCharge;
use Cmsvt\OpenEMR\Modules\RriBilling\Billing\BillingException;
use Cmsvt\OpenEMR\Modules\RriBilling\Billing\BillingPatient;
use Cmsvt\OpenEMR\Modules\RriBilling\Billing\ChargeBilling;
use Cmsvt\OpenEMR\Modules\RriBilling\Billing\Medigap;
use Cmsvt\OpenEMR\Modules\RriBilling\Billing\PlaceOfService;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\AccountFile;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\OrdfileLine;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\StoredOrdfile;
use Cmsvt\OpenEMR\Modules\RriBilling\Step\StepSix;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StepSixTest extends TestCase
{
    /** @var array<string, AccountRecord> */
    private array $accounts = [];

    /** @var list<string> accounts saved */
    private array $saved = [];

    /** @var array<string, BillingPatient> by account number */
    private array $patients = [];

    /** @var array<int, true> ordfile lines billed by an earlier run */
    private array $billedBefore = [];

    /** @var list<BillingCharge> */
    private array $billed = [];

    /** @var array<int, string> ordfile lines OpenEMR refuses, and why */
    private array $refused = [];

    private static function line(string $ord8, string $ord3, string $proc, string $date, string $ind = ' ', string $iopat = '5', string $docp = '01'): OrdfileLine
    {
        return new OrdfileLine(
            $ord8,
            $ord3,
            $proc,
            $ind,
            'W00',
            $iopat,
            '00000000',
            new \DateTimeImmutable($date),
            '20261019',
            '0001',
            'CLINICAL',
            $docp,
            '',
            '90000001',
            '',
            '',
            '',
            '20261021',
            '71046',
            'R07.9'
        );
    }

    private function account(string $actno, string $prins = '002', string $seins = '001', string $segroup = ''): AccountRecord
    {
        $account = new AccountRecord();
        $account->actno = $actno;
        $account->prins = $prins;
        $account->seins = $seins;
        $account->segroup = $segroup;
        return $this->accounts[$actno] = $account;
    }

    private function patient(string $actno, int $pid): void
    {
        $this->patients[$actno] = new BillingPatient($pid, 'uuid-' . $pid, null);
    }

    public function found(string $actno): ?AccountRecord
    {
        return $this->accounts[$actno] ?? null;
    }

    public function saved(AccountRecord $account): void
    {
        $this->saved[] = $account->actno;
    }

    public function patientFor(string $actno): ?BillingPatient
    {
        return $this->patients[$actno] ?? null;
    }

    public function billedBefore(int $recordId): bool
    {
        return isset($this->billedBefore[$recordId]);
    }

    public function bill(BillingCharge $charge): int
    {
        if (isset($this->refused[$charge->recordId])) {
            throw new BillingException($this->refused[$charge->recordId]);
        }
        $this->billed[] = $charge;
        return count($this->billed);
    }

    private function step(): StepSix
    {
        $test = $this;
        $accounts = new class ($test) implements AccountFile {
            public function __construct(private readonly StepSixTest $test)
            {
            }

            public function find(string $actno): ?AccountRecord
            {
                return $this->test->found($actno);
            }

            public function save(AccountRecord $account): void
            {
                $this->test->saved($account);
            }
        };
        $billing = new class ($test) implements ChargeBilling {
            public function __construct(private readonly StepSixTest $test)
            {
            }

            public function findPatient(string $actno): ?BillingPatient
            {
                return $this->test->patientFor($actno);
            }

            public function isBilled(int $recordId): bool
            {
                return $this->test->billedBefore($recordId);
            }

            public function bill(BillingCharge $charge): int
            {
                return $this->test->bill($charge);
            }
        };
        $facility = static fn(PlaceOfService $place): int => $place === PlaceOfService::InpatientHospital ? 5 : 4;
        return new StepSix($accounts, $billing, '01', 5000, $facility);
    }

    /**
     * @return list<array{int, int, int, int, ?int}> record id, pid, place of service, facility, provider
     */
    private function billedCharges(): array
    {
        return array_map(
            static fn(BillingCharge $charge): array => [$charge->recordId, $charge->patient->pid, $charge->place->value, $charge->facilityId, $charge->providerId],
            $this->billed
        );
    }

    #[Test]
    public function chargesAreBilledButNotPairsCancelsOrWhatWasBilledBefore(): void
    {
        $this->account('00111111');
        $this->patient('00111111', 11);
        $this->account('00222222');
        $this->patient('00222222', 22);
        $this->account('00444444');
        $ordfile = new StoredOrdfile(1, 'ordfile-1', [
            11 => self::line('00111111', '001', '7000', '2026-10-20'),
            12 => self::line('00111111', '002', '7000', '2026-10-20', '-'),
            13 => self::line('00111111', '003', '7001', '2026-10-20', ' ', 'E'),
            14 => self::line('00111111', '004', '7002', '2026-10-20', '-'),
            21 => self::line('00222222', '001', '7000', '2026-10-19', ' ', '3', '02'),
            22 => self::line('00222222', '002', '7001', '2026-10-19'),
            31 => self::line('00333333', '001', '7000', '2026-10-19'),
            41 => self::line('00444444', '001', '7000', '2026-10-19'),
        ]);
        $this->billedBefore = [22 => true];
        $asked = [];

        $result = $this->step()->run($ordfile, static function (OrdfileLine $line) use (&$asked): PlaceOfService {
            $asked[] = $line->ord3;
            return PlaceOfService::OutpatientHospital;
        });

        $this->assertSame([[13, 11, 23, 4, 5000], [21, 22, 21, 5, null]], $this->billedCharges());
        $this->assertSame([
            '00111111004 7002 71046 20261020 CANCEL WITHOUT ITS CHARGE, NOT BILLED',
            '00333333001 7000 71046 20261019 NOT IN THE ACCOUNT FILE',
            '00444444001 7000 71046 20261019 PATIENT NOT FOUND',
        ], $result->errors);
        $this->assertSame([2, 1, 2], [$result->billed, $result->billedBefore, $result->paired]);
        $this->assertSame([], $asked);
        $this->assertSame([], $result->file242);
    }

    #[Test]
    public function anUnknownPlaceOfServiceIsAskedAndARefusalIsReported(): void
    {
        $this->account('00111111');
        $this->patient('00111111', 11);
        $ordfile = new StoredOrdfile(1, 'ordfile-1', [
            11 => self::line('00111111', '001', '7000', '2026-10-20', ' ', 'X'),
            12 => self::line('00111111', '002', '7001', '2026-10-20'),
        ]);
        $this->refused = [12 => 'ENCOUNTER NOT CREATED'];

        $result = $this->step()->run($ordfile, static fn(OrdfileLine $line): PlaceOfService => PlaceOfService::InpatientHospital);

        $this->assertSame([[11, 11, 21, 5, 5000]], $this->billedCharges());
        $this->assertSame(['00111111002 7001 71046 20261020 ENCOUNTER NOT CREATED'], $result->errors);
    }

    #[Test]
    public function aMedicarePatientsMedigapSecondaryIsSetOncePerAccount(): void
    {
        $account = $this->account('00111111', '003', '025', 'GRP1');
        $account->secpol = PolicyNumber::fromString('MG555');
        $account->segrpname = 'SYNTHETIC PLAN';
        $this->account('00222222', '003', '001');
        $ordfile = new StoredOrdfile(1, 'ordfile-1', [
            11 => self::line('00111111', '001', '7000', '2026-10-20'),
            12 => self::line('00111111', '002', '7001', '2026-10-20'),
            21 => self::line('00222222', '001', '7000', '2026-10-19'),
        ]);

        $result = $this->step()->run($ordfile, static fn(OrdfileLine $line): PlaceOfService => PlaceOfService::OutpatientHospital);

        $this->assertSame(['00111111  062  MG555           GRP1          SYNTHETIC PLAN  '], $result->file242);
        $this->assertSame(['00111111'], $this->saved);
        $this->assertSame(['0005034', '062', 'A'], [$account->prgroup, $account->seins, $account->seassign]);
    }

    /**
     * @return array<string, array{string, string, string, ?string}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function medigapProvider(): array
    {
        return [
            'Medicare with a Medigap plan' => ['003', '025', '', '0005034'],
            'codes compare as numbers' => ['3', '25', '', '0005034'],
            '108 with its group' => ['003', '108', '38278', '0005037'],
            '108 with another group' => ['003', '108', '12345', null],
            'Medicaid secondary' => ['003', '004', '', null],
            'a plan not on the list' => ['003', '062', '', null],
            'not Medicare' => ['002', '025', '', null],
        ];
    }

    #[Test]
    #[DataProvider('medigapProvider')]
    public function theMedigapIdComesFromTheSecondary(string $prins, string $seins, string $segroup, ?string $expected): void
    {
        $this->assertSame($expected, Medigap::idFor($this->account('00111111', $prins, $seins, $segroup)));
    }

    #[Test]
    public function aChargeKeepsItsModifiersAndDiagnosis(): void
    {
        $line = new OrdfileLine('00111111', '001', '1285', ' ', 'W00', '5', '00000000', new \DateTimeImmutable('2026-10-20'), '', '0001', '', '01', '', '90000001', 'RT', '', '59', '', '73562', ' M17.11 ');
        $charge = new BillingCharge(1, $line, new BillingPatient(1, 'uuid-1', null), PlaceOfService::OutpatientHospital, 4, 5000);

        $this->assertSame(['RT:59', 'M17.11'], [$charge->modifiers(), $charge->diagnosis()]);
    }
}
