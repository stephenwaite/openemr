<?php

/**
 * Where an accession's diagnoses come from when no coder has confirmed them.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Dx;

enum DxSource
{
    /** The suggestion read from the report posts. */
    case Suggestion;
    /** The codes Gifford sent for the visit post. */
    case Gifford;
    /** Neither posts: a coder confirms. */
    case Coder;
}
