<?php

/**
 * What the payer import does with one crosswalk row.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Payer;

enum PayerChangeKind
{
    /** No map row yet: create the insurance company and map the mnemonic to it. */
    case Create;
    /** Mapped, but the insurance company was deleted: create a new one and remap. */
    case Recreate;
    /** Mapped to an existing company, which is left as billers have it. */
    case AlreadyMapped;
}
