<?php

/**
 * A criterion outcome: the label shown, and its role in the rates.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Mips;

final readonly class MipsStatus
{
    public const NOT_REPORTED = 'Not Reported';
    public const CONFLICTING = 'Conflicting codes entered';

    public function __construct(
        public string $label,
        public MipsRole $role,
        public string $code = '',
    ) {
    }

    /** "Met (M1388)" for a code's status. */
    public static function forCode(string $code, string $name, MipsRole $role): self
    {
        return new self($name . ' (' . $code . ')', $role, $code);
    }

    public static function notReported(): self
    {
        return new self(self::NOT_REPORTED, MipsRole::Missing);
    }

    public static function conflicting(): self
    {
        return new self(self::CONFLICTING, MipsRole::Missing);
    }
}
