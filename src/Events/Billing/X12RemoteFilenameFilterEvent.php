<?php

/**
 * Lets listeners rename a claim file before it is uploaded to an X12
 * partner's SFTP server, for clearinghouses that require their own names.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Events\Billing;

use Symfony\Contracts\EventDispatcher\Event;

final class X12RemoteFilenameFilterEvent extends Event
{
    public const EVENT_NAME = 'billing.x12_remote.filename.filter';

    public function __construct(public readonly string $sftpHost, private string $filename)
    {
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): void
    {
        $this->filename = basename($filename);
    }
}
