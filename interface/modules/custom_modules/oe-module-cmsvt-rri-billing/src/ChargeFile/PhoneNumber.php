<?php

/**
 * The patient's phone number from the charge file ("802-555-0100").
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile;

final readonly class PhoneNumber
{
    /**
     * @param string $full the 12-character field, trimmed
     * @param string $area characters 1-3
     * @param string $exchange characters 5-7
     * @param string $line characters 9-12
     */
    public function __construct(
        public string $full,
        public string $area,
        public string $exchange,
        public string $line,
    ) {
    }

    public static function parse(string $field): self
    {
        return new self(
            trim($field),
            trim(substr($field, 0, 3)),
            trim(substr($field, 4, 3)),
            trim(substr($field, 8, 4)),
        );
    }
}
