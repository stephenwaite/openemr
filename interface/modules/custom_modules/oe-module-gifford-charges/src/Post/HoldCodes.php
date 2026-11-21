<?php

/**
 * The procedure codes a person has put on hold (the Hold These Codes setting): a charge
 * with one of them is staged and held, not posted, until the code is taken off the list.
 * For a charge that must not be billed yet, such as a mammogram before the facility's FDA
 * certification number is known, or a code Gifford's charge master maps wrongly.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Post;

final readonly class HoldCodes
{
    /** The start of the problem a code on the list holds a line with. */
    public const PREFIX = 'on hold:';

    /**
     * @param list<string> $codes upper case
     */
    private function __construct(private array $codes)
    {
    }

    /**
     * From the setting's text: codes separated by commas, semicolons or white space, in
     * either case.
     */
    public static function fromSetting(string $setting): self
    {
        $codes = preg_split('/[\s,;]+/', strtoupper($setting), -1, PREG_SPLIT_NO_EMPTY);

        return new self($codes === false ? [] : array_values(array_unique($codes)));
    }

    public function holds(string $code): bool
    {
        return in_array(strtoupper(trim($code)), $this->codes, true);
    }

    /**
     * The problem a line with this code is held with.
     */
    public static function problem(string $codeType, string $code): string
    {
        return sprintf(
            '%s %s %s is in Hold These Codes (Admin > Config > Gifford Charge Import); it posts once the code is taken off that list, or skip it',
            self::PREFIX,
            $codeType,
            $code
        );
    }
}
