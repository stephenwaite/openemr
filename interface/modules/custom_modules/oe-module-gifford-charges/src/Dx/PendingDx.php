<?php

/**
 * An accession waiting for a coder to confirm its diagnoses, as the page lists it.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Dx;

use Cmsvt\OpenEMR\Modules\GiffordCharges\Review\ReportView;

final readonly class PendingDx
{
    /**
     * @param list<string> $charges the accession's held charges, code-modifiers
     * @param list<array{code: string, text: string}> $gifford the codes Gifford sent for the visit
     * @param list<array{code: string, confidence: string, rationale: string, text: string}> $suggested
     * @param list<string> $reasons why the suggestion did not post by itself
     * @param list<string> $otherProblems what else holds the accession's lines
     * @param bool $hasSuggestion false when none has been loaded for the accession yet
     */
    public function __construct(
        public string $accession,
        public string $fileName,
        public string $dateOfService,
        public string $patientName,
        public string $mrn,
        public array $charges,
        public string $location,
        public string $indication,
        public array $gifford,
        public array $suggested,
        public array $reasons,
        public array $otherProblems,
        public bool $hasSuggestion,
        public ?ReportView $report,
    ) {
    }

    /** The suggestion as the coder's field starts out. */
    public function suggestedCodes(): string
    {
        return implode(' ', array_column($this->suggested, 'code'));
    }

    /** Gifford's first codes, as many as a claim line points to. */
    public function giffordCodes(): string
    {
        return implode(' ', array_slice(array_column($this->gifford, 'code'), 0, DxRepository::MAX_CODES));
    }
}
