<?php

/**
 * The Gifford visit a charge belongs to.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Model;

final readonly class EncounterRecord
{
    /**
     * @param list<string> $diagnoses ICD-10-CM codes with the dot, in file order
     * @param ?string $placeOfService two-digit POS code, or null when the file has none
     */
    public function __construct(
        public string $visitNumber,
        public \DateTimeImmutable $admitDate,
        public ?\DateTimeImmutable $dischargeDate,
        public bool $workersComp,
        public ?\DateTimeImmutable $accidentDate,
        public string $authorizationNumber,
        public array $diagnoses,
        public string $renderingNpi,
        public string $renderingName,
        public string $referringNpi,
        public string $referringName,
        public string $location,
        public ?string $placeOfService,
    ) {
    }
}
