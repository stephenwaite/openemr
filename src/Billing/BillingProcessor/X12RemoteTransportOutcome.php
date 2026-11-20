<?php

/**
 * How a claim file handed to an X12RemoteTransportInterface fared.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Billing\BillingProcessor;

enum X12RemoteTransportOutcome
{
    /** The partner has the file. */
    case Delivered;
    /** The partner could not be reached or refused the login; nothing was sent. */
    case LoginFailed;
    /** The file was not delivered, or the partner did not confirm that it was. */
    case UploadFailed;
}
