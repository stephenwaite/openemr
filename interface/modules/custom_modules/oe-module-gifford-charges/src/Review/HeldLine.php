<?php

/**
 * A held charge line as the review page lists it.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Review;

final readonly class HeldLine
{
    /**
     * @param list<string> $problems
     * @param bool $correction held as a corrected re-send, which a person can release
     * @param bool $repeat held as a possible repeat procedure, which a person can post with 76 or 77
     * @param bool $released released, waiting for the next posting run
     * @param string $addedModifier the modifier it was released with (76 or 77), or ''
     * @param ?ReportView $report the line's report, when the file sent one
     * @param bool $expectedDenial held for Gifford's GZ modifier, which a person can release to post with GZ
     * @param bool $cci held by an NCCI edit, which a person can release to post anyway
     * @param bool $referrer held for want of a referring provider, which a person can release to post without one
     */
    public function __construct(
        public int $id,
        public string $fileName,
        public int $lineNumber,
        public string $receivedAt,
        public string $visitNumber,
        public string $mrn,
        public string $patientName,
        public string $accession,
        public string $dateOfService,
        public string $code,
        public array $problems,
        public bool $correction,
        public bool $repeat,
        public bool $released,
        public string $addedModifier,
        public ?ReportView $report = null,
        public bool $expectedDenial = false,
        public bool $cci = false,
        public bool $referrer = false,
    ) {
    }
}
