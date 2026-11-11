<?php

/**
 * Isolated tests for reading the Gifford payer crosswalk CSV.
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

use Cmsvt\OpenEMR\Modules\GiffordCharges\Payer\PayerCrosswalkException;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Payer\PayerCrosswalkReader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PayerCrosswalkReaderTest extends TestCase
{
    /** The crosswalk's header, with the descriptions it carries for people editing it. */
    private const HEADER = [
        'gifford_mnemonic',
        'gifford_name',
        'confidence',
        'street',
        'city',
        'state',
        'zip',
        'electronic (Y = high match with payer ID, or routed by Stephen; N = paper, no payer ID/partner)',
        'payer_id_to_use',
        'payer_id_source',
        'ins_type_code (2 Medicare B, 3 Medicaid, 17 commercial)',
        'x12_partner (default 111462 Optum iEDI)',
        'confirm (Y / N / other ins_key / NEW)',
    ];

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
    public function electronicAndPaperRowsAreRead(): void
    {
        $path = $this->csv([
            self::HEADER,
            ['MCR-AB', 'MEDICARE A/B', 'medium', 'PO BOX 7111', 'INDIANAPOLIS', 'in', '462077111', 'Y', '14512', '4800 MEDICARE B', '2', '6 NGS Medicare', ''],
            ['', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['aflac', 'AFLAC', 'high', 'PO BOX 1', 'COLUMBUS', 'GA', '31999', 'N', '', '', '17', '', ''],
            ['LIAB', 'Liability/Auto   claims', 'low', '', '', '', '', 'n', '', '', '20', '', ''],
        ]);

        $mappings = (new PayerCrosswalkReader())->read($path);

        $this->assertCount(3, $mappings, 'the blank row is skipped');
        [$medicare, $aflac, $liability] = $mappings;

        $this->assertSame(['MCR-AB', 'MEDICARE A/B', 2], [$medicare->mnemonic, $medicare->name, $medicare->insTypeCode]);
        $this->assertNotNull($medicare->electronic);
        $this->assertSame('14512', $medicare->electronic->payerId);
        $this->assertSame(6, $medicare->electronic->x12PartnerId);
        $address = $medicare->electronic->address;
        $this->assertSame(['PO BOX 7111', 'INDIANAPOLIS', 'IN', '462077111'], [$address->street, $address->city, $address->state, $address->postalCode]);

        // A paper payer keeps no address, even when the crosswalk suggested one.
        $this->assertSame(['AFLAC', 'AFLAC', 17], [$aflac->mnemonic, $aflac->name, $aflac->insTypeCode]);
        $this->assertNull($aflac->electronic);
        $this->assertSame(['LIAB', 'Liability/Auto claims', 20], [$liability->mnemonic, $liability->name, $liability->insTypeCode]);
        $this->assertNull($liability->electronic);
    }

    #[Test]
    public function everyBadRowIsReported(): void
    {
        $path = $this->csv([
            self::HEADER,
            ['OPTUM1', 'GOOD PAYER', 'high', 'PO BOX 1', 'TOWN', 'VT', '05401', 'Y', '87726', '', '17', '111462 Optum iEDI', ''],
            ['NOPAYERID', 'NO PAYER ID', 'high', '', '', '', '', 'Y', '', '', '17', '111462 Optum iEDI', ''],
            ['NOPARTNER', 'NO PARTNER', 'high', '', '', '', '', 'Y', '12345', '', '17', 'Optum iEDI', ''],
            ['PAPERPART', 'PAPER WITH PARTNER', 'low', '', '', '', '', 'N', '', '', '17', '111462 Optum iEDI', ''],
            ['BADTYPE', 'BAD TYPE', 'low', '', '', '', '', 'N', '', '', 'CI', '', ''],
            ['MAYBE', 'NEITHER', 'low', '', '', '', '', '?', '', '', '17', '', ''],
            ['BADZIP', 'BAD ZIP', 'high', 'PO BOX 1', 'TOWN', 'VT', '0540', 'Y', '12345', '', '17', '111462', ''],
            ['', 'NO MNEMONIC', 'low', '', '', '', '', 'N', '', '', '17', '', ''],
            ['NONAME', '', 'low', '', '', '', '', 'N', '', '', '17', '', ''],
            ['OPTUM1', 'SAME MNEMONIC AGAIN', 'low', '', '', '', '', 'N', '', '', '17', '', ''],
        ]);

        try {
            (new PayerCrosswalkReader())->read($path);
            $this->fail('A crosswalk with bad rows was read');
        } catch (PayerCrosswalkException $e) {
            $this->assertSame([
                'Line 3: NOPAYERID is electronic but has no payer_id_to_use',
                'Line 4: NOPARTNER is electronic but x12_partner does not start with a partner id',
                'Line 5: PAPERPART is a paper payer but has an x12_partner',
                'Line 6: BADTYPE: ins_type_code must be a number, not "CI"',
                'Line 7: MAYBE: electronic must be Y or N',
                'Line 8: BADZIP: zip must be 5 or 9 digits, not "0540"',
                'Line 9: no gifford_mnemonic',
                'Line 10: NONAME has no gifford_name',
                'Line 11: OPTUM1 is also on line 2',
            ], $e->problems);
        }
    }

    #[Test]
    public function aMissingColumnIsReported(): void
    {
        $header = array_values(array_filter(self::HEADER, static fn (string $h): bool => !str_starts_with($h, 'x12_partner')));
        $path = $this->csv([$header]);

        $this->expectException(PayerCrosswalkException::class);
        $this->expectExceptionMessage('Payer crosswalk is missing columns: x12_partner');
        (new PayerCrosswalkReader())->read($path);
    }

    #[Test]
    public function aMissingFileIsReported(): void
    {
        $this->expectException(PayerCrosswalkException::class);
        $this->expectExceptionMessage('Payer crosswalk not found or not readable');
        (new PayerCrosswalkReader())->read(sys_get_temp_dir() . '/no-such-crosswalk-' . bin2hex(random_bytes(4)) . '.csv');
    }

    /**
     * @param list<list<string>> $rows
     */
    private function csv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'payer-crosswalk-');
        if ($path === false) {
            $this->fail('Could not create a temporary file');
        }
        $this->files[] = $path;
        $handle = fopen($path, 'w');
        if ($handle === false) {
            $this->fail('Could not write the temporary file');
        }
        foreach ($rows as $row) {
            // The crosswalk is saved with Windows line endings.
            fputcsv($handle, $row, ',', '"', '', "\r\n");
        }
        fclose($handle);

        return $path;
    }
}
