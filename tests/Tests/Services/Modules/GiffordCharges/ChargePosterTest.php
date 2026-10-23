<?php

/**
 * Database tests for posting staged Gifford charge lines.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Services\Modules\GiffordCharges;

use Cmsvt\OpenEMR\Modules\GiffordCharges\Parse\ChargeRowParser;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Payer\PayerMap;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Post\ChargeLineRepository;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Post\ChargePoster;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Post\PostResult;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Post\PostSettings;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Post\PriceBook;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Post\SiteWriter;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Reader\RawRow;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Stage\ChargeBatchRepository;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Stage\StagedLine;
use Composer\Autoload\ClassLoader;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Services\Utils\SQLUpgradeService;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

class ChargePosterTest extends TestCase
{
    private const MODULE_DIR = '/interface/modules/custom_modules/oe-module-gifford-charges';

    private const NOW = '2026-10-22 10:00:00';

    /** Makes this run's rows unique, so rows left by an aborted run can't match. */
    private string $token = '';

    private string $mrn = '';

    private string $renderingNpi = '';

    private string $referringNpi = '';

    /** A CPT-shaped code that exists only for this test, priced with 26. */
    private string $code = '';

    /** Priced only without a modifier. */
    private string $bareCode = '';

    private string $payer = '';

    private int $facilityId = 0;

    private int $providerId = 0;

    private int $companyId = 0;

    /** @var list<int> */
    private array $codeIds = [];

    /** @var list<int> */
    private array $batchIds = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $loaders = ClassLoader::getRegisteredLoaders();
        $loader = reset($loaders);
        if (!$loader instanceof ClassLoader) {
            self::fail('Composer ClassLoader not available to register module autoload prefix.');
        }
        $loader->addPsr4('Cmsvt\\OpenEMR\\Modules\\GiffordCharges\\', self::root() . self::MODULE_DIR . '/src/');

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
        $digits = substr((string) hexdec(substr($this->token, 0, 7)), 0, 6);
        $this->mrn = 'MRT' . $this->token;
        $this->renderingNpi = '9' . str_pad($digits, 9, '1');
        $this->referringNpi = '8' . str_pad($digits, 9, '2');
        $this->code = 'T' . substr($this->token, 0, 4);
        $this->bareCode = 'U' . substr($this->token, 0, 4);
        $this->payer = 'PAY' . $this->token;

        $this->facilityId = QueryUtils::sqlInsert(
            "INSERT INTO `facility` (`name`, `billing_location`, `service_location`, `pos_code`) VALUES (?, 1, 1, 22)",
            ['Reading ' . $this->token]
        );
        $this->providerId = QueryUtils::sqlInsert(
            "INSERT INTO `users` (`username`, `password`, `authorized`, `active`, `fname`, `lname`, `npi`, `facility_id`)"
            . " VALUES (?, '', 1, 1, 'Ray', 'Reader', ?, ?)",
            ['reader' . strtolower($this->token), $this->renderingNpi, $this->facilityId]
        );
        $this->companyId = QueryUtils::generateId();
        QueryUtils::sqlStatementThrowException(
            "INSERT INTO `insurance_companies` (`id`, `name`, `cms_id`, `ins_type_code`) VALUES (?, ?, '12345', 17)",
            [$this->companyId, 'Payer ' . $this->token]
        );
        (new PayerMap())->set($this->payer, $this->companyId, new \DateTimeImmutable(self::NOW));
        $this->price($this->code, '26', '125.50');
        $this->price($this->code, '', '300.00');
        $this->price($this->bareCode, '', '80.00');
    }

    protected function tearDown(): void
    {
        $pids = QueryUtils::fetchTableColumn('SELECT `pid` FROM `patient_data` WHERE `pubpid` LIKE ?', 'pid', ['MRT' . $this->token . '%']);
        foreach ($pids as $pid) {
            $contactIds = QueryUtils::fetchTableColumn(
                "SELECT `id` FROM `contact` WHERE `foreign_table_name` = 'patient_data' AND `foreign_id` = ?",
                'id',
                [$pid]
            );
            foreach ($contactIds as $contactId) {
                QueryUtils::sqlStatementThrowException(
                    'DELETE FROM `addresses` WHERE `id` IN (SELECT `address_id` FROM `contact_address` WHERE `contact_id` = ?)',
                    [$contactId]
                );
                QueryUtils::sqlStatementThrowException('DELETE FROM `contact_address` WHERE `contact_id` = ?', [$contactId]);
                QueryUtils::sqlStatementThrowException('DELETE FROM `contact` WHERE `id` = ?', [$contactId]);
            }
            foreach (['billing', 'forms', 'form_encounter', 'insurance_data', 'employer_data', 'patient_data'] as $table) {
                QueryUtils::sqlStatementThrowException('DELETE FROM `' . $table . '` WHERE `pid` = ?', [$pid]);
            }
        }
        foreach ($this->batchIds as $id) {
            QueryUtils::sqlStatementThrowException('DELETE FROM `gifford_charge_line` WHERE `batch_id` = ?', [$id]);
            QueryUtils::sqlStatementThrowException('DELETE FROM `gifford_charge_batch` WHERE `id` = ?', [$id]);
        }
        foreach ($this->codeIds as $id) {
            QueryUtils::sqlStatementThrowException('DELETE FROM `prices` WHERE `pr_id` = ?', [$id]);
            QueryUtils::sqlStatementThrowException('DELETE FROM `codes` WHERE `id` = ?', [$id]);
        }
        QueryUtils::sqlStatementThrowException('DELETE FROM `users` WHERE `npi` IN (?, ?)', [$this->renderingNpi, $this->referringNpi]);
        QueryUtils::sqlStatementThrowException('DELETE FROM `insurance_companies` WHERE `id` = ?', [$this->companyId]);
        QueryUtils::sqlStatementThrowException('DELETE FROM `gifford_payer_map` WHERE `mnemonic` LIKE ?', ['%' . $this->token]);
        QueryUtils::sqlStatementThrowException('DELETE FROM `facility` WHERE `id` = ?', [$this->facilityId]);
        parent::tearDown();
    }

    public function testACleanLinePostsPatientPolicyEncounterAndCharge(): void
    {
        [$lineId] = $this->stage([$this->row(['chg_units' => '2', 'chg_mod1' => 'RT'])]);

        $result = $this->post();

        // Counts cover every pending line in the database; the assertions on this test's
        // own lines are what matter.
        $this->assertSame([1, 1, 1], [$result->posted, $result->encountersCreated, $result->patientsCreated]);
        $line = $this->line($lineId);
        $this->assertSame(['posted', '[]'], [$line['status'], $line['problems_json']]);

        $patient = QueryUtils::querySingleRow('SELECT * FROM `patient_data` WHERE `pubpid` = ?', [$this->mrn]);
        $this->assertIsArray($patient);
        $this->assertSame(self::text($patient['pid']), self::text($line['pid']));
        $this->assertSame(['Testpatient', 'Ann', '1964-03-25', 'Female'], [$patient['lname'], $patient['fname'], $patient['DOB'], $patient['sex']]);

        $policy = QueryUtils::querySingleRow('SELECT * FROM `insurance_data` WHERE `pid` = ?', [$line['pid']]);
        $this->assertIsArray($policy);
        $this->assertSame(
            ['primary', (string) $this->companyId, '38097', '2026-08-20', 'self', 'Testpatient'],
            [$policy['type'], self::text($policy['provider']), $policy['policy_number'], $policy['date'], $policy['subscriber_relationship'], $policy['subscriber_lname']]
        );

        $encounter = QueryUtils::querySingleRow('SELECT * FROM `form_encounter` WHERE `pid` = ? AND `encounter` = ?', [$line['pid'], $line['encounter']]);
        $this->assertIsArray($encounter);
        $this->assertSame(
            ['2026-08-20 00:00:00', 'GH-' . $this->token, '50', (string) $this->facilityId, (string) $this->facilityId, (string) $this->providerId],
            [$encounter['date'], $encounter['external_id'], self::text($encounter['pos_code']), self::text($encounter['facility_id']), self::text($encounter['billing_facility']), self::text($encounter['provider_id'])]
        );
        $referrer = QueryUtils::querySingleRow('SELECT * FROM `users` WHERE `id` = ?', [$encounter['referring_provider_id']]);
        $this->assertIsArray($referrer);
        $this->assertSame(['Sender', 'Sue', $this->referringNpi, '0', 'external_provider'], [$referrer['lname'], $referrer['fname'], $referrer['npi'], self::text($referrer['authorized']), $referrer['abook_type']]);
        $this->assertCount(1, QueryUtils::fetchRecords("SELECT 1 FROM `forms` WHERE `encounter` = ? AND `formdir` = 'newpatient'", [$line['encounter']]));

        $billing = QueryUtils::fetchRecords(
            'SELECT `code_type`, `code`, `modifier`, `units`, `fee`, `justify`, `billed`, `activity`, `provider_id`, `ndc_info`'
            . ' FROM `billing` WHERE `encounter` = ? ORDER BY `id`',
            [$line['encounter']]
        );
        $this->assertSame([
            ['ICD10', 'I50.20', '', '1', '0.00', '', '0', '1', (string) $this->providerId, ''],
            ['ICD10', 'Z95.811', '', '1', '0.00', '', '0', '1', (string) $this->providerId, ''],
            ['CPT4', $this->code, '26:RT', '2', '251.00', 'ICD10|I50.20:ICD10|Z95.811:', '0', '1', (string) $this->providerId, ''],
        ], array_map(static fn (array $r): array => array_values(array_map(self::text(...), $r)), $billing));

        $this->assertSame(0, $this->post()->posted, 'a posted line is not posted again');
    }

    public function testAnUnmappedPayerHoldsTheLineUntilItIsMapped(): void
    {
        [$lineId] = $this->stage([$this->row(['ins1_mnemonic' => 'NEW' . $this->token, 'ins1_payer_name' => 'NEW PLAN'])]);

        $this->post();
        $this->assertSame('held', $this->line($lineId)['status']);
        $this->assertSame(['payer NEW' . $this->token . ' (NEW PLAN) is not mapped to an insurance company'], $this->problems($lineId));

        (new PayerMap())->set('NEW' . $this->token, $this->companyId, new \DateTimeImmutable(self::NOW));
        $this->assertSame(1, $this->post()->posted);
        $this->assertSame('posted', $this->line($lineId)['status']);
    }

    public function testPricesPickTheProfessionalComponentOrTheBareCodeOrHold(): void
    {
        [$bare, $none, $tc] = $this->stage([
            $this->row(['chg_cpt' => $this->bareCode, 'chg_accession' => 'B' . $this->token]),
            $this->row(['chg_cpt' => 'V' . substr($this->token, 0, 4), 'chg_accession' => 'C' . $this->token]),
            $this->row(['chg_mod1' => 'TC', 'chg_accession' => 'D' . $this->token]),
        ]);

        $this->assertSame(1, $this->post()->posted);
        $this->assertSame(['posted', 'held', 'held'], [$this->line($bare)['status'], $this->line($none)['status'], $this->line($tc)['status']]);
        $this->assertSame(['', '80.00'], self::text2(QueryUtils::querySingleRow('SELECT `modifier`, `fee` FROM `billing` WHERE `id` = ?', [$this->line($bare)['billing_id']])));
        $this->assertSame(['no price for CPT4 V' . substr($this->token, 0, 4)], $this->problems($none));
        $this->assertSame(['modifier TC: the technical component is Gifford\'s to bill'], $this->problems($tc));
    }

    public function testAnUnknownRenderingProviderOrMixedPlacesOfServiceHold(): void
    {
        [$stranger] = $this->stage([$this->row(['prov_rend_npi' => '1111111112', 'encounter_num' => 'GH-X' . $this->token])]);
        [$office, $hospital] = $this->stage([
            $this->row(['chg_pos' => '11', 'chg_accession' => 'E' . $this->token]),
            $this->row(['chg_pos' => '22', 'chg_accession' => 'F' . $this->token]),
        ]);

        $this->post();
        $this->assertSame(['held', 'held', 'held'], [$this->line($stranger)['status'], $this->line($office)['status'], $this->line($hospital)['status']]);
        $this->assertSame(['rendering NPI 1111111112 is not a provider on this site'], $this->problems($stranger));
        $this->assertSame(['the visit\'s lines have different places of service: 11, 22'], $this->problems($office));
        $this->assertSame(['the visit\'s lines have different places of service: 11, 22'], $this->problems($hospital));
    }

    public function testALaterFileAddsToTheVisitAndCatchesDuplicatesAndCorrections(): void
    {
        [$first] = $this->stage([$this->row()]);
        $this->post();
        $encounter = $this->line($first)['encounter'];

        [$same, $changed, $another] = $this->stage([
            $this->row(),
            $this->row(['chg_units' => '3']),
            $this->row(['chg_cpt' => $this->bareCode, 'chg_accession' => 'G' . $this->token]),
        ]);
        $result = $this->post();

        $this->assertSame([1, 1, 0], [$result->posted, $result->duplicates, $result->encountersCreated]);
        $this->assertSame('held', $this->line($changed)['status']);
        $this->assertSame('duplicate', $this->line($same)['status']);
        $this->assertSame(
            ['corrected re-send? accession A' . $this->token . ' was posted from an earlier file as ' . $this->code . ' x1; post this line too, or skip it'],
            $this->problems($changed)
        );
        $this->assertSame($encounter, $this->line($another)['encounter'], 'the same visit and date reuse the encounter');
        $this->assertCount(2, QueryUtils::fetchRecords("SELECT 1 FROM `billing` WHERE `encounter` = ? AND `code_type` = 'ICD10'", [$encounter]), 'diagnoses are not added twice');

        // A person releases the corrected line.
        QueryUtils::sqlStatementThrowException('UPDATE `gifford_charge_line` SET `released` = 1 WHERE `id` = ?', [$changed]);
        $this->assertSame(1, $this->post()->posted);
        $this->assertSame('posted', $this->line($changed)['status']);
    }

    public function testTheSameCodeTwiceInOneFileIsHeldAsARepeatUntilPostedWith76(): void
    {
        // Two chest x-rays the same day, sent without 76/77: a repeat, or a duplicate.
        [$first, $second] = $this->stage([$this->row(), $this->row()]);

        $result = $this->post();

        $this->assertSame([1, 0], [$result->posted, $result->duplicates]);
        $this->assertSame(['posted', 'held'], [$this->line($first)['status'], $this->line($second)['status']]);
        $this->assertSame([$this->repeatProblem('A' . $this->token)], $this->problems($second));

        // A person posts it as a repeat by the same physician.
        QueryUtils::sqlStatementThrowException(
            "UPDATE `gifford_charge_line` SET `released` = 1, `added_modifier` = '76' WHERE `id` = ?",
            [$second]
        );
        $this->assertSame(1, $this->post()->posted);
        $this->assertSame(
            ['26:76', '125.50'],
            self::text2(QueryUtils::querySingleRow('SELECT `modifier`, `fee` FROM `billing` WHERE `id` = ?', [$this->line($second)['billing_id']]))
        );
    }

    public function testARepeatGiffordMarkedWith76Posts(): void
    {
        [$first, $second] = $this->stage([$this->row(), $this->row(['chg_mod1' => '76'])]);

        $this->assertSame(2, $this->post()->posted);
        $this->assertSame(
            ['26', '26:76'],
            [
                self::text2(QueryUtils::querySingleRow('SELECT `modifier` FROM `billing` WHERE `id` = ?', [$this->line($first)['billing_id']]))[0] ?? '',
                self::text2(QueryUtils::querySingleRow('SELECT `modifier` FROM `billing` WHERE `id` = ?', [$this->line($second)['billing_id']]))[0] ?? '',
            ]
        );
    }

    public function testTheSameCodeOnAnotherAccessionFromALaterFileIsHeldAsARepeat(): void
    {
        $this->stage([$this->row()]);
        $this->post();
        [$later] = $this->stage([$this->row(['chg_accession' => 'Z' . $this->token])]);

        $this->post();

        $this->assertSame('held', $this->line($later)['status']);
        $this->assertSame([$this->repeatProblem('A' . $this->token)], $this->problems($later));
    }

    public function testTwoCodesOnOneAccessionInOneFileBothPost(): void
    {
        [$study, $other] = $this->stage([$this->row(), $this->row(['chg_cpt' => $this->bareCode])]);

        $this->assertSame(2, $this->post()->posted);
        $this->assertSame(['posted', 'posted'], [$this->line($study)['status'], $this->line($other)['status']]);

        // A line added to that file later with one of those codes again is a possible
        // repeat, and the file's other code is not taken for a corrected re-send.
        $repeat = QueryUtils::sqlInsert(
            "INSERT INTO `gifford_charge_line` (`batch_id`, `line_number`, `visit_number`, `mrn`, `accession`, `date_of_service`,"
            . " `code`, `raw_json`, `problems_json`, `status`, `updated_at`)"
            . " SELECT `batch_id`, 9, `visit_number`, `mrn`, `accession`, `date_of_service`, `code`, `raw_json`, '[]', 'staged', `updated_at`"
            . " FROM `gifford_charge_line` WHERE `id` = ?",
            [$other]
        );
        $this->post();
        $this->assertSame('held', $this->line($repeat)['status']);
        $this->assertSame(
            ['repeat procedure? CPT4 ' . $this->bareCode . ' is also on accession A' . $this->token . ' for this patient on 2026-08-20;'
                . ' post it with modifier 76 (same physician) or 77 (another physician), or skip it if it is a duplicate'],
            $this->problems($repeat)
        );
    }

    public function testANewPatientGetsTheGuarantorAndEmployer(): void
    {
        [$line] = $this->stage([$this->row(self::guarantorAndEmployer())]);

        $this->post();

        $pid = $this->line($line)['pid'];
        $this->assertSame(
            ['Pat Parent', '9 Elm St', 'Barre', 'VT', '05641'],
            self::text2(QueryUtils::querySingleRow(
                'SELECT `guardiansname`, `guardianaddress`, `guardiancity`, `guardianstate`, `guardianpostalcode` FROM `patient_data` WHERE `pid` = ?',
                [$pid]
            ))
        );
        $this->assertSame(
            [['name' => 'Target', 'street' => '1 Store Rd', 'city' => 'Berlin']],
            QueryUtils::fetchRecords('SELECT `name`, `street`, `city` FROM `employer_data` WHERE `pid` = ?', [$pid])
        );
    }

    public function testANewerFileUpdatesThePatientAndKeepsTheOldAddress(): void
    {
        [$first] = $this->stage([$this->row(self::guarantorAndEmployer())]);
        $this->post();
        $pid = $this->line($first)['pid'];

        $this->stage([$this->row([
            'encounter_num' => 'GH-2' . $this->token,
            'chg_dos' => '09/10/2026',
            'enc_admit_date' => '09/10/2026',
            'chg_accession' => 'N' . $this->token,
            'pat_addr1' => '2 New Rd',
            'pat_city' => 'Bethel',
            'pat_zip' => '05032',
            'pat_phone' => '802-555-0100',
            // The patient as guarantor, first and last swapped as in some of Gifford's rows.
            'guar_last' => 'Ann',
            'guar_first' => 'Testpatient',
            'emp_name' => 'Retired',
        ])]);
        $this->post();

        $this->assertSame(
            ['2 New Rd', 'Bethel', '05032', '802-555-0100', ''],
            self::text2(QueryUtils::querySingleRow(
                'SELECT `street`, `city`, `postal_code`, `phone_home`, `guardiansname` FROM `patient_data` WHERE `pid` = ?',
                [$pid]
            ))
        );
        $this->assertSame(
            [['line1' => '1 Main St', 'city' => 'Randolph', 'zip' => '05060', 'use' => 'old', 'status' => 'I', 'inactivated_reason' => 'Moved']],
            QueryUtils::fetchRecords(
                'SELECT a.`line1`, a.`city`, a.`zip`, ca.`use`, ca.`status`, ca.`inactivated_reason` FROM `contact` AS c'
                . ' JOIN `contact_address` AS ca ON ca.`contact_id` = c.`id` JOIN `addresses` AS a ON a.`id` = ca.`address_id`'
                . " WHERE c.`foreign_table_name` = 'patient_data' AND c.`foreign_id` = ?",
                [$pid]
            )
        );
        $this->assertSame(
            ['Target', 'Retired'],
            QueryUtils::fetchTableColumn('SELECT `name` FROM `employer_data` WHERE `pid` = ? ORDER BY `id`', 'name', [$pid])
        );
    }

    public function testAnOlderFileArrivingLateLeavesThePatientAlone(): void
    {
        [$newer] = $this->stage([$this->row([
            'chg_dos' => '09/10/2026',
            'enc_admit_date' => '09/10/2026',
            'pat_addr1' => '2 New Rd',
            'pat_city' => 'Bethel',
        ])]);
        $this->post();
        $pid = $this->line($newer)['pid'];

        $this->stage([$this->row(['encounter_num' => 'GH-0' . $this->token, 'chg_accession' => 'O' . $this->token])]);
        $this->post();

        $this->assertSame(['2 New Rd', 'Bethel'], self::text2(QueryUtils::querySingleRow('SELECT `street`, `city` FROM `patient_data` WHERE `pid` = ?', [$pid])));
        $this->assertSame([], QueryUtils::fetchRecords("SELECT 1 FROM `contact` WHERE `foreign_table_name` = 'patient_data' AND `foreign_id` = ?", [$pid]));
    }

    public function testANewPayerStartsANewPolicyAndEndsTheOldOne(): void
    {
        [$first] = $this->stage([$this->row()]);
        $this->post();
        $pid = $this->line($first)['pid'];
        $this->stage([$this->row([
            'encounter_num' => 'GH-2' . $this->token,
            'chg_dos' => '09/10/2026',
            'enc_admit_date' => '09/10/2026',
            'chg_accession' => 'H' . $this->token,
            'ins1_member_id' => 'NEWID',
        ])]);

        $this->post();

        $policies = QueryUtils::fetchRecords('SELECT `policy_number`, `date`, `date_end` FROM `insurance_data` WHERE `pid` = ? ORDER BY `date`', [$pid]);
        $this->assertSame([
            ['policy_number' => '38097', 'date' => '2026-08-20', 'date_end' => '2026-09-09'],
            ['policy_number' => 'NEWID', 'date' => '2026-09-10', 'date_end' => null],
        ], $policies);
    }

    /**
     * @return array<string, string>
     */
    private static function guarantorAndEmployer(): array
    {
        return [
            'guar_last' => 'Parent',
            'guar_first' => 'Pat',
            'guar_addr1' => '9 Elm St',
            'guar_city' => 'Barre',
            'guar_state' => 'VT',
            'guar_zip' => '05641',
            'emp_name' => 'Target',
            'emp_addr1' => '1 Store Rd',
            'emp_city' => 'Berlin',
            'emp_state' => 'VT',
            'emp_zip' => '05602',
        ];
    }

    private function repeatProblem(string $otherAccession): string
    {
        return 'repeat procedure? CPT4 ' . $this->code . ' is also on accession ' . $otherAccession . ' for this patient on 2026-08-20;'
            . ' post it with modifier 76 (same physician) or 77 (another physician), or skip it if it is a duplicate';
    }

    private function post(): PostResult
    {
        $settings = PostSettings::load($this->facilityId, $this->facilityId);
        $this->assertNotNull($settings);

        return $this->poster()->run($settings);
    }

    private function poster(): ChargePoster
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable(self::NOW));

        return new ChargePoster(new ChargeLineRepository(), new ChargeRowParser(), new PayerMap(), new PriceBook(), new SiteWriter(), $clock);
    }

    /**
     * Save the rows as one new batch.
     *
     * @param list<array<string, string>> $rows
     * @return list<int> the lines' ids, in row order
     */
    private function stage(array $rows): array
    {
        $parser = new ChargeRowParser();
        $lines = [];
        foreach ($rows as $i => $values) {
            $raw = new RawRow($i + 2, $values);
            $lines[] = StagedLine::fromRow($raw, $parser->parse($raw));
        }
        $batchId = (new ChargeBatchRepository())->saveStagedBatch('RAD_TEST.xlsx', bin2hex(random_bytes(32)), new \DateTimeImmutable(self::NOW), $lines);
        $this->batchIds[] = $batchId;

        return array_map(
            static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0,
            QueryUtils::fetchTableColumn('SELECT `id` FROM `gifford_charge_line` WHERE `batch_id` = ? ORDER BY `line_number`', 'id', [$batchId])
        );
    }

    /**
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    private function row(array $overrides = []): array
    {
        return array_merge([
            'encounter_num' => 'GH-' . $this->token,
            'pat_mrn' => $this->mrn,
            'pat_last' => 'Testpatient',
            'pat_first' => 'Ann',
            'pat_sex' => 'F',
            'pat_dob' => '03/25/1964',
            'pat_addr1' => '1 Main St',
            'pat_city' => 'Randolph',
            'pat_state' => 'VT',
            'pat_zip' => '05060',
            'enc_admit_date' => '08/20/2026',
            'enc_dx_codes' => 'I50.20,Z95.811',
            'prov_rend_name' => 'Reader, Ray',
            'prov_rend_npi' => $this->renderingNpi,
            'prov_ref_name' => 'Sender, Sue',
            'prov_ref_npi' => $this->referringNpi,
            'chg_dos' => '08/20/2026',
            'chg_cpt' => $this->code,
            'chg_hcpcs' => '',
            'chg_units' => '1',
            'chg_pos' => '50',
            'chg_accession' => 'A' . $this->token,
            'chg_location' => 'GHC.TEST',
            'ins1_rank' => '1',
            'ins1_mnemonic' => $this->payer,
            'ins1_payer_name' => 'TEST PAYER',
            'ins1_member_id' => '38097',
            'ins1_sub_name' => 'Testpatient, Ann',
            'ins1_sub_dob' => '03/25/1964',
            'ins1_sub_gender' => 'F',
            'ins1_relate' => '18',
        ], $overrides);
    }

    private function price(string $code, string $modifier, string $fee): void
    {
        $ctId = QueryUtils::fetchSingleValue("SELECT `ct_id` FROM `code_types` WHERE `ct_key` = 'CPT4'", 'ct_id');
        $id = QueryUtils::sqlInsert(
            'INSERT INTO `codes` (`code_text`, `code`, `code_type`, `modifier`, `units`, `fee`, `active`) VALUES (?, ?, ?, ?, 1, ?, 1)',
            ['Test study ' . $code, $code, $ctId, $modifier, $fee]
        );
        QueryUtils::sqlStatementThrowException(
            "INSERT INTO `prices` (`pr_id`, `pr_selector`, `pr_level`, `pr_price`) VALUES (?, '', 'standard', ?)",
            [$id, $fee]
        );
        $this->codeIds[] = $id;
    }

    /**
     * @return array<mixed>
     */
    private function line(int $id): array
    {
        $row = QueryUtils::querySingleRow('SELECT * FROM `gifford_charge_line` WHERE `id` = ?', [$id]);
        $this->assertIsArray($row);

        return $row;
    }

    /**
     * @return list<string>
     */
    private function problems(int $id): array
    {
        $decoded = json_decode(self::text($this->line($id)['problems_json']), true);
        $this->assertIsArray($decoded);

        return array_values(array_map(self::text(...), $decoded));
    }

    /**
     * @return list<string>
     */
    private static function text2(mixed $row): array
    {
        return is_array($row) ? array_values(array_map(self::text(...), $row)) : [];
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
