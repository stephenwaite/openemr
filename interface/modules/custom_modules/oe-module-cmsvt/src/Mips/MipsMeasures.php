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
use Cmsvt\OpenEMR\Modules\Customizations\Mips\Measures\Measure178;
use Cmsvt\OpenEMR\Modules\Customizations\Mips\Measures\Measure180;
use Cmsvt\OpenEMR\Modules\Customizations\Mips\Measures\Measure410;
use Cmsvt\OpenEMR\Modules\Customizations\Mips\Measures\Measure509;

final readonly class MipsMeasures
{
    /**
     * @return non-empty-list<MipsMeasure> in menu order
     */
    public static function all(): array
    {
        return [new Measure176(), new Measure178(), new Measure180(), new Measure410(), new Measure509()];
    }

    /**
     * The measures a site shows: those its setting lists ("176, 410, 509"), or
     * all of them when it lists none the report has.
     *
     * @return non-empty-list<MipsMeasure> in menu order
     */
    public static function enabled(string $setting): array
    {
        $ids = array_map(trim(...), explode(',', $setting));
        $enabled = array_values(array_filter(
            self::all(),
            static fn(MipsMeasure $measure): bool => in_array($measure->id(), $ids, true)
        ));
        return $enabled === [] ? self::all() : $enabled;
    }

    /**
     * The measure with this number, or the first one.
     *
     * @param ?non-empty-list<MipsMeasure> $measures to pick from; null: all
     */
    public static function find(string $id, ?array $measures = null): MipsMeasure
    {
        $measures ??= self::all();
        foreach ($measures as $measure) {
            if ($measure->id() === $id) {
                return $measure;
            }
        }
        return $measures[0];
    }
}
