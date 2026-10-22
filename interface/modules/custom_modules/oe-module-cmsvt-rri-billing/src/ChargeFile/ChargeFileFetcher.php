<?php

/**
 * Gets a day's charge file from RRMC.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile;

interface ChargeFileFetcher
{
    /** Copies the remote file $fileName to $localPath; $localPath is left alone unless it was fetched whole. */
    public function fetch(string $fileName, string $localPath): FetchOutcome;
}
