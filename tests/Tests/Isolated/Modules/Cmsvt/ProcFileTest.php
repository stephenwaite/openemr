<?php

/**
 * Isolated tests for reading CMS fee files (PROCFILE01) into one price per code.
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

    use Cmsvt\OpenEMR\Modules\Customizations\Fees\CodePrice;
    use Cmsvt\OpenEMR\Modules\Customizations\Fees\CodePriceList;
    use Cmsvt\OpenEMR\Modules\Customizations\Fees\ProcFileException;
    use Cmsvt\OpenEMR\Modules\Customizations\Fees\ProcFileParser;
    use Cmsvt\OpenEMR\Modules\Customizations\Fees\ProcFileRecord;
    use PHPUnit\Framework\Attributes\DataProvider;
    use PHPUnit\Framework\Attributes\Test;
    use PHPUnit\Framework\TestCase;

    final class ProcFileTest extends TestCase
    {
        #[Test]
        public function aRecordIsReadByItsCopybookColumns(): void
        {
            $record = (new ProcFileParser())->parseLine(self::line('1090', '77057', '26', '5', 'MAMMOGRAM  BILATERAL SCREEN', '020900'), 7);

            $this->assertEquals(
                new ProcFileRecord(7, '1090', '77057', '26', '5', 'MAMMOGRAM  BILATERAL SCREEN', 20900),
                $record
            );
        }

        #[Test]
        public function aBlankModifierAndTitlePaddingAreTrimmed(): void
        {
            $record = (new ProcFileParser())->parseLine('00003100F  5MEASURE 11 CAROTID DIAM INCL000000', 1);

            $this->assertSame(['3100F', '', 'MEASURE 11 CAROTID DIAM INCL', 0], [$record->code, $record->modifier, $record->title, $record->feeCents]);
        }

        /**
         * @param non-empty-string $message
         */
        #[Test]
        #[DataProvider('badLineProvider')]
        public function aLineThatIsNotARecordIsRejected(string $line, string $message): void
        {
            $this->expectException(ProcFileException::class);
            $this->expectExceptionMessage($message);
            (new ProcFileParser())->parseLine($line, 3);
        }

        /**
         * @return array<string, array{string, string}>
         *
         * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
         */
        public static function badLineProvider(): array
        {
            return [
                'too short' => ['109077057265MAMMOGRAM', 'line 3: 21 characters, a record has 46'],
                'too long' => [self::line('1090', '77057', '26', '5', 'MAMMOGRAM', '020900') . 'X', 'line 3: 47 characters'],
                'amount not digits' => [self::line('1090', '77057', '26', '5', 'MAMMOGRAM', '0209.0'), "line 3: amount '0209.0' is not 6 digits"],
            ];
        }

        #[Test]
        public function parsingAFileSkipsBlankLinesAndCollectsBadOnes(): void
        {
            $result = (new ProcFileParser())->parse([
                self::line('2034', '71046', '26', '5', 'CHEST 2 VIEWS', '007200') . "\n",
                "\n",
                "short line\r\n",
                self::line('2035', '71045', '26', '5', 'CHEST 1 VIEW', '007000') . "\r\n",
            ]);

            $this->assertSame(['71046', '71045'], array_map(static fn (ProcFileRecord $r): string => $r->code, $result->records));
            $this->assertSame([1, 4], array_map(static fn (ProcFileRecord $r): int => $r->lineNumber, $result->records));
            $this->assertSame(['line 3: 10 characters, a record has 46'], $result->errors);
        }

        #[Test]
        #[DataProvider('codeTypeProvider')]
        public function codeTypesFollowTheirShape(string $code, ?string $expected): void
        {
            $this->assertSame($expected, CodePriceList::codeType($code));
        }

        /**
         * @return array<string, array{string, ?string}>
         *
         * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
         */
        public static function codeTypeProvider(): array
        {
            return [
                'CPT' => ['71046', 'CPT4'],
                'CPT category II' => ['3100F', 'CPT4'],
                'CPT category III' => ['0042T', 'CPT4'],
                'PLA' => ['0001U', 'CPT4'],
                'HCPCS' => ['A9579', 'HCPCS'],
                'four characters' => ['7104', null],
                'letter W' => ['W1234', null],
                'blank' => ['', null],
            ];
        }

        #[Test]
        public function recordsBecomeOnePricePerCodeAndModifier(): void
        {
            $list = CodePriceList::fromRecords([
                self::record(2, '71046', '26', 'CHEST PA/LAT', 7200),
                self::record(3, '71046', '26', 'CHEST 2 VIEWS', 7200),
                self::record(4, '62329', '', 'SPINAL PUNCTURE', 35000),
                self::record(5, '62329', '26', 'SPINAL PUNCTURE PROF', 12000),
                self::record(6, '3100F', '', 'MEASURE 11', 0),
                self::record(7, 'A9579', '', 'GAD CONTRAST', 4500),
                self::record(8, '7104', '', 'BAD CODE', 1000),
            ]);

            $this->assertSame(
                ['CPT4 62329', 'CPT4 62329:26', 'CPT4 71046:26', 'HCPCS A9579'],
                array_map(static fn (CodePrice $p): string => $p->label(), $list->prices)
            );
            $this->assertSame(1, $list->zeroFeeRecords);
            $this->assertSame(["line 8: code '7104'"], $list->unknownCodes);

            $chest = $list->prices[2];
            $this->assertSame(['72.00', 2, [], 'CHEST PA/LAT'], [$chest->fee(), $chest->recordCount, $chest->otherFeesCents, $chest->title]);
        }

        #[Test]
        public function theMostCommonFeeWinsAndTheOthersAreKept(): void
        {
            $list = CodePriceList::fromRecords([
                self::record(2, '77057', '26', 'MAMMOGRAM  BILATERAL SCREEN', 20900),
                self::record(3, '77057', '26', 'MAMMOGRAM  BILATERAL SCREEN', 13200),
                self::record(4, '77057', '26', 'MAMMOGRAM  BILATERAL REDUCED', 13200),
                self::record(5, '77057', '26', 'MAMMOGRAM BCPT', 13200),
            ]);

            $price = $list->prices[0];
            $this->assertSame(['132.00', [20900], 'MAMMOGRAM  BILATERAL SCREEN', 4], [$price->fee(), $price->otherFeesCents, $price->title, $price->recordCount]);
        }

        #[Test]
        public function ofEquallyCommonFeesTheHighestWins(): void
        {
            $list = CodePriceList::fromRecords([
                self::record(2, '36000', '', 'INTRO INTRACATH/NEEDLE IN VE', 6800),
                self::record(3, '36000', '', 'INTRODUCTION OF NEEDLE', 40800),
                self::record(4, '58999', '', 'UNL PROC FEM GEN SYS CT', 25200),
                self::record(5, '58999', '', 'UNL PROC FEM GEN SYS US', 12700),
                self::record(6, '58999', '', 'UNL PROC FEM GEN SYS IR', 37900),
            ]);

            $this->assertSame(['408.00', [6800], 'INTRODUCTION OF NEEDLE'], [$list->prices[0]->fee(), $list->prices[0]->otherFeesCents, $list->prices[0]->title]);
            $this->assertSame(['379.00', [25200, 12700]], [$list->prices[1]->fee(), $list->prices[1]->otherFeesCents]);
        }

        #[Test]
        public function theTitleMostRecordsShareIsUsed(): void
        {
            $list = CodePriceList::fromRecords([
                self::record(2, '71045', '26', 'XR PORTABLE CX 1V POST PROC', 7000),
                self::record(3, '71045', '26', 'CHEST 1 VIEW', 7000),
                self::record(4, '71045', '26', 'CHEST 1 VIEW', 7000),
                self::record(5, '71045', '26', 'CHEST PX 1 VIEW', 7000),
                // Not the chosen fee, so its titles don't count.
                self::record(6, '71045', '26', 'OLD WORDING', 6500),
            ]);

            $this->assertSame('CHEST 1 VIEW', $list->prices[0]->title);
        }

        #[Test]
        public function centsAreShownAsDollars(): void
        {
            $this->assertSame(['0.80', '44.00', '7207.05', '0.00'], array_map(CodePrice::dollars(...), [80, 4400, 720705, 0]));
        }

        private static function line(string $cdm, string $cpt, string $mod, string $type, string $title, string $amount): string
        {
            return str_pad($cdm, 4) . str_pad($cpt, 5) . str_pad($mod, 2) . $type . str_pad($title, 28) . $amount;
        }

        private static function record(int $line, string $code, string $modifier, string $title, int $cents): ProcFileRecord
        {
            return new ProcFileRecord($line, '0000', $code, $modifier, '5', $title, $cents);
        }
    }
}
