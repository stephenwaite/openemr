<?php

/**
 * Isolated tests for the invoice adjustment reason event's stock default and
 * the CMS module's "memo shows reason title" setting.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace {
    $cmsvtModuleSrc = dirname(__DIR__, 5) . '/interface/modules/custom_modules/oe-module-cmsvt/src/';
    if (!is_dir($cmsvtModuleSrc)) {
        throw new RuntimeException('oe-module-cmsvt source not found at ' . $cmsvtModuleSrc);
    }
    spl_autoload_register(static function (string $class) use ($cmsvtModuleSrc): void {
        $prefix = 'Cmsvt\\OpenEMR\\Modules\\Customizations\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $file = $cmsvtModuleSrc . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    });
}

namespace OpenEMR\Tests\Isolated\Modules\Cmsvt {

    use Cmsvt\OpenEMR\Modules\Customizations\Billing\AdjustmentMemoReason;
    use Cmsvt\OpenEMR\Modules\Customizations\Globals\CmsvtGlobals;
    use OpenEMR\Core\OEGlobalsBag;
    use OpenEMR\Events\Billing\InvoiceAdjustmentReasonEvent;
    use PHPUnit\Framework\Attributes\Test;
    use PHPUnit\Framework\TestCase;

    final class AdjustmentMemoReasonTest extends TestCase
    {
        /**
         * @param array<string, string> $settings
         */
        private static function apply(InvoiceAdjustmentReasonEvent $event, array $settings): string
        {
            (new AdjustmentMemoReason(new OEGlobalsBag($settings)))->apply($event);
            return $event->getReasonText();
        }

        #[Test]
        public function stockMemoIsTheReasonId(): void
        {
            self::assertSame('KC', (new InvoiceAdjustmentReasonEvent('KC', 'KanCare write-off'))->getReasonText());
        }

        #[Test]
        public function settingOffKeepsTheId(): void
        {
            self::assertSame('KC', self::apply(new InvoiceAdjustmentReasonEvent('KC', 'KanCare write-off'), []));
            self::assertSame('KC', self::apply(new InvoiceAdjustmentReasonEvent('KC', 'KanCare write-off'), [CmsvtGlobals::ADJUSTMENT_MEMO_USES_TITLE => '0']));
        }

        #[Test]
        public function settingOnStoresTheTitle(): void
        {
            $event = new InvoiceAdjustmentReasonEvent('KC', 'KanCare write-off');
            self::assertSame('KanCare write-off', self::apply($event, [CmsvtGlobals::ADJUSTMENT_MEMO_USES_TITLE => '1']));
        }

        #[Test]
        public function reasonWithoutTitleKeepsTheId(): void
        {
            $event = new InvoiceAdjustmentReasonEvent('KC', '');
            self::assertSame('KC', self::apply($event, [CmsvtGlobals::ADJUSTMENT_MEMO_USES_TITLE => '1']));
        }
    }
}
