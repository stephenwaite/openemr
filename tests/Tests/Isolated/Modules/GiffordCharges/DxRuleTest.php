<?php

/**
 * Isolated tests for the rule deciding whether a suggested diagnosis posts or waits for a coder.
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

use Cmsvt\OpenEMR\Modules\GiffordCharges\Dx\DxLists;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Dx\DxRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DxRuleTest extends TestCase
{
    private static function lists(): DxLists
    {
        return DxLists::fromArray([
            'not_primary' => ['Z9181'],
            'unspecified' => ['R079'],
            'exams' => [
                ['name' => 'Bone Density', 'cpts' => ['77080'], 'codes' => ['Z13820', 'M810'], 'ranges' => [['E242', 'E244']]],
            ],
        ]);
    }

    /**
     * @return array<string, array{list<array{string, string}>, list<string>, list<string>, list<string>, string, list<string>}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function reasonProvider(): array
    {
        return [
            'first code among Gifford\'s, high: posts' => [[['R07.9', 'high'], ['I10', 'medium']], ['I10', 'R07.9'], [], ['71046'], 'other', []],
            'no suggestion' => [[], ['R07.9'], [], ['71046'], 'other', ['no suggestion']],
            'Gifford sent none' => [[['R07.9', 'high']], [], [], ['71046'], 'other', ['Gifford sent no diagnosis']],
            'only a later code matches' => [[['J18.9', 'high'], ['R07.9', 'high']], ['R07.9'], [], ['71046'], 'other', ["first code is not among Gifford's"]],
            'same category is not a match' => [[['R07.89', 'high']], ['R07.9'], [], ['71046'], 'other', ["first code is not among Gifford's"]],
            'medium confidence' => [[['R07.9', 'medium']], ['R07.9'], [], ['71046'], 'other', ['first code is not high confidence']],
            'an invalid code anywhere' => [[['R07.9', 'high'], ['S52.574A', 'high']], ['R07.9'], ['S52.574A'], ['71046'], 'other', ['not valid for coding: S52.574A']],
            'cannot be primary, any payer' => [[['Z91.81', 'high']], ['Z91.81'], [], ['71046'], 'other', ['first code cannot be primary']],
            'unspecified, Medicaid primary' => [[['R07.9', 'high']], ['R07.9'], [], ['71046'], 'medicaid', ['first code is unspecified (Medicaid primary)']],
            'unspecified, Medicare primary: posts' => [[['R07.9', 'high']], ['R07.9'], [], ['71046'], 'medicare', []],
            'Medicare exam, no code on its list' => [[['M85.80', 'high']], ['M85.80'], [], ['77080'], 'medicare', ['no code on the Medicare list for the exam']],
            'Medicare Advantage exam, a later code on its list' => [[['M85.80', 'high'], ['Z13.820', 'high']], ['M85.80'], [], ['77080'], 'medicare advantage', []],
            'Medicare exam, a code inside a range' => [[['E24.3', 'high']], ['E24.3'], [], ['77080'], 'medicare', []],
            'Medicare exam, a code under the end of a range' => [[['E24.41', 'high']], ['E24.41'], [], ['77080'], 'medicare', []],
            'the exam list is Medicare\'s: other payers post' => [[['M85.80', 'high']], ['M85.80'], [], ['77080'], 'other', []],
            'several reasons' => [[['J18.9', 'low']], [], [], ['71046'], 'other', ['Gifford sent no diagnosis', 'first code is not high confidence']],
        ];
    }

    /**
     * @param list<array{string, string}> $suggested [code, confidence]
     * @param list<string> $gifford
     * @param list<string> $invalid
     * @param list<string> $cpts
     * @param list<string> $expected
     */
    #[DataProvider('reasonProvider')]
    public function testReasons(array $suggested, array $gifford, array $invalid, array $cpts, string $payerClass, array $expected): void
    {
        $suggestion = array_map(static fn (array $s): array => ['code' => $s[0], 'confidence' => $s[1]], $suggested);

        $this->assertSame($expected, DxRule::reasons($suggestion, $gifford, $invalid, $cpts, $payerClass, self::lists()));
    }

    public function testPayerClass(): void
    {
        $this->assertSame(
            ['medicaid', 'medicare', 'medicare advantage', 'medicare advantage', 'other', 'other'],
            array_map(DxRule::payerClass(...), ['MCD-VT', 'mcr-ab', 'BCBS-MCR', 'MCR-ADV', 'BCBS', ''])
        );
    }

    public function testNormalize(): void
    {
        $this->assertSame(
            ['R07.9', 'I10', 'S52.571A', 'Z13.820', '', '', ''],
            array_map(DxRule::normalize(...), ['r079', 'I10', 's52.571a', ' Z13.820 ', '71046', 'R0', 'R07.912345'])
        );
    }

    public function testAnExamTheWorkbookDoesNotCoverHasNoList(): void
    {
        $lists = self::lists();

        $this->assertNull($lists->supportsExam(['71046'], ['R07.9']));
        $this->assertFalse($lists->supportsExam(['71046', '77080'], ['R07.9']));
        $this->assertTrue($lists->supportsExam(['77080'], ['R07.9', 'M81.0']));
    }

    public function testAFileThatIsNotAListsFileIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DxLists::fromFile(__FILE__);
    }
}
