<?php

/**
 * A way to deliver a claim file to an X12 partner other than SFTP.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Billing\BillingProcessor;

/**
 * A listener of X12RemoteTransportEvent sets one for a partner whose files it delivers
 * itself (a payer that only takes uploads through its web portal, say). The send service
 * marks the file in progress, calls send() and records the result.
 */
interface X12RemoteTransportInterface
{
    /**
     * Deliver the claim file. Report every failure in the result: an exception thrown
     * here would stop the send service's run.
     */
    public function send(string $filename, string $contents): X12RemoteTransportResult;
}
