<?php

/**
 * Where step 1 fetches RRMC's charge files from.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Globals;

final readonly class SftpSettings
{
    /**
     * @param string $remoteFolder '' or a folder ending in '/'
     */
    public function __construct(
        public string $host,
        public int $port,
        public string $remoteFolder,
        public string $username,
        #[\SensitiveParameter] public string $password,
    ) {
    }

    public function remotePath(string $fileName): string
    {
        return $this->remoteFolder . $fileName;
    }
}
