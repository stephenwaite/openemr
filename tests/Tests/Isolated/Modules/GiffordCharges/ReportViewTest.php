<?php

/**
 * Isolated tests for the report shown beside a Gifford charge line.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\Modules\GiffordCharges;

require_once __DIR__ . '/autoload.php';

use Cmsvt\OpenEMR\Modules\GiffordCharges\Review\ReportView;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReportViewTest extends TestCase
{
    #[Test]
    public function aRowWithoutAReportHasNoView(): void
    {
        $this->assertNull(ReportView::fromRaw(['chg_cpt' => '71046']));
        $this->assertNull(ReportView::fromRaw(['report_status' => 'Signed', 'report_signed' => '10/14/2026 09:31', 'report_text' => '']));
    }

    #[Test]
    public function theReportIsDecodedForReading(): void
    {
        $view = ReportView::fromRaw([
            'report_status' => 'Addendum',
            'report_signed' => '10/14/2026 09:31',
            'report_text' => 'EXAM: Chest\n\nFINDINGS:\tClear',
        ]);

        $this->assertNotNull($view);
        $this->assertSame('Addendum', $view->status);
        $this->assertSame('10/14/2026 09:31', $view->signed);
        $this->assertSame("EXAM: Chest\n\nFINDINGS:\tClear", $view->text);
    }

    #[Test]
    public function textThatDoesNotDecodeIsShownAsSent(): void
    {
        $view = ReportView::fromRaw(['report_text' => 'a "bare" quote']);

        $this->assertNotNull($view);
        $this->assertSame('a "bare" quote', $view->text);
        $this->assertSame('', $view->status);
    }
}
