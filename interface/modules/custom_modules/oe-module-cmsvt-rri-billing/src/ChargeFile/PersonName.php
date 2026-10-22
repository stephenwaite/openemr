<?php

/**
 * A name as RRMC sends it: "LAST, FIRST MIDDLE" in a fixed-width field.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile;

final readonly class PersonName
{
    /**
     * @param string $full the whole field, trimmed
     * @param string $last before the comma; the whole field when there is no comma
     * @param string $first after the comma, up to the first space ('' without a comma)
     * @param string $middle the rest ('' when none)
     */
    public function __construct(
        public string $full,
        public string $last,
        public string $first,
        public string $middle,
    ) {
    }

    public static function parse(string $field): self
    {
        $full = trim($field);
        $comma = strpos($field, ',');
        if ($comma === false) {
            return new self($full, $full, '', '');
        }
        $rest = ltrim(substr($field, $comma + 1));
        $space = strpos($rest, ' ');
        return new self(
            $full,
            trim(substr($field, 0, $comma)),
            $space === false ? trim($rest) : substr($rest, 0, $space),
            $space === false ? '' : trim(substr($rest, $space)),
        );
    }

    public function isEmpty(): bool
    {
        return $this->full === '';
    }

    /** Both first and last name are present (the 7.0.2 hasRequiredNameFields). */
    public function hasFirstAndLast(): bool
    {
        return $this->first !== '' && $this->last !== '';
    }
}
