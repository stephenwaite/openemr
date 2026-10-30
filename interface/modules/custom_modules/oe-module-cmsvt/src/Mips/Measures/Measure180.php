<?php

/**
 * MIPS 180: Rheumatoid Arthritis (RA): Glucocorticoid Management (2026 spec, v10.0).
 *
 * The RA denominator (RheumatoidArthritisMeasure); met by a glucocorticoid
 * management assessment recorded as rule item act_glucocorticoid in the year.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Margaret Haywood <openemr@cmsvt.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Mips\Measures;

final readonly class Measure180 extends RheumatoidArthritisMeasure
{
    public function id(): string
    {
        return '180';
    }

    public function title(): string
    {
        return 'Rheumatoid Arthritis (RA): Glucocorticoid Management';
    }

    public function ruleItem(): string
    {
        return 'act_glucocorticoid';
    }

    protected function assessment(): string
    {
        return 'Glucocorticoid Management';
    }

    protected function metDescription(): string
    {
        return 'glucocorticoid use assessed, with a management plan where needed, in the year';
    }
}
