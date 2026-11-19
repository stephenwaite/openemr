<?php

/**
 * Lets a listener deliver an X12 partner's claim files itself, in place of the
 * send service's SFTP upload.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Events\Billing;

use OpenEMR\Billing\BillingProcessor\X12RemoteTransportInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched once for each waiting claim file, before the partner's SFTP settings are
 * looked at. With no transport set the file goes by SFTP as always.
 */
final class X12RemoteTransportEvent extends Event
{
    public const EVENT_NAME = 'billing.x12_remote.transport';

    private ?X12RemoteTransportInterface $transport = null;

    /**
     * @param \Closure(): string $password gives the partner's SFTP password, decrypted
     */
    public function __construct(
        public readonly int $partnerId,
        public readonly string $partnerName,
        public readonly string $sftpHost,
        public readonly string $sftpLogin,
        private readonly \Closure $password,
    ) {
    }

    /**
     * The password saved on the partner, for a transport that signs in with the same
     * account. Decrypted only when asked for.
     */
    public function getPassword(): string
    {
        return ($this->password)();
    }

    public function setTransport(X12RemoteTransportInterface $transport): void
    {
        $this->transport = $transport;
    }

    public function getTransport(): ?X12RemoteTransportInterface
    {
        return $this->transport;
    }
}
