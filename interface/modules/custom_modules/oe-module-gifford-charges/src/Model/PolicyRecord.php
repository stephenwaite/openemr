<?php

/**
 * An insurance policy from one of a row's ins1-ins3 column groups.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Model;

final readonly class PolicyRecord
{
    /**
     * @param int $rank 1 primary, 2 secondary, 3 tertiary
     * @param string $relationship OpenEMR subscriber relationship: self, spouse, child or other
     */
    public function __construct(
        public int $rank,
        public string $payerMnemonic,
        public string $payerName,
        public string $memberId,
        public string $groupNumber,
        public string $groupName,
        public string $subscriberLastName,
        public string $subscriberFirstName,
        public ?\DateTimeImmutable $subscriberDateOfBirth,
        public string $subscriberSex,
        public string $relationship,
    ) {
    }
}
