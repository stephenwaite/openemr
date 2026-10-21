<?php

/**
 * Database tests for staging Gifford charge files from the drop directory.
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
use Cmsvt\OpenEMR\Modules\GiffordCharges\Reader\ChargeFileReader;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Stage\ChargeBatchRepository;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Stage\ChargeFileStager;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Stage\FileOutcome;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Stage\StageException;
use Composer\Autoload\ClassLoader;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Services\Utils\SQLUpgradeService;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

class ChargeFileStagerTest extends TestCase
{
    private const MODULE_DIR = '/interface/modules/custom_modules/oe-module-gifford-charges';

    /** "Now" for every test; files the tests write are dated an hour earlier. */
    private const NOW = '2026-10-21 10:00:00';

    private string $dropDir = '';

    /** Makes this run's rows unique, so rows left by an aborted run can't match. */
    private string $token = '';

    /** @var list<int> batches this test saved */
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

        // The tables exist only where the module is installed; table.sql is what
        // installing runs, and every statement in it is guarded.
        if (!QueryUtils::existsTable('gifford_charge_batch')) {
            $install = new SQLUpgradeService();
            $install->setRenderOutputToScreen(false);
            $install->setThrowExceptionOnError(true);
            $install->upgradeFromSqlFile('table.sql', self::root() . self::MODULE_DIR);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = bin2hex(random_bytes(6));
        $dir = sys_get_temp_dir() . '/gifford-drop-' . $this->token;
        if (!mkdir($dir)) {
            self::fail('Could not create the drop directory');
        }
        $this->dropDir = $dir;
    }

    protected function tearDown(): void
    {
        foreach ($this->batchIds as $id) {
            QueryUtils::sqlStatementThrowException('DELETE FROM `gifford_charge_line` WHERE `batch_id` = ?', [$id]);
            QueryUtils::sqlStatementThrowException('DELETE FROM `gifford_charge_batch` WHERE `id` = ?', [$id]);
        }
        self::removeTree($this->dropDir);
        parent::tearDown();
    }

    public function testTableSqlAddsTheHourlyService(): void
    {
        $row = QueryUtils::querySingleRow(
            'SELECT `active`, `execute_interval`, `function`, `require_once` FROM `background_services` WHERE `name` = ?',
            ['Gifford_Charge_Import']
        );
        $this->assertIsArray($row);
        $this->assertSame('60', self::text($row['execute_interval']));
        $this->assertSame(self::MODULE_DIR . '/public/background_service.php', $row['require_once']);

        // What the background runner does with the row: require the file, call the function.
        $this->assertSame('Cmsvt\\OpenEMR\\Modules\\GiffordCharges\\runBackgroundService', $row['function']);
        require_once self::root() . self::MODULE_DIR . '/public/background_service.php';
        $this->assertTrue(function_exists('Cmsvt\\OpenEMR\\Modules\\GiffordCharges\\runBackgroundService'));
    }

    public function testANewFileIsStagedWithACleanLineAndAHeldLine(): void
    {
        $this->writeXlsx('RAD_20260915.xlsx', [
            $this->row(['encounter_num' => 'GH-CLEAN-' . $this->token]),
            $this->row(['encounter_num' => 'GH-HELD-' . $this->token, 'chg_accession' => '']),
        ]);

        $results = $this->stager()->run($this->dropDir);

        $this->assertCount(1, $results);
        $file = $results[0];
        $this->batchIds[] = $file->batchId;
        $this->assertSame(FileOutcome::Staged, $file->outcome);
        $this->assertSame([2, 1], [$file->lineCount, $file->heldCount]);
        $this->assertSame($this->dropDir . '/processed/' . $file->batchId . '-RAD_20260915.xlsx', $file->movedTo);
        $this->assertFileExists($file->movedTo);
        $this->assertFileDoesNotExist($this->dropDir . '/RAD_20260915.xlsx');

        $batch = QueryUtils::querySingleRow('SELECT * FROM `gifford_charge_batch` WHERE `id` = ?', [$file->batchId]);
        $this->assertIsArray($batch);
        $this->assertSame('RAD_20260915.xlsx', $batch['file_name']);
        $this->assertSame('staged', $batch['status']);
        $this->assertSame(['2', '1'], [self::text($batch['line_count']), self::text($batch['held_count'])]);
        $this->assertSame(self::NOW, $batch['received_at']);
        $this->assertSame(hash_file('sha256', $file->movedTo), $batch['file_sha256']);

        $lines = QueryUtils::fetchRecords(
            'SELECT * FROM `gifford_charge_line` WHERE `batch_id` = ? ORDER BY `line_number`',
            [$file->batchId]
        );
        $this->assertCount(2, $lines);
        [$clean, $held] = $lines;
        $this->assertSame(
            ['2', 'GH-CLEAN-' . $this->token, 'MR0000000001', 'A' . $this->token, '2026-08-20', '93005', 'staged', '[]'],
            [
                self::text($clean['line_number']),
                $clean['visit_number'],
                $clean['mrn'],
                $clean['accession'],
                $clean['date_of_service'],
                $clean['code'],
                $clean['status'],
                $clean['problems_json'],
            ]
        );
        $raw = json_decode(self::text($clean['raw_json']), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($raw);
        $this->assertSame('GHC.TEST', $raw['chg_location'] ?? null);

        $this->assertSame('held', $held['status']);
        $this->assertSame('GH-HELD-' . $this->token, $held['visit_number']);
        $this->assertSame('', $held['accession']);
        $this->assertSame(['chg_accession is empty'], json_decode(self::text($held['problems_json']), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testTheSameBytesAreNotStagedTwice(): void
    {
        $content = $this->csv([$this->row()]);
        $this->writeFile('RAD_20260916.csv', $content);
        $first = $this->stager()->run($this->dropDir);
        $this->assertCount(1, $first);
        $this->batchIds[] = $first[0]->batchId;

        $this->writeFile('RAD_20260916.csv', $content);
        $second = $this->stager()->run($this->dropDir);

        $this->assertCount(1, $second);
        $this->assertSame(FileOutcome::AlreadySeen, $second[0]->outcome);
        $this->assertSame($first[0]->batchId, $second[0]->batchId);
        $this->assertSame(
            $this->dropDir . '/processed/' . $first[0]->batchId . '-resent-20261021T100000-RAD_20260916.csv',
            $second[0]->movedTo
        );
        $this->assertFileExists($second[0]->movedTo);
        $this->assertSame(
            '1',
            self::text(QueryUtils::fetchSingleValue(
                'SELECT COUNT(*) AS n FROM `gifford_charge_batch` WHERE `file_sha256` = ?',
                'n',
                [hash('sha256', $content)]
            ))
        );
    }

    public function testAFileMissingColumnsIsSavedAsFailedAndMovedToFailed(): void
    {
        $this->writeFile('RAD_20260917.csv', "encounter_num,pat_mrn\nGH-" . $this->token . ",MR1\n");

        $results = $this->stager()->run($this->dropDir);

        $this->assertCount(1, $results);
        $file = $results[0];
        $this->batchIds[] = $file->batchId;
        $this->assertSame(FileOutcome::Failed, $file->outcome);
        $this->assertSame($this->dropDir . '/failed/' . $file->batchId . '-RAD_20260917.csv', $file->movedTo);
        $this->assertFileExists($file->movedTo);

        $batch = QueryUtils::querySingleRow('SELECT `status`, `failure`, `line_count` FROM `gifford_charge_batch` WHERE `id` = ?', [$file->batchId]);
        $this->assertIsArray($batch);
        $this->assertSame('failed', $batch['status']);
        $this->assertStringContainsString('missing columns', self::text($batch['failure']));
        $this->assertSame('0', self::text($batch['line_count']));
        $this->assertSame(
            '0',
            self::text(QueryUtils::fetchSingleValue('SELECT COUNT(*) AS n FROM `gifford_charge_line` WHERE `batch_id` = ?', 'n', [$file->batchId]))
        );
    }

    public function testAFileStillArrivingWaitsForTheNextRun(): void
    {
        $path = $this->writeFile('RAD_20260918.csv', $this->csv([$this->row()]));
        // Modified a minute before "now": inside the two-minute settle time.
        touch($path, (new \DateTimeImmutable(self::NOW))->getTimestamp() - 60);

        $this->assertSame([], $this->stager()->run($this->dropDir));
        $this->assertFileExists($path);
    }

    public function testOnlyChargeFilesAreRead(): void
    {
        $this->writeFile('notes.txt', 'not a charge file');
        $this->writeFile('.RAD_20260919.csv.part', $this->csv([$this->row()]));
        $this->writeFile('.hidden.csv', $this->csv([$this->row()]));
        mkdir($this->dropDir . '/sub.csv');

        $this->assertSame([], $this->stager()->run($this->dropDir));
        $this->assertFileExists($this->dropDir . '/notes.txt');
        $this->assertFileExists($this->dropDir . '/.hidden.csv');
    }

    public function testFilesAreStagedInNameOrder(): void
    {
        $this->writeFile('RAD_20260921.csv', $this->csv([$this->row(['encounter_num' => 'GH-B-' . $this->token])]));
        $this->writeFile('RAD_20260920.csv', $this->csv([$this->row(['encounter_num' => 'GH-A-' . $this->token])]));

        $results = $this->stager()->run($this->dropDir);

        foreach ($results as $file) {
            $this->batchIds[] = $file->batchId;
        }
        $this->assertSame(['RAD_20260920.csv', 'RAD_20260921.csv'], array_map(static fn ($f): string => $f->fileName, $results));
        $this->assertLessThan($results[1]->batchId, $results[0]->batchId);
    }

    public function testAMissingDirectoryIsAnError(): void
    {
        $this->expectException(StageException::class);
        $this->stager()->run($this->dropDir . '/missing');
    }

    private function stager(): ChargeFileStager
    {
        $clock = new class (new \DateTimeImmutable(self::NOW)) implements ClockInterface {
            public function __construct(private readonly \DateTimeImmutable $now)
            {
            }

            public function now(): \DateTimeImmutable
            {
                return $this->now;
            }
        };

        return new ChargeFileStager(new ChargeFileReader(), new ChargeRowParser(), new ChargeBatchRepository(), $clock);
    }

    /**
     * One charge row with the columns the reader requires plus a policy; $overrides
     * replace values. The accession carries this run's token.
     *
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    private function row(array $overrides = []): array
    {
        return array_merge([
            'encounter_num' => 'GH-' . $this->token,
            'pat_mrn' => 'MR0000000001',
            'pat_last' => 'Testpatient',
            'pat_first' => 'Ann',
            'pat_sex' => 'F',
            'pat_dob' => '03/25/1964',
            'enc_admit_date' => '08/20/2026',
            'enc_dx_codes' => 'I50.20,Z95.811',
            'prov_rend_name' => 'Reader, Ray',
            'prov_rend_npi' => '1234567893',
            'chg_dos' => '08/20/2026',
            'chg_cpt' => '93005',
            'chg_hcpcs' => '',
            'chg_units' => '1',
            'chg_pos' => '50',
            'chg_accession' => 'A' . $this->token,
            'chg_location' => 'GHC.TEST',
            'ins1_rank' => '1',
            'ins1_mnemonic' => 'MCDVT',
            'ins1_member_id' => '38097',
            'ins1_sub_name' => 'Testpatient, Ann',
            'ins1_sub_dob' => '03/25/1964',
            'ins1_sub_gender' => 'F',
            'ins1_relate' => '18',
        ], $overrides);
    }

    /**
     * @param list<array<string, string>> $rows
     */
    private function csv(array $rows): string
    {
        $out = fopen('php://memory', 'r+');
        if ($out === false) {
            self::fail('Could not open memory stream');
        }
        fputcsv($out, array_keys($rows[0]), escape: '');
        foreach ($rows as $row) {
            fputcsv($out, array_values($row), escape: '');
        }
        rewind($out);
        $content = stream_get_contents($out);
        fclose($out);

        return is_string($content) ? $content : '';
    }

    /**
     * @param list<array<string, string>> $rows
     */
    private function writeXlsx(string $name, array $rows): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        foreach ([array_keys($rows[0]), ...array_map(array_values(...), $rows)] as $r => $cells) {
            foreach ($cells as $c => $value) {
                $sheet->setCellValueExplicit([$c + 1, $r + 1], $value, DataType::TYPE_STRING);
            }
        }
        $path = $this->dropDir . '/' . $name;
        (new Xlsx($spreadsheet))->save($path);
        $this->settle($path);
    }

    private function writeFile(string $name, string $content): string
    {
        $path = $this->dropDir . '/' . $name;
        file_put_contents($path, $content);
        $this->settle($path);

        return $path;
    }

    /** Date a file an hour before "now", well past the settle time. */
    private function settle(string $path): void
    {
        touch($path, (new \DateTimeImmutable(self::NOW))->getTimestamp() - 3600);
    }

    private static function removeTree(string $dir): void
    {
        if ($dir === '' || !is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if (!$item instanceof \SplFileInfo) {
                continue;
            }
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }

    /** A database value as text; null and non-scalars are ''. */
    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function root(): string
    {
        return dirname(__DIR__, 5);
    }
}
