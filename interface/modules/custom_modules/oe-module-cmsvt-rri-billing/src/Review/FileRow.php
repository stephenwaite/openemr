<?php

/**
 * A file245 line: date (8), account (8), name (24), problem (22) and detail
 * (15), separated by a space (7.0.2's Rri245ErrorRecord).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Review;

final readonly class FileRow
{
    public function __construct(
        public string $err0,
        public string $err1,
        public string $err2,
        public string $err3,
        public string $err4,
        private string $separator,
    ) {
    }

    /** A free-text line cut into the five columns. */
    public static function fromText(string $text, string $separator): self
    {
        return new self(substr($text, 0, 8), substr($text, 8, 8), substr($text, 16, 24), substr($text, 40, 22), substr($text, 62, 15), $separator);
    }

    public function with(string $problem, string $detail): self
    {
        return new self($this->err0, $this->err1, $this->err2, $problem, $detail, $this->separator);
    }

    public function withDate(string $date): self
    {
        return new self($date, $this->err1, $this->err2, $this->err3, $this->err4, $this->separator);
    }

    public function toText(): string
    {
        return implode($this->separator, [
            str_pad($this->err0, 8), str_pad($this->err1, 8), str_pad($this->err2, 24), str_pad($this->err3, 22), str_pad($this->err4, 15),
        ]);
    }
}
