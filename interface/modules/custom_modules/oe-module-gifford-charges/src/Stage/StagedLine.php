<?php

/**
 * A charge line as it is saved in gifford_charge_line.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Stage;

use Cmsvt\OpenEMR\Modules\GiffordCharges\Model\ParsedRow;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Reader\RawRow;

final readonly class StagedLine
{
    /**
     * @param array<string, string> $rawValues the row as read, by lower-case column name
     * @param list<string> $problems
     */
    public function __construct(
        public int $lineNumber,
        public string $visitNumber,
        public string $mrn,
        public string $accession,
        public ?\DateTimeImmutable $dateOfService,
        public string $code,
        public array $rawValues,
        public array $problems,
        public LineStatus $status,
    ) {
    }

    /**
     * The line for a row and what the parser made of it. The key fields come from the
     * parsed records where they parsed, else straight from the row, so a held line can
     * still be found by its visit, MRN and accession.
     */
    public static function fromRow(RawRow $row, ParsedRow $parsed): self
    {
        $code = $parsed->charge?->code;
        $code ??= $row->get('chg_cpt') !== '' ? $row->get('chg_cpt') : $row->get('chg_hcpcs');

        return new self(
            $row->line,
            $parsed->encounter->visitNumber ?? $row->get('encounter_num'),
            $parsed->patient->mrn ?? $row->get('pat_mrn'),
            $parsed->charge->accession ?? $row->get('chg_accession'),
            $parsed->charge?->dateOfService,
            $code,
            $row->values,
            $parsed->problems,
            $parsed->isImportable() ? LineStatus::Staged : LineStatus::Held,
        );
    }
}
