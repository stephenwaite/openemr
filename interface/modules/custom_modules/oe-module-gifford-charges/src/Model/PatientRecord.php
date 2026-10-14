<?php

/**
 * The patient on a Gifford charge row. The Gifford MRN becomes the pubpid.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Model;

final readonly class PatientRecord
{
    /**
     * @param string $sex OpenEMR sex list value: Male, Female or UNK
     */
    public function __construct(
        public string $mrn,
        public string $lastName,
        public string $firstName,
        public \DateTimeImmutable $dateOfBirth,
        public string $sex,
        public Address $address,
        public string $phone,
        public string $email,
    ) {
    }
}
