<?php

/**
 * Looks up and sets which insurance company a Gifford payer mnemonic is billed to.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Payer;

use OpenEMR\Common\Database\QueryUtils;

final class PayerMap
{
    /**
     * The mapped insurance company, or null when the mnemonic isn't mapped or its
     * company was deleted.
     */
    public function insuranceCompanyId(string $mnemonic): ?int
    {
        $id = QueryUtils::fetchSingleValue(
            'SELECT ic.`id` FROM `gifford_payer_map` AS m JOIN `insurance_companies` AS ic ON ic.`id` = m.`insurance_company_id`'
            . ' WHERE m.`mnemonic` = ?',
            'id',
            [strtoupper($mnemonic)]
        );

        return is_numeric($id) ? (int) $id : null;
    }

    public function set(string $mnemonic, int $insuranceCompanyId, \DateTimeImmutable $at): void
    {
        QueryUtils::sqlStatementThrowException(
            'INSERT INTO `gifford_payer_map` (`mnemonic`, `insurance_company_id`, `updated_at`) VALUES (?, ?, ?)'
            . ' ON DUPLICATE KEY UPDATE `insurance_company_id` = VALUES(`insurance_company_id`), `updated_at` = VALUES(`updated_at`)',
            [strtoupper($mnemonic), $insuranceCompanyId, $at->format('Y-m-d H:i:s')]
        );
    }
}
