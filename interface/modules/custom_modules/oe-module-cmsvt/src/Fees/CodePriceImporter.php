<?php

/**
 * Brings a site's codes and standard prices in line with a fee file.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Fees;

use OpenEMR\Common\Database\QueryUtils;

/**
 * plan() compares each price with the site's codes; apply() makes the changes in one
 * transaction. A missing code is added (the fee file's title, 1 unit, active) with its
 * standard price; an existing code keeps its description, active flag and other price
 * levels, and only its standard price (and codes.fee, which mirrors it) changes.
 */
final class CodePriceImporter
{
    public const PRICE_LEVEL = 'standard';

    /** @var array<string, int> code_types key => ct_id */
    private array $codeTypeIds = [];

    /**
     * @param list<CodePrice> $prices
     * @return list<CodePriceChange> one per price, in the same order
     * @throws \RuntimeException when the site has no such code type or price level
     */
    public function plan(array $prices): array
    {
        $levelExists = QueryUtils::fetchSingleValue(
            "SELECT COUNT(*) AS n FROM `list_options` WHERE `list_id` = 'pricelevel' AND `option_id` = ?",
            'n',
            [self::PRICE_LEVEL]
        );
        if (!is_numeric($levelExists) || (int) $levelExists === 0) {
            throw new \RuntimeException("The site has no '" . self::PRICE_LEVEL . "' price level");
        }

        $changes = [];
        foreach ($prices as $price) {
            $existing = QueryUtils::querySingleRow(
                'SELECT c.`id`, p.`pr_price` FROM `codes` AS c'
                . ' LEFT JOIN `prices` AS p ON p.`pr_id` = c.`id` AND p.`pr_selector` = \'\' AND p.`pr_level` = ?'
                . ' WHERE c.`code_type` = ? AND c.`code` = ? AND c.`modifier` = ?'
                . ' ORDER BY c.`active` DESC, c.`id` LIMIT 1',
                [self::PRICE_LEVEL, $this->codeTypeId($price->codeType), $price->code, $price->modifier]
            );
            $codeId = is_array($existing) && is_numeric($existing['id'] ?? null) ? (int) $existing['id'] : null;
            if ($codeId === null) {
                $changes[] = new CodePriceChange(CodePriceChangeKind::AddCode, $price, null, null);
                continue;
            }
            $old = $existing['pr_price'] ?? null;
            if (!is_numeric($old)) {
                $changes[] = new CodePriceChange(CodePriceChangeKind::AddPrice, $price, $codeId, null);
                continue;
            }
            $oldCents = (int) round((float) $old * 100);
            $kind = $oldCents === $price->feeCents ? CodePriceChangeKind::Unchanged : CodePriceChangeKind::ChangePrice;
            $changes[] = new CodePriceChange($kind, $price, $codeId, $oldCents);
        }

        return $changes;
    }

    /**
     * @param list<CodePriceChange> $changes from plan()
     */
    public function apply(array $changes): void
    {
        QueryUtils::inTransaction(function () use ($changes): void {
            foreach ($changes as $change) {
                $price = $change->price;
                match ($change->kind) {
                    CodePriceChangeKind::AddCode => $this->insertPrice($this->insertCode($price), $price),
                    CodePriceChangeKind::AddPrice => $this->insertPrice(self::existingCodeId($change), $price),
                    CodePriceChangeKind::ChangePrice => $this->updatePrice(self::existingCodeId($change), $price),
                    CodePriceChangeKind::Unchanged => null,
                };
            }
        });
    }

    private static function existingCodeId(CodePriceChange $change): int
    {
        if ($change->codeId === null) {
            throw new \LogicException('A change to an existing code has no code id: ' . $change->price->label());
        }

        return $change->codeId;
    }

    private function insertCode(CodePrice $price): int
    {
        return QueryUtils::sqlInsert(
            'INSERT INTO `codes` (`code_text`, `code_text_short`, `code`, `code_type`, `modifier`, `units`, `fee`,'
            . ' `active`, `reportable`, `financial_reporting`) VALUES (?, ?, ?, ?, ?, 1, ?, 1, 0, 0)',
            [$price->title, $price->title, $price->code, $this->codeTypeId($price->codeType), $price->modifier, $price->fee()]
        );
    }

    private function insertPrice(int $codeId, CodePrice $price): void
    {
        QueryUtils::sqlStatementThrowException(
            "INSERT INTO `prices` (`pr_id`, `pr_selector`, `pr_level`, `pr_price`) VALUES (?, '', ?, ?)",
            [$codeId, self::PRICE_LEVEL, $price->fee()]
        );
        QueryUtils::sqlStatementThrowException('UPDATE `codes` SET `fee` = ? WHERE `id` = ?', [$price->fee(), $codeId]);
    }

    private function updatePrice(int $codeId, CodePrice $price): void
    {
        QueryUtils::sqlStatementThrowException(
            "UPDATE `prices` SET `pr_price` = ? WHERE `pr_id` = ? AND `pr_selector` = '' AND `pr_level` = ?",
            [$price->fee(), $codeId, self::PRICE_LEVEL]
        );
        QueryUtils::sqlStatementThrowException('UPDATE `codes` SET `fee` = ? WHERE `id` = ?', [$price->fee(), $codeId]);
    }

    private function codeTypeId(string $key): int
    {
        if (!isset($this->codeTypeIds[$key])) {
            $id = QueryUtils::fetchSingleValue('SELECT `ct_id` FROM `code_types` WHERE `ct_key` = ?', 'ct_id', [$key]);
            if (!is_numeric($id)) {
                throw new \RuntimeException("The site has no '" . $key . "' code type");
            }
            $this->codeTypeIds[$key] = (int) $id;
        }

        return $this->codeTypeIds[$key];
    }
}
