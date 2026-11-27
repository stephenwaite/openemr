<?php

/**
 * One row of the ACR registry (NRDR) CSV, as the billing system's export writes it.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Measure;

final readonly class AcrRow
{
    public const VERSION = '1.1';

    public function __construct(
        public \DateTimeImmutable $dateOfService,
        public string $npi,
        public string $patientId,
        public int $age,
        public string $sex,
        public Measure $measure,
        public string $cpt,
        public string $diagnosis,
        public string $numeratorCode,
        public string $examId,
    ) {
    }

    /**
     * The line as cms php/rri/mea004acr.php writes it: version, practice id, exam date,
     * TIN, NPI, patient id, age, sex, measure, CPT, diagnosis without the dot, numerator
     * code, exam id. Only digits in the TIN and NPI.
     */
    public function line(string $practiceId, string $tin): string
    {
        $sex = match (strtoupper(substr($this->sex, 0, 1))) {
            'M' => 'M',
            'F' => 'F',
            default => 'U',
        };

        return implode(',', [
            self::VERSION,
            $practiceId,
            $this->dateOfService->format('m/d/Y'),
            (string) preg_replace('/\D/', '', $tin),
            (string) preg_replace('/\D/', '', $this->npi),
            $this->patientId,
            (string) $this->age,
            $sex,
            $this->measure->value,
            $this->cpt,
            str_replace('.', '', $this->diagnosis),
            $this->numeratorCode,
            $this->examId,
        ]);
    }
}
