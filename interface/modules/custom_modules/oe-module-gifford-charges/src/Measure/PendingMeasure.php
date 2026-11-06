<?php

/**
 * A held charge line waiting for its quality measure answers, as the page shows it.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Measure;

final readonly class PendingMeasure
{
    /**
     * @param list<string> $diagnoses
     * @param list<Measure> $measures
     * @param list<string> $otherProblems what else holds the line, if anything
     */
    public function __construct(
        public int $id,
        public int $lineId,
        public string $fileName,
        public int $lineNumber,
        public string $patientName,
        public string $mrn,
        public string $dateOfService,
        public string $code,
        public string $modifiers,
        public string $accession,
        public string $reader,
        public array $diagnoses,
        public array $measures,
        public array $otherProblems,
    ) {
    }
}
