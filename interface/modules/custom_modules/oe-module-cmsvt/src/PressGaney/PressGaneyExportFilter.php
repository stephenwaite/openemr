<?php

/**
 * Date range and optional filters for the Press Ganey export.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\PressGaney;

use OpenEMR\Services\Utils\DateFormatterUtils;
use Symfony\Component\HttpFoundation\InputBag;

final readonly class PressGaneyExportFilter
{
    public function __construct(
        public string $fromDate,
        public string $toDate,
        public ?int $providerId,
        public ?int $facilityId,
        public ?int $categoryId,
    ) {
    }

    /**
     * @param InputBag<string> $post
     */
    public static function fromPost(InputBag $post): self
    {
        $today = date('Y-m-d');
        return new self(
            self::date($post->getString('form_from_date')) ?? $today,
            self::date($post->getString('form_to_date')) ?? $today,
            self::id($post->getString('form_provider')),
            self::id($post->getString('form_facility')),
            self::id($post->getString('form_encounter_type')),
        );
    }

    private static function date(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        $ymd = DateFormatterUtils::DateToYYYYMMDD($value);
        return is_string($ymd) && $ymd !== '' ? $ymd : null;
    }

    private static function id(string $value): ?int
    {
        return ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }
}
