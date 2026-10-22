<?php

/**
 * Isolated tests for steps 4 and 5: dangling credits, CDMs to review, the
 * charge checks (file245) and the relate code review. All data is invented.
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
use Cmsvt\OpenEMR\Modules\RriBilling\Account\StepThreePrompts;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\PersonName;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\AccountFile;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\ChargeMaster;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\ChargeMasterEntry;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\ReferringDoctor;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\ReferringDoctors;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\OrdfileLine;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\StoredOrdfile;
use Cmsvt\OpenEMR\Modules\RriBilling\Review\AccountFileText;
use Cmsvt\OpenEMR\Modules\RriBilling\Review\ChargeChecks;
use Cmsvt\OpenEMR\Modules\RriBilling\Review\DanglingCredits;
use Cmsvt\OpenEMR\Modules\RriBilling\Review\OrdfileReview;
use Cmsvt\OpenEMR\Modules\RriBilling\Review\RelateReview;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReviewStepsTest extends TestCase
{
    /** @var array<string, AccountRecord> */
    private array $accounts = [];

    /** @var list<string> accounts saved */
    private array $saved = [];

    private bool $proceedOnBadMrn = true;

    private static function line(string $ord8, string $ord3, string $proc, string $date, string $ind = ' ', string $ref = 'W00', string $cpt = '71046', string $mod2 = '', string $docp = '01'): OrdfileLine
    {
        return new OrdfileLine(
            $ord8,
            $ord3,
            $proc,
            $ind,
            $ref,
            '5',
            '00000000',
            new \DateTimeImmutable($date),
            '',
            '0001',
            'CLINICAL',
            $docp,
            '',
            '90000001',
            $mod2,
            '',
            '',
            '20261021',
            $cpt,
            ''
        );
    }

    private function account(string $actno, string $garname): AccountRecord
    {
        $account = new AccountRecord();
        $account->actno = $actno;
        $account->garname = $garname;
        return $this->accounts[$actno] = $account;
    }

    private function accountFile(): AccountFile
    {
        $test = $this;
        return new class ($test) implements AccountFile {
            public function __construct(private readonly ReviewStepsTest $test)
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
    }

    public function found(string $actno): ?AccountRecord
    {
        return $this->accounts[$actno] ?? null;
    }

    public function saved(AccountRecord $account): void
    {
        $this->saved[] = $account->actno . ' ' . $account->prrelate . $account->serelate;
    }

    public function proceed(): bool
    {
        return $this->proceedOnBadMrn;
    }

    private function checks(): ChargeChecks
    {
        $test = $this;
        return new ChargeChecks(
            $this->accountFile(),
            new class implements ReferringDoctors {
                public function findByNpi(string $npi): array
                {
                    return [];
                }

                public function add(PersonName $name, string $npi): ReferringDoctor
                {
                    throw new \LogicException('not expected');
                }

                public function npiForRefkey(string $refkey): ?string
                {
                    return match ($refkey) {
                        'W00' => '1900000001',
                        'N00' => '',
                        default => null,
                    };
                }
            },
            new class implements ChargeMaster {
                public function find(string $cdm, string $code): ?ChargeMasterEntry
                {
                    return null;
                }

                public function findAnyModifier(string $cdm, string $code): ?ChargeMasterEntry
                {
                    return match ($cdm) {
                        '7000' => new ChargeMasterEntry($cdm, $code, '', 2500),
                        '7002' => new ChargeMasterEntry($cdm, $code, '', 0),
                        default => null,
                    };
                }
            },
            new class ($test) implements StepThreePrompts {
                public function __construct(private readonly ReviewStepsTest $test)
                {
                }

                public function confirm(string $question, bool $default): bool
                {
                    return $this->test->proceed();
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
            }
        );
    }

    #[Test]
    public function aCancelWithItsChargeIsAPairAndWithoutOneIsDangling(): void
    {
        $this->account('00111111', 'DOE;JANE');
        $this->account('00222222', 'ROE;JOHN');
        $ordfile = new StoredOrdfile(1, '/x/ordfile-1', [
            11 => self::line('00111111', '001', '7000', '2026-10-20'),
            12 => self::line('00111111', '002', '7001', '2026-10-20', '-'),
            21 => self::line('00222222', '001', '7000', '2026-10-19'),
            22 => self::line('00222222', '002', '7000', '2026-10-19', '-'),
            23 => self::line('00222222', '003', '7000', '2026-10-20', '-'),
            24 => self::line('00222222', '004', '7002', '2026-10-20'),
            25 => self::line('00222222', '005', '7002', '2026-10-19', '-'),
            31 => self::line('00333333', '001', '7000', '2026-10-18', '-'),
        ]);

        $result = (new DanglingCredits($this->accountFile()))->check($ordfile);

        // 22 cancels 21 the same day; 23 finds 21 taken; 25 is dated before 24.
        $this->assertSame([
            '00111111000 0001 7001- 20261020 DOE;JANE                 *** DANGLING CREDIT *** ',
            '00222222001 0001 7000  20261019 ROE;JOHN                                         ',
            '00222222002 0001 7000- 20261019 ROE;JOHN                                         ',
            '00222222000 0001 7000- 20261020 ROE;JOHN                 *** DANGLING CREDIT *** ',
            '00222222000 0001 7002- 20261019 ROE;JOHN                 *** DANGLING CREDIT *** ',
        ], $result->file222);
        $this->assertSame([11, 12, 23, 24, 25, 31], array_keys($result->remaining), 'the pair is left out of the other checks');
        $this->assertSame(['00333333 NOT ON FILE???'], $result->warnings);
    }

    #[Test]
    public function reviewCdmsAndFirstCharges(): void
    {
        $lines = [
            1 => self::line('00111111', '001', '7001', '2026-10-20', '-'),
            2 => self::line('00111111', '002', '7000', '2026-10-20'),
            3 => self::line('00111111', '003', '1131', '2026-10-20'),
            4 => self::line('00222222', '001', '7000', '2026-10-19'),
        ];

        $this->assertSame([$lines[1]->toText(), $lines[3]->toText()], OrdfileReview::cdmsToReview($lines));
        $this->assertSame([2, 4], array_keys(OrdfileReview::firstChargePerAccount($lines)));
    }

    #[Test]
    public function chargeChecksReportTheOldWay(): void
    {
        $this->account('00111111', 'DOE;JANE');
        $this->account('0000000X', 'BAD;NUMBER');
        $lines = [
            1 => self::line('00111111', '001', '7000', '2026-10-20', ' ', 'Z99'),
            2 => self::line('00111111', '002', '7000', '2026-10-20', ' ', 'N00'),
            3 => self::line('00111111', '003', '7000', '2026-10-20', ' ', 'W00', '71046', '', '00'),
            4 => self::line('00111111', '004', '9999', '2026-10-20'),
            5 => self::line('00111111', '005', '7002', '2026-10-20', ' ', 'W00', 'G1004'),
            6 => self::line('0000000X', '001', '7000', '2026-10-19'),
        ];

        $file245 = $this->checks()->check($lines, OrdfileReview::firstChargePerAccount($lines));

        // Sorted on the fourth column (the referring key for doctor problems), as 7.0.2;
        // the undefined procedure's free text puts "046 We..." there, and is cut at 77.
        $this->assertSame([
            'UNDEFINED PROCEDURE FOR 00111111 9999 71046 We should ask RRMC for the cpt/hc',
            '20261019 0000000X BAD;NUMBER               BAD MED REC NUMBER     0000000X       ',
            '20261020 00111111 DOE;JANE                 N00                    NO NPI         ',
            '20261020 00111111 DOE;JANE                 SAME CHARGES?          7000/20261020ZZ',
            '20261020 00111111 DOE;JANE                 SAME CHARGES?          7000/20261020ZZ',
            '20261020 00111111 DOE;JANE                 W00                    NOT READ       ',
            '20261020 00111111 DOE;JANE                 Z99                    BAD REF        ',
        ], $file245);
    }

    #[Test]
    public function aMissingAccountIsListedOrStopsTheChecks(): void
    {
        $lines = [1 => self::line('00999999', '001', '7000', '2026-10-20'), 2 => self::line('01000000', '001', '7000', '2026-10-20')];

        $this->assertSame(
            // The ordfile line's first 36 characters cut into the columns.
            ['00999999 0017000  W0050000000020261020                                           ', '01000000 0017000  W0050000000020261020                                           '],
            $this->checks()->check($lines, $lines)
        );
        $this->proceedOnBadMrn = false;
        $this->assertSame([], $this->checks()->check($lines, $lines));
    }

    #[Test]
    public function relateCodesAreReviewedAndTwoAreCorrected(): void
    {
        $female = $this->account('00111111', 'DOE;JANE');
        $female->prins = '003';
        $female->relate = 'K';
        $female->prname = 'DOE; JANE';
        $female->prrelate = '2';
        $female->seins = '062';
        $female->sename = 'DOE; JANE';
        $female->serelate = '2';
        $male = $this->account('00222222', 'ROE;JOHN');
        $male->prins = '268';
        $male->relate = '2';
        $male->prname = 'ROE; JOHN';
        $male->prrelate = '2';
        $male->seins = '001';
        $selfPay = $this->account('00333333', 'POE;ANN');
        $selfPay->prins = '001';

        $result = (new RelateReview($this->accountFile()))->review([
            1 => self::line('00111111', '001', '7000', '2026-10-20'),
            2 => self::line('00222222', '001', '7000', '2026-10-20'),
            3 => self::line('00333333', '001', '7000', '2026-10-20'),
            4 => self::line('00444444', '001', '7000', '2026-10-20'),
        ]);

        // Her own subscriber on both: K on the primary, then the secondary.
        $this->assertSame(['00111111 K2', '00111111 KK'], $this->saved);
        $this->assertSame([['PRIMARY', '2', 'ROE; JOHN', '00222222']], $result->rows);
        $this->assertCount(2, $result->w2);
        $this->assertStringStartsWith('00111111DOE;JANE', $result->w2[0]);
        $this->assertSame([], $result->mismatches, 'names match without spaces, relate codes agree');
        $this->assertSame(['00444444'], $result->missing);
    }

    #[Test]
    public function aSubscriberWhoIsntTheGuarantorIsAMismatch(): void
    {
        $account = $this->account('00111111', 'DOE;JANE');
        $account->prins = '003';
        $account->relate = 'M';
        $account->prname = 'DOE; JOHN';
        $account->prrelate = '2';
        $account->seins = '001';

        $result = (new RelateReview($this->accountFile()))->review([1 => self::line('00111111', '001', '7000', '2026-10-20')]);

        $this->assertSame([['PRIMARY DOE;JANE M 00111111', 'PRIMARY DOE; JOHN 2']], $result->mismatches);
    }

    #[Test]
    public function theOldAccountRecordIsFixedWidth(): void
    {
        $account = new AccountRecord();
        $account->actno = '123';
        $account->garname = 'DOE;JANE';
        $account->pripol = PolicyNumber::fromString('POL1');
        $line = AccountFileText::line($account);

        $this->assertSame('00000123DOE;JANE', substr($line, 0, 16));
        // actno 8, name 24, addresses 22+22+18, state 12, zip 9, ... prgroup 10: the policy starts at 161.
        $this->assertSame(161, strpos($line, 'POL1'));
        $this->assertLessThanOrEqual(309, strlen($line));
    }
}
