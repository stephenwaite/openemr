<?php

/**
 * Database tests for the Gifford review page's lists and actions.
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
use Cmsvt\OpenEMR\Modules\GiffordCharges\Reader\RawRow;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Review\HeldLine;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Review\ReviewRepository;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Review\UnmappedPayer;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Stage\ChargeBatchRepository;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Stage\StagedLine;
use Composer\Autoload\ClassLoader;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Services\Utils\SQLUpgradeService;
use PHPUnit\Framework\TestCase;

class ReviewRepositoryTest extends TestCase
{
    private const MODULE_DIR = '/interface/modules/custom_modules/oe-module-gifford-charges';

    private string $token = '';

    private int $batchId = 0;

    private int $companyId = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $loaders = ClassLoader::getRegisteredLoaders();
        $loader = reset($loaders);
        if (!$loader instanceof ClassLoader) {
            self::fail('Composer ClassLoader not available to register module autoload prefix.');
        }
        $loader->addPsr4('Cmsvt\\OpenEMR\\Modules\\GiffordCharges\\', dirname(__DIR__, 5) . self::MODULE_DIR . '/src/');
        if (!QueryUtils::existsTable('gifford_payer_map')) {
            $install = new SQLUpgradeService();
            $install->setRenderOutputToScreen(false);
            $install->setThrowExceptionOnError(true);
            $install->upgradeFromSqlFile('table.sql', dirname(__DIR__, 5) . self::MODULE_DIR);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = strtoupper(bin2hex(random_bytes(4)));
        $this->companyId = QueryUtils::generateId();
        QueryUtils::sqlStatementThrowException(
            "INSERT INTO `insurance_companies` (`id`, `name`, `cms_id`, `ins_type_code`) VALUES (?, ?, '', 17)",
            [$this->companyId, 'Mapped ' . $this->token]
        );
        (new PayerMap())->set('OLD' . $this->token, $this->companyId, new \DateTimeImmutable('2026-10-22 10:00:00'));
    }

    protected function tearDown(): void
    {
        QueryUtils::sqlStatementThrowException('DELETE FROM `gifford_charge_line` WHERE `batch_id` = ?', [$this->batchId]);
        QueryUtils::sqlStatementThrowException('DELETE FROM `gifford_charge_batch` WHERE `id` = ?', [$this->batchId]);
        QueryUtils::sqlStatementThrowException('DELETE FROM `gifford_payer_map` WHERE `mnemonic` LIKE ?', ['%' . $this->token]);
        QueryUtils::sqlStatementThrowException('DELETE FROM `insurance_companies` WHERE `id` = ?', [$this->companyId]);
        parent::tearDown();
    }

    public function testListsUnmappedPayersAndHeldLinesAndActsOnThem(): void
    {
        [$unmapped, $correction] = $this->stageHeld([
            [$this->row('NEW' . $this->token, 'A1' . $this->token), ['payer NEW' . $this->token . ' (NEW PLAN) is not mapped to an insurance company']],
            [$this->row('OLD' . $this->token, 'A2' . $this->token), [ReviewRepository::CORRECTION_PREFIX . ' accession A2 was posted from an earlier file as 93005 x1; post this line too, or skip it']],
        ]);
        $review = new ReviewRepository(new ChargeRowParser(), new PayerMap());

        $payers = array_values(array_filter($review->unmappedPayers(), fn (UnmappedPayer $p): bool => str_ends_with($p->mnemonic, $this->token)));
        $this->assertEquals([new UnmappedPayer('NEW' . $this->token, 'NEW PLAN', 1, '2026-10-22 10:00:00')], $payers);

        $held = array_values(array_filter($review->heldLines(), fn (HeldLine $l): bool => in_array($l->id, [$unmapped, $correction], true)));
        $this->assertCount(2, $held);
        $this->assertSame(['Testpatient, Ann', 'GH-' . $this->token, false], [$held[0]->patientName, $held[0]->visitNumber, $held[0]->correction]);
        $this->assertTrue($held[1]->correction);

        $now = new \DateTimeImmutable('2026-10-22 11:00:00');
        $this->assertTrue($review->release($correction, $now));
        $this->assertTrue($review->skip($unmapped, $now));
        $this->assertFalse($review->skip($unmapped, $now), 'a skipped line is no longer held');
        $this->assertSame(
            ['skipped:0', 'held:1'],
            QueryUtils::fetchTableColumn(
                "SELECT CONCAT(`status`, ':', `released`) AS s FROM `gifford_charge_line` WHERE `batch_id` = ? ORDER BY `line_number`",
                's',
                [$this->batchId]
            )
        );
    }

    /**
     * @param list<array{array<string, string>, list<string>}> $rows row and the problems to hold it with
     * @return list<int>
     */
    private function stageHeld(array $rows): array
    {
        $parser = new ChargeRowParser();
        $lines = [];
        foreach ($rows as $i => [$values]) {
            $raw = new RawRow($i + 2, $values);
            $lines[] = StagedLine::fromRow($raw, $parser->parse($raw));
        }
        $this->batchId = (new ChargeBatchRepository())->saveStagedBatch('RAD_REVIEW.csv', bin2hex(random_bytes(32)), new \DateTimeImmutable('2026-10-22 10:00:00'), $lines);
        $ids = array_map(
            static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0,
            QueryUtils::fetchTableColumn('SELECT `id` FROM `gifford_charge_line` WHERE `batch_id` = ? ORDER BY `line_number`', 'id', [$this->batchId])
        );
        foreach ($rows as $i => [, $problems]) {
            QueryUtils::sqlStatementThrowException(
                "UPDATE `gifford_charge_line` SET `status` = 'held', `problems_json` = ? WHERE `id` = ?",
                [json_encode($problems, JSON_THROW_ON_ERROR), $ids[$i]]
            );
        }

        return $ids;
    }

    /**
     * @return array<string, string>
     */
    private function row(string $payer, string $accession): array
    {
        return [
            'encounter_num' => 'GH-' . $this->token,
            'pat_mrn' => 'MRR' . $this->token,
            'pat_last' => 'Testpatient',
            'pat_first' => 'Ann',
            'pat_sex' => 'F',
            'pat_dob' => '03/25/1964',
            'enc_dx_codes' => 'R42',
            'prov_rend_npi' => '1234567893',
            'chg_dos' => '08/20/2026',
            'chg_cpt' => '93005',
            'chg_units' => '1',
            'chg_accession' => $accession,
            'chg_location' => 'GHC.TEST',
            'ins1_rank' => '1',
            'ins1_mnemonic' => $payer,
            'ins1_payer_name' => 'NEW PLAN',
            'ins1_member_id' => '38097',
            'ins1_sub_name' => 'Testpatient, Ann',
            'ins1_sub_dob' => '03/25/1964',
            'ins1_sub_gender' => 'F',
            'ins1_relate' => '18',
        ];
    }
}
