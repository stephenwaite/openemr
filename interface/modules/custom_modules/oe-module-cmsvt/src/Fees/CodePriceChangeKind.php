<?php

/**
 * The kinds of change a fee file price makes.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Fees;

enum CodePriceChangeKind
{
    /** The code and modifier are not in codes yet: add it with its standard price. */
    case AddCode;
    /** The code exists without a standard price: add the price. */
    case AddPrice;
    /** The code's standard price differs: change it. */
    case ChangePrice;
    /** The code already has this standard price. */
    case Unchanged;
}
