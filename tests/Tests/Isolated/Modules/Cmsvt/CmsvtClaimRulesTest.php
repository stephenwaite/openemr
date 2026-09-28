<?php

/**
 * Isolated tests for the CMS 837P claim rules.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace {
    $cmsvtModuleSrc = dirname(__DIR__, 5) . '/interface/modules/custom_modules/oe-module-cmsvt/src/';
    if (!is_dir($cmsvtModuleSrc)) {
        throw new RuntimeException('oe-module-cmsvt source not found at ' . $cmsvtModuleSrc);
    }
    spl_autoload_register(static function (string $class) use ($cmsvtModuleSrc): void {
        $prefix = 'Cmsvt\\OpenEMR\\Modules\\Customizations\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $file = $cmsvtModuleSrc . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    });
}

namespace OpenEMR\Tests\Isolated\Modules\Cmsvt {

    use Cmsvt\OpenEMR\Modules\Customizations\Billing\CmsvtClaimRules;
    use OpenEMR\Billing\Claim;
    use PHPUnit\Framework\Attributes\DataProvider;
    use PHPUnit\Framework\Attributes\Test;
    use PHPUnit\Framework\TestCase;

    final class CmsvtClaimRulesTest extends TestCase
    {
        private const FOOT_CARE_NPI = '1999999984';

        /**
         * @param array<string, mixed> $spec payer data by index, lines, NPIs and so on
         */
        private function claim(array $spec): Claim
        {
            $byIns = static fn(string $key): \Closure => static fn(int $ins = 0): string => self::at($spec, $key, $ins);
            $lines = [];
            $rawLines = $spec['lines'] ?? [];
            foreach (is_array($rawLines) ? $rawLines : [] as $line) {
                $lines[] = is_array($line)
                    ? [is_string($line[0] ?? null) ? $line[0] : '', is_string($line[1] ?? null) ? $line[1] : '']
                    : ['', ''];
            }
            $line = static fn(int $k, int $part): string => $lines[$k][$part] ?? '';
            $claim = $this->createStub(Claim::class);
            $claim->method('payerID')->willReturnCallback($byIns('payerIds'));
            $claim->method('claimType')->willReturnCallback($byIns('claimTypes'));
            $claim->method('payerSequence')->willReturnCallback($byIns('sequences'));
            $claim->method('payerName')->willReturnCallback($byIns('payerNames'));
            $claim->method('policyNumber')->willReturnCallback($byIns('policies'));
            $claim->method('groupNumber')->willReturnCallback($byIns('groups'));
            $claim->method('procCount')->willReturn(count($lines));
            $claim->method('cptCode')->willReturnCallback(static fn(int $k): string => $line($k, 0));
            $claim->method('cptModifier')->willReturnCallback(static fn(int $k): string => $line($k, 1));
            $claim->method('cptKey')->willReturnCallback(
                static fn(int $k): string => $line($k, 0) . ($line($k, 1) !== '' ? ':' . $line($k, 1) : '')
            );
            $claim->method('diagArray')->willReturn($spec['diags'] ?? []);
            $claim->method('billingFacilityNPI')->willReturn($spec['billingNpi'] ?? '1111111111');
            $claim->method('facilityNPI')->willReturn($spec['facilityNpi'] ?? '1111111111');
            $claim->method('facilityPOS')->willReturn($spec['pos'] ?? '11');
            $claim->method('priorAuth')->willReturn($spec['priorAuth'] ?? '');
            $claim->method('x12_sender_id')->willReturn('CMSTEST        ');
            $claim->method('payerTotals')->willReturn($spec['primaryTotals'] ?? ['', '0.00', '0.00']);
            return $claim;
        }

        /**
         * @param array<string, mixed> $spec
         */
        private static function at(array $spec, string $key, int $ins): string
        {
            $values = $spec[$key] ?? [];
            $value = is_array($values) ? ($values[$ins] ?? '') : '';
            return is_string($value) ? $value : '';
        }

        private function rules(): CmsvtClaimRules
        {
            return new CmsvtClaimRules([self::FOOT_CARE_NPI], ['87726', 'TREST'], ['1999999992']);
        }

        /**
         * @return array<string, array{array<string, mixed>, string}>
         *
         * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
         */
        public static function medicaidCarrierCodeProvider(): array
        {
            return [
                'Medicare' => [['payerIds' => ['MCDVT', '14512'], 'claimTypes' => ['MC', 'MB']], 'MDB'],
                'BCBS NJ' => [['payerIds' => ['MCDVT', 'BCBSVT'], 'payerNames' => ['', 'BCBS NJ']], 'H6'],
                'BCBS VT V4BV' => [['payerIds' => ['MCDVT', 'BCSVT'], 'policies' => ['', 'V4BV123']], 'MDB'],
                'BCBS VT PEX' => [['payerIds' => ['MCDVT', 'BCBSVT'], 'policies' => ['', 'PEX99']], 'BV'],
                'BCBS VT other' => [['payerIds' => ['MCDVT', 'BCBSVT'], 'policies' => ['', 'ZZZ1']], 'EE'],
                'mapped payer' => [['payerIds' => ['MCDVT', '62308']], 'FB6'],
                '60054 with group' => [['payerIds' => ['MCDVT', '60054'], 'groups' => ['G77', '']], 'G77'],
                '60054 without group' => [['payerIds' => ['MCDVT', '60054']], '92'],
                'group fallback' => [['payerIds' => ['MCDVT', 'OTHER'], 'groups' => ['', 'ABCDEF']], 'ABC'],
            ];
        }

        /**
         * @param array<string, mixed> $spec
         */
        #[Test]
        #[DataProvider('medicaidCarrierCodeProvider')]
        public function medicaidGetsCarrierCodes(array $spec, string $expected): void
        {
            $this->assertSame($expected, $this->rules()->otherPayerIdentifier($this->claim($spec), 1));
        }

        #[Test]
        public function missingCarrierCodeIsWarned(): void
        {
            $rules = $this->rules();
            $claim = $this->claim(['payerIds' => ['MCDVT', 'OTHER']]);

            $this->assertSame('', $rules->otherPayerIdentifier($claim, 1));
            $this->assertContains('Missing carrier code for medicaid? Enter in previous ins group # then?', $rules->warnings($claim));
        }

        #[Test]
        public function otherPayersKeepTheirIdOutsideMedicaid(): void
        {
            $this->assertNull($this->rules()->otherPayerIdentifier($this->claim(['payerIds' => ['BCBSVT', '14512']]), 1));
        }

        #[Test]
        public function secondMedicareAndVaCommunityCareSkipOtherPayers(): void
        {
            $rules = $this->rules();
            $this->assertFalse($rules->sendsOtherPayer($this->claim(['claimTypes' => ['MB', 'MB']]), 1));
            $this->assertFalse($rules->sendsOtherPayer($this->claim(['payerIds' => ['VACCN', '14512']]), 1));
            $this->assertTrue($rules->sendsOtherPayer($this->claim(['payerIds' => ['MCDVT', '14512'], 'claimTypes' => ['MC', 'MB']]), 1));
        }

        #[Test]
        public function medicareCopayIsCoinsuranceForMedicaid(): void
        {
            $rules = $this->rules();
            $medicaidAfterMedicare = $this->claim(['payerIds' => ['MCDVT', '14512'], 'claimTypes' => ['MC', 'MB']]);
            $this->assertSame('2', $rules->lineAdjustmentReason($medicaidAfterMedicare, 1, 'PR', '3'));
            $this->assertSame('45', $rules->lineAdjustmentReason($medicaidAfterMedicare, 1, 'CO', '45'));
            $this->assertSame('3', $rules->lineAdjustmentReason($this->claim(['payerIds' => ['BCBSVT'], 'claimTypes' => ['BL', 'MB']]), 1, 'PR', '3'));
        }

        #[Test]
        public function tertiaryClaimsReportPrimaryAdjudicationAsOa23(): void
        {
            $rules = $this->rules();
            $tertiary = $this->claim([
                'sequences' => ['T', 'P', 'S'],
                'lines' => [['99213', '']],
                'primaryTotals' => ['20260620', '40.00', '15.00'],
            ]);

            $this->assertSame([['group' => 'OA', 'reason' => '23', 'amount' => 55.0]], $rules->extraLineAdjustments($tertiary, 2, 0));
            $this->assertSame([], $rules->extraLineAdjustments($tertiary, 1, 0));
            $this->assertSame([], $rules->extraLineAdjustments($this->claim(['sequences' => ['S', 'P']]), 1, 0));
        }

        #[Test]
        public function routineFootCareNeedsThePracticeTheCodeAndTheDiagnosis(): void
        {
            $rules = $this->rules();
            $footCare = ['billingNpi' => self::FOOT_CARE_NPI, 'lines' => [['11055', 'Q8']], 'diags' => ['E1142'], 'claimTypes' => ['MB']];

            $this->assertTrue($rules->requiresLastSeenDate($this->claim($footCare)));
            $this->assertTrue($rules->requiresReferringProvider($this->claim($footCare)));
            $this->assertFalse($rules->requiresLastSeenDate($this->claim(['billingNpi' => '1111111111'] + $footCare)));
            $this->assertFalse($rules->requiresLastSeenDate($this->claim(['diags' => ['M7989']] + $footCare)));
            $this->assertFalse($rules->requiresReferringProvider($this->claim(['claimTypes' => ['CI']] + $footCare)));
        }

        #[Test]
        public function footXraysNeedAReferrerForMedicare(): void
        {
            $xray = $this->claim(['billingNpi' => self::FOOT_CARE_NPI, 'lines' => [['73630', '']], 'claimTypes' => ['MB']]);

            $this->assertFalse($this->rules()->requiresLastSeenDate($xray));
            $this->assertTrue($this->rules()->requiresReferringProvider($xray));
        }

        #[Test]
        public function perSiteListsAndPayerSpecificDecisions(): void
        {
            $rules = $this->rules();
            $this->assertFalse($rules->sendsNdc($this->claim(['payerIds' => ['87726']]), 0));
            $this->assertTrue($rules->sendsNdc($this->claim(['payerIds' => ['25169']]), 0));
            $this->assertTrue($rules->sendsClia($this->claim(['claimTypes' => ['CI'], 'lines' => [['81002', '']]])));
            $this->assertFalse($rules->sendsClia($this->claim(['claimTypes' => ['CI'], 'lines' => [['99213', '']]])));
            $this->assertTrue($rules->sendsLineRenderingProvider($this->claim(['payerIds' => ['14165']]), 0, false));
            $this->assertFalse($rules->sendsServiceFacility($this->claim(['billingNpi' => '1', 'facilityNpi' => '1']), true));
            $this->assertTrue($rules->sendsServiceFacility($this->claim(['billingNpi' => '1', 'facilityNpi' => '2']), false));
            $this->assertSame('CMSTEST', $rules->submitterIdentifier($this->claim([])));
        }

        #[Test]
        public function warnings(): void
        {
            $claim = $this->claim([
                'payerIds' => ['VACCN'],
                'pos' => '1',
                'facilityNpi' => '1999999992',
                'billingNpi' => self::FOOT_CARE_NPI,
                'lines' => [['11055', 'Q7'], ['11720', '']],
                'diags' => ['E1142'],
            ]);

            $this->assertSame([
                'Place of service code is 01 Pharmacy!',
                'Missing prior auth for VACCN',
                "Service facility NPI 1999999992 is closed; fix the encounter's facility.",
                "Procedure '11720' is missing Class findings modifier!",
            ], $this->rules()->warnings($claim));
        }
    }
}
