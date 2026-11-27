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

use Cmsvt\OpenEMR\Modules\GiffordCharges\Dx\DxDecision;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Dx\DxRepository;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Dx\DxRule;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Dx\PendingDx;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Measure\Measure;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Measure\MeasureRepository;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Measure\MeasureSuggestion;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Parse\ChargeRowParser;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Payer\PayerMap;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Post\AddOnCodes;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Post\ChargeLineRepository;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Post\ChargePoster;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Post\PostResult;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Post\PostSettings;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Post\PriceBook;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Post\SiteWriter;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Reader\RawRow;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Review\ReviewRepository;
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

        // Also when the tables are from before the newest column: table.sql adds what is missing.
        if (
            !QueryUtils::existsTable('gifford_measure_suggestion')
            || QueryUtils::fetchRecords("SHOW COLUMNS FROM `gifford_charge_line` LIKE 'unique_id'") === []
        ) {
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
            QueryUtils::sqlStatementThrowException('DELETE FROM `claims` WHERE `patient_id` = ?', [$pid]);
            QueryUtils::sqlStatementThrowException('DELETE FROM `form_misc_billing_options` WHERE `pid` = ?', [$pid]);
            foreach (['billing', 'forms', 'form_encounter', 'insurance_data', 'employer_data', 'patient_data'] as $table) {
                QueryUtils::sqlStatementThrowException('DELETE FROM `' . $table . '` WHERE `pid` = ?', [$pid]);
            }
        }
        foreach ($this->batchIds as $id) {
            QueryUtils::sqlStatementThrowException(
                'DELETE FROM `gifford_measure` WHERE `line_id` IN (SELECT `id` FROM `gifford_charge_line` WHERE `batch_id` = ?)',
                [$id]
            );
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
        QueryUtils::sqlStatementThrowException('DELETE FROM `icd10_dx_order_code` WHERE `short_desc` = ?', ['Test ' . $this->token]);
        QueryUtils::sqlStatementThrowException('DELETE FROM `gifford_cci_edit` WHERE `column1` IN (?, ?)', [$this->code, $this->bareCode]);
        QueryUtils::sqlStatementThrowException('DELETE FROM `gifford_dx` WHERE `accession` LIKE ?', ['%' . $this->token]);
        QueryUtils::sqlStatementThrowException('DELETE FROM `gifford_measure_suggestion` WHERE `accession` LIKE ?', ['%' . $this->token]);
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

    public function testDiagnosesGetTheCodeSetsDescriptionAsTheLoaderStoresIt(): void
    {
        // Made-up codes, stored as core's ICD-10 loader stores them: dx_code padded to the
        // order file's width (so a three-character code gets a dotted, padded
        // formatted_dx_code) and long_desc with the file's line ending.
        $three = 'U8' . $this->token[0];
        $longer = $three . '.' . substr($this->token, 1, 2);
        foreach ([[$three, 'Three character test'], [str_replace('.', '', $longer), 'Longer test']] as [$code, $text]) {
            QueryUtils::sqlStatementThrowException(
                'INSERT INTO `icd10_dx_order_code` (`dx_code`, `formatted_dx_code`, `valid_for_coding`, `short_desc`, `long_desc`, `active`, `revision`)'
                . " VALUES (?, ?, '1', ?, ?, 1, 1)",
                [
                    str_pad($code, 7),
                    str_pad(substr($code, 0, 3) . '.' . substr($code, 3), 10),
                    'Test ' . $this->token,
                    $text . "\r\n",
                ]
            );
        }
        [$lineId] = $this->stage([$this->row(['enc_dx_codes' => $three . ',' . $longer])]);

        $this->post();

        $this->assertSame(
            [[$three, 'Three character test'], [$longer, 'Longer test']],
            array_map(
                static fn (array $r): array => array_values(array_map(self::text(...), $r)),
                QueryUtils::fetchRecords(
                    "SELECT `code`, `code_text` FROM `billing` WHERE `encounter` = ? AND `code_type` = 'ICD10' ORDER BY `id`",
                    [$this->line($lineId)['encounter']]
                )
            )
        );
    }

    public function testASelfPayVisitIsMarkedBilledWithNoClaimAndAVisitWithNoPayerNamedIsNot(): void
    {
        [$selfPay, $noPayer] = $this->stage([
            $this->row(['ins1_mnemonic' => 'SP', 'ins1_payer_name' => 'SELF PAY']),
            $this->row([
                // Another day, so the same code is not taken for a repeat procedure.
                'encounter_num' => 'GN-' . $this->token,
                'enc_admit_date' => '08/21/2026',
                'chg_dos' => '08/21/2026',
                'chg_accession' => 'N' . $this->token,
                'ins1_mnemonic' => '',
                'ins1_payer_name' => '',
            ]),
        ]);

        $this->post();

        $cleared = $this->line($selfPay);
        $waiting = $this->line($noPayer);
        $this->assertSame(['posted', 'posted'], [$cleared['status'], $waiting['status']]);
        $this->assertNotSame(self::text($cleared['encounter']), self::text($waiting['encounter']));
        $this->assertSame([], QueryUtils::fetchRecords('SELECT 1 FROM `insurance_data` WHERE `pid` = ?', [$cleared['pid']]), 'self pay adds no policy');

        // Self pay: every charge row of the visit is billed today, to no payer, and the
        // encounter has the claims row Mark as Cleared would have written.
        $billing = QueryUtils::fetchRecords(
            'SELECT `billed`, `payer_id`, `bill_date` IS NOT NULL AS `dated` FROM `billing` WHERE `encounter` = ? AND `activity` = 1',
            [$cleared['encounter']]
        );
        $this->assertCount(3, $billing);
        foreach ($billing as $row) {
            $this->assertSame(['1', '0', '1'], array_values(array_map(self::text(...), $row)));
        }
        $claims = QueryUtils::fetchRecords(
            'SELECT `version`, `status`, `payer_id`, `payer_type`, `bill_time` IS NOT NULL AS `timed` FROM `claims` WHERE `patient_id` = ? AND `encounter_id` = ?',
            [$cleared['pid'], $cleared['encounter']]
        );
        $this->assertSame([['1', '2', '0', '0', '1']], array_map(static fn (array $r): array => array_values(array_map(self::text(...), $r)), $claims));

        // No payer named is not self pay: the charges wait in the Billing Manager.
        $unbilled = QueryUtils::fetchTableColumn('SELECT `billed` FROM `billing` WHERE `encounter` = ? AND `activity` = 1', 'billed', [$waiting['encounter']]);
        $this->assertSame(['0', '0', '0'], array_map(self::text(...), $unbilled));
        $this->assertSame([], QueryUtils::fetchRecords('SELECT 1 FROM `claims` WHERE `patient_id` = ? AND `encounter_id` = ?', [$waiting['pid'], $waiting['encounter']]));
    }

    public function testASelfPayVisitOfAPatientInsuredThatDayIsLeftForAPerson(): void
    {
        // The insured visit gives the patient a policy from 08/20; Gifford sends the
        // next day's visit as self pay.
        [$insured, $selfPay] = $this->stage([
            $this->row(),
            $this->row([
                'encounter_num' => 'GS-' . $this->token,
                'enc_admit_date' => '08/21/2026',
                'chg_dos' => '08/21/2026',
                'chg_accession' => 'S' . $this->token,
                'ins1_mnemonic' => 'SP',
                'ins1_payer_name' => 'SELF PAY',
            ]),
        ]);

        $this->post();

        $line = $this->line($selfPay);
        $this->assertSame(['posted', 'posted'], [$this->line($insured)['status'], $line['status']]);
        $unbilled = QueryUtils::fetchTableColumn('SELECT `billed` FROM `billing` WHERE `encounter` = ? AND `activity` = 1', 'billed', [$line['encounter']]);
        $this->assertSame(['0', '0', '0'], array_map(self::text(...), $unbilled));
        $this->assertSame([], QueryUtils::fetchRecords('SELECT 1 FROM `claims` WHERE `patient_id` = ? AND `encounter_id` = ?', [$line['pid'], $line['encounter']]));
    }

    public function testAnAccidentVisitGetsItsMiscBillingOptionsAndAnotherVisitGetsNone(): void
    {
        [$workComp, $plain] = $this->stage([
            $this->row(['enc_workcomp' => 'Y', 'enc_accident_date' => '08/01/2026']),
            $this->row([
                // Another day, so the same code is not taken for a repeat procedure.
                'encounter_num' => 'GP-' . $this->token,
                'enc_admit_date' => '08/21/2026',
                'chg_dos' => '08/21/2026',
                'chg_accession' => 'P' . $this->token,
            ]),
        ]);

        $this->post();

        $accident = $this->line($workComp);
        $other = $this->line($plain);
        $this->assertSame(['posted', 'posted'], [$accident['status'], $other['status']]);

        // The form the 837P reads: one row, linked to the encounter as a saved form is.
        $options = QueryUtils::fetchRecords(
            'SELECT m.`employment_related`, m.`auto_accident`, m.`other_accident`, m.`accident_state`, m.`box_15_date_qual`,'
            . ' m.`date_initial_treatment`, m.`pid`, f.`form_name`, f.`deleted`'
            . ' FROM `forms` AS f JOIN `form_misc_billing_options` AS m ON m.`id` = f.`form_id`'
            . " WHERE f.`encounter` = ? AND f.`formdir` = 'misc_billing_options'",
            [$accident['encounter']]
        );
        $this->assertSame(
            [['1', '0', '0', '', '439', '2026-08-01', self::text($accident['pid']), 'Misc Billing Options', '0']],
            array_map(static fn (array $r): array => array_values(array_map(self::text(...), $r)), $options)
        );

        $this->assertSame([], QueryUtils::fetchRecords(
            "SELECT 1 FROM `forms` WHERE `encounter` = ? AND `formdir` = 'misc_billing_options'",
            [$other['encounter']]
        ));
    }

    public function testAnAccidentVisitWithNoAccidentDateIsHeldUntilAPersonPostsItAnyway(): void
    {
        [$lineId] = $this->stage([$this->row(['enc_workcomp' => 'Y'])]);

        $this->assertSame(0, $this->post()->posted);
        $this->assertSame('held', $this->line($lineId)['status']);
        $this->assertSame(
            ["accident claim: Gifford did not send the accident date; post it anyway and add it on the encounter's Misc Billing Options, or skip it"],
            $this->problems($lineId)
        );

        // A person posts it anyway: the form has the cause and waits for its date.
        QueryUtils::sqlStatementThrowException('UPDATE `gifford_charge_line` SET `released` = 1 WHERE `id` = ?', [$lineId]);
        $this->assertSame(1, $this->post()->posted);
        $line = $this->line($lineId);
        $this->assertSame('posted', $line['status']);
        $options = QueryUtils::fetchRecords(
            'SELECT m.`employment_related`, m.`box_15_date_qual`, m.`date_initial_treatment`'
            . ' FROM `forms` AS f JOIN `form_misc_billing_options` AS m ON m.`id` = f.`form_id`'
            . " WHERE f.`encounter` = ? AND f.`formdir` = 'misc_billing_options'",
            [$line['encounter']]
        );
        $this->assertSame([['1', '', '']], array_map(static fn (array $r): array => array_values(array_map(self::text(...), $r)), $options));
    }

    public function testAChargeTakesTheVisitsFirstTwoDiagnoses(): void
    {
        [$first, $second, $third] = $this->diagnosisCodes(3);
        [$lineId] = $this->stage([$this->row(['enc_dx_codes' => "$first,$second,$third"])]);

        $this->post();

        $this->assertSame('posted', $this->line($lineId)['status']);
        $this->assertSame(
            [['ICD10', $first, ''], ['ICD10', $second, ''], ['CPT4', $this->code, "ICD10|$first:ICD10|$second:"]],
            $this->billed($lineId)
        );
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
        $this->price('73030', '26', '25.00');
        [$bare, $none, $tc, $gz] = $this->stage([
            $this->row(['chg_cpt' => $this->bareCode, 'chg_accession' => 'B' . $this->token]),
            $this->row(['chg_cpt' => 'V' . substr($this->token, 0, 4), 'chg_accession' => 'C' . $this->token]),
            // Gifford's TC says its own claim is technical only: the read is billed with 26.
            $this->row(['chg_cpt' => '73030', 'chg_mod1' => 'tc', 'chg_mod2' => 'LT', 'chg_accession' => 'D' . $this->token]),
            $this->row(['chg_mod1' => 'RT', 'chg_mod2' => 'gz', 'chg_accession' => 'E' . $this->token]),
        ]);

        $this->assertSame(2, $this->post()->posted);
        $this->assertSame(['posted', 'held', 'posted', 'held'], [$this->line($bare)['status'], $this->line($none)['status'], $this->line($tc)['status'], $this->line($gz)['status']]);
        $this->assertSame(
            '26:LT',
            self::text(QueryUtils::fetchSingleValue('SELECT `modifier` FROM `billing` WHERE `id` = ?', 'modifier', [$this->line($tc)['billing_id']]))
        );
        $this->assertSame(['modifier GZ: Gifford expects a denial as not medically necessary, with no ABN; post it with GZ, or skip it'], $this->problems($gz));

        // A person releases the GZ line: it posts with the GZ kept, after the 26 and the side.
        QueryUtils::sqlStatementThrowException('UPDATE `gifford_charge_line` SET `released` = 1 WHERE `id` = ?', [$gz]);
        $this->assertSame(1, $this->post()->posted);
        $this->assertSame('posted', $this->line($gz)['status']);
        $this->assertSame(
            '26:RT:GZ',
            self::text(QueryUtils::fetchSingleValue('SELECT `modifier` FROM `billing` WHERE `id` = ?', 'modifier', [$this->line($gz)['billing_id']]))
        );
        $this->assertSame(['', '80.00'], self::text2(QueryUtils::querySingleRow('SELECT `modifier`, `fee` FROM `billing` WHERE `id` = ?', [$this->line($bare)['billing_id']])));
        $this->assertSame(['no price for CPT4 V' . substr($this->token, 0, 4)], $this->problems($none));
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

    public function testALineWithNoReferringNpiIsHeldUntilAPersonPostsItAnyway(): void
    {
        [$blank, $short] = $this->stage([
            $this->row(['prov_ref_npi' => '']),
            $this->row(['prov_ref_npi' => '12345', 'encounter_num' => 'V2' . $this->token, 'chg_accession' => 'R' . $this->token, 'chg_dos' => '08/21/2026']),
        ]);

        $this->assertSame(0, $this->post()->posted);
        $this->assertSame(['held', 'held'], [$this->line($blank)['status'], $this->line($short)['status']]);
        $this->assertSame(
            ['no referring provider: prov_ref_npi "" is not a 10-digit NPI; post it anyway and add the referring provider on the encounter, or skip it'],
            $this->problems($blank)
        );
        $this->assertSame(
            ['no referring provider: prov_ref_npi "12345" is not a 10-digit NPI; post it anyway and add the referring provider on the encounter, or skip it'],
            $this->problems($short)
        );

        // A person posts the first anyway: its encounter has no referring provider.
        QueryUtils::sqlStatementThrowException('UPDATE `gifford_charge_line` SET `released` = 1 WHERE `id` = ?', [$blank]);
        $this->assertSame(1, $this->post()->posted);
        $this->assertSame(['posted', 'held'], [$this->line($blank)['status'], $this->line($short)['status']]);
        $encounter = QueryUtils::querySingleRow(
            'SELECT `referring_provider_id` FROM `form_encounter` WHERE `pid` = ? AND `encounter` = ?',
            [$this->line($blank)['pid'], $this->line($blank)['encounter']]
        );
        $this->assertIsArray($encounter);
        $this->assertSame('0', self::text($encounter['referring_provider_id']));
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

    public function testTheColumn2CodeOfAnNcciPairPostsWith59(): void
    {
        $this->cciEdit($this->code, $this->bareCode, '1');
        [$column1, $column2, $otherDay, $distinct] = $this->stage([
            $this->row(),
            $this->row(['chg_cpt' => $this->bareCode, 'chg_mod1' => 'RT', 'chg_accession' => 'H' . $this->token]),
            // The same pair on another day, the column 2 code alone that day: no 59.
            $this->row(['chg_cpt' => $this->bareCode, 'chg_dos' => '08/21/2026', 'enc_admit_date' => '08/21/2026', 'chg_accession' => 'I' . $this->token]),
            // Gifford's own XU is kept and nothing is added.
            $this->row(['chg_dos' => '08/22/2026', 'enc_admit_date' => '08/22/2026', 'chg_accession' => 'J' . $this->token]),
        ]);
        [$withXu] = $this->stage([
            $this->row(['chg_cpt' => $this->bareCode, 'chg_mod1' => 'XU', 'chg_dos' => '08/22/2026', 'enc_admit_date' => '08/22/2026', 'chg_accession' => 'K' . $this->token]),
        ]);

        $this->assertSame(5, $this->post()->posted);
        $this->assertSame(
            ['26', 'RT:59', '', '26', 'XU'],
            array_map($this->billedModifier(...), [$column1, $column2, $otherDay, $distinct, $withXu])
        );
    }

    public function testAnNcciPairThatAllowsNoModifierHoldsTheColumn2Line(): void
    {
        $this->cciEdit($this->code, $this->bareCode, '0');
        [$column1, $column2] = $this->stage([
            $this->row(),
            $this->row(['chg_cpt' => $this->bareCode, 'chg_accession' => 'H' . $this->token]),
        ]);

        $this->assertSame(1, $this->post()->posted);
        $this->assertSame(['posted', 'held'], [$this->line($column1)['status'], $this->line($column2)['status']]);
        $this->assertSame(
            ['NCCI: ' . $this->bareCode . ' is not billed with ' . $this->code . ' for this patient on 2026-08-20 (no modifier allowed); post it anyway, or skip it'],
            $this->problems($column2)
        );

        // A person posts it anyway: as sent, no 59.
        QueryUtils::sqlStatementThrowException('UPDATE `gifford_charge_line` SET `released` = 1 WHERE `id` = ?', [$column2]);
        $this->assertSame(1, $this->post()->posted);
        $this->assertSame('', $this->billedModifier($column2));
    }

    public function testAPostedColumn2CodeMissingIts59HoldsTheNewLineUntilItIsAdded(): void
    {
        $this->cciEdit($this->code, $this->bareCode, '1');
        [$column2] = $this->stage([$this->row(['chg_cpt' => $this->bareCode, 'chg_accession' => 'H' . $this->token])]);
        $this->assertSame(1, $this->post()->posted);
        $this->assertSame('', $this->billedModifier($column2), 'alone that day, it posted without 59');

        [$column1] = $this->stage([$this->row()]);
        $this->assertSame(0, $this->post()->posted);
        $this->assertSame(
            ['NCCI: ' . $this->bareCode . ', already posted for this patient on 2026-08-20, needs modifier 59 to be billed with ' . $this->code
                . '; add it in the fee sheet and this line posts on the next run, or post it anyway, or skip it'],
            $this->problems($column1)
        );

        // A person adds 59 to the earlier charge in the fee sheet.
        QueryUtils::sqlStatementThrowException("UPDATE `billing` SET `modifier` = '59' WHERE `id` = ?", [$this->line($column2)['billing_id']]);
        $this->assertSame(1, $this->post()->posted);
        $this->assertSame('26', $this->billedModifier($column1));
    }

    public function testAnNcciPairOnOppositeSidesStillPostsWith59(): void
    {
        // Stephen 2026-11-18: LT and RT don't replace the 59; it goes after the side
        // (73223-LT with 73221-RT: 73221 as 26:RT:59).
        $this->cciEdit($this->code, $this->bareCode, '1');
        [$leftColumn1, $rightColumn2, $both1, $both2] = $this->stage([
            $this->row(['chg_mod1' => 'LT']),
            $this->row(['chg_cpt' => $this->bareCode, 'chg_mod1' => 'RT', 'chg_accession' => 'H' . $this->token]),
            $this->row(['chg_mod1' => '50', 'chg_dos' => '08/22/2026', 'enc_admit_date' => '08/22/2026', 'chg_accession' => 'K' . $this->token]),
            $this->row(['chg_cpt' => $this->bareCode, 'chg_mod1' => 'LT', 'chg_dos' => '08/22/2026', 'enc_admit_date' => '08/22/2026', 'chg_accession' => 'L' . $this->token]),
        ]);

        $this->assertSame(4, $this->post()->posted);
        $this->assertSame(
            ['26:LT', 'RT:59', '26:50', 'LT:59'],
            array_map($this->billedModifier(...), [$leftColumn1, $rightColumn2, $both1, $both2])
        );
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

    public function testTheSameCodeOnTheRightAndLeftPostsBoth(): void
    {
        // A shoulder x-ray on each side: two procedures, not a repeat.
        [$right, $left] = $this->stage([$this->row(['chg_mod1' => 'RT']), $this->row(['chg_mod1' => 'LT'])]);

        $this->assertSame(2, $this->post()->posted);
        $this->assertSame(
            ['26:RT', '26:LT'],
            [
                self::text2(QueryUtils::querySingleRow('SELECT `modifier` FROM `billing` WHERE `id` = ?', [$this->line($right)['billing_id']]))[0] ?? '',
                self::text2(QueryUtils::querySingleRow('SELECT `modifier` FROM `billing` WHERE `id` = ?', [$this->line($left)['billing_id']]))[0] ?? '',
            ]
        );

        // The left side again from a later file is a possible repeat of the left one only.
        [$again] = $this->stage([$this->row(['chg_mod1' => 'LT', 'chg_accession' => 'Z' . $this->token])]);
        $this->post();
        $this->assertSame('held', $this->line($again)['status']);
        $this->assertSame([$this->repeatProblem('A' . $this->token)], $this->problems($again));
    }

    public function testBilateralAfterOneSideIsHeldAsARepeat(): void
    {
        [, $both] = $this->stage([$this->row(['chg_mod1' => 'RT']), $this->row(['chg_mod1' => '50'])]);

        $this->post();

        $this->assertSame('held', $this->line($both)['status']);
        $this->assertSame([$this->repeatProblem('A' . $this->token)], $this->problems($both));
    }

    public function testAnAddOnWaitsForItsParent(): void
    {
        // The add-on ($bareCode) needs $code, which Gifford sends in a later file.
        $addOns = $this->addOns();
        [$addOn] = $this->stage([$this->row(['chg_cpt' => $this->bareCode])]);

        $this->post($addOns);
        $this->assertSame('held', $this->line($addOn)['status']);
        $this->assertSame(
            ['add-on code ' . $this->bareCode . ' is billed with ' . $this->code . ', which is not billed for this patient on 2026-08-20;'
                . ' it posts once that is, or skip it'],
            $this->problems($addOn)
        );

        [$parent] = $this->stage([$this->row(['chg_accession' => 'Z' . $this->token])]);
        $this->post($addOns);
        $this->assertSame(['posted', 'posted'], [$this->line($addOn)['status'], $this->line($parent)['status']]);
        $this->assertSame($this->line($parent)['encounter'], $this->line($addOn)['encounter']);
    }

    public function testAnAddOnPostsWithItsParentFromTheSameFileOrAlreadyPosted(): void
    {
        $addOns = $this->addOns();
        // The add-on comes first in the file.
        [$addOn, $parent] = $this->stage([
            $this->row(['chg_cpt' => $this->bareCode]),
            $this->row(['chg_accession' => 'Z' . $this->token]),
        ]);
        $this->post($addOns);
        $this->assertSame(['posted', 'posted'], [$this->line($addOn)['status'], $this->line($parent)['status']]);

        // The parent's add-on sent again later, on another day's visit, waits for that day's parent.
        [$otherDay] = $this->stage([$this->row(['chg_cpt' => $this->bareCode, 'chg_dos' => '08/21/2026', 'enc_admit_date' => '08/21/2026', 'chg_accession' => 'Y' . $this->token])]);
        $this->post($addOns);
        $this->assertSame('held', $this->line($otherDay)['status']);
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

    public function testAMedicareChestCtIsHeldForItsMeasureAnswersAndPostsWithTheGCodes(): void
    {
        QueryUtils::sqlStatementThrowException('UPDATE `insurance_companies` SET `ins_type_code` = 2 WHERE `id` = ?', [$this->companyId]);
        $this->price('71250', '26', '90.00');
        [$lineId, $other] = $this->stage([
            $this->row(['chg_cpt' => '71250']),
            $this->row(['chg_cpt' => $this->bareCode, 'chg_accession' => 'B' . $this->token]),
        ]);
        $result = $this->post();
        // held and measures count every pending line in the database, this test's and others'.
        $this->assertSame(1, $result->posted, 'the CT waits, the other line of the visit posts');
        $this->assertGreaterThanOrEqual(1, $result->measures);
        $this->assertSame(['held', 'posted'], [$this->line($lineId)['status'], $this->line($other)['status']]);
        $this->assertSame([MeasureRepository::PROBLEM], $this->problems($lineId));
        $measure = QueryUtils::querySingleRow('SELECT * FROM `gifford_measure` WHERE `line_id` = ?', [$lineId]);
        $this->assertIsArray($measure);
        $this->assertSame(['364,405,406', 'pending', ''], [$measure['measures'], $measure['status'], self::text($measure['billing_id'])]);

        $repository = new MeasureRepository();
        $pending = array_values(array_filter($repository->pending(), fn ($p): bool => $p->mrn === $this->mrn));
        $this->assertCount(1, $pending);
        $this->assertSame(
            ['71250', '', 'A' . $this->token, ['I50.20', 'Z95.811'], [Measure::M364, Measure::M405, Measure::M406], 'Reader, Ray', 'Testpatient, Ann', []],
            [$pending[0]->code, $pending[0]->modifiers, $pending[0]->accession, $pending[0]->diagnoses, $pending[0]->measures, $pending[0]->reader, $pending[0]->patientName, $pending[0]->otherProblems]
        );
        $this->assertSame(0, $this->post()->posted, 'still held on the next run');

        $measureId = self::int($measure['id']);
        $now = new \DateTimeImmutable(self::NOW);
        $this->assertFalse($repository->answer($measureId, ['364' => 1, '405' => 0], 7, $now), 'every measure needs an answer');
        $this->assertFalse($repository->answer($measureId, ['364' => 1, '405' => 9, '406' => 2], 7, $now), 'an unknown answer is refused');
        $this->assertTrue($repository->answer($measureId, ['364' => 1, '405' => 0, '406' => 2], 7, $now));
        $this->assertFalse($repository->answer($measureId, ['364' => 1, '405' => 0, '406' => 2], 7, $now), 'answered once');
        $this->assertSame([], array_filter($repository->pending(), fn ($p): bool => $p->mrn === $this->mrn));

        $this->assertSame(1, $this->post()->posted);
        $line = $this->line($lineId);
        $this->assertSame(['posted', self::text($this->line($other)['encounter'])], [$line['status'], self::text($line['encounter'])], 'into the visit\'s encounter');
        $billing = QueryUtils::fetchRecords(
            // The site may price 71250 itself, so its text and fee aren't this test's.
            'SELECT `code_type`, `code`, IF(`code_type` = \'HCPCS\', `code_text`, \'\') AS `text`, `modifier`, `fee` <> 0 AS `priced`,'
            . ' `authorized`, `justify`, `provider_id`, `user` FROM `billing`'
            . ' WHERE `encounter` = ? AND `code_type` <> \'ICD10\' AND `id` >= ? ORDER BY `id`',
            [$line['encounter'], $line['billing_id']]
        );
        $this->assertSame([
            ['CPT4', '71250', '', '26', '1', '1', 'ICD10|I50.20:ICD10|Z95.811:', (string) $this->providerId, ''],
            ['HCPCS', 'G9551', 'Final report: no incidentally found lesion noted', '', '0', '1', 'ICD10|I50.20:ICD10|Z95.811:', (string) $this->providerId, '7'],
            ['HCPCS', 'G9555', 'Follow-up imaging recommended for a documented medical reason', '', '0', '1', 'ICD10|I50.20:ICD10|Z95.811:', (string) $this->providerId, '7'],
            ['HCPCS', 'G9552', 'Incidental thyroid nodule under 1.0 cm noted', '', '0', '1', 'ICD10|I50.20:ICD10|Z95.811:', (string) $this->providerId, '7'],
        ], array_map(static fn (array $r): array => array_values(array_map(self::text(...), $r)), $billing));
        $posted = QueryUtils::querySingleRow('SELECT * FROM `gifford_measure` WHERE `id` = ?', [$measureId]);
        $this->assertIsArray($posted);
        $this->assertSame(
            ['{"364":1,"405":0,"406":2}', 'answered', self::NOW, '7', self::text($line['billing_id']), self::text($line['pid']), self::text($line['encounter'])],
            [$posted['answers_json'], $posted['status'], $posted['answered_at'], self::text($posted['answered_by']), self::text($posted['billing_id']), self::text($posted['pid']), self::text($posted['encounter'])]
        );

        $rows = array_values(array_filter(
            $repository->acrRows(new \DateTimeImmutable('2026-08-01'), new \DateTimeImmutable('2026-08-20')),
            fn ($r): bool => $r->patientId === $this->mrn
        ));
        $this->assertCount(1, $rows, 'only the registry measure, 364, makes an ACR row');
        $this->assertSame(
            '1.1,107816,08/20/2026,,' . $this->renderingNpi . ',' . $this->mrn . ',62,F,364,71250,I5020,G9345,A' . $this->token,
            $rows[0]->line('107816', '')
        );
        $this->assertSame([], array_filter($repository->acrRows(new \DateTimeImmutable('2026-08-21'), new \DateTimeImmutable('2026-08-31')), fn ($r): bool => $r->patientId === $this->mrn));
    }

    public function testASureNoFindingAnswersTheMeasureAndAFindingWaitsForTheCoder(): void
    {
        QueryUtils::sqlStatementThrowException('UPDATE `insurance_companies` SET `ins_type_code` = 2 WHERE `id` = ?', [$this->companyId]);
        $this->price('71250', '26', '90.00');
        $this->price('74177', '26', '90.00');
        [$chest, $abdomen] = $this->stage([
            $this->row(['chg_cpt' => '71250']),
            $this->row(['chg_cpt' => '74177', 'chg_accession' => 'B' . $this->token]),
        ]);
        $repository = new MeasureRepository();
        $now = new \DateTimeImmutable(self::NOW);
        $sure = static fn (Measure $m, int $answer = 0, string $confidence = 'high'): MeasureSuggestion
            => new MeasureSuggestion($m, $answer, $confidence, 'The kidneys are unremarkable.', 'Nothing noted.');
        $before = $repository->tally();

        // The chest CT's answers were read before any posting run: it is never held for them.
        $repository->saveSuggestions('A' . $this->token, [$sure(Measure::M364), $sure(Measure::M405), $sure(Measure::M406)], 'run-1', $now);
        // The abdomen's: no nodule, but a renal cyst the report says nothing more about.
        $repository->saveSuggestions('B' . $this->token, [$sure(Measure::M364), $sure(Measure::M405, 3, 'medium')], 'run-1', $now);
        $this->assertSame(0, $repository->applySuggestions('A' . $this->token, $now), 'no measure row yet');

        $this->post();

        $this->assertSame(['posted', 'held'], [$this->line($chest)['status'], $this->line($abdomen)['status']]);
        $this->assertSame([MeasureRepository::PROBLEM], $this->problems($abdomen));
        $row = QueryUtils::querySingleRow('SELECT * FROM `gifford_measure` WHERE `line_id` = ?', [$chest]);
        $this->assertIsArray($row);
        $this->assertSame(['answered', '{"364":0,"405":0,"406":0}', 0], [$row['status'], $row['answers_json'], self::int($row['answered_by'])]);
        $codes = QueryUtils::fetchTableColumn(
            "SELECT `code` FROM `billing` WHERE `encounter` = ? AND `code_type` = 'HCPCS' AND `activity` = 1 ORDER BY `id`",
            'code',
            [$this->line($chest)['encounter']]
        );
        $this->assertSame(['G9551', 'G9557'], $codes, 'no lesion and no thyroid nodule go on the claim; 364 sends nothing');

        // The coder sees what was read for the abdomen, and the answer is still theirs.
        $pending = array_values(array_filter($repository->pending(), fn ($p): bool => $p->mrn === $this->mrn));
        $this->assertCount(1, $pending);
        $shown = $pending[0]->suggestions;
        $this->assertSame(
            [0, 'high', true, 3, 'medium', false, 'Lesion, follow-up imaging recommended or not addressed (performance not met)'],
            [$shown['364']->answer, $shown['364']->confidence, $shown['364']->standsAlone(), $shown['405']->answer, $shown['405']->confidence, $shown['405']->standsAlone(), $shown['405']->label()]
        );
        $this->assertSame(1, $repository->tally()['automatic'] - $before['automatic']);

        // A later reading that is sure of no lesion answers the held line; the coder's answers are never replaced.
        $repository->saveSuggestions('B' . $this->token, [$sure(Measure::M364), $sure(Measure::M405)], 'run-2', $now);
        $this->assertSame(1, $repository->applySuggestions('B' . $this->token, $now));
        $this->assertSame(0, $repository->applySuggestions('B' . $this->token, $now), 'answered once');
        $this->assertSame(1, $this->post()->posted);
        $this->assertSame('posted', $this->line($abdomen)['status']);
    }

    public function testAChargeWithNoMeasurePostsAndAHeldOneCanBeReleased(): void
    {
        $this->price('71250', '26', '90.00');
        $this->price('76706', '26', '60.00');
        [$commercial, $young] = $this->stage([
            $this->row(['chg_cpt' => '71250', 'pat_dob' => '03/25/1964']),
            $this->row(['chg_cpt' => '76706', 'pat_dob' => '03/25/1994', 'pat_mrn' => $this->mrn . 'Y', 'encounter_num' => 'GH-Y' . $this->token, 'chg_accession' => 'Y' . $this->token]),
        ]);
        $result = $this->post();
        $this->assertSame(1, $result->posted, 'the 71250 line gets 364 (age) but not 405/406 (not Medicare); 76706 under 50 gets nothing');
        $this->assertGreaterThanOrEqual(1, $result->measures);
        $this->assertSame(['held', 'posted'], [$this->line($commercial)['status'], $this->line($young)['status']]);
        $measureId = self::int(QueryUtils::fetchSingleValue('SELECT `id` FROM `gifford_measure` WHERE `line_id` = ?', 'id', [$commercial]));
        $this->assertSame('364', self::text(QueryUtils::fetchSingleValue('SELECT `measures` FROM `gifford_measure` WHERE `id` = ?', 'measures', [$measureId])));

        $repository = new MeasureRepository();
        $this->assertTrue($repository->release($measureId, 7, new \DateTimeImmutable(self::NOW)));
        $this->assertFalse($repository->release($measureId, 7, new \DateTimeImmutable(self::NOW)));
        $this->assertSame(1, $this->post()->posted);
        $line = $this->line($commercial);
        $this->assertSame('posted', $line['status']);
        $this->assertSame('released', self::text(QueryUtils::fetchSingleValue('SELECT `status` FROM `gifford_measure` WHERE `id` = ?', 'status', [$measureId])));
        $this->assertCount(0, QueryUtils::fetchRecords("SELECT 1 FROM `billing` WHERE `encounter` = ? AND `code_type` = 'HCPCS'", [$line['encounter']]));
        $this->assertSame([], array_filter($repository->acrRows(new \DateTimeImmutable('2026-08-01'), new \DateTimeImmutable('2026-08-31')), fn ($r): bool => $r->patientId === $this->mrn), 'released: nothing for the registry');
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

    public function testAStagedLineWaitsForItsDiagnosesBeforeAnyPostingRun(): void
    {
        [$first] = $this->diagnosisCodes(1);
        $dx = new DxRepository();
        $now = new \DateTimeImmutable(self::NOW);
        $accession = 'A' . $this->token;
        // A site that stages without posting: the line is never held for its diagnoses.
        [$lineId] = $this->stage([$this->row(['chg_clinical' => 'Cough', 'report_text' => 'FINDINGS: clear.', 'report_status' => 'Signed', 'report_signed' => '08/21/2026 09:15'])]);
        $waits = static fn (): array => array_values(array_filter($dx->pending(), static fn ($p): bool => $p->accession === $accession));
        $before = $dx->pendingCount();

        $this->assertSame('staged', $this->line($lineId)['status']);
        $this->assertCount(1, $waits());
        $this->assertSame([], $waits()[0]->otherProblems);

        // A suggestion a coder must look at keeps it waiting; the coder's codes end the wait.
        $this->assertTrue($dx->saveSuggestion($accession, [['code' => $first, 'confidence' => 'medium', 'rationale' => '']], DxDecision::coder(['first code is not high confidence']), 'run-1', $now));
        $this->assertSame([true], array_map(static fn ($p): bool => $p->hasSuggestion, $waits()));
        $this->assertSame($before, $dx->pendingCount());
        $this->assertSame([], $dx->confirm($accession, $first, 1, $now));
        $this->assertSame([], $waits());
        $this->assertSame($before - 1, $dx->pendingCount());
        $this->assertSame('staged', $this->line($lineId)['status']);
    }

    public function testCodingFromTheReportHoldsALineUntilACoderConfirmsItsDiagnoses(): void
    {
        [$first, $second, $third] = $this->diagnosisCodes(3);
        $dx = new DxRepository();
        $now = new \DateTimeImmutable(self::NOW);
        $accession = 'A' . $this->token;
        // Gifford sent no diagnosis: no longer a problem of the row.
        [$lineId] = $this->stage([$this->row(['enc_dx_codes' => '', 'chg_clinical' => 'Cough', 'report_text' => 'FINDINGS: clear.', 'report_status' => 'Signed', 'report_signed' => '08/21/2026 09:15'])]);

        $result = $this->post(dx: $dx);

        $this->assertGreaterThanOrEqual(1, $result->diagnoses);
        $this->assertSame(['held', [DxRepository::PROBLEM]], [$this->line($lineId)['status'], $this->problems($lineId)]);
        $waiting = array_values(array_filter($dx->pending(), static fn ($p): bool => $p->accession === $accession));
        $this->assertCount(1, $waiting);
        $this->assertSame(
            ['Testpatient, Ann', [$this->code], 'Cough', [], false, 'FINDINGS: clear.'],
            [$waiting[0]->patientName, $waiting[0]->charges, $waiting[0]->indication, $waiting[0]->gifford, $waiting[0]->hasSuggestion, $waiting[0]->report?->text]
        );

        // A suggestion with a reason to look waits; the coder sees it with the code set's text.
        $suggested = [
            ['code' => $first, 'confidence' => 'high', 'rationale' => 'The finding.'],
            ['code' => $second, 'confidence' => 'medium', 'rationale' => ''],
        ];
        $this->assertTrue($dx->saveSuggestion($accession, $suggested, DxDecision::coder(['Gifford sent no diagnosis']), 'run-1', $now));
        $this->assertNull($dx->codesFor($accession));
        $waiting = array_values(array_filter($dx->pending(), static fn ($p): bool => $p->accession === $accession));
        $this->assertSame(
            [$first . ' ' . $second, 'Test diagnosis 0', ['Gifford sent no diagnosis'], true],
            [$waiting[0]->suggestedCodes(), $waiting[0]->suggested[0]['text'], $waiting[0]->reasons, $waiting[0]->hasSuggestion]
        );

        // Codes that are not codes, not in the code set, or too many are refused.
        $this->assertSame(['enter at least one code'], $dx->confirm($accession, ' ', 7, $now));
        $this->assertSame(['71046 is not an ICD-10 code', 'not valid for coding: Q99.9Z'], $dx->confirm($accession, '71046 Q999Z', 7, $now));
        $this->assertSame(
            ['a charge takes 2 diagnoses at most', 'not valid for coding: Q99.9Y, Q99.9Z'],
            $dx->confirm($accession, "$first $second $third Q999Y Q999Z", 7, $now)
        );
        $this->assertNull($dx->codesFor($accession));

        // Three valid codes are one too many.
        $this->assertSame(['a charge takes 2 diagnoses at most'], $dx->confirm($accession, "$first $second $third", 7, $now));
        $this->assertNull($dx->codesFor($accession));

        // The coder changes the order: without dots, with a comma, in lower case.
        $this->assertSame([], $dx->confirm($accession, str_replace('.', '', $second) . ', ' . strtolower($first), 7, $now));
        $this->assertSame([$second, $first], $dx->codesFor($accession));
        // A later suggestion doesn't undo the coder.
        $this->assertFalse($dx->saveSuggestion($accession, $suggested, DxDecision::suggestion([$first, $second]), 'run-2', $now));

        $this->post(dx: $dx);

        $this->assertSame('posted', $this->line($lineId)['status']);
        $justify = "ICD10|$second:ICD10|$first:";
        $this->assertSame(
            [['ICD10', $second, ''], ['ICD10', $first, ''], ['CPT4', $this->code, $justify]],
            $this->billed($lineId)
        );
        $this->assertSame([], array_filter($dx->pending(), static fn ($p): bool => $p->accession === $accession));
    }

    public function testAnAgreedSuggestionPostsWithItsOwnCodesNotTheVisits(): void
    {
        [$first, $second] = $this->diagnosisCodes(2);
        $dx = new DxRepository();
        $now = new \DateTimeImmutable(self::NOW);
        // Two accessions on one visit; Gifford sent the visit's diagnoses on both.
        [$one, $two] = $this->stage([
            $this->row(['enc_dx_codes' => "$second,$first"]),
            $this->row(['enc_dx_codes' => "$second,$first", 'chg_cpt' => $this->bareCode, 'chg_accession' => 'B' . $this->token]),
        ]);
        $this->assertSame(
            ['gifford' => [$second, $first], 'cpts' => [$this->code], 'payer' => $this->payer, 'location' => 'GHC.TEST'],
            $dx->staged('A' . $this->token)
        );
        $this->assertNull($dx->staged('Z' . $this->token));
        $this->assertSame(['Q99.9Z'], $dx->invalidCodes([$first, 'Q99.9Z']));
        $before = $dx->tally();

        // No reason for a coder: agreed, and the first accession posts while the other waits.
        $this->assertTrue($dx->saveSuggestion('A' . $this->token, [['code' => $first, 'confidence' => 'high', 'rationale' => '']], DxDecision::suggestion([$first]), 'run-1', $now));
        $this->post(dx: $dx);

        $this->assertSame(['posted', 'held'], [$this->line($one)['status'], $this->line($two)['status']]);
        $this->assertSame([['ICD10', $first, ''], ['CPT4', $this->code, "ICD10|$first:"]], $this->billed($one));

        // No suggestion for the second accession: it posts with Gifford's code, and the encounter gains it.
        $this->assertTrue($dx->saveSuggestion('B' . $this->token, [], DxDecision::gifford([$second]), 'run-1', $now));
        $this->post(dx: $dx);
        $this->assertSame(1, $dx->tally()['gifford'] - $before['gifford']);
        // A coder can still confirm it afterwards.
        $this->assertSame([], $dx->confirm('B' . $this->token, $second, 7, $now));

        $this->assertSame(
            [['ICD10', $first, ''], ['CPT4', $this->code, "ICD10|$first:"], ['ICD10', $second, ''], ['CPT4', $this->bareCode, "ICD10|$second:"]],
            $this->billed($two)
        );
        $after = $dx->tally();
        $this->assertSame(
            [1, 0, 1, 1],
            [$after['agreed'] - $before['agreed'], $after['gifford'] - $before['gifford'], $after['confirmed'] - $before['confirmed'], $after['no_suggestion'] - $before['no_suggestion']]
        );
    }

    public function testAScreeningMammogramIsCodedFromTheExamWithNoReadAndNoCoder(): void
    {
        [$family, $knee] = $this->diagnosisCodes(2);
        $this->validCode(DxRule::SCREENING_MAMMOGRAM);
        $this->price('77067', '26', '60.00');
        $this->price('77063', '26', '30.00');
        $dx = new DxRepository();
        $now = new \DateTimeImmutable(self::NOW);
        // The screening and its 3-D add-on on one accession, with a family history from
        // Gifford; one sent with no diagnosis at all; and two other exams the same day.
        [$screen, $tomo, $bare, $other, $third] = $this->stage([
            $this->row(['chg_cpt' => '77067', 'enc_dx_codes' => DxRule::SCREENING_MAMMOGRAM . ",$family,$knee"]),
            $this->row(['chg_cpt' => '77063', 'enc_dx_codes' => DxRule::SCREENING_MAMMOGRAM . ",$family,$knee"]),
            $this->row(['chg_cpt' => '77067', 'enc_dx_codes' => '', 'pat_mrn' => $this->mrn . 'B', 'encounter_num' => 'GH-B' . $this->token, 'chg_accession' => 'D' . $this->token]),
            $this->row(['chg_accession' => 'B' . $this->token]),
            $this->row(['chg_cpt' => $this->bareCode, 'chg_accession' => 'C' . $this->token]),
        ]);
        $before = $dx->tally();
        $waiting = fn (): array => array_values(array_filter($dx->pending(), fn ($p): bool => str_ends_with($p->accession, $this->token)));

        // Before any posting run, as the diagnoses page does on a site that only stages.
        $this->assertGreaterThanOrEqual(2, $dx->settleByExam($now));
        $this->assertSame([DxRule::SCREENING_MAMMOGRAM, $family], $dx->codesFor('A' . $this->token));
        $this->assertSame([DxRule::SCREENING_MAMMOGRAM], $dx->codesFor('D' . $this->token));
        $this->assertNull($dx->codesFor('B' . $this->token));
        $this->assertSame(['B' . $this->token, 'C' . $this->token], array_map(static fn ($p): string => $p->accession, $waiting()));
        $this->assertSame(2, $dx->tally()['exam'] - $before['exam']);

        $this->post(dx: $dx);

        $this->assertSame(
            ['posted', 'posted', 'posted', 'held', 'held'],
            array_map(fn (int $id): string => self::text($this->line($id)['status']), [$screen, $tomo, $bare, $other, $third])
        );
        $mammogram = DxRule::SCREENING_MAMMOGRAM;
        $this->assertSame(
            [['ICD10', $mammogram, ''], ['ICD10', $family, ''], ['CPT4', '77067', "ICD10|$mammogram:ICD10|$family:"], ['CPT4', '77063', "ICD10|$mammogram:ICD10|$family:"]],
            $this->billed($screen)
        );
        $this->assertSame([['ICD10', $mammogram, ''], ['CPT4', '77067', "ICD10|$mammogram:"]], $this->billed($bare));

        // The screening code is never offered for another exam; a coder's codes are.
        $this->assertSame([[], []], array_map(static fn ($p): array => $p->sameDay, $waiting()));
        $this->assertSame([], $dx->confirm('B' . $this->token, "$knee $family", 7, $now));
        // (B stays listed until the next posting run; its own codes are not offered to it.)
        $this->assertSame(
            ['B' . $this->token => [], 'C' . $this->token => [['charges' => $this->code, 'codes' => "$knee $family"]]],
            array_column(array_map(static fn ($p): array => ['a' => $p->accession, 's' => $p->sameDay], $waiting()), 's', 'a')
        );
        // A second run settles nothing new and leaves the coder's accession alone.
        $dx->settleByExam($now);
        $this->assertSame([$knee, $family], $dx->codesFor('B' . $this->token));
    }

    public function testWithoutCodingFromTheReportALineWithNoDiagnosisIsStillHeld(): void
    {
        [$lineId] = $this->stage([$this->row(['enc_dx_codes' => ''])]);

        $this->post();

        $this->assertSame(['held', [ChargeRowParser::NO_DIAGNOSES]], [$this->line($lineId)['status'], $this->problems($lineId)]);
    }

    public function testAChargePostsWithTheCodeAPersonChangedItToAndItsResendIsADuplicate(): void
    {
        $review = new ReviewRepository(new ChargeRowParser(), new PayerMap());
        $now = new \DateTimeImmutable(self::NOW);
        $accession = 'A' . $this->token;
        [$line] = $this->stage([$this->row()]);

        $this->assertSame(['ZZ9ZZ is not an active CPT4 code on this site'], $review->changeCode($accession, $this->code, 'ZZ9ZZ', $now));
        $this->assertSame(['accession ' . $accession . ' has no ' . $this->bareCode . ' charge waiting to post'], $review->changeCode($accession, $this->bareCode, $this->code, $now));
        $this->assertSame(['the charge already has that code'], $review->changeCode($accession, $this->code, $this->code, $now));
        $this->assertCount(1, $review->changeCode($accession, $this->code, 'nope', $now));
        $this->assertSame('', $this->line($line)['changed_code'], 'a refused change leaves the line alone');

        // Changed, changed back (which undoes it), and changed again.
        $this->assertSame([], $review->changeCode($accession, $this->code, strtolower($this->bareCode), $now));
        $this->assertSame([$this->bareCode, $this->bareCode], [$this->line($line)['code'], $this->line($line)['changed_code']]);
        $this->assertSame([], $review->changeCode($accession, $this->bareCode, $this->code, $now));
        $this->assertSame([$this->code, ''], [$this->line($line)['code'], $this->line($line)['changed_code']]);
        $this->assertSame([], $review->changeCode($accession, $this->code, $this->bareCode, $now));
        $this->assertStringContainsString('"chg_cpt":"' . $this->code . '"', self::text($this->line($line)['raw_json']), 'the row keeps the code Gifford sent');

        $this->assertSame(1, $this->post()->posted);
        $this->assertContains(['CPT4', $this->bareCode, 'ICD10|I50.20:ICD10|Z95.811:'], $this->billed($line));
        $this->assertSame('80.00', self::text(QueryUtils::fetchSingleValue('SELECT `fee` FROM `billing` WHERE `id` = ?', 'fee', [$this->line($line)['billing_id']])));
        $this->assertSame(['accession ' . $accession . ' has no ' . $this->bareCode . ' charge waiting to post'], $review->changeCode($accession, $this->bareCode, $this->code, $now), 'a posted charge is changed on the fee sheet');

        // Gifford sends it again, as it was and corrected: both are the charge already posted.
        [$asSent, $corrected] = $this->stage([$this->row(), $this->row(['chg_cpt' => $this->bareCode])]);
        $result = $this->post();
        $this->assertSame([0, 2], [$result->posted, $result->duplicates]);
        $this->assertSame(['duplicate', 'duplicate'], [$this->line($asSent)['status'], $this->line($corrected)['status']]);
    }

    public function testTheDiagnosesPageNamesTheCodeGiffordSentBesideAChangedOne(): void
    {
        $accession = 'A' . $this->token;
        $this->stage([$this->row(['chg_mod1' => 'LT'])]);
        $dx = new DxRepository();
        $find = static function () use ($dx, $accession): ?PendingDx {
            foreach ($dx->pending() as $item) {
                if ($item->accession === $accession) {
                    return $item;
                }
            }
            return null;
        };
        $before = $find();
        $this->assertNotNull($before);
        $this->assertSame([[$this->code . '-LT'], [$this->code]], [$before->charges, $before->codes]);

        $this->assertSame([], (new ReviewRepository(new ChargeRowParser(), new PayerMap()))->changeCode($accession, $this->code, $this->bareCode, new \DateTimeImmutable(self::NOW)));
        $after = $find();
        $this->assertNotNull($after);
        $this->assertSame([[$this->bareCode . '-LT (sent as ' . $this->code . ')'], [$this->bareCode]], [$after->charges, $after->codes]);
    }

    private function cciEdit(string $column1, string $column2, string $indicator): void
    {
        QueryUtils::sqlStatementThrowException(
            'INSERT INTO `gifford_cci_edit` (`column1`, `column2`, `indicator`) VALUES (?, ?, ?)',
            [$column1, $column2, $indicator]
        );
    }

    private function billedModifier(int $lineId): string
    {
        return self::text(QueryUtils::fetchSingleValue('SELECT `modifier` FROM `billing` WHERE `id` = ?', 'modifier', [$this->line($lineId)['billing_id']]));
    }

    private function repeatProblem(string $otherAccession): string
    {
        return 'repeat procedure? CPT4 ' . $this->code . ' is also on accession ' . $otherAccession . ' for this patient on 2026-08-20;'
            . ' post it with modifier 76 (same physician) or 77 (another physician), or skip it if it is a duplicate';
    }

    /**
     * The real add-ons plus $bareCode needing $code. Posting reads every pending line in
     * the database, so the real rules must stay in force for lines that aren't this test's.
     */
    private function addOns(): AddOnCodes
    {
        return new AddOnCodes(AddOnCodes::PARENTS + [$this->bareCode => [$this->code]]);
    }

    private function post(AddOnCodes $addOns = new AddOnCodes(), ?DxRepository $dx = null): PostResult
    {
        $settings = PostSettings::load($this->facilityId, $this->facilityId);
        $this->assertNotNull($settings);

        return $this->poster($addOns, $dx)->run($settings);
    }

    /**
     * Put a real code in the site's code set (a second row when it is loaded already).
     */
    private function validCode(string $code): void
    {
        QueryUtils::sqlStatementThrowException(
            'INSERT INTO `icd10_dx_order_code` (`dx_code`, `formatted_dx_code`, `valid_for_coding`, `short_desc`, `long_desc`, `active`, `revision`)'
            . " VALUES (?, ?, '1', ?, ?, 1, 1)",
            [str_pad(str_replace('.', '', $code), 7), str_pad($code, 10), 'Test ' . $this->token, 'Test diagnosis ' . $code]
        );
    }

    /**
     * Made-up diagnosis codes in the site's code set, valid for coding.
     *
     * @return list<string> with the dot
     */
    private function diagnosisCodes(int $count): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $code = 'U' . (7 - $i) . $this->token[0] . '.' . substr($this->token, 1, 2);
            QueryUtils::sqlStatementThrowException(
                'INSERT INTO `icd10_dx_order_code` (`dx_code`, `formatted_dx_code`, `valid_for_coding`, `short_desc`, `long_desc`, `active`, `revision`)'
                . " VALUES (?, ?, '1', ?, ?, 1, 1)",
                [str_pad(str_replace('.', '', $code), 7), str_pad($code, 10), 'Test ' . $this->token, 'Test diagnosis ' . $i . "\r\n"]
            );
            $codes[] = $code;
        }

        return $codes;
    }

    /**
     * @return list<list<string>> [code type, code, justify] of the encounter's billing rows, in order
     */
    private function billed(int $lineId): array
    {
        return array_map(
            static fn (array $r): array => array_values(array_map(self::text(...), $r)),
            QueryUtils::fetchRecords('SELECT `code_type`, `code`, `justify` FROM `billing` WHERE `encounter` = ? ORDER BY `id`', [$this->line($lineId)['encounter']])
        );
    }

    private function poster(AddOnCodes $addOns, ?DxRepository $dx = null): ChargePoster
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable(self::NOW));

        return new ChargePoster(
            new ChargeLineRepository(),
            new ChargeRowParser(),
            new PayerMap(),
            new PriceBook(),
            new SiteWriter(),
            $clock,
            addOns: $addOns,
            dx: $dx,
        );
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

    private static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
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
