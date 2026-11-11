<?php

/**
 * What the coder decided for a charge line's quality measures.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Measure;

final readonly class MeasureAnswers
{
    /**
     * @param array<array-key, int> $answers measure value => option value; empty when released
     * @param int $userId users.id of the coder, 0 when unknown
     */
    public function __construct(
        public string $status,
        public array $answers,
        public int $userId,
    ) {
    }

    public function pending(): bool
    {
        return $this->status === MeasureRepository::STATUS_PENDING;
    }

    /**
     * The G-codes to bill beside the charge, in order.
     *
     * @param list<Measure> $measures
     * @return list<string>
     */
    public function claimCodes(array $measures): array
    {
        $codes = [];
        foreach ($measures as $measure) {
            $value = $this->answers[$measure->value] ?? null;
            $option = $value === null ? null : $measure->option($value);
            if ($option === null) {
                continue;
            }
            foreach ($option->claimCodes as $code) {
                $codes[] = $code;
            }
        }

        return $codes;
    }
}
