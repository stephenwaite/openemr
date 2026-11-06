<?php

/**
 * Prices a Gifford charge from the site's standard fee schedule, adding the
 * professional component modifier the site bills.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Post;

use Cmsvt\OpenEMR\Modules\GiffordCharges\Model\ChargeRecord;
use OpenEMR\Common\Database\QueryUtils;

/**
 * Gifford bills the technical component and sends no 26; the site's radiologists bill
 * the professional one. So a code priced with 26 is billed as code-26 (even when a bare
 * price exists too), a code priced only bare (most type 1 procedures) is billed bare,
 * and anything else is held. Modifiers Gifford does send follow the 26 (26:RT); a TC
 * from Gifford is held, since that component is Gifford's.
 */
final class PriceBook
{
    public const PRICE_LEVEL = 'standard';
    public const PROFESSIONAL = '26';

    /** @var array<string, ?int> code_types key => ct_id */
    private array $codeTypeIds = [];

    /**
     * @throws PostProblem when the charge can't be priced
     */
    public function price(ChargeRecord $charge): PricedCharge
    {
        $modifiers = array_map(strtoupper(...), $charge->modifiers);
        if (in_array('TC', $modifiers, true)) {
            throw new PostProblem('modifier TC: the technical component is Gifford\'s to bill');
        }
        $modifiers = array_values(array_filter($modifiers, static fn (string $m): bool => $m !== self::PROFESSIONAL));

        $professional = $this->lookup($charge->codeType, $charge->code, self::PROFESSIONAL);
        $bare = $professional === null ? $this->lookup($charge->codeType, $charge->code, '') : null;
        $found = $professional ?? $bare;
        if ($found === null) {
            throw new PostProblem(sprintf('no price for %s %s', $charge->codeType, $charge->code));
        }
        if ($professional !== null) {
            array_unshift($modifiers, self::PROFESSIONAL);
        }
        $modifier = implode(':', $modifiers);
        if (strlen($modifier) > 12) {
            throw new PostProblem('too many modifiers: ' . $modifier);
        }

        return new PricedCharge($modifier, $found['cents'], $found['text']);
    }

    /**
     * @return ?array{cents: int, text: string}
     */
    private function lookup(string $codeType, string $code, string $modifier): ?array
    {
        $typeId = $this->codeTypeId($codeType);
        if ($typeId === null) {
            return null;
        }
        $row = QueryUtils::querySingleRow(
            'SELECT c.`code_text`, p.`pr_price` FROM `codes` AS c'
            . ' JOIN `prices` AS p ON p.`pr_id` = c.`id` AND p.`pr_selector` = \'\' AND p.`pr_level` = ?'
            . ' WHERE c.`code_type` = ? AND c.`code` = ? AND c.`modifier` = ? AND c.`active` = 1'
            . ' ORDER BY c.`id` LIMIT 1',
            [self::PRICE_LEVEL, $typeId, $code, $modifier]
        );
        if (!is_array($row) || !is_numeric($row['pr_price'] ?? null)) {
            return null;
        }

        return [
            'cents' => (int) round((float) $row['pr_price'] * 100),
            'text' => is_string($row['code_text'] ?? null) ? $row['code_text'] : '',
        ];
    }

    private function codeTypeId(string $key): ?int
    {
        if (!array_key_exists($key, $this->codeTypeIds)) {
            $id = QueryUtils::fetchSingleValue('SELECT `ct_id` FROM `code_types` WHERE `ct_key` = ?', 'ct_id', [$key]);
            $this->codeTypeIds[$key] = is_numeric($id) ? (int) $id : null;
        }

        return $this->codeTypeIds[$key];
    }
}
