<?php

/**
 * Isolated tests for X12Amount, the money format the 837P uses in loop 2430
 * (SVD02 and the CAS amounts).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\Billing;

use OpenEMR\Billing\X12\X12Amount;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('isolated')]
class X12AmountTest extends TestCase
{
    /**
     * @return array<string, array{float|int|string, string}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function amountProvider(): array
    {
        return [
            'zero is a digit, not .00' => ['0.00', '0'],
            'zero as a number' => [0, '0'],
            'negative zero' => ['-0.00', '0'],
            'under a dollar drops the leading zero' => ['0.50', '.50'],
            'negative under a dollar' => [-0.5, '-.50'],
            'whole dollars keep their digits' => ['166.67', '166.67'],
            'a float is rounded to cents' => [154.333, '154.33'],
            'thousands' => [1000, '1000.00'],
            'not a number is zero' => ['', '0'],
        ];
    }

    #[DataProvider('amountProvider')]
    public function testFormat(float|int|string $amount, string $expected): void
    {
        $this->assertSame($expected, X12Amount::format($amount));
    }
}
