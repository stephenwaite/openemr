<?php

/**
 * Isolated tests for the Gifford 837P service location rule.
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

use Cmsvt\OpenEMR\Modules\GiffordCharges\Billing\GiffordClaimRules;
use OpenEMR\Billing\Claim;
use OpenEMR\Billing\X12\Claim837PRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GiffordClaimRulesTest extends TestCase
{
    private const GIFFORD_FACILITY_ID = 7;

    private function claim(int $facilityId, string $claimType): Claim
    {
        $claim = $this->createStub(Claim::class);
        $claim->facility = ['id' => (string) $facilityId];
        $claim->method('claimType')->willReturn($claimType);
        return $claim;
    }

    private function inner(bool $sendsServiceFacility): Claim837PRules
    {
        $inner = $this->createStub(Claim837PRules::class);
        $inner->method('sendsServiceFacility')->willReturn($sendsServiceFacility);
        $inner->method('submitterIdentifier')->willReturn('INNER');
        $inner->method('warnings')->willReturn(['inner warning']);
        return $inner;
    }

    /**
     * @return array<string, array{int, string, bool}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function serviceFacilityProvider(): array
    {
        return [
            'Gifford, Medicare B: read location, no 2310C' => [self::GIFFORD_FACILITY_ID, 'MB', false],
            'Gifford, Medicare A: read location, no 2310C' => [self::GIFFORD_FACILITY_ID, 'MA', false],
            'Gifford, Medicaid: read location, no 2310C' => [self::GIFFORD_FACILITY_ID, 'MC', false],
            'Gifford, BCBS: Gifford in 2310C' => [self::GIFFORD_FACILITY_ID, 'BL', true],
            'Gifford, commercial (Medicare Advantage too): Gifford in 2310C' => [self::GIFFORD_FACILITY_ID, 'CI', true],
            'Gifford, other HCFA: Gifford in 2310C' => [self::GIFFORD_FACILITY_ID, '16', true],
            'another facility, Medicare: the site rules decide' => [3, 'MB', true],
        ];
    }

    #[Test]
    #[DataProvider('serviceFacilityProvider')]
    public function serviceFacilityByPayer(int $facilityId, string $claimType, bool $expected): void
    {
        $rules = new GiffordClaimRules($this->inner(true), self::GIFFORD_FACILITY_ID);
        $this->assertSame($expected, $rules->sendsServiceFacility($this->claim($facilityId, $claimType), true));
    }

    #[Test]
    public function commercialKeepsTheSiteRulesAnswer(): void
    {
        // When the site's rules leave 2310C out (e.g. same NPI as the billing provider), so does this.
        $rules = new GiffordClaimRules($this->inner(false), self::GIFFORD_FACILITY_ID);
        $this->assertFalse($rules->sendsServiceFacility($this->claim(self::GIFFORD_FACILITY_ID, 'BL'), true));
    }

    #[Test]
    public function otherDecisionsAreTheWrappedRules(): void
    {
        $rules = new GiffordClaimRules($this->inner(true), self::GIFFORD_FACILITY_ID);
        $claim = $this->claim(self::GIFFORD_FACILITY_ID, 'MB');
        $this->assertSame('INNER', $rules->submitterIdentifier($claim));
        $this->assertSame(['inner warning'], $rules->warnings($claim));
    }

    #[Test]
    public function isGiffordEncounter(): void
    {
        $this->assertTrue(GiffordClaimRules::isGiffordEncounter($this->claim(7, 'MB'), 7));
        $this->assertFalse(GiffordClaimRules::isGiffordEncounter($this->claim(3, 'MB'), 7));
        // Setting unset (0): never a Gifford encounter.
        $this->assertFalse(GiffordClaimRules::isGiffordEncounter($this->claim(0, 'MB'), 0));
    }
}
