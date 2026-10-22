<?php

/**
 * Isolated tests for step 3's ordfile lines, work comp records, email and
 * authorization records, and RRMC's referring doctor keys. All data is invented.
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
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeFileAccount;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeFileReader;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\RefKeySequence;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\EmailAuthRecord;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\OrdfileBuilder;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\OrdfileCharge;
use Lcobucci\Clock\FrozenClock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OrdfileBuilderTest extends TestCase
{
    private const TODAY = '2026-10-22 10:30:00';

    /**
     * @param list<array<string, string>> $charges
     * @param array<string, string> $account
     * @param array<string, string> $continuation
     */
    private static function file(array $charges, array $account = [], array $continuation = []): ChargeFileAccount
    {
        $contents = ChargeFileFixture::accountLine($account + [
            'visit' => '10000001',
            'name' => 'DOE, JANE',
            'guarantor' => 'DOE, JOHN',
            'admitDate' => '10/19/2026',
            'p1.code' => 'P0001',
            'p1.name' => 'EXAMPLE COMP',
            'p2.code' => 'P0002',
            'p2.name' => 'SECOND COMP',
            'email' => 'jane@example.org',
            'authorization' => 'AUTH77',
        ]) . ChargeFileFixture::continuationLine($continuation + [
            'mrn' => '123456',
            'diagnosis' => 'ABDOMINAL PAIN, LOWER RIGHT QUADRANT',
            'p3.code' => 'P0003',
            'p3.name' => 'THIRD COMP',
            'guarantorSsn' => '000000000',
        ]);
        foreach ($charges as $charge) {
            $contents .= ChargeFileFixture::chargeLine($charge + [
                'cdm' => '7000',
                'dos' => '102026',
                'clinical' => 'A CLINICAL NOTE LONGER THAN THIRTY EIGHT CHARACTERS',
                'cpt' => '71046',
                'accession' => 'C50.912',
            ]);
        }
        return (new ChargeFileReader(new FrozenClock(new \DateTimeImmutable(self::TODAY))))->read($contents)[0];
    }

    private static function account(string $prins = '003', string $seins = '001'): AccountRecord
    {
        $account = new AccountRecord();
        $account->actno = '00123456';
        $account->garname = 'DOE;JANE';
        $account->prins = $prins;
        $account->seins = $seins;
        return $account;
    }

    private static function line(OrdfileBuilder $builder, ChargeFileAccount $file, int $charge, string $refkey = 'W00', ?AccountRecord $account = null): OrdfileCharge
    {
        return $builder->charge(
            $file,
            $account ?? self::account(),
            $file->charges[$charge],
            $refkey,
            OrdfileBuilder::accidentDate($file),
            new \DateTimeImmutable('2026-10-21'),
            new \DateTimeImmutable(self::TODAY)
        );
    }

    #[Test]
    public function aChargeBecomesAnOrdfileLine(): void
    {
        $file = self::file([['mod1' => 'RT', 'mod2' => '59']]);
        $result = self::line(new OrdfileBuilder('01'), $file, 0);
        $line = $result->line;

        $this->assertNotNull($line);
        $this->assertSame(['00123456', '001', '7000', ' ', 'W00', '5'], [$line->ord8, $line->ord3, $line->proc, $line->ind, $line->ref, $line->iopat]);
        $this->assertSame(['00000000', '2026-10-20', '20261019', '0001'], [$line->dateA, $line->dateT->format('Y-m-d'), $line->dateAdmit, $line->order]);
        $this->assertSame('A CLINICAL NOTE LONGER THAN THIRTY EIG', $line->clinical);
        $this->assertSame(['01', 'ABDOMINAL PAIN, L', '10000001'], [$line->docp, $line->admitDiag, $line->visitNo]);
        $this->assertSame(['RT', '59', '', '20261021', '71046', 'C50.912'], [$line->mod2, $line->mod3, $line->mod4, $line->dateE, $line->cpt, $line->accession]);
        $this->assertSame([], $result->workComp);
        $this->assertSame([], $result->errors);
        $this->assertSame(
            '00123456001' . '7000' . ' ' . 'W00' . '5' . '00000000' . '20261020' . '20261019' . '0001'
                . 'A CLINICAL NOTE LONGER THAN THIRTY EIG' . '01' . 'ABDOMINAL PAIN, L' . '10000001'
                . 'RT' . '59' . '  ' . '20261021' . '71046',
            $line->toText()
        );
    }

    #[Test]
    public function eachChargeOfAnAccountGetsTheNextSequence(): void
    {
        $file = self::file([[], [], ['indicator' => 'CAN']]);
        $builder = new OrdfileBuilder('01');

        $sequences = [];
        foreach ([0, 1, 2] as $charge) {
            $sequences[] = self::line($builder, $file, $charge)->line?->ord3;
        }

        $this->assertSame(['001', '002', '003'], $sequences);
        $this->assertSame('-', self::line($builder, $file, 2)->line?->ind);
    }

    #[Test]
    public function placeOfServiceComesFromRrmcAndEdReferrals(): void
    {
        $file = self::file([['place' => 'EMER'], ['place' => 'INPT'], ['place' => 'OUTP'], ['observation' => 'OBSERV']], ['admitDate' => '']);
        $builder = new OrdfileBuilder('01');

        $this->assertSame('E', self::line($builder, $file, 0)->line?->iopat);
        $inpatient = self::line($builder, $file, 1);
        $this->assertSame('3', $inpatient->line?->iopat);
        $this->assertSame(['DOE, JOHN WAS SENT AS INPT WITH NO ADMIT DATE THIS MUST BE FIXED OR CLAIM FILE WILL REJECT.'], $inpatient->errors);
        $edReferral = self::line($builder, $file, 2, 'B51');
        $this->assertSame('E', $edReferral->line?->iopat);
        $this->assertSame(['DOE, JOHN WAS REFERRED FROM ED BY B51 CHANGING POS TO E'], $edReferral->errors);
        $this->assertSame(
            ['DOE, JOHN WAS UNDER OBSERVATION, USING POS OUTP FOR CPT 71046 AND DATE 102026'],
            self::line($builder, $file, 3)->errors
        );
    }

    #[Test]
    public function theHcpcsIsBilledWithoutACptAndNothingWithoutEither(): void
    {
        $file = self::file([['cpt' => '', 'hcpcs' => 'G0279'], ['cpt' => '', 'hcpcs' => '']]);
        $builder = new OrdfileBuilder('01');

        $this->assertSame('G0279', self::line($builder, $file, 0)->line?->cpt);
        $none = self::line($builder, $file, 1);
        $this->assertNull($none->line);
        $this->assertSame(['DOE, JOHN HAD NO CPT  AND NO HCPCS TO BILL WITH . Call somebody'], $none->errors);
    }

    #[Test]
    public function anAccidentDateAndADateOfServiceThatIsntADate(): void
    {
        $file = self::file([['dos' => '133126']], [], ['accidentDate' => '031598']);

        $this->assertSame('19980315', OrdfileBuilder::accidentDate($file));
        $this->assertSame('2026-10-22', self::line(new OrdfileBuilder('01'), $file, 0)->line?->dateT->format('Y-m-d'), 'today, as 7.0.2');
    }

    #[Test]
    public function workCompInsurersAreRecorded(): void
    {
        $file = self::file([[]]);
        $builder = new OrdfileBuilder('01');

        $primary = self::line($builder, $file, 0, 'W00', self::account('095', '001'));
        $this->assertSame(['00123456202610207000'], array_map(static fn($record): string => $record->key(), $primary->workComp));
        $this->assertSame('EXAMPLE COMP', $primary->workComp[0]->insurance->payerName);

        $secondary = self::line(new OrdfileBuilder('01'), $file, 0, 'W00', self::account('003', '076'));
        $this->assertSame('SECOND COMP', $secondary->workComp[0]->insurance->payerName);

        // A secondary without a name falls to the tertiary (7.0.2 stopped with an Error here).
        $unnamed = self::file([[]], ['p2.name' => '']);
        $tertiary = self::line(new OrdfileBuilder('01'), $unnamed, 0, 'W00', self::account('003', '076'));
        $this->assertSame('THIRD COMP', $tertiary->workComp[0]->insurance->payerName);

        $blank = self::file([[]], ['p1.code' => '00931', 'p1.name' => '']);
        $this->assertSame(['DOE;JANE BLANK WORK COMP1 ADDRESS 003 001'], self::line(new OrdfileBuilder('01'), $blank, 0)->errors);
    }

    #[Test]
    public function anEmailOrAuthorizationIsKept(): void
    {
        $file = self::file([[]]);
        $record = EmailAuthRecord::forAccount($file, self::line(new OrdfileBuilder('01'), $file, 0)->line);

        $this->assertNotNull($record);
        $this->assertSame(['10000001', 'DOE, JOHN', '000000000', 'jane@example.org', 'AUTH77', '20261020'], [
            $record->medrec, $record->name, $record->ssn, $record->email, $record->authorization, $record->date,
        ]);
        $this->assertNull(EmailAuthRecord::forAccount(self::file([], ['email' => '', 'authorization' => '']), null));
        $this->assertNotNull(EmailAuthRecord::forAccount(self::file([], ['email' => '', 'authorization' => ''], ['guarantorSsn' => '123456789']), null));
    }

    /**
     * @return array<string, array{?string, string}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function refKeys(): array
    {
        return [
            'first for the letter' => [null, 'W00'],
            'next digit' => ['W00', 'W01'],
            'after 9' => ['W09', 'W0A'],
            'next letter' => ['W2I', 'W2J'],
            'after Z' => ['W0Z', 'W10'],
            'middle after 9' => ['W9Z', 'WA0'],
        ];
    }

    #[Test]
    #[DataProvider('refKeys')]
    public function referringDoctorKeysCountDigitsThenLetters(?string $last, string $next): void
    {
        $this->assertSame($next, RefKeySequence::next('W', $last));
    }

    #[Test]
    public function aLetterRunsOutOfKeys(): void
    {
        $this->expectException(\OverflowException::class);
        RefKeySequence::next('W', 'WZZ');
    }
}
