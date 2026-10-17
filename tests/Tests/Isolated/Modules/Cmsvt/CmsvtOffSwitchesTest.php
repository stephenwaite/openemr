<?php

/**
 * Isolated tests for the CMS module's off switches, which let a practice
 * outside CMS use the module without the Vermont claim rules or the CMS menu.
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

    use Cmsvt\OpenEMR\Modules\Customizations\Billing\ClaimOptions;
    use Cmsvt\OpenEMR\Modules\Customizations\Billing\CmsvtClaimRules;
    use Cmsvt\OpenEMR\Modules\Customizations\Globals\CmsvtGlobals;
    use Cmsvt\OpenEMR\Modules\Customizations\Menu\CmsvtMenu;
    use OpenEMR\Billing\Claim;
    use OpenEMR\Billing\X12\DefaultClaim837PRules;
    use OpenEMR\Core\OEGlobalsBag;
    use OpenEMR\Events\Billing\Claim837PRulesEvent;
    use OpenEMR\Menu\MenuEvent;
    use PHPUnit\Framework\Attributes\Test;
    use PHPUnit\Framework\TestCase;
    use stdClass;

    final class CmsvtOffSwitchesTest extends TestCase
    {
        private static function claim(): Claim
        {
            return new class extends Claim {
                public function __construct()
                {
                }
            };
        }

        /**
         * @param array<string, string> $values
         */
        private static function globals(array $values): OEGlobalsBag
        {
            return new OEGlobalsBag($values);
        }

        #[Test]
        public function claimRulesApplyUnlessDisabled(): void
        {
            // Unsaved, as on a CMS site that never touched the new setting.
            $event = new Claim837PRulesEvent(self::claim());
            (new ClaimOptions(self::globals([])))->applyRules($event);
            $this->assertInstanceOf(CmsvtClaimRules::class, $event->getRules());

            $event = new Claim837PRulesEvent(self::claim());
            (new ClaimOptions(self::globals([CmsvtGlobals::CLAIM_RULES_DISABLED => '1'])))->applyRules($event);
            $this->assertInstanceOf(DefaultClaim837PRules::class, $event->getRules());
        }

        #[Test]
        public function menuChangesApplyUnlessDisabled(): void
        {
            $this->assertSame('pay', $this->frontPaymentTarget(self::globals([])));
            $this->assertSame('enc', $this->frontPaymentTarget(self::globals([CmsvtGlobals::MENU_CHANGES_DISABLED => '1'])));
        }

        /**
         * The target CmsvtMenu leaves on the front payment item, which it moves
         * from the encounter frame to its own tab.
         */
        private function frontPaymentTarget(OEGlobalsBag $globals): string
        {
            $item = new stdClass();
            $item->label = 'Payment';
            $item->url = '/interface/patient_file/front_payment.php';
            $item->target = 'enc';
            $item->children = [];
            $event = new MenuEvent([$item]);

            (new CmsvtMenu($globals))->apply($event);

            $first = $event->getMenu()[0] ?? null;
            return $first instanceof stdClass && is_string($first->target ?? null) ? $first->target : '';
        }
    }
}
