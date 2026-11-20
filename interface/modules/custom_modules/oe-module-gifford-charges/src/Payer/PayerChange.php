<?php

/**
 * One planned step of the payer import.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Payer;

final readonly class PayerChange
{
    /**
     * @param ?int $insuranceCompanyId the mapped company (AlreadyMapped) or the deleted one (Recreate)
     */
    public function __construct(
        public PayerChangeKind $kind,
        public PayerMapping $mapping,
        public ?int $insuranceCompanyId,
    ) {
    }

    public function describe(): string
    {
        $m = $this->mapping;
        $what = $m->electronic === null
            ? 'paper'
            : sprintf('payer ID %s, X12 partner %d', $m->electronic->payerId, $m->electronic->x12PartnerId);

        return match ($this->kind) {
            PayerChangeKind::Create => sprintf('create %s "%s" (%s, type %d)', $m->mnemonic, $m->name, $what, $m->insTypeCode),
            PayerChangeKind::Recreate => sprintf(
                'create %s "%s" again (%s, type %d); insurance company %d it was mapped to is gone',
                $m->mnemonic,
                $m->name,
                $what,
                $m->insTypeCode,
                $this->insuranceCompanyId ?? 0
            ),
            PayerChangeKind::AlreadyMapped => sprintf('%s already mapped to insurance company %d', $m->mnemonic, $this->insuranceCompanyId ?? 0),
        };
    }
}
