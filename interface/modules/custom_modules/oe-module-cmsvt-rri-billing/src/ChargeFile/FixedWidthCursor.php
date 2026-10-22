<?php

/**
 * Reads consecutive fixed-width fields from one charge file record.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile;

final class FixedWidthCursor
{
    private int $offset = 0;

    public function __construct(private readonly string $record)
    {
    }

    /** The next field, uppercased and trimmed (RRMC's fields are uppercase). */
    public function text(int $length): string
    {
        return trim($this->upper($length));
    }

    /** The next field uppercased but untrimmed, for names whose comma position matters. */
    public function upper(int $length): string
    {
        return strtoupper($this->raw($length));
    }

    /** The next field as sent, trimmed (the email address keeps its case). */
    public function asSent(int $length): string
    {
        return trim($this->raw($length));
    }

    public function skip(int $length): void
    {
        $this->offset += $length;
    }

    private function raw(int $length): string
    {
        $field = substr($this->record, $this->offset, $length);
        $this->offset += $length;
        return $field;
    }
}
