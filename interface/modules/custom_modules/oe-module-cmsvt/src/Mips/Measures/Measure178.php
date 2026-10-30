<?php

/**
 * MIPS 178: Rheumatoid Arthritis (RA): Functional Status Assessment (2026 spec, v10.0).
 *
 * The RA denominator (RheumatoidArthritisMeasure); met by a functional status
 * assessment with an ACR-preferred tool, recorded as rule item act_rafunc in the year.
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

final readonly class Measure178 extends RheumatoidArthritisMeasure
{
    public function id(): string
    {
        return '178';
    }

    public function title(): string
    {
        return 'Rheumatoid Arthritis (RA): Functional Status Assessment';
    }

    public function ruleItem(): string
    {
        return 'act_rafunc';
    }

    protected function assessment(): string
    {
        return 'Functional Status';
    }

    protected function metDescription(): string
    {
        return 'functional status assessed with an ACR-preferred tool (PROMIS PF10a, HAQ-II or MD-HAQ) in the year';
    }
}
