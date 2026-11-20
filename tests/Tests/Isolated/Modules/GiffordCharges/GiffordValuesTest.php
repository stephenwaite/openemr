<?php

/**
 * Isolated tests for the Gifford charge file value conversions.
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

use Cmsvt\OpenEMR\Modules\GiffordCharges\Parse\GiffordValues;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GiffordValuesTest extends TestCase
{
    /**
     * @return array<string, array{string, ?string}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function dateProvider(): array
    {
        return [
            'mm/dd/yyyy' => ['10/18/1969', '1969-10-18'],
            'Excel serial day number' => ['25917', '1970-12-15'],
            'Excel serial for a recent date' => ['46254', '2026-08-20'],
            'yyyy-mm-dd' => ['2026-08-25', '2026-08-25'],
            'empty' => ['', null],
            'impossible day' => ['02/30/2026', null],
            'two-digit year' => ['08/25/26', null],
            'a number too large to be a date' => ['9999991199', null],
            'text' => ['unknown', null],
        ];
    }

    #[Test]
    #[DataProvider('dateProvider')]
    public function datesAreReadInBothForms(string $value, ?string $expected): void
    {
        $this->assertSame($expected, GiffordValues::date($value)?->format('Y-m-d'));
    }

    /**
     * @return array<string, array{string, ?string}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function dateTimeProvider(): array
    {
        return [
            'mm/dd/yyyy hh:mm' => ['11/07/2026 14:05', '2026-11-07 14:05:00'],
            'one-digit hour' => ['11/07/2026 9:05', '2026-11-07 09:05:00'],
            'with seconds' => ['11/07/2026 14:05:59', '2026-11-07 14:05:59'],
            'yyyy-mm-dd hh:mm:ss' => ['2026-11-07 00:00:01', '2026-11-07 00:00:01'],
            'ISO T separator' => ['2026-11-07T14:05', '2026-11-07 14:05:00'],
            'a date alone is midnight' => ['11/07/2026', '2026-11-07 00:00:00'],
            'empty' => ['', null],
            'hour 24' => ['11/07/2026 24:00', null],
            'minute 60' => ['11/07/2026 10:60', null],
            'impossible day' => ['02/30/2026 10:00', null],
            'a serial number with a time' => ['46254 10:00', null],
            'text' => ['soon', null],
        ];
    }

    #[Test]
    #[DataProvider('dateTimeProvider')]
    public function dateTimesAreReadWithOrWithoutSeconds(string $value, ?string $expected): void
    {
        $this->assertSame($expected, GiffordValues::dateTime($value)?->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function jsonTextDecodesAStringBodyWithOrWithoutItsQuotes(): void
    {
        $this->assertSame("a\nb\tc \"d\" e\\f g/h é", GiffordValues::jsonText('a\nb\tc \"d\" e\\\\f g\/h \u00e9'));
        $this->assertSame("a\nb", GiffordValues::jsonText('"a\nb"'));
        $this->assertSame("a\nb\nc", GiffordValues::jsonText('a\r\nb\rc'), 'line endings become \n');
        $this->assertSame('plain', GiffordValues::jsonText('plain'));
        $this->assertSame('', GiffordValues::jsonText(''));
        $this->assertSame('"', GiffordValues::jsonText('\"'), 'an escaped quote alone is a body, not a pair of quotes');
        $this->assertNull(GiffordValues::jsonText('a "bare" quote'));
        $this->assertNull(GiffordValues::jsonText('bad \q escape'));
        $this->assertNull(GiffordValues::jsonText("a raw\ttab"));
    }

    #[Test]
    public function lastFirstSplitsOnTheFirstComma(): void
    {
        $this->assertSame(['Duck', 'Daffney'], GiffordValues::lastFirst('Duck, Daffney'));
        $this->assertSame(['Smith', 'Jo Ann'], GiffordValues::lastFirst('Smith,Jo Ann'));
        $this->assertSame(['Cher', ''], GiffordValues::lastFirst('Cher'));
    }

    #[Test]
    public function sexAndRelationshipMapToOpenEmrValues(): void
    {
        $this->assertSame('Male', GiffordValues::sex('m'));
        $this->assertSame('Female', GiffordValues::sex('F'));
        $this->assertSame('UNK', GiffordValues::sex('U'));
        $this->assertSame('self', GiffordValues::relationship('18'));
        $this->assertSame('spouse', GiffordValues::relationship('01'));
        $this->assertSame('child', GiffordValues::relationship('19'));
        $this->assertSame('other', GiffordValues::relationship('G8'));
    }

    #[Test]
    public function placeOfServiceIsTwoDigits(): void
    {
        $this->assertSame('50', GiffordValues::placeOfService('50'));
        $this->assertSame('05', GiffordValues::placeOfService('5'));
        $this->assertNull(GiffordValues::placeOfService('0'));
        $this->assertNull(GiffordValues::placeOfService('150'));
        $this->assertNull(GiffordValues::placeOfService('O2'));
    }

    #[Test]
    public function diagnosesAreUpperCasedDeduplicatedAndChecked(): void
    {
        $this->assertSame(
            [['I10', 'R53.83', 'Z13.1'], []],
            GiffordValues::diagnoses('i10, R53.83,Z13.1,I10')
        );
        $this->assertSame(
            [['R42'], ['786.50', 'CHEST PAIN']],
            GiffordValues::diagnoses('786.50,R42,chest pain')
        );
    }
}
