<?php

/**
 * Database tests for step 6's billing: the patient lookup, and the encounter,
 * CPT4 and ICD10 lines and ordfile link made for a charge. Rows are invented
 * and removed afterwards.
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
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\DatabaseStepThreeFiles;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\OrdfileLine;
use Composer\Autoload\ClassLoader;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Common\Uuid\UuidRegistry;
use OpenEMR\Services\EncounterService;
use OpenEMR\Services\Utils\SQLUpgradeService;
use PHPUnit\Framework\TestCase;

class StepSixBillingTest extends TestCase
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
            'INSERT INTO `patient_data` (`uuid`, `pid`, `pubpid`, `fname`, `lname`, `DOB`, `ref_providerID`) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [(new UuidRegistry(['table_name' => 'patient_data']))->createUuid(), $this->pid, $this->token, 'JANE', 'DOE', '1950-07-04', 31]
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

    public function testAChargeGetsItsEncounterAndBillingLines(): void
    {
        $line = new OrdfileLine(
            $this->token,
            '001',
            '1285',
            ' ',
            'W00',
            '3',
            '00000000',
            new \DateTimeImmutable('2026-10-20'),
            '20261019',
            '0001',
            '',
            '01',
            '',
            '90000001',
            'RT',
            '',
            '',
            '20261021',
            '73562',
            'M17.11'
        );
        $files = new DatabaseStepThreeFiles();
        $recordId = $files->saveOrdfileLine($files->createOrdfile($this->token), $line);
        $billing = new DatabaseChargeBilling(new EncounterService());

        $patient = $billing->findPatient('00' . $this->token);
        $this->assertNotNull($patient);
        $this->assertSame([$this->pid, 31], [$patient->pid, $patient->referringProviderId]);
        $this->assertNull($billing->findPatient($this->token . 'X'));
        $this->assertFalse($billing->isBilled($recordId));

        $encounter = $billing->bill(new BillingCharge($recordId, $line, $patient, PlaceOfService::InpatientHospital, 5, 5000));

        $this->assertTrue($billing->isBilled($recordId));
        $row = QueryUtils::querySingleRow('SELECT * FROM `form_encounter` WHERE `encounter` = ?', [$encounter]);
        $this->assertIsArray($row);
        $this->assertSame(
            ['2026-10-20 00:00:00', '2026-10-19 00:00:00', '90000001', 21, 'IMP', 5, 5000, 31],
            [$row['date'], $row['onset_date'], $row['external_id'], self::number($row['pos_code']), $row['class_code'],
                self::number($row['facility_id']), self::number($row['provider_id']), self::number($row['referring_provider_id'])]
        );
        $lines = QueryUtils::fetchRecords(
            'SELECT b.`code_type`, b.`code`, b.`modifier`, b.`justify`, b.`external_id`, b.`billed`, c.`ordfile_record_id`'
            . ' FROM `billing` b LEFT JOIN `cmsvt_ordfile_record_billing` c ON c.`billing_id` = b.`id` WHERE b.`encounter` = ? ORDER BY b.`id`',
            [$encounter]
        );
        $this->assertCount(2, $lines);
        $this->assertSame(['CPT4', '73562', 'RT', 'ICD10|M17.11:', 'M17.11', 0, $recordId], [
            $lines[0]['code_type'], $lines[0]['code'], $lines[0]['modifier'], $lines[0]['justify'], $lines[0]['external_id'],
            self::number($lines[0]['billed']), self::number($lines[0]['ordfile_record_id']),
        ]);
        $this->assertSame(['ICD10', 'M17.11', null], [$lines[1]['code_type'], $lines[1]['code'], $lines[1]['ordfile_record_id']]);
    }
}
