<?php

/**
 * The radiology report sent with a Gifford charge row.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Model;

final readonly class ReportRecord
{
    /**
     * @param string $text the report as written, line breaks as "\n"
     */
    public function __construct(
        public \DateTimeImmutable $signedAt,
        public ReportStatus $status,
        public string $text,
    ) {
    }
}
