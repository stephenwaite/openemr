<?php

/**
 * Isolated tests for the old billing system's insurance rules (rrmc006 P2-2,
 * P2-3) and the policy number they work on. Payer codes are the old system's;
 * all data is invented.
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
use Cmsvt\OpenEMR\Modules\RriBilling\Account\InsuranceRules;
use Cmsvt\OpenEMR\Modules\RriBilling\Account\PolicyNumber;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InsuranceRulesTest extends TestCase
{
    private static function account(
        string $prins = '',
        string $pripol = '',
        string $seins = '',
        string $secpol = '',
        string $prgroup = '',
        string $segroup = '',
        string $prassign = 'U',
        string $seassign = 'U',
    ): AccountRecord {
        $account = new AccountRecord();
        $account->garname = 'DOE;JANEQ';
        $account->relate = 'K';
        $account->dob = '20010704';
        $account->prins = $prins;
        $account->pripol = PolicyNumber::fromString($pripol);
        $account->seins = $seins;
        $account->secpol = PolicyNumber::fromString($secpol);
        $account->prgroup = $prgroup;
        $account->segroup = $segroup;
        $account->prassign = $prassign;
        $account->seassign = $seassign;
        return $account;
    }

    private static function withOffice(AccountRecord $account, string $office): AccountRecord
    {
        $account->proffice = $office;
        return $account;
    }

    private static function withSubscriber(AccountRecord $account, string $name, string $relate): AccountRecord
    {
        $account->prname = $name;
        $account->prrelate = $relate;
        return $account;
    }

    /**
     * @return list<string>
     */
    private static function apply(AccountRecord $account): array
    {
        return (new InsuranceRules())->apply($account, '123456');
    }

    #[Test]
    public function aPolicyNumberHasNineCharactersAndTheRest(): void
    {
        $policy = PolicyNumber::fromString('AB-C 123/456 789');
        $this->assertSame(['AB-C 123/', '456 789'], [$policy->part1, $policy->part2]);
        $this->assertSame(16, $policy->length());

        // Packing keeps the parts apart, as the old system did.
        $packed = $policy->packed();
        $this->assertSame(['ABC123', '456789'], [$packed->part1, $packed->part2]);
        $this->assertSame('ABC123456789', $packed->toString());
        $this->assertSame('ABC123XYZ', $packed->withPart2('XYZ')->toString());
        $this->assertTrue(PolicyNumber::none()->isEmpty());
    }

    #[Test]
    public function policiesArePackedExceptAarps(): void
    {
        $account = self::account('091', '12-34 56/78', '245', '98-76 54');
        self::apply($account);

        $this->assertSame('12345678', $account->pripol->toString());
        $this->assertSame('98-76 54', $account->secpol->toString());
    }

    #[Test]
    public function medicareWithARailroadPolicyBecomesRailroadMedicare(): void
    {
        $account = self::account('003', 'WA1234567', '003', 'A98765');
        self::apply($account);

        $this->assertSame(['028', '028'], [$account->prins, $account->seins]);

        $account = self::account('003', '1EG4TE5MK72');
        self::apply($account);
        $this->assertSame('003', $account->prins);
    }

    #[Test]
    public function medicareKeepsItsGroupOnlyWithAMedigapSecondary(): void
    {
        $alone = self::account('003', '1EG4TE5MK72', '', '', 'G1');
        self::apply($alone);
        $this->assertSame('', $alone->prgroup);

        $withMedigap = self::account('003', '1EG4TE5MK72', '062', 'M1', 'G1', 'G2');
        self::apply($withMedigap);
        $this->assertSame(['G1', 'G2'], [$withMedigap->prgroup, $withMedigap->segroup]);
    }

    #[Test]
    public function aGroupEqualToThePolicyIsCleared(): void
    {
        $account = self::account('091', 'POL123', '091', 'POL456', 'POL123', 'POL456');
        self::apply($account);

        $this->assertSame(['', ''], [$account->prgroup, $account->segroup]);
    }

    #[Test]
    public function cnaWorkCompGetsItsOffice(): void
    {
        $cna = self::account('121', 'WC1');
        self::apply($cna);
        $this->assertSame('X100', $cna->proffice);

        $other = self::withOffice(self::account('091', 'P1'), 'OLD');
        self::apply($other);
        $this->assertSame('', $other->proffice);
    }

    #[Test]
    public function medicompTakesTheMedicareSuffix(): void
    {
        $account = self::account('003', '1EG4TE5MKA', '005', '1EG4TE5MKXYZ', 'G1', '', 'A', 'A');
        self::apply($account);

        $this->assertSame('1EG4TE5MKA', $account->secpol->toString());
    }

    #[Test]
    public function otherBcbsPlansWithAnAlphaPrefixBecomeBcbsVtOrOutOfState(): void
    {
        $vermont = self::account('025', 'ZIX123456789');
        self::apply($vermont);
        $this->assertSame(['002', 'A'], [$vermont->prins, $vermont->prassign]);

        $facility = self::account('240', 'FAC123456789');
        $messages = self::apply($facility);
        $this->assertSame('002', $facility->prins);
        // A BCBS VT policy gets its V.
        $this->assertSame('FACV123456789', $facility->pripol->toString());
        $this->assertSame(['ADDING V TO 002 POLICY FOR DOE;JANEQ 123456 FAC123456789'], $messages);

        $outOfState = self::account('234', 'QRS123456789');
        self::apply($outOfState);
        $this->assertSame(['268', 'A'], [$outOfState->prins, $outOfState->prassign]);
    }

    #[Test]
    public function bcbsWithoutAnAlphaPrefixIsMiscUnassigned(): void
    {
        $account = self::account('268', '123456789012', '', '', '', '', 'A');
        self::apply($account);

        $this->assertSame(['076', 'U'], [$account->prins, $account->prassign]);
    }

    #[Test]
    public function bcbsVtWithAnotherPrefixIsOutOfState(): void
    {
        $account = self::account('002', 'QRS123456789', '', '', '', '', 'U');
        self::apply($account);

        $this->assertSame(['268', 'A'], [$account->prins, $account->prassign]);
    }

    #[Test]
    public function aYlsPolicyIsNysEmpireWithItsMiddlePart(): void
    {
        $account = self::account('268', 'YLS123456789ABC');
        self::apply($account);

        $this->assertSame('149', $account->prins);
        $this->assertSame('123456789', $account->pripol->toString());
    }

    #[Test]
    public function bcbsWithAnEmpireSecondarySwitches(): void
    {
        $account = self::account('268', 'QRS123456789', '149', 'EMP555');
        $messages = self::apply($account);

        $this->assertSame(['149', '001', 'EMP555'], [$account->prins, $account->seins, $account->pripol->toString()]);
        $this->assertSame(['SWITCHEROO FOR BCBS EMPIRE DOE;JANEQ 123456 EMP555'], $messages);
    }

    #[Test]
    public function anEmpirePlanYlsPolicyWithANysPersonalSecondarySwaps(): void
    {
        // BCBS VT from the hospital file, a YLS policy and NYS personal secondary:
        // 7.0.2 threw a TypeError here clearing the secondary policy.
        $account = self::account('002', 'YLS123456789', '116', 'NYS777', '', 'SG1');
        self::apply($account);

        $this->assertSame(['116', '001'], [$account->prins, $account->seins]);
        $this->assertSame('NYS777', $account->pripol->toString());
        $this->assertSame('SG1', $account->prgroup);
        $this->assertTrue($account->secpol->isEmpty());
        $this->assertSame('', $account->segroup);
    }

    #[Test]
    public function secondaryRulesForMiscBcbsVtAndCigna(): void
    {
        $misc = self::account('076', 'ABC123', '076');
        self::apply($misc);
        $this->assertSame('268', $misc->seins, 'misc with an alpha policy prefix: BCBS secondary');

        $hsa = self::account('076', '123456', '076');
        self::apply($hsa);
        $this->assertSame('075', $hsa->seins);

        $vermont = self::account('002', 'ZIV123456789', '002');
        self::apply($vermont);
        $this->assertSame('023', $vermont->seins);

        $cigna = self::account('003', '1EG4TE5MK72', '715', 'C1', 'G1', '', 'A', 'U');
        self::apply($cigna);
        $this->assertSame(['062', '0099001     ', 'A'], [$cigna->seins, $cigna->prgroup, $cigna->seassign]);
    }

    #[Test]
    public function theSamePayerTwiceWhenAssignedMovesTheSecondary(): void
    {
        $account = self::account('091', 'P1', '091', 'P2', '', '', 'A');
        $messages = self::apply($account);

        $this->assertSame('020', $account->seins);
        $this->assertSame(['PRI AND SEC SAME INS DOE;JANEQ 123456 P1'], $messages);
    }

    #[Test]
    public function payerCodesCompareAsNumbers(): void
    {
        // A code stored without its zeros is still Medicare.
        $account = self::account('3', 'WA1234567');
        self::apply($account);

        $this->assertSame('028', $account->prins);
    }

    #[Test]
    public function unknownPoliciesAndGroupsAreCleared(): void
    {
        $account = self::account('091', 'UNK', '092', 'X', ' NA ', 'X');
        self::apply($account);

        $this->assertTrue($account->pripol->isEmpty());
        $this->assertTrue($account->secpol->isEmpty());
        $this->assertSame(['', ''], [$account->prgroup, $account->segroup]);
    }

    #[Test]
    public function medicaidUsesThePatientAndMedicareTheLastCentury(): void
    {
        $medicaid = self::withSubscriber(self::account('004', 'M1', '004', 'M2'), 'SUB;ONE', '2');
        self::apply($medicaid);
        $this->assertSame(['DOE;JANEQ', 'K', 'DOE;JANEQ', 'K'], [$medicaid->prname, $medicaid->prrelate, $medicaid->sename, $medicaid->serelate]);
        $this->assertSame('20010704', $medicaid->dob);

        $medicare = self::account('003', '1EG4TE5MK72');
        self::apply($medicare);
        $this->assertSame('19010704', $medicare->dob);
    }

    #[Test]
    public function theAccountTakesTheMrnAndANameKey(): void
    {
        $account = self::account('091', 'P1');
        self::apply($account);

        $this->assertSame(['123456', 'DOE'], [$account->actno, $account->namekey]);
    }
}
