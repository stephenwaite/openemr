<?php

/**
 * Which quality measures a posted charge needs an answer for.
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
 * The code sets and gates are the billing system's (rri/load/mea003.cob): QMM26 at 50 and
 * over and 364 at 35 and over for any payer; 405 and 406 for Medicare primaries only. A
 * code in two or three sets gets each measure whose gate the patient passes, and none
 * when none passes.
 */
final readonly class MeasureRules
{
    /** Medicare Part B's insurance_type_codes.id: the primary payer the claims measures apply to. */
    public const MEDICARE_TYPE = 2;

    private const AGE_QMM26 = 50;
    private const AGE_364 = 35;

    /** @var array<array-key, list<Measure>> numeric codes are int keys, as PHP stores them */
    private const SETS = [
        '76706' => [Measure::Qmm26],
        '75571' => [Measure::M364], '75572' => [Measure::M364], '75573' => [Measure::M364], '75574' => [Measure::M364],
        '72128' => [Measure::M364], '72129' => [Measure::M364], '72130' => [Measure::M364],
        '74174' => [Measure::M364], '74175' => [Measure::M364],
        '72131' => [Measure::M405], '72191' => [Measure::M405], '72192' => [Measure::M405], '72193' => [Measure::M405],
        '72194' => [Measure::M405], '72195' => [Measure::M405], '72196' => [Measure::M405], '72197' => [Measure::M405],
        '72198' => [Measure::M405], '74181' => [Measure::M405], '74182' => [Measure::M405], '74183' => [Measure::M405],
        '70471' => [Measure::M406], '70486' => [Measure::M406], '70487' => [Measure::M406], '70488' => [Measure::M406],
        '70540' => [Measure::M406], '70542' => [Measure::M406], '70543' => [Measure::M406],
        '72141' => [Measure::M406], '72142' => [Measure::M406], '72156' => [Measure::M406],
        '71550' => [Measure::M406], '71551' => [Measure::M406], '71552' => [Measure::M406],
        '71275' => [Measure::M364, Measure::M405],
        '74150' => [Measure::M364, Measure::M405], '74160' => [Measure::M364, Measure::M405], '74170' => [Measure::M364, Measure::M405],
        '74176' => [Measure::M364, Measure::M405], '74177' => [Measure::M364, Measure::M405], '74178' => [Measure::M364, Measure::M405],
        '70490' => [Measure::M364, Measure::M406], '70491' => [Measure::M364, Measure::M406], '70492' => [Measure::M364, Measure::M406],
        '70498' => [Measure::M364, Measure::M406],
        '72125' => [Measure::M364, Measure::M406], '72126' => [Measure::M364, Measure::M406], '72127' => [Measure::M364, Measure::M406],
        '71271' => [Measure::M405, Measure::M406], '71555' => [Measure::M405, Measure::M406],
        '71250' => [Measure::M364, Measure::M405, Measure::M406],
        '71260' => [Measure::M364, Measure::M405, Measure::M406],
        '71270' => [Measure::M364, Measure::M405, Measure::M406],
    ];

    /**
     * @param int $age the patient's age on the date of service
     * @param bool $medicare the primary payer is Medicare
     * @return list<Measure>
     */
    public function measuresFor(string $cpt, int $age, bool $medicare): array
    {
        $measures = [];
        foreach (self::SETS[$cpt] ?? [] as $measure) {
            $applies = match ($measure) {
                Measure::Qmm26 => $age >= self::AGE_QMM26,
                Measure::M364 => $age >= self::AGE_364,
                Measure::M405, Measure::M406 => $medicare,
            };
            if ($applies) {
                $measures[] = $measure;
            }
        }

        return $measures;
    }

    public static function ageOn(\DateTimeImmutable $dateOfBirth, \DateTimeImmutable $date): int
    {
        return $dateOfBirth > $date ? 0 : $dateOfBirth->diff($date)->y;
    }
}
