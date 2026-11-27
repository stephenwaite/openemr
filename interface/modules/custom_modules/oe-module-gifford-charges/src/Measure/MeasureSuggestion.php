<?php

/**
 * A measure's answer as read from the report, for the coder to see or, when it is a sure "no finding", to stand by itself.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Measure;

final readonly class MeasureSuggestion
{
    public const HIGH = 'high';

    /**
     * @param int $answer one of the measure's option values
     * @param string $confidence high, medium or low
     * @param string $evidence the words of the report the answer rests on
     */
    public function __construct(
        public Measure $measure,
        public int $answer,
        public string $confidence,
        public string $evidence,
        public string $rationale,
    ) {
    }

    /**
     * Whether the answer stands without a coder: only a "no finding" (0) the reading was sure
     * of. Every finding, and every answer it was less than sure of, is the coder's to give.
     * QMM26 has no such answer, so its questions always go to a coder.
     */
    public function standsAlone(): bool
    {
        return $this->answer === 0 && $this->confidence === self::HIGH && $this->measure->option(0) !== null;
    }

    public function label(): string
    {
        return $this->measure->option($this->answer)->label ?? '';
    }
}
