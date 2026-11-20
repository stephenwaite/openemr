<?php

/**
 * One data row of a Gifford charge file, as read: values keyed by column name.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Reader;

final readonly class RawRow
{
    /**
     * @param int $line the row's line number in the file, the header being line 1
     * @param array<string, string> $values trimmed values keyed by lower-case column name
     */
    public function __construct(
        public int $line,
        public array $values,
    ) {
    }

    /**
     * The trimmed value of a column, or '' when the column is absent or empty.
     */
    public function get(string $column): string
    {
        return $this->values[$column] ?? '';
    }

    public function has(string $column): bool
    {
        return array_key_exists($column, $this->values);
    }
}
