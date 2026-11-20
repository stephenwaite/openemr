<?php

/**
 * One file the import handled, for the run's summary (counts only, no patient data).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Stage;

final readonly class StagedFile
{
    /**
     * @param string $fileName the file's name in the drop directory
     * @param int $batchId the batch saved for it, or the earlier one for an already-seen file
     * @param string $movedTo where the file is now
     */
    public function __construct(
        public string $fileName,
        public FileOutcome $outcome,
        public int $batchId,
        public int $lineCount,
        public int $heldCount,
        public string $movedTo,
    ) {
    }
}
