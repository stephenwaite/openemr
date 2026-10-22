<?php

/**
 * The site's account file (cmsvt_actfile).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Lookup;

use Cmsvt\OpenEMR\Modules\RriBilling\Account\AccountRecord;

interface AccountFile
{
    /**
     * @param string $actno the account number zero-filled to eight characters
     */
    public function find(string $actno): ?AccountRecord;

    /** Inserts or replaces the account (by actno). */
    public function save(AccountRecord $account): void;
}
