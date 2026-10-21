<?php

/**
 * Status of a staged charge line (gifford_charge_line.status).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Stage;

enum LineStatus: string
{
    /** No problems: ready to post. */
    case Staged = 'staged';
    /** Has problems: never posted until they are resolved. */
    case Held = 'held';
}
