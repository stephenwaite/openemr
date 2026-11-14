<?php

/**
 * Isolated tests for reading the unloaded NCCI edit file.
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

use Cmsvt\OpenEMR\Modules\GiffordCharges\Cci\CciEdit;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Cci\CciFileException;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Cci\CciFileReader;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Cci\CciIndicator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CciFileReaderTest extends TestCase
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
    public function eachLineIsTwoCodesAndAnIndicator(): void
    {
        $path = $this->file("0144T712501\r\n7350172170" . "1\n\n7363073610" . "9\n7350172170" . "0\n");

        $edits = (new CciFileReader())->read($path);

        $this->assertEquals([
            new CciEdit('0144T', '71250', CciIndicator::WithModifier),
            new CciEdit('73501', '72170', CciIndicator::NotAllowed),
            new CciEdit('73630', '73610', CciIndicator::NotApplicable),
        ], $edits, 'a pair given twice keeps its last line, in its first place');
    }

    #[Test]
    public function aLineOfAnotherShapeStopsTheRead(): void
    {
        $path = $this->file("73501721701\n73501 721701\n735017217\n73501721702\n");

        $this->expectException(CciFileException::class);
        $this->expectExceptionMessage('3 line(s) that are not two 5-character codes and an indicator of 0, 1 or 9: line 2, 3, 4');
        (new CciFileReader())->read($path);
    }

    #[Test]
    public function anEmptyOrMissingFileIsRejected(): void
    {
        $reader = new CciFileReader();
        try {
            $reader->read($this->file("\n\n"));
            $this->fail('an empty file was read');
        } catch (CciFileException $e) {
            $this->assertSame('NCCI edit file is empty', $e->getMessage());
        }

        $this->expectException(CciFileException::class);
        $this->expectExceptionMessage('not found or not readable');
        $reader->read(sys_get_temp_dir() . '/gifford-no-such-cci-file');
    }

    private function file(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'giffordcci');
        if ($path === false) {
            throw new \RuntimeException('Could not create a temporary file');
        }
        file_put_contents($path, $content);
        $this->files[] = $path;

        return $path;
    }
}
