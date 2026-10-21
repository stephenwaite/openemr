<?php

/**
 * The one price a fee file gives a code and modifier.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Fees;

final readonly class CodePrice
{
    /**
     * @param string $codeType a code_types key: CPT4 or HCPCS
     * @param string $title the chosen fee's most common title, else its first in the file
     * @param int $feeCents the chosen fee
     * @param int $recordCount how many priced records had this code and modifier
     * @param list<int> $otherFeesCents other fees those records gave, highest first; empty when they agreed
     */
    public function __construct(
        public string $codeType,
        public string $code,
        public string $modifier,
        public string $title,
        public int $feeCents,
        public int $recordCount,
        public array $otherFeesCents,
    ) {
    }

    public function fee(): string
    {
        return self::dollars($this->feeCents);
    }

    public static function dollars(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    public function label(): string
    {
        return $this->codeType . ' ' . $this->code . ($this->modifier === '' ? '' : ':' . $this->modifier);
    }
}
