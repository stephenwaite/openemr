<?php

/**
 * What one run of the import did with one file in the drop directory.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Stage;

enum FileOutcome
{
    /** Read and saved as a new batch with its lines. */
    case Staged;
    /** Could not be read; saved as a failed batch and moved to failed/. */
    case Failed;
    /** Same bytes as a file already recorded; moved to processed/ and nothing saved. */
    case AlreadySeen;
}
