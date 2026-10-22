<?php

/**
 * Saves step 3's ordfile, work comp and email/authorization records.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Ordfile;

interface StepThreeFiles
{
    /** @return int the new ordfile's id */
    public function createOrdfile(string $fileName): int;

    /** @return int the line's ordfile_record_id */
    public function saveOrdfileLine(int $ordfileId, OrdfileLine $line): int;

    /** Inserts or replaces the work comp address for the record's key. */
    public function saveWorkComp(WorkCompRecord $record): void;

    /** Adds the row with the next key. */
    public function saveEmailAuth(EmailAuthRecord $record): void;
}
