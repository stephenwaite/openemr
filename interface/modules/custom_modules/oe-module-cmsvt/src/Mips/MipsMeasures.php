<?php

/**
 * The MIPS measures the report offers.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Mips;

use Cmsvt\OpenEMR\Modules\Customizations\Mips\Measures\Measure176;
use Cmsvt\OpenEMR\Modules\Customizations\Mips\Measures\Measure410;
use Cmsvt\OpenEMR\Modules\Customizations\Mips\Measures\Measure509;

final readonly class MipsMeasures
{
    /**
     * @return non-empty-list<MipsMeasure> in menu order
     */
    public static function all(): array
    {
        return [new Measure176(), new Measure410(), new Measure509()];
    }

    /** The measure with this number, or the first one. */
    public static function find(string $id): MipsMeasure
    {
        $all = self::all();
        foreach ($all as $measure) {
            if ($measure->id() === $id) {
                return $measure;
            }
        }
        return $all[0];
    }
}
