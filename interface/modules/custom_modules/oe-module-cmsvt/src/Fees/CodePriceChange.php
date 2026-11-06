<?php

/**
 * What importing one fee file price would do to the site's codes and prices.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Fees;

final readonly class CodePriceChange
{
    /**
     * @param ?int $codeId the existing codes row, or null when the code is added
     * @param ?int $oldFeeCents the existing standard price, or null when there is none
     */
    public function __construct(
        public CodePriceChangeKind $kind,
        public CodePrice $price,
        public ?int $codeId,
        public ?int $oldFeeCents,
    ) {
    }

    public function describe(): string
    {
        return match ($this->kind) {
            CodePriceChangeKind::AddCode => sprintf('add %s "%s" at %s', $this->price->label(), $this->price->title, $this->price->fee()),
            CodePriceChangeKind::AddPrice => sprintf('price %s at %s (it had no standard price)', $this->price->label(), $this->price->fee()),
            CodePriceChangeKind::ChangePrice => sprintf(
                'change %s from %s to %s',
                $this->price->label(),
                CodePrice::dollars($this->oldFeeCents ?? 0),
                $this->price->fee()
            ),
            CodePriceChangeKind::Unchanged => sprintf('keep %s at %s', $this->price->label(), $this->price->fee()),
        };
    }
}
