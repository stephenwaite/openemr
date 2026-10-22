<?php

/**
 * Database tests for what step 3 reads and saves: the account file, referring
 * doctors, the ordfile (and steps 4-6 reading it back), work comp and
 * email/authorization rows, and a patient's current primary. Rows are invented and removed afterwards.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Services\Modules\RriBilling;

use Cmsvt\OpenEMR\Modules\RriBilling\Account\AccountRecord;
use Cmsvt\OpenEMR\Modules\RriBilling\Account\PolicyNumber;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\Address;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeFileInsurance;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\PersonName;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\DatabaseAccountFile;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\DatabasePatientInsuranceHistory;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\DatabaseReferringDoctors;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\DatabaseOrdfileSource;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\DatabaseStepThreeFiles;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\EmailAuthRecord;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\OrdfileLine;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\WorkCompRecord;
use Composer\Autoload\ClassLoader;
use OpenEMR\BC\ServiceContainer;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Common\Uuid\UuidRegistry;
use OpenEMR\Services\InsuranceService;
use OpenEMR\Services\Utils\SQLUpgradeService;
use PHPUnit\Framework\TestCase;

class StepThreeStorageTest extends TestCase
{
    private const MODULE_DIR = '/interface/modules/custom_modules/oe-module-cmsvt-rri-billing';

    /** This run's marker: account numbers, names and file names start with it. */
    private string $token = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $loaders = ClassLoader::getRegisteredLoaders();
        $loader = reset($loaders);
        if (!$loader instanceof ClassLoader) {
            self::fail('Composer ClassLoader not available to register module autoload prefix.');
        }
        $loader->addPsr4('Cmsvt\\OpenEMR\\Modules\\RriBilling\\', dirname(__DIR__, 5) . self::MODULE_DIR . '/src/');
        if (!QueryUtils::existsTable('cmsvt_actfile') || !QueryUtils::existsTable('cmsvt_emailauthfile')) {
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
    }

    protected function tearDown(): void
    {
        QueryUtils::sqlStatementThrowException('DELETE FROM `cmsvt_actfile` WHERE `actno` LIKE ?', [$this->token . '%']);
        QueryUtils::sqlStatementThrowException('DELETE FROM `users` WHERE `lname` = ?', [$this->token]);
        QueryUtils::sqlStatementThrowException('DELETE FROM `cmsvt_refphy` WHERE `name` LIKE ?', [$this->token . '%']);
        QueryUtils::sqlStatementThrowException(
            'DELETE r FROM `cmsvt_ordfile_record` r JOIN `cmsvt_ordfile` f USING (`ordfile_id`) WHERE f.`filename` = ?',
            [$this->token]
        );
        QueryUtils::sqlStatementThrowException('DELETE FROM `cmsvt_ordfile` WHERE `filename` = ?', [$this->token]);
        QueryUtils::sqlStatementThrowException('DELETE FROM `cmsvt_compfile` WHERE `medrec` = ?', [substr($this->token, 0, 8)]);
        QueryUtils::sqlStatementThrowException('DELETE FROM `cmsvt_emailauthfile` WHERE `medrec` = ?', [substr($this->token, 0, 8)]);
        $pids = QueryUtils::fetchTableColumn('SELECT `pid` FROM `patient_data` WHERE `pubpid` = ?', 'pid', [$this->token]);
        foreach ($pids as $pid) {
            QueryUtils::sqlStatementThrowException('DELETE FROM `insurance_data` WHERE `pid` = ?', [$pid]);
        }
        QueryUtils::sqlStatementThrowException('DELETE FROM `patient_data` WHERE `pubpid` = ?', [$this->token]);
        parent::tearDown();
    }

    private static function number(mixed $value): int
    {
        self::assertTrue(is_numeric($value), 'expected a number');
        return (int) $value;
    }

    public function testAnAccountIsSavedAndReadBack(): void
    {
        $account = new AccountRecord();
        $account->actno = $this->token . '01';
        $account->garname = 'DOE;JANEQ';
        $account->billadd = '1 MAIN ST';
        $account->city = 'RUTLAND';
        $account->state = 'VT';
        $account->zip = '05701-1234';
        $account->phone = '8025550199';
        $account->sex = 'F';
        $account->relate = 'K';
        $account->dob = '19500704';
        $account->prins = '003';
        $account->prassign = 'A';
        $account->prgroup = '0005199     ';
        $account->pripol = PolicyNumber::fromString('1EG4TE5MK72');
        $account->seins = '062';
        $account->secpol = PolicyNumber::fromString('MG555');
        $account->namekey = 'DOE';
        $files = new DatabaseAccountFile();

        $files->save($account);
        $read = $files->find($this->token . '01');

        $this->assertNotNull($read);
        $this->assertSame(['DOE;JANEQ', '05701-1234', '8025550199', 'F', 'K', '19500704'], [$read->garname, $read->zip, $read->phone, $read->sex, $read->relate, $read->dob]);
        // The group is cut to its column; the secondary policy is the secondary's (7.0.2 saved the primary's).
        $this->assertSame(['003', 'A', '0005199   ', '1EG4TE5MK72'], [$read->prins, $read->prassign, $read->prgroup, $read->pripol->toString()]);
        $this->assertSame(['062', 'MG555'], [$read->seins, $read->secpol->toString()]);
        $this->assertSame(['0', '1', '1', null], [$read->collt, $read->dunning, $read->acctstat, $read->garno]);

        $account->seins = '001';
        $account->garno = '42';
        $files->save($account);
        $again = $files->find($this->token . '01');
        $this->assertSame(['001', '42'], [$again?->seins, $again?->garno]);
        $this->assertNull($files->find($this->token . '99'));
    }

    public function testReferringDoctorsAreAddedWithTheNextKeyAndFoundByNpi(): void
    {
        $npi = '9' . substr((string) random_int(100000000, 999999999), 0, 9);
        $initial = substr($this->token, 0, 1);
        $doctors = new DatabaseReferringDoctors();

        $first = $doctors->add(new PersonName($this->token . ', ONE', $this->token, 'ONE', ''), $npi);
        $second = $doctors->add(new PersonName($this->token . ', TWO', $this->token, 'TWO', ''), $npi);

        $this->assertSame($first->refkey[0], $second->refkey[0]);
        $this->assertSame($initial, $first->refkey[0]);
        $this->assertGreaterThan($first->refkey, $second->refkey);
        $found = $doctors->findByNpi($npi);
        $this->assertSame([$first->refkey, $second->refkey], array_map(static fn($doctor): string => $doctor->refkey, $found));
        $this->assertSame(['ONE', 'TWO'], array_map(static fn($doctor): string => $doctor->firstName, $found));
        $user = QueryUtils::querySingleRow('SELECT `abook_type`, `notes`, `authorized` FROM `users` WHERE `id` = ?', [$first->userId]);
        $this->assertIsArray($user);
        $this->assertSame(['external_provider', $first->refkey, 0], [$user['abook_type'], $user['notes'], self::number($user['authorized'])]);
        $refphy = QueryUtils::fetchSingleValue('SELECT `name` FROM `cmsvt_refphy` WHERE `refkey` = ?', 'name', [$first->refkey]);
        $this->assertSame($this->token . '; ONE', $refphy);
    }

    public function testTheOrdfileWorkCompAndEmailRowsAreSaved(): void
    {
        $medrec = substr($this->token, 0, 8);
        $files = new DatabaseStepThreeFiles();
        $line = new OrdfileLine(
            $medrec,
            '001',
            '7000',
            ' ',
            'W00',
            '5',
            '00000000',
            new \DateTimeImmutable('2026-10-20'),
            '20261019',
            '0001',
            'CLINICAL',
            '01',
            'ABDOMINAL PAIN, L',
            '10000001',
            'RT',
            '',
            '',
            '20261021',
            '71046',
            'C50.912'
        );

        $ordfileId = $files->createOrdfile($this->token);
        $recordId = $files->saveOrdfileLine($ordfileId, $line);
        $row = QueryUtils::querySingleRow('SELECT * FROM `cmsvt_ordfile_record` WHERE `ordfile_record_id` = ?', [$recordId]);
        $this->assertIsArray($row);
        $this->assertSame([$ordfileId, 'C50.912', $medrec, '001', '20261020', '20261019', 'RT', '71046'], [
            self::number($row['ordfile_id']), $row['accession'], $row['ord8'], $row['ord3'], $row['dateT'], $row['dateAdmit'], $row['mod2'], $row['cpt'],
        ]);

        $insurance = new ChargeFileInsurance(
            'P0001',
            '',
            '',
            '',
            '',
            PersonName::parse(''),
            '',
            '',
            '',
            '',
            '',
            'FIRST COMP INSURER NAME LONG',
            'ADJUSTER',
            new Address('PO BOX 1', '', 'BARRE', 'VT', '05641'),
            '800-555-0100',
            ''
        );
        $record = new WorkCompRecord($medrec, new \DateTimeImmutable('2026-10-20'), '7000', $insurance);
        $files->saveWorkComp($record);
        $files->saveWorkComp(new WorkCompRecord($medrec, new \DateTimeImmutable('2026-10-20'), '7000', $insurance));
        $comp = QueryUtils::fetchRecords('SELECT * FROM `cmsvt_compfile` WHERE `compkey` = ?', [$record->key()]);
        $this->assertCount(1, $comp, 'the same key is updated, not added');
        $this->assertSame(['20261020', 'FIRST COMP INSURER NAME L', 'BARRE'], [$comp[0]['date'], $comp[0]['name'], $comp[0]['city']]);

        $files->saveEmailAuth(new EmailAuthRecord($medrec, 'DOE, JOHN', '123456789', 'jane@example.org', 'AUTH77', '20261020'));
        $files->saveEmailAuth(new EmailAuthRecord($medrec, 'DOE, JOHN', '123456789', '', 'AUTH78', null));
        $keys = QueryUtils::fetchTableColumn('SELECT `eakey` FROM `cmsvt_emailauthfile` WHERE `medrec` = ? ORDER BY `eakey`', 'eakey', [$medrec]);
        $this->assertCount(2, $keys);
        $this->assertSame(1, self::number($keys[1]) - self::number($keys[0]));
    }

    public function testStepsFourToSixReadTheOrdfileBack(): void
    {
        $medrec = substr($this->token, 0, 8);
        $files = new DatabaseStepThreeFiles();
        $charge = static fn(string $seq, string $ind, string $date): OrdfileLine => new OrdfileLine(
            $medrec,
            $seq,
            '7000',
            $ind,
            'W00',
            '5',
            '00000000',
            new \DateTimeImmutable($date),
            '20261019',
            '0001',
            '',
            '01',
            '',
            '10000001',
            '',
            '',
            '',
            '20261021',
            '71046',
            ''
        );
        $ordfileId = $files->createOrdfile($this->token);
        $chargeId = $files->saveOrdfileLine($ordfileId, $charge('001', ' ', '2026-10-20'));
        $cancelId = $files->saveOrdfileLine($ordfileId, $charge('002', '-', '2026-10-19'));
        $source = new DatabaseOrdfileSource(ServiceContainer::getClock());

        $ordfile = $source->byFileName($this->token);

        $this->assertNotNull($ordfile);
        $this->assertSame([$ordfileId, $this->token], [$ordfile->id, $ordfile->fileName]);
        $this->assertSame([$chargeId, $cancelId], array_keys($ordfile->lines));
        $this->assertSame(
            [[$medrec . '001', ' ', '20261020', 'W00', '71046'], [$medrec . '002', '-', '20261019', 'W00', '71046']],
            array_map(static fn(OrdfileLine $line): array => [$line->ord8 . $line->ord3, $line->ind, $line->dateT->format('Ymd'), $line->ref, $line->cpt], array_values($ordfile->lines))
        );
        $created = QueryUtils::fetchSingleValue('SELECT DATE(`date_created`) AS d FROM `cmsvt_ordfile` WHERE `ordfile_id` = ?', 'd', [$ordfileId]);
        $this->assertIsString($created);
        $this->assertSame($ordfileId, $source->byDate(new \DateTimeImmutable($created))?->id);
        $this->assertNull($source->byFileName($this->token . 'X'));
    }

    public function testAPatientsCurrentPrimaryIsTheNewest(): void
    {
        $pid = self::number(QueryUtils::fetchSingleValue('SELECT COALESCE(MAX(`pid`), 0) + 1000 AS p FROM `patient_data`', 'p'));
        QueryUtils::sqlStatementThrowException(
            'INSERT INTO `patient_data` (`uuid`, `pid`, `pubpid`, `fname`, `lname`, `DOB`) VALUES (?, ?, ?, ?, ?, ?)',
            [(new UuidRegistry(['table_name' => 'patient_data']))->createUuid(), $pid, $this->token, 'JANE', 'DOE', '1950-07-04']
        );
        $patientId = self::number(QueryUtils::fetchSingleValue('SELECT `id` FROM `patient_data` WHERE `pid` = ?', 'id', [$pid]));
        foreach ([['2025-01-01', 3], ['2026-01-01', 95]] as [$date, $provider]) {
            QueryUtils::sqlStatementThrowException(
                'INSERT INTO `insurance_data` (`uuid`, `pid`, `type`, `provider`, `date`, `policy_number`) VALUES (?, ?, ?, ?, ?, ?)',
                [(new UuidRegistry(['table_name' => 'insurance_data']))->createUuid(), $pid, 'primary', $provider, $date, 'P' . $provider]
            );
        }
        $history = new DatabasePatientInsuranceHistory(new InsuranceService());

        $prior = $history->currentPrimary($this->token);

        $this->assertNotNull($prior);
        $this->assertSame([$patientId, '95'], [$prior->patientId, $prior->provider]);
        $this->assertNull($history->currentPrimary($this->token . 'X'));
    }
}
