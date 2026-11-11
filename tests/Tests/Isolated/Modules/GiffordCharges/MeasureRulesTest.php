<?php

/**
 * Isolated tests for which quality measures a Gifford charge needs answered.
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

use Cmsvt\OpenEMR\Modules\GiffordCharges\Measure\AcrRow;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Measure\Measure;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Measure\MeasureRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MeasureRulesTest extends TestCase
{
    /**
     * @return array<string, array{string, int, bool, list<Measure>}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function measureProvider(): array
    {
        return [
            'AAA screen at 50, any payer' => ['76706', 50, false, [Measure::Qmm26]],
            'AAA screen at 49' => ['76706', 49, true, []],
            'chest CT 364 set at 35' => ['75571', 35, false, [Measure::M364]],
            'chest CT 364 set at 34' => ['75571', 34, false, []],
            'abdomen MRI 405 set, Medicare' => ['74183', 20, true, [Measure::M405]],
            'abdomen MRI 405 set, commercial' => ['74183', 70, false, []],
            'neck CT 406 set, Medicare' => ['70491', 70, true, [Measure::M364, Measure::M406]],
            'neck CT 406 set, Medicare, under 35' => ['70491', 30, true, [Measure::M406]],
            'neck CT 406 set, commercial, 35' => ['70491', 35, false, [Measure::M364]],
            'chest CT 71250, Medicare, 35' => ['71250', 35, true, [Measure::M364, Measure::M405, Measure::M406]],
            'chest CT 71250, Medicare, under 35' => ['71250', 30, true, [Measure::M405, Measure::M406]],
            'chest CT 71250, commercial, under 35' => ['71250', 30, false, []],
            'chest CTA 71275, Medicare' => ['71275', 80, true, [Measure::M364, Measure::M405]],
            'low-dose CT 71271, Medicare' => ['71271', 60, true, [Measure::M405, Measure::M406]],
            'low-dose CT 71271, commercial' => ['71271', 60, false, []],
            'head CT: no measure now' => ['70450', 70, true, []],
            'x-ray' => ['71046', 70, true, []],
        ];
    }

    /**
     * @param list<Measure> $expected
     */
    #[DataProvider('measureProvider')]
    public function testTheCodeSetsAndGatesAreTheBillingSystems(string $cpt, int $age, bool $medicare, array $expected): void
    {
        $this->assertSame($expected, (new MeasureRules())->measuresFor($cpt, $age, $medicare));
    }

    public function testAgeOnTheDateOfService(): void
    {
        $this->assertSame(34, MeasureRules::ageOn(new \DateTimeImmutable('1991-08-21'), new \DateTimeImmutable('2026-08-20')));
        $this->assertSame(35, MeasureRules::ageOn(new \DateTimeImmutable('1991-08-20'), new \DateTimeImmutable('2026-08-20')));
        $this->assertSame(0, MeasureRules::ageOn(new \DateTimeImmutable('2027-01-01'), new \DateTimeImmutable('2026-08-20')));
    }

    public function testClaimsMeasuresAnswerWithGCodesAndRegistryMeasuresWithANumeratorCode(): void
    {
        $this->assertSame(['G9551'], Measure::M405->option(0)?->claimCodes);
        $this->assertSame(['G9549', 'G9547'], Measure::M405->option(2)?->claimCodes);
        $this->assertSame(['G9557'], Measure::M406->option(0)?->claimCodes);
        $this->assertSame(['G9554', 'G9552'], Measure::M406->option(1)?->claimCodes);
        $this->assertNull(Measure::M405->option(4));
        $this->assertSame('G9345', Measure::M364->option(1)?->acrCode);
        $this->assertSame('G9755', Measure::M364->option(2)?->acrCode);
        $this->assertSame('G9347', Measure::M364->option(3)?->acrCode);
        $this->assertSame('M1018', Measure::M364->option(4)?->acrCode);
        $this->assertNull(Measure::M364->option(0)?->acrCode, 'no nodule is not in the denominator');
        $this->assertSame(['PM002', 'PM102', 'PM202', 'PNM02', 'PE002'], array_map(static fn ($o): ?string => $o->acrCode, Measure::Qmm26->options()));
        foreach (Measure::cases() as $measure) {
            foreach ($measure->options() as $option) {
                $this->assertSame($measure->onClaim(), $option->claimCodes !== [], $measure->value . ' option ' . $option->value);
                $this->assertTrue($measure->onClaim() === false || $option->acrCode === null, $measure->value . ' option ' . $option->value);
            }
        }
        $this->assertSame('Final report: no incidentally found lesion noted', Measure::codeText('G9551'));
    }

    public function testTheAcrRowMatchesTheBillingSystemsExport(): void
    {
        $row = new AcrRow(new \DateTimeImmutable('2026-01-02'), '12-34567890', 'MR0012', 73, 'Female', Measure::Qmm26, '76706', 'I71.40', 'PM002', '500000413');

        $this->assertSame('1.1,107816,01/02/2026,030238095,1234567890,MR0012,73,F,QMM26,76706,I7140,PM002,500000413', $row->line('107816', '03-0238095'));
        $this->assertSame('U', explode(',', (new AcrRow(new \DateTimeImmutable('2026-01-02'), '', 'MR1', 40, '', Measure::M364, '71250', '', 'G9345', 'X'))->line('1', ''))[7]);
    }
}
