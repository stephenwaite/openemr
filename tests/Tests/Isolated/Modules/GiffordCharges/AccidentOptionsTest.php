<?php

/**
 * Isolated tests for AccidentOptions: which visits are accident claims and the cause each gets.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\Modules\GiffordCharges;

use Cmsvt\OpenEMR\Modules\GiffordCharges\Model\EncounterRecord;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Post\AccidentOptions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('isolated')]
class AccidentOptionsTest extends TestCase
{
    /**
     * @param ?array{bool, bool, bool} $expected employment related, auto accident, other accident; null: not an accident claim
     */
    #[Test]
    #[DataProvider('visits')]
    public function aVisitGetsOneCauseOrNone(bool $workersComp, ?string $accidentDate, string $claimType, ?array $expected): void
    {
        $options = AccidentOptions::forVisit(self::visit($workersComp, $accidentDate), $claimType);

        if ($expected === null) {
            $this->assertNull($options);
            return;
        }
        $this->assertNotNull($options);
        $this->assertSame($expected, [$options->employmentRelated, $options->autoAccident, $options->otherAccident]);
        $this->assertSame($accidentDate, $options->accidentDate?->format('Y-m-d'));
    }

    /**
     * @return array<string, array{bool, ?string, string, ?array{bool, bool, bool}}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function visits(): array
    {
        return [
            'nothing says accident' => [false, null, 'MB', null],
            'no payer, nothing said' => [false, null, '', null],
            'the file says work comp' => [true, '2026-10-03', '16', [true, false, false]],
            'a work comp payer, the file silent' => [false, null, 'wc', [true, false, false]],
            'work comp wins over an auto payer' => [true, null, 'AM', [true, false, false]],
            'an auto payer' => [false, '2026-11-02', 'AM', [false, true, false]],
            'a liability payer' => [false, null, 'LI', [false, false, true]],
            'a liability medical payer' => [false, null, 'LM', [false, false, true]],
            'an accident date on a health plan' => [false, '2026-11-05', 'BL', [false, false, true]],
        ];
    }

    #[Test]
    public function itNamesWhatTheFileLeftOut(): void
    {
        $complete = AccidentOptions::forVisit(self::visit(true, '2026-10-03'), 'WC');
        $noDate = AccidentOptions::forVisit(self::visit(true, null), 'WC');
        $auto = AccidentOptions::forVisit(self::visit(false, '2026-11-02'), 'AM');
        $autoNoDate = AccidentOptions::forVisit(self::visit(false, null), 'AM');

        $this->assertNull($complete?->missing());
        $this->assertSame('the accident date', $noDate?->missing());
        $this->assertSame('the auto accident state', $auto?->missing(), 'the file never carries the state');
        $this->assertSame('the accident date and the auto accident state', $autoNoDate?->missing());
    }

    private static function visit(bool $workersComp, ?string $accidentDate): EncounterRecord
    {
        return new EncounterRecord(
            'GH0000000001',
            new \DateTimeImmutable('2026-11-09'),
            null,
            $workersComp,
            $accidentDate === null ? null : new \DateTimeImmutable($accidentDate),
            '',
            ['S63.502A'],
            '1234567893',
            'Reader, Ray',
            '1234567893',
            'Sender, Sue',
            'GHC.TEST',
            '22',
        );
    }
}
