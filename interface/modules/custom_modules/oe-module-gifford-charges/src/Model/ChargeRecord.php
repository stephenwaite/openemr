<?php

/**
 * One charge line from a Gifford charge row.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Model;

final readonly class ChargeRecord
{
    /**
     * @param string $codeType OpenEMR code type: CPT4 or HCPCS
     * @param list<string> $modifiers up to three, in file order
     */
    public function __construct(
        public \DateTimeImmutable $dateOfService,
        public string $codeType,
        public string $code,
        public array $modifiers,
        public int $units,
        public string $accession,
        public string $department,
        public string $clinicalNote,
        public string $comment,
    ) {
    }
}
