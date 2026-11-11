<?php

/**
 * Database tests for loading codes and standard prices from a CMS fee file.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Services\Modules\Cmsvt;

use Cmsvt\OpenEMR\Modules\Customizations\Fees\CodePrice;
use Cmsvt\OpenEMR\Modules\Customizations\Fees\CodePriceChange;
use Cmsvt\OpenEMR\Modules\Customizations\Fees\CodePriceChangeKind;
use Cmsvt\OpenEMR\Modules\Customizations\Fees\CodePriceImporter;
use Composer\Autoload\ClassLoader;
use OpenEMR\Common\Database\QueryUtils;
use PHPUnit\Framework\TestCase;

class CodePriceImporterTest extends TestCase
{
    /** HCPCS shaped codes no real code set uses; cleanup removes their rows and prices. */
    private const CODE = 'V9901';
    private const OTHER_CODE = 'V9902';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $loaders = ClassLoader::getRegisteredLoaders();
        $loader = reset($loaders);
        if (!$loader instanceof ClassLoader) {
            self::fail('Composer ClassLoader not available to register module autoload prefix.');
        }
        $loader->addPsr4(
            'Cmsvt\\OpenEMR\\Modules\\Customizations\\',
            dirname(__DIR__, 5) . '/interface/modules/custom_modules/oe-module-cmsvt/src/'
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    public function testAMissingCodeIsAddedWithItsStandardPrice(): void
    {
        $importer = new CodePriceImporter();
        $changes = $importer->plan([self::price(self::CODE, '26', 'CT HEAD WO CONTRAST', 24300)]);

        $this->assertSame([CodePriceChangeKind::AddCode], self::kinds($changes));
        $this->assertSame('add HCPCS ' . self::CODE . ':26 "CT HEAD WO CONTRAST" at 243.00', $changes[0]->describe());
        $importer->apply($changes);

        $row = $this->codeRow(self::CODE, '26');
        $this->assertSame(
            ['CT HEAD WO CONTRAST', 'CT HEAD WO CONTRAST', '1', '1', '243.00', '243.00'],
            [$row['code_text'], $row['code_text_short'], self::text($row['units']), self::text($row['active']), self::money($row['fee']), self::money($row['pr_price'])]
        );
    }

    public function testRunningTheSameFileAgainChangesNothing(): void
    {
        $importer = new CodePriceImporter();
        $price = self::price(self::CODE, '', 'XR CHEST', 7200);
        $importer->apply($importer->plan([$price]));

        $again = $importer->plan([$price]);

        $this->assertSame([CodePriceChangeKind::Unchanged], self::kinds($again));
        $this->assertSame(72_00, $again[0]->oldFeeCents);
        $this->assertSame(
            '1',
            self::text(QueryUtils::fetchSingleValue("SELECT COUNT(*) AS n FROM `codes` WHERE `code` = ?", 'n', [self::CODE]))
        );
    }

    public function testADifferentFeeChangesOnlyThePrice(): void
    {
        $importer = new CodePriceImporter();
        $importer->apply($importer->plan([self::price(self::CODE, '26', 'CT HEAD', 24300)]));
        QueryUtils::sqlStatementThrowException("UPDATE `codes` SET `code_text` = 'CT head, edited by a biller' WHERE `code` = ?", [self::CODE]);

        $changes = $importer->plan([self::price(self::CODE, '26', 'CT HEAD WO CONTRAST', 25000)]);
        $this->assertSame([CodePriceChangeKind::ChangePrice], self::kinds($changes));
        $this->assertSame('change HCPCS ' . self::CODE . ':26 from 243.00 to 250.00', $changes[0]->describe());
        $importer->apply($changes);

        $row = $this->codeRow(self::CODE, '26');
        $this->assertSame(
            ['CT head, edited by a biller', '250.00', '250.00'],
            [$row['code_text'], self::money($row['fee']), self::money($row['pr_price'])]
        );
    }

    public function testAnExistingCodeWithoutAPriceGetsOne(): void
    {
        $typeId = QueryUtils::fetchSingleValue("SELECT `ct_id` FROM `code_types` WHERE `ct_key` = 'HCPCS'", 'ct_id');
        QueryUtils::sqlInsert(
            "INSERT INTO `codes` (`code_text`, `code`, `code_type`, `modifier`, `units`, `active`) VALUES ('Existing', ?, ?, '', 1, 1)",
            [self::CODE, $typeId]
        );

        $importer = new CodePriceImporter();
        $changes = $importer->plan([self::price(self::CODE, '', 'FROM THE FEE FILE', 4500)]);
        $this->assertSame([CodePriceChangeKind::AddPrice], self::kinds($changes));
        $importer->apply($changes);

        $row = $this->codeRow(self::CODE, '');
        $this->assertSame(['Existing', '45.00', '45.00'], [$row['code_text'], self::money($row['fee']), self::money($row['pr_price'])]);
    }

    public function testCodesWithAndWithoutAModifierAreSeparate(): void
    {
        $importer = new CodePriceImporter();
        $importer->apply($importer->plan([
            self::price(self::OTHER_CODE, '', 'SPINAL PUNCTURE', 35000),
            self::price(self::OTHER_CODE, '26', 'SPINAL PUNCTURE PROF', 12000),
        ]));

        $this->assertSame('350.00', self::money($this->codeRow(self::OTHER_CODE, '')['pr_price']));
        $this->assertSame('120.00', self::money($this->codeRow(self::OTHER_CODE, '26')['pr_price']));
    }

    public function testPlanningWritesNothing(): void
    {
        (new CodePriceImporter())->plan([self::price(self::CODE, '', 'XR CHEST', 7200)]);

        $this->assertSame(
            '0',
            self::text(QueryUtils::fetchSingleValue('SELECT COUNT(*) AS n FROM `codes` WHERE `code` = ?', 'n', [self::CODE]))
        );
    }

    private static function price(string $code, string $modifier, string $title, int $cents): CodePrice
    {
        return new CodePrice('HCPCS', $code, $modifier, $title, $cents, 1, []);
    }

    /**
     * @param list<CodePriceChange> $changes
     * @return list<CodePriceChangeKind>
     */
    private static function kinds(array $changes): array
    {
        return array_map(static fn (CodePriceChange $c): CodePriceChangeKind => $c->kind, $changes);
    }

    /**
     * @return array<mixed>
     */
    private function codeRow(string $code, string $modifier): array
    {
        $row = QueryUtils::querySingleRow(
            "SELECT c.`code_text`, c.`code_text_short`, c.`units`, c.`active`, c.`fee`, p.`pr_price` FROM `codes` AS c"
            . " LEFT JOIN `prices` AS p ON p.`pr_id` = c.`id` AND p.`pr_selector` = '' AND p.`pr_level` = 'standard'"
            . " WHERE c.`code` = ? AND c.`modifier` = ?",
            [$code, $modifier]
        );
        $this->assertIsArray($row);

        return $row;
    }

    private function cleanUp(): void
    {
        foreach ([self::CODE, self::OTHER_CODE] as $code) {
            QueryUtils::sqlStatementThrowException(
                'DELETE p FROM `prices` AS p JOIN `codes` AS c ON c.`id` = p.`pr_id` WHERE c.`code` = ?',
                [$code]
            );
            QueryUtils::sqlStatementThrowException('DELETE FROM `codes` WHERE `code` = ?', [$code]);
        }
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /** A price column as dollars and cents, however the driver returns it. */
    private static function money(mixed $value): string
    {
        return is_numeric($value) ? sprintf('%.2f', (float) $value) : '';
    }
}
