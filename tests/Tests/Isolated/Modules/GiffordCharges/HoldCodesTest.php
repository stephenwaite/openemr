<?php

/**
 * Isolated tests for the Hold These Codes setting of the Gifford charge import.
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

use Cmsvt\OpenEMR\Modules\GiffordCharges\Post\HoldCodes;
use PHPUnit\Framework\TestCase;

final class HoldCodesTest extends TestCase
{
    public function testTheSettingIsReadWithAnySeparatorAndInEitherCase(): void
    {
        $hold = HoldCodes::fromSetting(" 77067, 77063;g0279\n77065  77067 ");

        foreach (['77067', '77063', 'G0279', 'g0279', ' 77065 '] as $code) {
            $this->assertTrue($hold->holds($code), $code);
        }
        // A code is held whole: not by its start, and not a code that is merely near one.
        foreach (['77066', '7706', '770670', ''] as $code) {
            $this->assertFalse($hold->holds($code), $code);
        }
    }

    public function testABlankSettingHoldsNothing(): void
    {
        foreach (['', '  ', ' , ; '] as $setting) {
            $this->assertFalse(HoldCodes::fromSetting($setting)->holds('77067'), $setting);
            $this->assertFalse(HoldCodes::fromSetting($setting)->holds(''), $setting);
        }
    }

    public function testTheProblemNamesTheCodeAndWhereTheListIs(): void
    {
        $this->assertSame(
            'on hold: CPT4 77067 is in Hold These Codes (Admin > Config > Gifford Charge Import); it posts once the code is taken off that list, or skip it',
            HoldCodes::problem('CPT4', '77067')
        );
        $this->assertStringStartsWith(HoldCodes::PREFIX, HoldCodes::problem('HCPCS', 'G0279'));
    }
}
