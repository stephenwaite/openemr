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
use Cmsvt\OpenEMR\Modules\GiffordCharges\Dx\DxSource;
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
     * @return array<string, array{list<array{string, string}>, list<string>, list<string>, list<string>, string, string, DxSource, list<string>, list<string>}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function decisionProvider(): array
    {
        $s = DxSource::Suggestion;
        $g = DxSource::Gifford;
        $e = DxSource::Exam;
        $c = DxSource::Coder;

        return [
            'screening mammogram: Z12.31, whatever the suggestion' => [[['N63.10', 'high']], [], [], ['77067', '77063'], 'medicare', 'GMC.MAMMO', $e, ['Z12.31'], []],
            'screening mammogram: Gifford\'s first other valid code comes second' => [[], ['Z99.9Z', 'Z12.31', 'Z80.3', 'Z78.0'], ['Z99.9Z'], ['77067'], 'other', 'GMC.MAMMO', $e, ['Z12.31', 'Z80.3'], []],
            'screening with another exam on the accession: the usual rule' => [[], ['Z12.31'], [], ['77067', '77065'], 'other', 'GMC.MAMMO', $g, ['Z12.31'], []],
            'screening mammogram, Z12.31 not in the site\'s code set: the usual rule' => [[], [], ['Z12.31'], ['77067'], 'other', 'GMC.MAMMO', $c, [], ['no suggestion', 'Gifford sent no diagnosis']],
            'high: the suggestion posts' => [[['R07.9', 'high'], ['I10', 'medium']], ['I10', 'R07.9'], [], ['71046'], 'other', 'GMC.XRAY', $s, ['R07.9', 'I10'], []],
            'high, not among Gifford\'s: still posts' => [[['J18.9', 'high']], ['R07.9'], [], ['71046'], 'other', 'GMC.ED', $s, ['J18.9'], []],
            'high, Gifford sent none: posts' => [[['R07.9', 'high']], [], [], ['71046'], 'other', 'GMC.ED', $s, ['R07.9'], []],
            'high, more than two codes: the first two' => [[['J18.9', 'high'], ['R05.9', 'high'], ['I10', 'high']], [], [], ['71046'], 'other', 'GMC.ED', $s, ['J18.9', 'R05.9'], []],
            'high, an invalid code' => [[['R07.9', 'high'], ['S52.574A', 'high']], ['R07.9'], ['S52.574A'], ['71046'], 'other', 'GMC.ED', $c, [], ['not valid for coding: S52.574A']],
            'high, cannot be primary, any payer' => [[['Z91.81', 'high']], ['Z91.81'], [], ['71046'], 'other', 'GMC.CT', $c, [], ['first code cannot be primary']],
            'high, unspecified, Medicaid primary' => [[['R07.9', 'high']], ['R07.9'], [], ['71046'], 'medicaid', 'GMC.CT', $c, [], ['first code is unspecified (Medicaid primary)']],
            'high, unspecified, Medicare primary: posts' => [[['R07.9', 'high']], ['R07.9'], [], ['71046'], 'medicare', 'GMC.CT', $s, ['R07.9'], []],
            'high, Medicare exam, no code on its list' => [[['M85.80', 'high']], ['M85.80'], [], ['77080'], 'medicare', 'GMC.BD', $c, [], ['no code on the Medicare list for the exam']],
            'high, Medicare Advantage exam, a later code on its list' => [[['M85.80', 'high'], ['Z13.820', 'high']], ['M85.80'], [], ['77080'], 'medicare advantage', 'GMC.BD', $s, ['M85.80', 'Z13.820'], []],
            'high, Medicare exam, a code inside a range' => [[['E24.3', 'high']], ['E24.3'], [], ['77080'], 'medicare', 'GMC.BD', $s, ['E24.3'], []],
            'high, Medicare exam, a code under the end of a range' => [[['E24.41', 'high']], ['E24.41'], [], ['77080'], 'medicare', 'GMC.BD', $s, ['E24.41'], []],
            'high, the exam list is Medicare\'s: other payers post' => [[['M85.80', 'high']], ['M85.80'], [], ['77080'], 'other', 'GMC.BD', $s, ['M85.80'], []],
            'medium: Gifford\'s first two post' => [[['J18.9', 'medium']], ['R05.9', 'I10', 'E11.9'], [], ['71046'], 'other', 'GMC.XRAY', $g, ['R05.9', 'I10'], []],
            'low: Gifford\'s post' => [[['J18.9', 'low']], ['R05.9'], [], ['71046'], 'other', 'GMC.XRAY', $g, ['R05.9'], []],
            'medium, Gifford sent none' => [[['J18.9', 'medium']], [], [], ['71046'], 'other', 'GMC.ED', $c, [], ['first code is not high confidence', 'Gifford sent no diagnosis']],
            'medium, the suggestion\'s invalid code does not matter' => [[['S52.574A', 'medium']], ['R05.9'], ['S52.574A'], ['71046'], 'other', 'GMC.ED', $g, ['R05.9'], []],
            'medium, Gifford\'s code is not valid' => [[['J18.9', 'medium']], ['Q99.9Z'], ['Q99.9Z'], ['71046'], 'other', 'GMC.ED', $c, [], ['first code is not high confidence', "Gifford's: not valid for coding: Q99.9Z"]],
            'medium, Gifford\'s first cannot be primary' => [[['J18.9', 'medium']], ['Z91.81'], [], ['71046'], 'other', 'GMC.ED', $c, [], ['first code is not high confidence', "Gifford's: first code cannot be primary"]],
            'medium, Gifford\'s third code is not looked at' => [[['J18.9', 'medium']], ['R05.9', 'I10', 'Q99.9Z'], ['Q99.9Z'], ['71046'], 'other', 'GMC.ED', $g, ['R05.9', 'I10'], []],
            'no suggestion: Gifford\'s post' => [[], ['R07.9'], [], ['71046'], 'other', 'GMC.MRI', $g, ['R07.9'], []],
            'no suggestion, Gifford sent none' => [[], [], [], ['71046'], 'other', 'GMC.MRI', $c, [], ['no suggestion', 'Gifford sent no diagnosis']],
            'surgery film: Gifford\'s, whatever the suggestion' => [[['Z96.642', 'high']], ['M16.12'], [], ['73501'], 'other', 'GMC.OR', $g, ['M16.12'], []],
            'surgery film, Gifford sent none' => [[['Z96.642', 'high']], [], [], ['73501'], 'other', 'GMC.OR', $c, [], ['film taken during surgery', 'Gifford sent no diagnosis']],
            'Gifford\'s on a Medicare exam, none on its list' => [[['Z13.820', 'medium']], ['M85.80'], [], ['77080'], 'medicare', 'GMC.BD', $c, [], ['first code is not high confidence', "Gifford's: no code on the Medicare list for the exam"]],
        ];
    }

    /**
     * @param list<array{string, string}> $suggested [code, confidence]
     * @param list<string> $gifford
     * @param list<string> $invalid
     * @param list<string> $cpts
     * @param list<string> $codes
     * @param list<string> $reasons
     */
    #[DataProvider('decisionProvider')]
    public function testDecide(array $suggested, array $gifford, array $invalid, array $cpts, string $payerClass, string $location, DxSource $source, array $codes, array $reasons): void
    {
        $suggestion = array_map(static fn (array $s): array => ['code' => $s[0], 'confidence' => $s[1]], $suggested);

        $decision = DxRule::decide($suggestion, $gifford, $invalid, $cpts, $payerClass, $location, self::lists());

        $this->assertSame([$source, $codes, $reasons], [$decision->source, $decision->codes, $decision->reasons]);
        $this->assertSame($source !== DxSource::Coder, $decision->posts());
    }

    public function testOnlyAScreeningMammogramIsCodedByExam(): void
    {
        $this->assertNull(DxRule::byExam([], ['Z12.31'], []));
        $this->assertNull(DxRule::byExam(['77066'], ['Z12.31'], []));
        $this->assertSame(['Z12.31'], DxRule::byExam(['77063', '77067'], ['Z12.31'], [])?->codes);
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
