<?php

/**
 * A charge line's radiology report as the review pages show it.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Review;

use Cmsvt\OpenEMR\Modules\GiffordCharges\Parse\GiffordValues;

final readonly class ReportView
{
    /**
     * @param string $status as Gifford sent it (Signed, Addendum ...)
     * @param string $signed as Gifford sent it (mm/dd/yyyy hh:mm)
     * @param string $text the report, line breaks as "\n"
     */
    public function __construct(
        public string $status,
        public string $signed,
        public string $text,
    ) {
    }

    /**
     * The report in a line's saved row, or null when the row has none. Text that does
     * not decode is shown as it was sent rather than hidden: a person can still read it.
     *
     * @param array<string, string> $raw
     */
    public static function fromRaw(array $raw): ?self
    {
        $sent = $raw['report_text'] ?? '';
        if ($sent === '') {
            return null;
        }
        return new self(
            $raw['report_status'] ?? '',
            $raw['report_signed'] ?? '',
            GiffordValues::jsonText($sent) ?? $sent,
        );
    }
}
