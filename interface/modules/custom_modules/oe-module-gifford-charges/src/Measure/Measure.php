<?php

/**
 * The quality measures the site reports on its radiologists' reads, as the billing
 * system reports them today (cms rri/load/mea003.cob and rri/coding/mea004.cob).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Measure;

/**
 * 405 and 406 are claims measures: the coder's answer goes on the claim as G-codes.
 * 364 and QMM26 go to the ACR registry (NRDR) as a CSV row with the numerator code.
 * The value is what is stored; the options' values are what the coder picks.
 */
enum Measure: string
{
    case Qmm26 = 'QMM26';
    case M364 = '364';
    case M405 = '405';
    case M406 = '406';

    /** billing.code_text for the G-codes a claims measure adds. */
    private const CODE_TEXT = [
        'G9547' => 'Incidentally found cystic renal or adrenal lesion noted',
        'G9548' => 'Final report: no follow-up imaging recommended',
        'G9549' => 'Follow-up imaging indicated for a documented medical reason',
        'G9550' => 'Final report: follow-up imaging recommended',
        'G9551' => 'Final report: no incidentally found lesion noted',
        'G9552' => 'Incidental thyroid nodule under 1.0 cm noted',
        'G9554' => 'Final report: follow-up imaging recommended',
        'G9555' => 'Follow-up imaging recommended for a documented medical reason',
        'G9556' => 'Final report: follow-up imaging not recommended',
        'G9557' => 'Final report: no incidental thyroid nodule under 1.0 cm noted',
    ];

    public function label(): string
    {
        return match ($this) {
            self::Qmm26 => 'QMM26 screening AAA',
            self::M364 => '364 pulmonary nodule follow-up',
            self::M405 => '405 incidental renal or adrenal lesion',
            self::M406 => '406 incidental thyroid nodule',
        };
    }

    /** The question the coder answers from the report. */
    public function question(): string
    {
        return match ($this) {
            self::Qmm26 => 'What does the report say about the abdominal aorta?',
            self::M364 => 'Is there an incidental pulmonary nodule, and is follow-up addressed?',
            self::M405 => 'Is there an incidental cystic renal or adrenal lesion, and is follow-up addressed?',
            self::M406 => 'Is there an incidental thyroid nodule under 1.0 cm, and is follow-up addressed?',
        };
    }

    /** Whether the answer goes on the claim (G-codes) rather than to the ACR registry. */
    public function onClaim(): bool
    {
        return match ($this) {
            self::M405, self::M406 => true,
            self::Qmm26, self::M364 => false,
        };
    }

    /**
     * The answers, in the order shown. The values match the coder's codes in the billing
     * system (CD-QP1), so an answer means the same thing in both.
     *
     * @return list<MeasureOption>
     */
    public function options(): array
    {
        return match ($this) {
            self::Qmm26 => [
                new MeasureOption(1, 'AAA under 5.5 cm: follow-up recommended per guideline, source cited', [], 'PM002'),
                new MeasureOption(2, 'AAA 5.5 cm or more: follow-up recommended and direct communication documented', [], 'PM102'),
                new MeasureOption(3, 'Negative for AAA: states no future screening needed', [], 'PM202'),
                new MeasureOption(4, 'Performance not met: recommendation, source, communication or statement missing', [], 'PNM02'),
                new MeasureOption(5, 'Exception: under active surveillance by a vascular specialist, AAA unchanged', [], 'PE002'),
            ],
            // The denominator is reports with an incidental pulmonary nodule (G9754), so no
            // nodule is not eligible and sends nothing. Granulomas, hamartomas and other
            // characteristically benign findings are not incidental nodules here.
            self::M364 => [
                new MeasureOption(0, 'No incidental pulmonary nodule: not eligible', [], null),
                new MeasureOption(1, 'Nodule: follow-up (or none) recommended per guideline, with interval, modality and source (performance met)', [], 'G9345'),
                new MeasureOption(2, 'Nodule: medical reason for no recommendation, e.g. unexplained fever, immunocompromised (exception)', [], 'G9755'),
                new MeasureOption(3, 'Nodule: recommendation not per guideline or missing, reason not given (performance not met)', [], 'G9347'),
                new MeasureOption(4, 'Nodule, but cancer history, heavy smoker or lung cancer screening: excluded', [], 'M1018'),
            ],
            self::M405 => [
                new MeasureOption(0, 'No incidental lesion', ['G9551'], null),
                new MeasureOption(1, 'Lesion, no follow-up imaging recommended', ['G9548', 'G9547'], null),
                new MeasureOption(2, 'Lesion, follow-up imaging indicated for a medical reason', ['G9549', 'G9547'], null),
                new MeasureOption(3, 'Lesion, follow-up imaging recommended or not addressed (performance not met)', ['G9550', 'G9547'], null),
            ],
            self::M406 => [
                new MeasureOption(0, 'No incidental thyroid nodule under 1.0 cm', ['G9557'], null),
                new MeasureOption(1, 'Nodule, follow-up imaging recommended (performance not met)', ['G9554', 'G9552'], null),
                new MeasureOption(2, 'Nodule, follow-up imaging recommended for a medical reason', ['G9555', 'G9552'], null),
                new MeasureOption(3, 'Nodule, follow-up imaging not recommended', ['G9556', 'G9552'], null),
            ],
        };
    }

    public function option(int $value): ?MeasureOption
    {
        foreach ($this->options() as $option) {
            if ($option->value === $value) {
                return $option;
            }
        }

        return null;
    }

    public static function codeText(string $code): string
    {
        return self::CODE_TEXT[$code] ?? $code;
    }
}
