<?php

/**
 * Finds the ordfile steps 4-6 work on.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Ordfile;

interface OrdfileSource
{
    /** The newest ordfile. */
    public function latest(): ?StoredOrdfile;

    /** The newest ordfile with this name (the full path step 3 saved, or just its file name). */
    public function byFileName(string $fileName): ?StoredOrdfile;

    /** The newest ordfile made on this day. */
    public function byDate(\DateTimeImmutable $date): ?StoredOrdfile;
}
