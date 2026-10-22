<?php

/**
 * What one posting run did, in counts (no patient data).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Post;

final class PostResult
{
    public int $posted = 0;
    public int $held = 0;
    public int $duplicates = 0;
    public int $encountersCreated = 0;
    public int $patientsCreated = 0;

    /** @return array<string, int> */
    public function toArray(): array
    {
        return [
            'posted' => $this->posted,
            'held' => $this->held,
            'duplicates' => $this->duplicates,
            'encounters_created' => $this->encountersCreated,
            'patients_created' => $this->patientsCreated,
        ];
    }
}
