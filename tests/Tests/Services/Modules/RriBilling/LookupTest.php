<?php

/**
 * Database tests for the RRI billing module's lookups: the charge master
 * (cmsvt_procfile) and RRMC payer codes through the hospital file to
 * insurance companies. Rows are invented and removed afterwards.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Services\Modules\RriBilling;

use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\ChargeMasterEntry;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\HospFilePayerDirectory;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\PayerCompany;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\ProcFileChargeMaster;
use Composer\Autoload\ClassLoader;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Services\Utils\SQLUpgradeService;
use PHPUnit\Framework\TestCase;

class LookupTest extends TestCase
{
    private const MODULE_DIR = '/interface/modules/custom_modules/oe-module-cmsvt-rri-billing';

    /** Marks this run's rows (charge master titles, hospital file names). */
    private string $token = '';

    /** @var list<int> insurance companies this test added */
    private array $companyIds = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $loaders = ClassLoader::getRegisteredLoaders();
        $loader = reset($loaders);
        if (!$loader instanceof ClassLoader) {
            self::fail('Composer ClassLoader not available to register module autoload prefix.');
        }
        $loader->addPsr4('Cmsvt\\OpenEMR\\Modules\\RriBilling\\', dirname(__DIR__, 5) . self::MODULE_DIR . '/src/');

        // The tables exist only where the module is installed; table.sql is what
        // installing runs, and every statement in it is guarded.
        if (!QueryUtils::existsTable('cmsvt_procfile') || !QueryUtils::existsTable('cmsvt_hospfile')) {
            $install = new SQLUpgradeService();
            $install->setRenderOutputToScreen(false);
            $install->setThrowExceptionOnError(true);
            $install->upgradeFromSqlFile('table.sql', dirname(__DIR__, 5) . self::MODULE_DIR);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = 'T' . bin2hex(random_bytes(5));
    }

    protected function tearDown(): void
    {
        QueryUtils::sqlStatementThrowException('DELETE FROM `cmsvt_procfile` WHERE `proc_title` = ?', [$this->token]);
        QueryUtils::sqlStatementThrowException('DELETE FROM `cmsvt_hospfile` WHERE `insurancename` = ?', [$this->token]);
        foreach ($this->companyIds as $id) {
            QueryUtils::sqlStatementThrowException('DELETE FROM `insurance_companies` WHERE `id` = ?', [$id]);
        }
        parent::tearDown();
    }

    public function testTheProfessionalComponentRowWins(): void
    {
        $cdm = $this->unusedCdm();
        $this->chargeMasterRow($cdm, '73560', '', '31.00');
        $this->chargeMasterRow($cdm, '73560', '26', '30.50');
        $this->chargeMasterRow($cdm, '73562', '', '0.00');
        $this->chargeMasterRow($cdm, '73564', 'TC', '12.00');
        $this->chargeMasterRow($cdm, '73565', null, '12.00');

        $chargeMaster = new ProcFileChargeMaster();
        $this->assertEquals(new ChargeMasterEntry($cdm, '73560', '26', 3050), $chargeMaster->find($cdm, '73560'));
        $zero = $chargeMaster->find($cdm, '73562');
        $this->assertEquals(new ChargeMasterEntry($cdm, '73562', '', 0), $zero);
        $this->assertTrue($zero?->isZeroDollar());
        // Neither 26 nor blank: not found, as in 7.0.2 (a NULL modifier too).
        $this->assertNull($chargeMaster->find($cdm, '73564'));
        $this->assertNull($chargeMaster->find($cdm, '73565'));
        $this->assertNull($chargeMaster->find($cdm, '99999'));
    }

    public function testPayerCodesResolveThroughTheHospitalFile(): void
    {
        [$electronicId, $paperId] = $this->unusedCompanyIds();
        $this->company($electronicId, 'X1234');
        $this->company($paperId, '');
        $prefix = substr($this->token, 1, 3);
        $this->hospitalRow('E' . $prefix, str_pad((string) $electronicId, 3, '0', STR_PAD_LEFT));
        $this->hospitalRow('P' . $prefix, (string) $paperId);
        $this->hospitalRow('N' . $prefix, '');

        $payers = new HospFilePayerDirectory();
        $this->assertEquals(new PayerCompany($electronicId, true), $payers->companyForRrmcCode('E' . $prefix));
        $this->assertEquals(new PayerCompany($paperId, false), $payers->companyForRrmcCode('P' . $prefix));
        $this->assertNull($payers->companyForRrmcCode('N' . $prefix), 'a blank key names no company');
        $this->assertNull($payers->companyForRrmcCode('U' . $prefix), 'not in the hospital file');
    }

    private function unusedCdm(): string
    {
        do {
            $cdm = 'Z' . strtoupper(bin2hex(random_bytes(2)))[0] . random_int(10, 99);
        } while (QueryUtils::querySingleRow('SELECT 1 FROM `cmsvt_procfile` WHERE `proc_cdm` = ?', [$cdm]) !== false);
        return $cdm;
    }

    /**
     * Two free insurance company ids below 1000 (the hospital file key has three digits).
     *
     * @return array{int, int}
     */
    private function unusedCompanyIds(): array
    {
        $free = [];
        for ($id = 999; $id > 900 && count($free) < 2; $id--) {
            if (QueryUtils::querySingleRow('SELECT 1 FROM `insurance_companies` WHERE `id` = ?', [$id]) === false) {
                $free[] = $id;
            }
        }
        if (count($free) < 2) {
            self::markTestSkipped('No free insurance company ids between 901 and 999.');
        }
        return [$free[0], $free[1]];
    }

    private function chargeMasterRow(string $cdm, string $code, ?string $modifier, string $amount): void
    {
        QueryUtils::sqlStatementThrowException(
            'INSERT INTO `cmsvt_procfile` (`proc_cdm`, `proc_cpt`, `proc_mod`, `proc_title`, `proc_amount`) VALUES (?, ?, ?, ?, ?)',
            [$cdm, $code, $modifier, $this->token, $amount]
        );
    }

    private function hospitalRow(string $code, string $key): void
    {
        QueryUtils::sqlStatementThrowException(
            'INSERT INTO `cmsvt_hospfile` (`hospkey`, `insurancekey`, `insurancename`) VALUES (?, ?, ?)',
            [$code, $key, $this->token]
        );
    }

    private function company(int $id, string $cmsId): void
    {
        QueryUtils::sqlStatementThrowException(
            'INSERT INTO `insurance_companies` (`id`, `name`, `cms_id`) VALUES (?, ?, ?)',
            [$id, $this->token, $cmsId]
        );
        $this->companyIds[] = $id;
    }
}
