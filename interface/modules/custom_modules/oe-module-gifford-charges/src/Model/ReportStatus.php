<?php

/**
 * The status Gifford sends with a radiology report.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Model;

enum ReportStatus: string
{
    /** Signed by the radiologist (Gifford's ISigned and Signed). */
    case Signed = 'signed';
    /** Signed, then added to or corrected: Gifford uses Addendum for both. */
    case Addendum = 'addendum';
    case Draft = 'draft';
    case Canceled = 'canceled';

    /**
     * The status as it comes in the file, whatever its case; null when it is not one Gifford named.
     */
    public static function fromFile(string $value): ?self
    {
        return match (strtolower($value)) {
            'isigned', 'signed' => self::Signed,
            'addendum' => self::Addendum,
            'draft' => self::Draft,
            'cancelled', 'canceled' => self::Canceled,
            default => null,
        };
    }

    /** A draft or canceled report is not one to bill from. */
    public function isFinal(): bool
    {
        return $this === self::Signed || $this === self::Addendum;
    }
}
