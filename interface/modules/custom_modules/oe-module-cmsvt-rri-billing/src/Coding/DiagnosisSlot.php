<?php

/**
 * The coding page's two diagnosis boxes, by the record_type that links their
 * ICD10 line to the ordfile line in cmsvt_ordfile_record_billing.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Coding;

/** Backed by cmsvt_ordfile_record_billing.record_type. */
enum DiagnosisSlot: string
{
    case First = 'primary';
    case Second = 'secondary';
}
