<?php

/**
 * An insurance company as the RRI steps need it.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Lookup;

final readonly class PayerCompany
{
    /**
     * @param int $id insurance_companies.id
     * @param bool $electronic has a payer id (cms_id), i.e. is billed electronically
     *                         (the old system's claim type 'E')
     * @param string $assign the old insurance file's assignment flag (cmsvt_insfile.insassign:
     *                       'A' assigned, 'U' unassigned, '' when the file has no row)
     */
    public function __construct(
        public int $id,
        public bool $electronic,
        public string $assign = '',
        public string $name = '',
    ) {
    }

    /** The id as the old system's three-digit code ('003'). */
    public function legacyCode(): string
    {
        return str_pad((string) $this->id, 3, '0', STR_PAD_LEFT);
    }
}
