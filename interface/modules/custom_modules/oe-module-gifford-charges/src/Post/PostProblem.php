<?php

/**
 * A reason a staged charge line can't be posted yet; the line is held with it.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Post;

/**
 * The message is shown to billers on the review page, so it names codes, NPIs and
 * payers but never patient details.
 */
final class PostProblem extends \RuntimeException
{
}
