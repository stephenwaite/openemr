<?php

/**
 * An ordfile saved by step 3, with its lines in the order they were saved
 * (by account and sequence), as steps 4-6 read it back.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Ordfile;

final readonly class StoredOrdfile
{
    /**
     * @param array<int, OrdfileLine> $lines by ordfile_record_id
     */
    public function __construct(
        public int $id,
        public string $fileName,
        public array $lines,
    ) {
    }
}
