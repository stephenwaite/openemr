<?php

/**
 * Step 4's simple passes over the ordfile: the CDMs RRMC's billers want to see
 * (rri224) and each account's first charge (rri249).
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

use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\OrdfileLine;

final class OrdfileReview
{
    /** RRMC charge master numbers listed for review (rri224). */
    private const REVIEW_CDMS = [
        '1131', '1321', '1838', '1911', '1950', '1951', '1956', '1958', '1959', '1985', '4052', '0700', '0502', '0518',
        '1408', '1853', '1400', '1401', '0901', '0531', '0532', '0533', '0534', '0544', '8818', '0749', '0750', '0751',
        '0752', '0535', '0902', '0501', '0505', '0508', '0509', '0507', '0503', '0701', '0702', '0504', '0512', '0519',
        '0500', '0600', '0514', '0515', '0506', '0525', '0516', '6002', '0513', '0517', '0706', '0809', '0802', '0707',
        '0900', '0800', '0510', '0810', '0705', '0522', '0511', '5101', '0903', '0528', '0709', '0808', '0813', '0805',
        '0530', '0804', '0812', '0803', '0807', '4051', '0811', '0818', '7405', '7406', '7601', '7602', '1616', '4622',
        '4623', '4470', '4472', '7003', '4510', '4421', '4626', '4627', '2310', '7001', '7506', '7513', '8284', '8296',
        '8303', '8304',
    ];

    /**
     * @param array<int, OrdfileLine> $lines
     * @return list<string> file224: the ordfile lines with a CDM to review
     */
    public static function cdmsToReview(array $lines): array
    {
        $review = [];
        foreach ($lines as $line) {
            if (in_array($line->proc, self::REVIEW_CDMS, true)) {
                $review[] = $line->toText();
            }
        }
        return $review;
    }

    /**
     * @param array<int, OrdfileLine> $lines in ordfile order
     * @return array<int, OrdfileLine> the first charge (not a cancel) of each account
     */
    public static function firstChargePerAccount(array $lines): array
    {
        $first = [];
        $last = null;
        foreach ($lines as $id => $line) {
            if ($line->ind === '-' || $line->ord8 === $last) {
                continue;
            }
            $first[$id] = $line;
            $last = $line->ord8;
        }
        return $first;
    }
}
