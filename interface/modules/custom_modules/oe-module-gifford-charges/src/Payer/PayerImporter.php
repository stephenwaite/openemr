<?php

/**
 * Creates the site's insurance companies for Gifford's payer mnemonics and maps each
 * mnemonic to its company in gifford_payer_map.
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
use OpenEMR\Services\InsuranceCompanyService;
use Psr\Clock\ClockInterface;

/**
 * plan() checks the rows against the site and the map; apply() makes the changes in
 * one transaction. A mapped company is never changed: once created, billers own it
 * (a paper payer gets its address and payer ID when its first claim comes up), so
 * importing the crosswalk again only adds mnemonics that are new.
 */
final readonly class PayerImporter
{
    public function __construct(
        private InsuranceCompanyService $insuranceCompanies,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param list<PayerMapping> $mappings
     * @return list<PayerChange> one per mapping, in the same order
     * @throws PayerCrosswalkException when a row names an X12 partner or insurance type the site doesn't have
     */
    public function plan(array $mappings): array
    {
        $partners = self::ids('SELECT `id` FROM `x12_partners`');
        $types = self::ids('SELECT `id` FROM `insurance_type_codes`');
        $problems = [];
        foreach ($mappings as $m) {
            if (!isset($types[$m->insTypeCode])) {
                $problems[] = sprintf('%s: the site has no insurance type %d', $m->mnemonic, $m->insTypeCode);
            }
            if ($m->electronic !== null && !isset($partners[$m->electronic->x12PartnerId])) {
                $problems[] = sprintf('%s: the site has no X12 partner %d', $m->mnemonic, $m->electronic->x12PartnerId);
            }
        }
        if ($problems !== []) {
            throw new PayerCrosswalkException('The site is missing X12 partners or insurance types the crosswalk uses', $problems);
        }

        $changes = [];
        foreach ($mappings as $m) {
            $row = QueryUtils::querySingleRow(
                'SELECT m.`insurance_company_id`, ic.`id` AS `company_exists` FROM `gifford_payer_map` AS m'
                . ' LEFT JOIN `insurance_companies` AS ic ON ic.`id` = m.`insurance_company_id`'
                . ' WHERE m.`mnemonic` = ?',
                [$m->mnemonic]
            );
            if (!is_array($row) || !is_numeric($row['insurance_company_id'] ?? null)) {
                $changes[] = new PayerChange(PayerChangeKind::Create, $m, null);
                continue;
            }
            $kind = ($row['company_exists'] ?? null) === null ? PayerChangeKind::Recreate : PayerChangeKind::AlreadyMapped;
            $changes[] = new PayerChange($kind, $m, (int) $row['insurance_company_id']);
        }

        return $changes;
    }

    /**
     * @param list<PayerChange> $changes from plan()
     * @return array<string, int> mnemonic => insurance company id, for the companies created
     */
    public function apply(array $changes): array
    {
        $at = $this->clock->now()->format('Y-m-d H:i:s');

        return QueryUtils::inTransaction(function () use ($changes, $at): array {
            $created = [];
            foreach ($changes as $change) {
                if ($change->kind === PayerChangeKind::AlreadyMapped) {
                    continue;
                }
                $id = $this->createCompany($change->mapping);
                QueryUtils::sqlStatementThrowException(
                    'INSERT INTO `gifford_payer_map` (`mnemonic`, `insurance_company_id`, `updated_at`) VALUES (?, ?, ?)'
                    . ' ON DUPLICATE KEY UPDATE `insurance_company_id` = VALUES(`insurance_company_id`), `updated_at` = VALUES(`updated_at`)',
                    [$change->mapping->mnemonic, $id, $at]
                );
                $created[$change->mapping->mnemonic] = $id;
            }

            return $created;
        });
    }

    private function createCompany(PayerMapping $m): int
    {
        $e = $m->electronic;
        $zip = $e?->address->postalCode ?? '';
        $id = $this->insuranceCompanies->insert([
            'name' => $m->name,
            'attn' => '',
            'cms_id' => $e->payerId ?? '',
            'ins_type_code' => $m->insTypeCode,
            'x12_receiver_id' => null,
            // A paper payer has no partner; the insurance company page saves "none" as ''.
            'x12_default_partner_id' => $e->x12PartnerId ?? '',
            'alt_cms_id' => null,
            'line1' => $e?->address->street ?? '',
            'line2' => '',
            'city' => $e?->address->city ?? '',
            'state' => $e?->address->state ?? '',
            'zip' => substr($zip, 0, 5),
            'plus_four' => strlen($zip) === 9 ? substr($zip, 5) : null,
            'country' => $e === null ? '' : 'USA',
        ]);
        if (!is_numeric($id)) {
            throw new \RuntimeException('Inserting an insurance company returned no id');
        }

        return (int) $id;
    }

    /**
     * @return array<int, true>
     */
    private static function ids(string $sql): array
    {
        $ids = [];
        foreach (QueryUtils::fetchRecords($sql) as $row) {
            if (is_numeric($row['id'] ?? null)) {
                $ids[(int) $row['id']] = true;
            }
        }

        return $ids;
    }
}
