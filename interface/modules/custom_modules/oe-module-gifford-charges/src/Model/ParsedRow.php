<?php

/**
 * A Gifford charge row parsed into records, with every problem found in it.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Model;

final readonly class ParsedRow
{
    /**
     * The records are null when the row is too broken to build them; the
     * problems say why. A row is importable only when it has no problems.
     *
     * @param list<PolicyRecord> $policies by rank; self-pay ranks are left out
     * @param list<string> $problems
     */
    public function __construct(
        public int $line,
        public ?PatientRecord $patient,
        public ?EncounterRecord $encounter,
        public ?ChargeRecord $charge,
        public ?PartyRecord $guarantor,
        public ?PartyRecord $employer,
        public array $policies,
        public array $problems,
    ) {
    }

    public function isImportable(): bool
    {
        return $this->problems === [] && $this->patient !== null && $this->encounter !== null && $this->charge !== null;
    }
}
