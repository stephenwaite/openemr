<?php

/**
 * Isolated tests for the "PDF Custom" statement text layout.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\Billing;

use DateTimeImmutable;
use OpenEMR\Billing\Statement\CustomPdfStatementText;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('isolated')]
class CustomPdfStatementTextTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['disable_translation'] = true;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['disable_translation']);
    }

    public function testSinglePageLayout(): void
    {
        $text = $this->render(self::statement(['Jane Doe', '1 Main  St', 'Apt 2', 'Burlington, VT 05401']));
        $lines = explode("\r\n", $text);

        $this->assertSame(sprintf('%-9s %-55s %6s ', '', 'JANE DOE', '42'), $lines[0]);
        $this->assertSame(sprintf('%-9s %-43s %-8s ', '', '1 MAIN ST', '09 28 26'), $lines[1]);
        $this->assertSame(sprintf('%-9s %-43s ', '', 'APT 2'), $lines[2]);
        $this->assertSame(sprintf('%-9s %-43s %-8s %9s', '', 'BURLINGTON, VT 05401', '09 28 26', '60.00'), $lines[3]);
        $this->assertSame('', $lines[4]);
        $this->assertSame('', $lines[5]);
        $this->assertSame(sprintf('%-8s %-44s   %-8s          %-8s ', '06 01 26', 'Office visit', ' 100.00', '  60.00'), $lines[6]);
        $this->assertSame(sprintf('%-8s %-44s           %8s', '06 20 26', 'Paid Ins1 Check', '40.00'), $lines[7]);
        $this->assertSame(sprintf('%-8s %-44s           %8s', '06 20 26', 'Adj Contractual', '-5.00'), $lines[8]);
        $this->assertStringEndsWith("\014", $text);
        $this->assertStringNotContainsString('CONTINUED', $text);
    }

    public function testAgingLineAgesFromLastPayment(): void
    {
        // Second notice: 60.00 aged from the 06-20 payment, 100 days before 09-28, lands in "over 90".
        $lines = explode("\r\n", $this->render(self::statement(['Jane Doe', '1 Main St', 'Burlington, VT 05401'], dunCount: 1)));
        $ageline = $lines[count($lines) - 2];

        $this->assertSame(
            sprintf(' %7.2f %10s %7.2f', 60, '', 0) . sprintf('   %7.2f', 0) . sprintf('   %7.2f', 0)
            . sprintf('      %.2f              %.2f', 60, 60),
            $ageline
        );
    }

    public function testFirstNoticeIsAllCurrent(): void
    {
        $lines = explode("\r\n", $this->render(self::statement(['Jane Doe', '1 Main St', 'Burlington, VT 05401'])));

        $this->assertStringStartsWith(sprintf(' %7.2f %10s %7.2f', 60, '', 60), $lines[count($lines) - 2]);
    }

    public function testMissingStreetIsFlagged(): void
    {
        $lines = explode("\r\n", $this->render(self::statement(['Jane Doe', 'Burlington, VT 05401'])));

        $this->assertStringContainsString('***BAD ADDRESS***', $lines[1]);
        $this->assertSame('', $lines[2]);
        $this->assertStringContainsString('BURLINGTON, VT 05401', $lines[3]);
    }

    public function testLongStatementIsSplitEvery34DetailLines(): void
    {
        $detail = [];
        for ($i = 0; $i < 40; $i++) {
            $detail[sprintf('2026062%d  %04d', $i % 10, 3000 + $i)] = ['pmt' => 1, 'src' => 'Ins1', 'pmt_method' => 'Check'];
        }
        $pages = explode("\014", $this->render(self::statement(['Jane Doe', '1 Main St', 'Burlington, VT 05401'], detail: $detail)));

        $this->assertCount(3, $pages); // two parts and the empty string after the final form feed
        $this->assertStringContainsString('CONTINUED PAGE 1', $pages[0]);
        $this->assertSame(34, substr_count($pages[0], 'Paid Ins1 Check'));
        $this->assertSame(6, substr_count($pages[1], 'Paid Ins1 Check'));
        $this->assertSame('', $pages[2]);
    }

    /**
     * @param array<string, mixed> $stmt
     */
    private function render(array $stmt): string
    {
        return (new CustomPdfStatementText(new DateTimeImmutable('2026-09-28')))->render($stmt);
    }

    /**
     * @param list<string> $to
     * @param ?array<string, array<string, int|string>> $detail
     * @return array<string, mixed>
     */
    private static function statement(array $to, int $dunCount = 0, ?array $detail = null): array
    {
        return [
            'pid' => 42,
            'amount' => '60.00',
            'dun_count' => $dunCount,
            'to' => $to,
            'lines' => [[
                'dos' => '2026-06-01',
                'desc' => 'Procedure 99213',
                'code_text' => 'Office visit',
                'amount' => '95.00',
                'paid' => '35.00',
                'detail' => $detail ?? [
                    '          1000' => ['chg' => '100.00'],
                    '20260620  2000' => ['pmt' => 40, 'src' => 'Ins1', 'pmt_method' => 'Check'],
                    '20260620  2001' => ['chg' => 5, 'rsn' => 'Contractual'],
                ],
            ]],
        ];
    }
}
