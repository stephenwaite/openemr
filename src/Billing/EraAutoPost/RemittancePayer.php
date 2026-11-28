<?php

/**
 * The insurance company a remittance file is posted for, worked out as unattended posting does.
 *
 * A person posting the file by hand is offered this company, so that both ways of posting
 * a file look for its earlier postings under the same one.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Billing\EraAutoPost;

final readonly class RemittancePayer
{
    public function __construct(
        private RemittanceReader $reader,
        private CheckPoster $poster,
    ) {
    }

    /**
     * @return int the insurance_companies.id every check in the file leads to; 0 when the file
     *             cannot be read, has no check, a check leads to no company, or its checks
     *             lead to more than one
     */
    public function forFile(string $path): int
    {
        if (!is_file($path)) {
            return 0;
        }
        $remittance = $this->reader->read($path);
        if ($remittance->error !== '' || $remittance->checks === []) {
            return 0;
        }
        $payers = [];
        foreach ($remittance->checks as $check) {
            $payer = $this->poster->payerFor($check);
            if (!ctype_digit($payer) || (int) $payer === 0) {
                return 0;
            }
            $payers[(int) $payer] = true;
        }
        return count($payers) === 1 ? array_key_first($payers) : 0;
    }
}
