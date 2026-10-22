<?php

/**
 * Isolated tests for a step 3 run over a charge file: what is saved, resuming
 * at a patient, and a dry run saving nothing. All data is invented.
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
use Cmsvt\OpenEMR\Modules\RriBilling\Account\AccountRecord;
use Cmsvt\OpenEMR\Modules\RriBilling\Account\InsuranceRules;
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
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\EmailAuthRecord;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\OrdfileBuilder;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\OrdfileLine;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\StepThreeFiles;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\WorkCompRecord;
use Cmsvt\OpenEMR\Modules\RriBilling\Patient\PatientUpdates;
use Cmsvt\OpenEMR\Modules\RriBilling\Step\StepThree;
use Lcobucci\Clock\FrozenClock;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StepThreeTest extends TestCase
{
    private const TODAY = '2026-10-22 10:30:00';

    /** @var list<string> what the run saved, in order */
    private array $saved = [];

    /**
     * @return list<ChargeFileAccount>
     */
    private static function accounts(): array
    {
        $contents = '';
        foreach ([['111111', 'DOE, JANE'], ['222222', 'ROE, JOHN'], ['333333', 'POE, ANN']] as [$mrn, $name]) {
            $contents .= ChargeFileFixture::accountLine([
                'visit' => '9' . $mrn . '0',
                'name' => $name,
                'guarantorStreet' => '1 MAIN ST',
                'guarantorCity' => 'RUTLAND',
                'guarantorState' => 'VT',
                'guarantorZip' => '05701',
                'email' => strtolower(substr($name, 0, 3)) . '@example.org',
                'p1.code' => '00010',
                'p1.cert' => 'POL' . $mrn,
                'p1.gender' => 'F',
            ]) . ChargeFileFixture::continuationLine([
                'mrn' => $mrn,
                'referring' => 'WELBY, MARCUS',
                'referringNpi' => '1900000001',
                'dob' => '070450',
            ]) . ChargeFileFixture::chargeLine(['cdm' => '7000', 'cpt' => '71046', 'dos' => '102026']) . "\n";
        }
        return (new ChargeFileReader(new FrozenClock(new \DateTimeImmutable(self::TODAY))))->read($contents);
    }

    private function step(bool $dryRun): StepThree
    {
        $test = $this;
        $clock = new FrozenClock(new \DateTimeImmutable(self::TODAY));
        $accountFile = new class ($test) implements AccountFile {
            public function __construct(private readonly StepThreeTest $test)
            {
            }

            public function find(string $actno): ?AccountRecord
            {
                return null;
            }

            public function save(AccountRecord $account): void
            {
                $this->test->record('account ' . $account->actno);
            }
        };
        $builder = new AccountBuilder(
            new class implements PayerDirectory {
                public function insuranceKeyForRrmcCode(string $rrmcCode): ?string
                {
                    return $rrmcCode === '00010' ? '003' : null;
                }

                public function companyById(int $id): ?PayerCompany
                {
                    return $id === 3 ? new PayerCompany(3, true, 'A', 'MEDICARE') : null;
                }

                public function companyForRrmcCode(string $rrmcCode): ?PayerCompany
                {
                    return $rrmcCode === '00010' ? $this->companyById(3) : null;
                }
            },
            $accountFile,
            new class implements PatientInsuranceHistory {
                public function currentPrimary(string $pubpid): ?PriorPrimary
                {
                    return null;
                }
            },
            new class implements ReferringDoctors {
                public function findByNpi(string $npi): array
                {
                    return [new ReferringDoctor(9, 'W00', 'WELBY', 'MARCUS', $npi)];
                }

                public function npiForRefkey(string $refkey): ?string
                {
                    return null;
                }

                public function add(PersonName $name, string $npi): ReferringDoctor
                {
                    throw new \LogicException('not expected');
                }
            },
            new InsuranceRules(),
            new class implements StepThreePrompts {
                public function confirm(string $question, bool $default): bool
                {
                    return $default;
                }

                public function ask(string $question): string
                {
                    return '';
                }

                public function choose(string $question, array $choices): int
                {
                    return 0;
                }

                public function notice(string $message): void
                {
                }

                public function warning(string $message): void
                {
                }
            },
            new StepThreeOptions(true),
            $clock
        );
        $files = new class ($test) implements StepThreeFiles {
            public function __construct(private readonly StepThreeTest $test)
            {
            }

            public function createOrdfile(string $fileName): int
            {
                return 1;
            }

            public function saveOrdfileLine(int $ordfileId, OrdfileLine $line): int
            {
                return 1;
            }

            public function saveWorkComp(WorkCompRecord $record): void
            {
                $this->test->record('work comp ' . $record->medrec);
            }

            public function saveEmailAuth(EmailAuthRecord $record): void
            {
                $this->test->record('email ' . $record->medrec);
            }
        };
        $patients = new class ($test) implements PatientUpdates {
            public function __construct(private readonly StepThreeTest $test)
            {
            }

            public function update(ChargeFileAccount $file, AccountRecord $account, string $mrn, string $patientDob, string $patientSex, ReferringDoctor $doctor): array
            {
                $this->test->record('patient ' . $mrn);
                return $mrn === '00222222' ? ['Failed to save patient for MRN 00222222: system error'] : [];
            }
        };
        return new StepThree($builder, $accountFile, new OrdfileBuilder('01'), $files, $patients, $clock, $dryRun);
    }

    public function record(string $what): void
    {
        $this->saved[] = $what;
    }

    #[Test]
    public function eachAccountIsSavedWithItsLinesAndPatient(): void
    {
        $advanced = 0;
        $result = $this->step(false)->run(self::accounts(), new \DateTimeImmutable('2026-10-21'), null, static function () use (&$advanced): void {
            $advanced++;
        });

        $this->assertSame([
            'account 00111111', 'email 91111110', 'patient 00111111',
            'account 00222222', 'email 92222220', 'patient 00222222',
            'account 00333333', 'email 93333330', 'patient 00333333',
        ], $this->saved);
        $this->assertSame(3, $result->patients);
        $this->assertSame(3, $advanced);
        $this->assertSame(['00111111001', '00222222001', '00333333001'], array_map(static fn(OrdfileLine $line): string => $line->key(), $result->sortedLines()));
        $this->assertSame(['Failed to save patient for MRN 00222222: system error'], $result->errors);
        $this->assertSame([], $result->insuranceErrors);
    }

    #[Test]
    public function aRunCanResumeAtAPatient(): void
    {
        $result = $this->step(false)->run(self::accounts(), new \DateTimeImmutable('2026-10-21'), '222222');

        $this->assertSame(2, $result->patients);
        $this->assertSame('account 00222222', $this->saved[0]);
    }

    #[Test]
    public function aDryRunSavesNothing(): void
    {
        $result = $this->step(true)->run(self::accounts(), new \DateTimeImmutable('2026-10-21'));

        $this->assertSame([], $this->saved);
        $this->assertCount(3, $result->lines);
        $this->assertSame(3, $result->patients);
    }
}
