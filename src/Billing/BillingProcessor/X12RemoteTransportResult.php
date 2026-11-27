<?php

/**
 * What became of a claim file handed to an X12RemoteTransportInterface.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Billing\BillingProcessor;

final readonly class X12RemoteTransportResult
{
    /**
     * @param list<string> $messages shown with the file in the claim file tracker
     */
    private function __construct(public X12RemoteTransportOutcome $outcome, public array $messages)
    {
    }

    /**
     * The partner has the file.
     */
    public static function delivered(string ...$messages): self
    {
        return new self(X12RemoteTransportOutcome::Delivered, array_values($messages));
    }

    /**
     * The partner could not be reached or did not accept the login; nothing was sent.
     */
    public static function loginFailed(string ...$messages): self
    {
        return new self(X12RemoteTransportOutcome::LoginFailed, array_values($messages));
    }

    /**
     * The file was not delivered, or the partner did not confirm that it was.
     */
    public static function uploadFailed(string ...$messages): self
    {
        return new self(X12RemoteTransportOutcome::UploadFailed, array_values($messages));
    }

    public function isDelivered(): bool
    {
        return $this->outcome === X12RemoteTransportOutcome::Delivered;
    }
}
