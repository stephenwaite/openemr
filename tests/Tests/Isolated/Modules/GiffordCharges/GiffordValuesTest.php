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
