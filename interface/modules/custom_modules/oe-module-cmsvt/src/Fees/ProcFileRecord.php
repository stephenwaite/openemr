<?php

/**
 * One record of a CMS fee file (COBOL copybook PROCFILE01).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Fees;

final readonly class ProcFileRecord
{
    /**
     * @param int $lineNumber the record's line in the file, from 1
     * @param string $cdm PROC-CDM, the charge master number
     * @param string $code PROC-CPT, trimmed
     * @param string $modifier PROC-MOD, trimmed ('' when none)
     * @param string $type PROC-TYPE
     * @param string $title PROC-TITLE, trimmed
     * @param int $feeCents PROC-AMOUNT in cents (the copybook's implied two decimals)
     */
    public function __construct(
        public int $lineNumber,
        public string $cdm,
        public string $code,
        public string $modifier,
        public string $type,
        public string $title,
        public int $feeCents,
    ) {
    }
}
