<?php

/**
 * A diagnosis box on the coding page: the ICD10 code and its description.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Coding;

final readonly class CodingDiagnosis
{
    public function __construct(
        public string $code,
        public string $description,
    ) {
    }
}
