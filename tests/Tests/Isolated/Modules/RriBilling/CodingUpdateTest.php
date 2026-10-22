<?php

/**
 * Isolated tests for reading the coding page's form into updates, and the
 * justify they give the CPT line. All data is invented.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\Modules\RriBilling;

require_once __DIR__ . '/autoload.php';

use Cmsvt\OpenEMR\Modules\RriBilling\Coding\CodingUpdate;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CodingUpdateTest extends TestCase
{
    #[Test]
    public function theFormIsReadIntoUpdates(): void
    {
        $updates = CodingUpdate::fromForm([
            12 => ['billing-provider' => '7', 'billing-code-1' => ' m17.11 ', 'billing-code-2' => ''],
            13 => ['billing-code-1' => 'R07.9'],
            'x' => ['billing-code-1' => 'Z00.00'],
            14 => 'not a record',
        ]);

        $this->assertSame(
            [[12, 7, 'M17.11', '', 'ICD10|M17.11:'], [13, null, 'R07.9', '', 'ICD10|R07.9:']],
            array_map(static fn(CodingUpdate $update): array => [$update->recordId, $update->providerId, $update->dx1, $update->dx2, $update->justify()], $updates)
        );
    }
}
