<?php

/**
 * The modifier indicator of an NCCI procedure-to-procedure edit.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Cci;

enum CciIndicator: string
{
    /** The two codes are not billed together; no modifier makes them payable. */
    case NotAllowed = '0';
    /** Billed together when the column 2 code carries modifier 59 (or XE, XS, XP, XU). */
    case WithModifier = '1';
    /** The edit was deleted: it does not apply. */
    case NotApplicable = '9';
}
