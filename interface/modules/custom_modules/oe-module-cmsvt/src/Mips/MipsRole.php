<?php

/**
 * What a quality data code means for a criterion's rates.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Mips;

enum MipsRole
{
    /** Performance met (for an inverse criterion, the worse outcome). */
    case Met;
    case Exception;
    case NotMet;
    /** No usable code: not reported, or conflicting codes. */
    case Missing;
}
