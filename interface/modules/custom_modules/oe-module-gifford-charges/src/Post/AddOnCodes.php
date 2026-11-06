<?php

/**
 * Add-on codes and the primary procedures they are billed with.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Post;

/**
 * An add-on code is only paid beside its primary procedure on the same day, so a line
 * with one is held until a parent is billed for the patient that day. Gifford's first
 * live file sent the 3-D add-on 77063 without the screening mammogram 77067.
 */
final readonly class AddOnCodes
{
    /** Add-on code => the primary codes any one of which it needs. */
    public const PARENTS = [
        // Screening digital breast tomosynthesis, with the screening mammogram.
        '77063' => ['77067'],
        // Diagnostic digital breast tomosynthesis (Medicare), with a diagnostic mammogram.
        'G0279' => ['77065', '77066'],
        // Obstetric ultrasound, each additional gestation, with the first.
        '76802' => ['76801'],
        '76810' => ['76805'],
        '76812' => ['76811'],
        '76814' => ['76813'],
    ];

    /**
     * @param array<array-key, list<string>> $parents numeric codes are int keys, as PHP stores them
     */
    public function __construct(private array $parents = self::PARENTS)
    {
    }

    /**
     * @return list<string> the codes $code needs one of, or none when it isn't an add-on
     */
    public function parentsOf(string $code): array
    {
        return $this->parents[strtoupper($code)] ?? [];
    }
}
