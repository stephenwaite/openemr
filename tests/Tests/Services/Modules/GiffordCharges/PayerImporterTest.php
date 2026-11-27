<?php

/**
 * Database tests for creating insurance companies from the Gifford payer crosswalk.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Services\Modules\GiffordCharges;

use Cmsvt\OpenEMR\Modules\GiffordCharges\Model\Address;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Payer\ElectronicPayer;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Payer\PayerChange;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Payer\PayerChangeKind;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Payer\PayerCrosswalkException;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Payer\PayerImporter;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Payer\PayerMapping;
use Composer\Autoload\ClassLoader;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Services\InsuranceCompanyService;
use OpenEMR\Services\Utils\SQLUpgradeService;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

class PayerImporterTest extends TestCase
{
    private const MODULE_DIR = '/interface/modules/custom_modules/oe-module-gifford-charges';

    private const NOW = '2026-10-22 10:00:00';

    /** Makes this run's mnemonics unique, so rows left by an aborted run can't match. */
    private string $token = '';

    private int $partnerId = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $loaders = ClassLoader::getRegisteredLoaders();
        $loader = reset($loaders);
        if (!$loader instanceof ClassLoader) {
            self::fail('Composer ClassLoader not available to register module autoload prefix.');
        }
        $loader->addPsr4('Cmsvt\\OpenEMR\\Modules\\GiffordCharges\\', self::root() . self::MODULE_DIR . '/src/');

        // The tables exist only where the module is installed; table.sql is what
        // installing runs, and every statement in it is guarded.
        if (!QueryUtils::existsTable('gifford_payer_map')) {
            $install = new SQLUpgradeService();
            $install->setRenderOutputToScreen(false);
            $install->setThrowExceptionOnError(true);
            $install->upgradeFromSqlFile('table.sql', self::root() . self::MODULE_DIR);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = strtoupper(bin2hex(random_bytes(4)));
        $next = QueryUtils::fetchSingleValue('SELECT COALESCE(MAX(`id`), 0) + 1 AS n FROM `x12_partners`', 'n');
        $this->partnerId = is_numeric($next) ? (int) $next : 1;
        QueryUtils::sqlStatementThrowException(
            "INSERT INTO `x12_partners` (`id`, `name`, `processing_format`) VALUES (?, ?, 'standard')",
            [$this->partnerId, 'Test partner ' . $this->token]
        );
    }

    protected function tearDown(): void
    {
        $companyIds = QueryUtils::fetchTableColumn(
            'SELECT `insurance_company_id` FROM `gifford_payer_map` WHERE `mnemonic` LIKE ?',
            'insurance_company_id',
            ['%' . $this->token]
        );
        foreach ($companyIds as $id) {
            QueryUtils::sqlStatementThrowException('DELETE FROM `addresses` WHERE `foreign_id` = ?', [$id]);
            QueryUtils::sqlStatementThrowException('DELETE FROM `insurance_companies` WHERE `id` = ?', [$id]);
        }
        QueryUtils::sqlStatementThrowException('DELETE FROM `gifford_payer_map` WHERE `mnemonic` LIKE ?', ['%' . $this->token]);
        QueryUtils::sqlStatementThrowException('DELETE FROM `x12_partners` WHERE `id` = ?', [$this->partnerId]);
        parent::tearDown();
    }

    public function testElectronicAndPaperPayersAreCreatedAndMapped(): void
    {
        $importer = $this->importer();
        $changes = $importer->plan([$this->electronic('OPT'), $this->paper('PAPER')]);
        $this->assertSame([PayerChangeKind::Create, PayerChangeKind::Create], self::kinds($changes));

        $created = $importer->apply($changes);

        $this->assertSame(['OPT' . $this->token, 'PAPER' . $this->token], array_keys($created));
        $electronic = $this->company($created['OPT' . $this->token]);
        $this->assertSame(
            ['ELECTRONIC ' . $this->token, '87726', '17', (string) $this->partnerId],
            [$electronic['name'], $electronic['cms_id'], self::text($electronic['ins_type_code']), self::text($electronic['x12_default_partner_id'])]
        );
        $this->assertSame(
            ['PO BOX 31362', 'SALT LAKE CITY', 'UT', '84131', '0362'],
            [$electronic['line1'], $electronic['city'], $electronic['state'], $electronic['zip'], $electronic['plus_four']]
        );

        $paper = $this->company($created['PAPER' . $this->token]);
        $this->assertSame(
            ['PAPER ' . $this->token, '', '20', '0'],
            [$paper['name'], $paper['cms_id'], self::text($paper['ins_type_code']), self::text($paper['x12_default_partner_id'])]
        );
        $this->assertNull($paper['line1'], 'a paper payer gets no address');

        $map = QueryUtils::fetchRecords(
            'SELECT `mnemonic`, `insurance_company_id`, `updated_at` FROM `gifford_payer_map` WHERE `mnemonic` LIKE ? ORDER BY `mnemonic`',
            ['%' . $this->token]
        );
        $this->assertSame([
            ['mnemonic' => 'OPT' . $this->token, 'insurance_company_id' => (string) $created['OPT' . $this->token], 'updated_at' => self::NOW],
            ['mnemonic' => 'PAPER' . $this->token, 'insurance_company_id' => (string) $created['PAPER' . $this->token], 'updated_at' => self::NOW],
        ], array_map(static fn (array $row): array => array_map(self::text(...), $row), $map));
    }

    public function testImportingAgainLeavesMappedCompaniesAlone(): void
    {
        $importer = $this->importer();
        $created = $importer->apply($importer->plan([$this->electronic('OPT')]));
        $id = $created['OPT' . $this->token];
        // A biller corrects the company after the first import.
        QueryUtils::sqlStatementThrowException("UPDATE `insurance_companies` SET `cms_id` = 'FIXED' WHERE `id` = ?", [$id]);

        $changes = $importer->plan([$this->electronic('OPT'), $this->paper('NEW')]);

        $this->assertSame([PayerChangeKind::AlreadyMapped, PayerChangeKind::Create], self::kinds($changes));
        $this->assertSame($id, $changes[0]->insuranceCompanyId);
        $again = $importer->apply($changes);
        $this->assertSame(['NEW' . $this->token], array_keys($again));
        $this->assertSame('FIXED', $this->company($id)['cms_id']);
    }

    public function testAMappedCompanyThatWasDeletedIsCreatedAgain(): void
    {
        $importer = $this->importer();
        $first = $importer->apply($importer->plan([$this->paper('GONE')]))['GONE' . $this->token];
        QueryUtils::sqlStatementThrowException('DELETE FROM `insurance_companies` WHERE `id` = ?', [$first]);

        $changes = $importer->plan([$this->paper('GONE')]);

        $this->assertSame([PayerChangeKind::Recreate], self::kinds($changes));
        $this->assertSame($first, $changes[0]->insuranceCompanyId);
        $second = $importer->apply($changes)['GONE' . $this->token];
        $this->assertNotSame($first, $second);
        $this->assertSame((string) $second, self::text(QueryUtils::fetchSingleValue(
            'SELECT `insurance_company_id` FROM `gifford_payer_map` WHERE `mnemonic` = ?',
            'insurance_company_id',
            ['GONE' . $this->token]
        )));
    }

    public function testAPartnerOrTypeTheSiteLacksStopsThePlan(): void
    {
        $noPartner = new PayerMapping('NOPART' . $this->token, 'NO PARTNER', 17, new ElectronicPayer('1', $this->partnerId + 1000, self::address()));
        $noType = new PayerMapping('NOTYPE' . $this->token, 'NO TYPE', 9999, null);

        try {
            $this->importer()->plan([$this->electronic('OPT'), $noPartner, $noType]);
            $this->fail('A plan with an unknown partner and type was made');
        } catch (PayerCrosswalkException $e) {
            $this->assertSame([
                sprintf('NOPART%s: the site has no X12 partner %d', $this->token, $this->partnerId + 1000),
                sprintf('NOTYPE%s: the site has no insurance type 9999', $this->token),
            ], $e->problems);
        }
        $this->assertSame([], QueryUtils::fetchRecords('SELECT 1 FROM `gifford_payer_map` WHERE `mnemonic` LIKE ?', ['%' . $this->token]));
    }

    private function importer(): PayerImporter
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable(self::NOW));

        return new PayerImporter(new InsuranceCompanyService(), $clock);
    }

    private function electronic(string $prefix): PayerMapping
    {
        return new PayerMapping(
            $prefix . $this->token,
            'ELECTRONIC ' . $this->token,
            17,
            new ElectronicPayer('87726', $this->partnerId, self::address())
        );
    }

    private function paper(string $prefix): PayerMapping
    {
        return new PayerMapping($prefix . $this->token, 'PAPER ' . $this->token, 20, null);
    }

    private static function address(): Address
    {
        return new Address('PO BOX 31362', '', 'SALT LAKE CITY', 'UT', '841310362');
    }

    /**
     * @param list<PayerChange> $changes
     * @return list<PayerChangeKind>
     */
    private static function kinds(array $changes): array
    {
        return array_map(static fn (PayerChange $c): PayerChangeKind => $c->kind, $changes);
    }

    /**
     * @return array<mixed>
     */
    private function company(int $id): array
    {
        $row = QueryUtils::querySingleRow(
            'SELECT ic.*, a.`line1`, a.`city`, a.`state`, a.`zip`, a.`plus_four` FROM `insurance_companies` AS ic'
            . ' LEFT JOIN `addresses` AS a ON a.`foreign_id` = ic.`id` WHERE ic.`id` = ?',
            [$id]
        );
        $this->assertIsArray($row);

        return $row;
    }

    private static function text(mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            is_int($value) => (string) $value,
            default => '',
        };
    }

    private static function root(): string
    {
        return dirname(__DIR__, 5);
    }
}
