<?php

/**
 * Status of a charge file's batch row (gifford_charge_batch.status).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Stage;

enum BatchStatus: string
{
    /** Read; every row saved as a line. */
    case Staged = 'staged';
    /** Could not be read (wrong type, missing columns, ...); no lines saved. */
    case Failed = 'failed';
}
