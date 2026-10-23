<?php

/**
 * The payer crosswalk file could not be read or has rows that can't be imported.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Payer;

final class PayerCrosswalkException extends \RuntimeException
{
    /**
     * @param list<string> $problems one per bad row or setting, safe to show
     */
    public function __construct(string $message, public readonly array $problems = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
