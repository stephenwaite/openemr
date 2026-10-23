<?php

/**
 * Isolated tests for reading Gifford charge files (.xlsx and .csv).
 *
 * The files are generated here from made-up rows shaped like Gifford's sample,
 * so no file from Gifford is kept in the repository.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\Modules\GiffordCharges;

require_once __DIR__ . '/autoload.php';

use Cmsvt\OpenEMR\Modules\GiffordCharges\Reader\ChargeFileException;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Reader\ChargeFileReader;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ChargeFileReaderTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    #[Test]
    public function xlsxRowsAreKeyedByColumnWithNumbersAsPlainText(): void
    {
        $path = $this->xlsx([
            // Header in mixed case and a different order than Gifford's.
            ['Pat_MRN', 'encounter_num', 'pat_last', 'pat_first', 'pat_dob', 'pat_sex', 'pat_zip', 'enc_dx_codes', 'prov_rend_npi', 'chg_dos', 'chg_cpt', 'chg_units', 'chg_accession', 'chg_location'],
            // pat_dob as an Excel serial number cell, the zip as text with its leading zero.
            ['MR0000000001', 'GH0000000001', 'Testpatient', 'Ann', 25917, 'F', '05675', 'R42', 1234567893, '08/20/2026', '93005', 1, 'A0000000001', 'GHC.TEST'],
            [null, null, null, null, null, null, null, null, null, null, null, null, null, null],
            ['MR0000000002', 'GH0000000002', 'Testpatient', 'Bo', '10/18/1969', 'M', '05452', 'I10', 1234567893, '08/28/2026', '93005', 2, 'A0000000002', 'GHC.TEST'],
        ]);

        $rows = (new ChargeFileReader())->read($path);

        $this->assertCount(2, $rows, 'the blank row is skipped');
        $this->assertSame(2, $rows[0]->line);
        $this->assertSame(4, $rows[1]->line);
        $this->assertSame('MR0000000001', $rows[0]->get('pat_mrn'));
        $this->assertSame('25917', $rows[0]->get('pat_dob'));
        $this->assertSame('05675', $rows[0]->get('pat_zip'));
        $this->assertSame('1234567893', $rows[0]->get('prov_rend_npi'));
        $this->assertSame('2', $rows[1]->get('chg_units'));
        $this->assertFalse($rows[0]->has('chg_pos'));
        $this->assertSame('', $rows[0]->get('chg_pos'));
    }

    #[Test]
    public function csvRowsAreReadTheSameWay(): void
    {
        $path = $this->csv(implode("\n", [
            'encounter_num,pat_mrn,pat_last,pat_first,pat_dob,pat_sex,enc_dx_codes,prov_rend_npi,chg_dos,chg_cpt,chg_units,chg_accession,chg_location,chg_pos',
            'GH0000000003,MR0000000003,Testpatient,Cy,03/25/1964,M,"I10,R55",1234567893,08/25/2026,93005,1,A0000000003,GHC.TEST, 22 ',
        ]));

        $rows = (new ChargeFileReader())->read($path);

        $this->assertCount(1, $rows);
        $this->assertSame('I10,R55', $rows[0]->get('enc_dx_codes'));
        $this->assertSame('22', $rows[0]->get('chg_pos'), 'values are trimmed');
    }

    #[Test]
    public function aFileMissingRequiredColumnsIsRejected(): void
    {
        $path = $this->csv("encounter_num,pat_mrn\nGH1,MR1\n");

        $this->expectException(ChargeFileException::class);
        $this->expectExceptionMessage('missing columns: pat_last');
        (new ChargeFileReader())->read($path);
    }

    #[Test]
    public function otherFileTypesAreRejected(): void
    {
        $path = $this->tempFile('txt', 'encounter_num');

        $this->expectException(ChargeFileException::class);
        $this->expectExceptionMessage('.xlsx or .csv');
        (new ChargeFileReader())->read($path);
    }

    /**
     * @param list<list<string|int|null>> $rows
     */
    private function xlsx(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        foreach ($rows as $r => $cells) {
            foreach ($cells as $c => $value) {
                if ($value === null) {
                    continue;
                }
                $coordinate = [$c + 1, $r + 1];
                if (is_int($value)) {
                    $sheet->setCellValue($coordinate, $value);
                } else {
                    $sheet->setCellValueExplicit($coordinate, $value, DataType::TYPE_STRING);
                }
            }
        }
        $path = $this->tempFile('xlsx', '');
        (new Xlsx($spreadsheet))->save($path);
        return $path;
    }

    private function csv(string $content): string
    {
        return $this->tempFile('csv', $content);
    }

    private function tempFile(string $extension, string $content): string
    {
        $base = tempnam(sys_get_temp_dir(), 'gifford');
        if ($base === false) {
            throw new \RuntimeException('Could not create a temporary file');
        }
        unlink($base);
        $path = $base . '.' . $extension;
        file_put_contents($path, $content);
        $this->files[] = $path;
        return $path;
    }
}
