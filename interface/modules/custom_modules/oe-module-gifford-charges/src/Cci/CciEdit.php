<?php

/**
 * One NCCI procedure-to-procedure edit: a pair of codes and what billing both takes.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Cci;

final readonly class CciEdit
{
    /**
     * @param string $column1 the comprehensive code, which is paid
     * @param string $column2 the component code, which carries the modifier when one is allowed
     */
    public function __construct(
        public string $column1,
        public string $column2,
        public CciIndicator $indicator,
    ) {
    }
}
