<?php

/**
 * How fetching a charge file from RRMC's SFTP server went.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile;

enum FetchOutcome
{
    case Fetched;
    case NotOnServer;
    case LoginFailed;
    case Failed;
}
