<?php

/**
 * A staged or held charge line, as posting reads it back.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Post;

use Cmsvt\OpenEMR\Modules\GiffordCharges\Reader\RawRow;

final readonly class PendingLine
{
    /**
     * @param bool $released a person chose to post it although it looks like a corrected re-send
     */
    public function __construct(
        public int $id,
        public int $batchId,
        public RawRow $row,
        public bool $released,
    ) {
    }
}
