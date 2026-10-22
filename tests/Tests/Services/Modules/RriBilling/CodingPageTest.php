<?php

/**
 * Database tests for the coding page: the charges listed for an ordfile, and
 * saving diagnoses and a provider. Rows are invented and removed afterwards.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Services\Modules\RriBilling;

use Cmsvt\OpenEMR\Modules\RriBilling\Billing\BillingCharge;
use Cmsvt\OpenEMR\Modules\RriBilling\Billing\DatabaseChargeBilling;
use Cmsvt\OpenEMR\Modules\RriBilling\Billing\PlaceOfService;
use Cmsvt\OpenEMR\Modules\RriBilling\Coding\CodingRepository;
use Cmsvt\OpenEMR\Modules\RriBilling\Coding\CodingUpdate;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\DatabaseStepThreeFiles;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\OrdfileLine;
use Composer\Autoload\ClassLoader;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Common\Uuid\UuidRegistry;
use OpenEMR\Services\CodeTypesService;
use OpenEMR\Services\EncounterService;
use OpenEMR\Services\Utils\SQLUpgradeService;
use PHPUnit\Framework\TestCase;

class CodingPageTest extends TestCase
{
    private const MODULE_DIR = '/interface/modules/custom_modules/oe-module-cmsvt-rri-billing';

    /** This run's marker: the patient's public id and the ordfile's name. */
    private string $token = '';

    private int $pid = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $loaders = ClassLoader::getRegisteredLoaders();
        $loader = reset($loaders);
        if (!$loader instanceof ClassLoader) {
            self::fail('Composer ClassLoader not available to register module autoload prefix.');
        }
        $loader->addPsr4('Cmsvt\\OpenEMR\\Modules\\RriBilling\\', dirname(__DIR__, 5) . self::MODULE_DIR . '/src/');
        if (!QueryUtils::existsTable('cmsvt_ordfile_record_billing')) {
            $install = new SQLUpgradeService();
            $install->setRenderOutputToScreen(false);
            $install->setThrowExceptionOnError(true);
            $install->upgradeFromSqlFile('table.sql', dirname(__DIR__, 5) . self::MODULE_DIR);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = 'T' . strtoupper(bin2hex(random_bytes(3)));
        $this->pid = self::number(QueryUtils::fetchSingleValue('SELECT COALESCE(MAX(`pid`), 0) + 1000 AS p FROM `patient_data`', 'p'));
        QueryUtils::sqlStatementThrowException(
            'INSERT INTO `patient_data` (`uuid`, `pid`, `pubpid`, `fname`, `lname`, `DOB`) VALUES (?, ?, ?, ?, ?, ?)',
            [(new UuidRegistry(['table_name' => 'patient_data']))->createUuid(), $this->pid, $this->token, 'JANE', 'DOE', '1950-07-04']
        );
    }

    protected function tearDown(): void
    {
        QueryUtils::sqlStatementThrowException(
            'DELETE FROM `cmsvt_ordfile_record_billing` WHERE `billing_id` IN (SELECT `id` FROM `billing` WHERE `pid` = ?)',
            [$this->pid]
        );
        foreach (['billing', 'forms', 'form_encounter', 'patient_data'] as $table) {
            QueryUtils::sqlStatementThrowException('DELETE FROM `' . $table . '` WHERE `pid` = ?', [$this->pid]);
        }
        QueryUtils::sqlStatementThrowException(
            'DELETE r FROM `cmsvt_ordfile_record` r JOIN `cmsvt_ordfile` f USING (`ordfile_id`) WHERE f.`filename` = ?',
            [$this->token]
        );
        QueryUtils::sqlStatementThrowException('DELETE FROM `cmsvt_ordfile` WHERE `filename` = ?', [$this->token]);
        parent::tearDown();
    }

    private static function number(mixed $value): int
    {
        self::assertTrue(is_numeric($value), 'expected a number');
        return (int) $value;
    }

    /**
     * A billed charge (step 6) in a new ordfile.
     *
     * @return array{int, int, int} ordfile id, ordfile line id, encounter
     */
    private function charge(?int $providerId): array
    {
        $line = new OrdfileLine(
            $this->token,
            '001',
            '1285',
            ' ',
            'W00',
            '5',
            '00000000',
            new \DateTimeImmutable('2026-10-20'),
            '20261019',
            '0001',
            'SYNTHETIC CLINICAL',
            '01',
            'KNEE PAIN',
            '90000001',
            'RT',
            '',
            '',
            '20261021',
            '73562',
            'M17.11'
        );
        $files = new DatabaseStepThreeFiles();
        $ordfileId = $files->createOrdfile($this->token);
        $recordId = $files->saveOrdfileLine($ordfileId, $line);
        $billing = new DatabaseChargeBilling(new EncounterService());
        $patient = $billing->findPatient($this->token);
        $this->assertNotNull($patient);
        $encounter = $billing->bill(new BillingCharge($recordId, $line, $patient, PlaceOfService::OutpatientHospital, 4, $providerId));
        return [$ordfileId, $recordId, $encounter];
    }

    /**
     * @return array<string, array{int, string}> billing id and code of the charge's lines by record type
     */
    private static function lines(int $recordId): array
    {
        $lines = [];
        foreach (
            QueryUtils::fetchRecords(
                'SELECT c.`record_type`, b.`id`, b.`code`, b.`justify` FROM `cmsvt_ordfile_record_billing` c JOIN `billing` b ON b.`id` = c.`billing_id`'
                . ' WHERE c.`ordfile_record_id` = ?',
                [$recordId]
            ) as $row
        ) {
            $type = $row['record_type'];
            self::assertIsString($type);
            $code = $row['code'];
            self::assertIsString($code);
            $lines[$type] = [self::number($row['id']), $type === 'source' ? (is_string($row['justify']) ? $row['justify'] : '') : $code];
        }
        ksort($lines);
        return $lines;
    }

    public function testRrmcsDiagnosisIsDxOneAndTheBillerCanChangeIt(): void
    {
        [$ordfileId, $recordId] = $this->charge(5000);
        $repository = new CodingRepository(new CodeTypesService());

        $rows = $repository->rows($ordfileId);
        $this->assertCount(1, $rows);
        $this->assertSame(
            [$recordId, 'M17.11', '', '001', '10-20-2026', 'DOE, JANE', 5000],
            [$rows[0]->recordId, $rows[0]->dx1->code, $rows[0]->dx2->code, $rows[0]->payer, $rows[0]->dateOfService, $rows[0]->patientName, $rows[0]->providerId]
        );
        $before = self::lines($recordId);

        $this->assertSame(1, $repository->save([new CodingUpdate($recordId, null, 'M17.12', 'R07.9')]));
        $after = self::lines($recordId);
        $this->assertSame($before['primary'][0], $after['primary'][0], 'Dx 1 is changed in place');
        $this->assertSame(['M17.12', 'R07.9', 'ICD10|M17.12:ICD10|R07.9:'], [$after['primary'][1], $after['secondary'][1], $after['source'][1]]);

        $repository->save([new CodingUpdate($recordId, null, '', 'R07.9')]);
        $this->assertSame(['secondary', 'source'], array_keys(self::lines($recordId)), 'an emptied box removes its line');
        $this->assertSame('ICD10|R07.9:', self::lines($recordId)['source'][1]);

        QueryUtils::sqlStatementThrowException('UPDATE `billing` SET `billed` = 1 WHERE `pid` = ?', [$this->pid]);
        $this->assertSame(0, $repository->save([new CodingUpdate($recordId, null, 'Z00.00', '')]), 'a billed charge is not changed');
        $this->assertSame('ICD10|R07.9:', self::lines($recordId)['source'][1]);
        $this->assertSame([], $repository->rows($ordfileId));
    }

    public function testAChargeWithoutAProviderGetsOneOnItsOwnEncounter(): void
    {
        [$ordfileId, $recordId, $encounter] = $this->charge(null);
        $repository = new CodingRepository(new CodeTypesService());
        $providers = $repository->providers();
        $this->assertNotSame([], $providers);
        $provider = $providers[0]['id'];
        $before = $repository->rows($ordfileId)[0];
        $this->assertNull($before->providerId);

        $repository->save([new CodingUpdate($recordId, $provider, 'M17.11', '')]);

        $after = $repository->rows($ordfileId)[0];
        $this->assertSame($provider, $after->providerId);
        $this->assertSame($provider, self::number(QueryUtils::fetchSingleValue('SELECT `provider_id` FROM `form_encounter` WHERE `encounter` = ?', 'provider_id', [$encounter])));
        $lines = QueryUtils::fetchTableColumn('SELECT `provider_id` FROM `billing` WHERE `encounter` = ?', 'provider_id', [$encounter]);
        $this->assertSame([$provider, $provider], array_map(self::number(...), $lines));
    }
}
