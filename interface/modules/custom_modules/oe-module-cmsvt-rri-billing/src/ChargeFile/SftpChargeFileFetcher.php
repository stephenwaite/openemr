<?php

/**
 * Gets a charge file from RRMC's SFTP server (the settings' host, folder and
 * login), through a .part file so a broken transfer never looks like a
 * charge file.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile;

use Cmsvt\OpenEMR\Modules\RriBilling\Globals\SftpSettings;
use phpseclib3\Net\SFTP;

final readonly class SftpChargeFileFetcher implements ChargeFileFetcher
{
    private const TIMEOUT_SECONDS = 30;

    public function __construct(private SftpSettings $settings)
    {
    }

    public function fetch(string $fileName, string $localPath): FetchOutcome
    {
        $sftp = new SFTP($this->settings->host, $this->settings->port, self::TIMEOUT_SECONDS);
        $part = $localPath . '.part';
        try {
            if (!$sftp->login($this->settings->username, $this->settings->password)) {
                return FetchOutcome::LoginFailed;
            }
            $remote = $this->settings->remotePath($fileName);
            if (!$sftp->file_exists($remote)) {
                return FetchOutcome::NotOnServer;
            }
            if ($sftp->get($remote, $part) === true && rename($part, $localPath)) {
                return FetchOutcome::Fetched;
            }
            return FetchOutcome::Failed;
        } catch (\RuntimeException) {
            // phpseclib's connection, timeout and closed-connection errors
            return FetchOutcome::Failed;
        } finally {
            $sftp->disconnect();
            if (is_file($part)) {
                unlink($part);
            }
        }
    }
}
