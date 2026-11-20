<?php

/**
 * Isolated tests for the ERA adjustment policy event's stock defaults and the
 * CMS module's per-site rules on top of them.
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

    use Cmsvt\OpenEMR\Modules\Customizations\Billing\EraAdjustmentPolicy;
    use Cmsvt\OpenEMR\Modules\Customizations\Globals\CmsvtGlobals;
    use OpenEMR\Core\OEGlobalsBag;
    use OpenEMR\Events\Billing\EraAdjustmentPolicyEvent;
    use PHPUnit\Framework\Attributes\Test;
    use PHPUnit\Framework\TestCase;

    final class EraAdjustmentPolicyTest extends TestCase
    {
        private const SETTINGS = [
            CmsvtGlobals::ERA_SECONDARY_WRITEOFF_PAYER_IDS => '96385, 52461',
            CmsvtGlobals::ERA_CONTRACTUAL_CO_BY_PROCEDURE => '97:92015',
        ];

        private static function event(string $payerId, bool $primary, string $group, string $reason, string $code = '92004'): EraAdjustmentPolicyEvent
        {
            return new EraAdjustmentPolicyEvent($payerId, $primary, $group, $reason, $code);
        }

        /**
         * @param array<string, string> $settings
         */
        private static function apply(EraAdjustmentPolicyEvent $event, array $settings = self::SETTINGS): EraAdjustmentPolicyEvent
        {
            (new EraAdjustmentPolicy(new OEGlobalsBag($settings)))->apply($event);
            return $event;
        }

        #[Test]
        public function defaultsAreTheStockPosting(): void
        {
            $co45 = self::event('96385', false, 'CO', '45');
            $this->assertFalse($co45->isSecondaryWriteOff());
            $this->assertTrue($co45->isContractual());
            $this->assertSame('Adjust code 45', $co45->getMemo());

            $this->assertTrue(self::event('X', true, 'CO', '59')->isContractual());
            $this->assertFalse(self::event('X', true, 'CO', '97')->isContractual());
            $this->assertFalse(self::event('X', true, 'OA', '23')->isContractual());
        }

        #[Test]
        public function aMedicaidSecondaryOfLastResortWritesOff(): void
        {
            $event = self::apply(self::event('96385', false, 'CO', '45'));
            $this->assertTrue($event->isSecondaryWriteOff());
            $this->assertTrue($event->isContractual());
            $this->assertSame('Medicaid w/o CO-45', $event->getMemo());

            $pi = self::apply(self::event('52461', false, 'PI', '96'));
            $this->assertTrue($pi->isSecondaryWriteOff());
            $this->assertTrue($pi->isContractual(), 'zero pay from the payer of last resort is expected');
        }

        #[Test]
        public function primaryPostingsAndPatientResponsibilityAreLeftAlone(): void
        {
            $primary = self::apply(self::event('96385', true, 'CO', '97'));
            $this->assertFalse($primary->isSecondaryWriteOff());
            $this->assertFalse($primary->isContractual());
            $this->assertSame('Adjust code 97', $primary->getMemo());

            $patient = self::apply(self::event('96385', false, 'PR', '2'));
            $this->assertFalse($patient->isSecondaryWriteOff());

            // OA-23 is the prior payer's adjudication: writing it off would credit
            // again what the primary settled, so it stays a note.
            $priorPayer = self::apply(self::event('96385', false, 'OA', '23'));
            $this->assertFalse($priorPayer->isSecondaryWriteOff());
            $this->assertSame('Adjust code 23', $priorPayer->getMemo());

            $otherPayer = self::apply(self::event('60054', false, 'CO', '45'));
            $this->assertFalse($otherPayer->isSecondaryWriteOff());
        }

        #[Test]
        public function contractualReasonsArePerProcedure(): void
        {
            $this->assertTrue(self::apply(self::event('X', true, 'CO', '97', '92015'))->isContractual());
            $this->assertFalse(self::apply(self::event('X', true, 'CO', '97', '92004'))->isContractual());
            $this->assertFalse(self::apply(self::event('X', true, 'OA', '97', '92015'))->isContractual());
        }

        #[Test]
        public function blankSettingsChangeNothing(): void
        {
            $event = self::apply(self::event('96385', false, 'CO', '97', '92015'), []);
            $this->assertFalse($event->isSecondaryWriteOff());
            $this->assertFalse($event->isContractual());
            $this->assertSame('Adjust code 97', $event->getMemo());
        }

        #[Test]
        public function theMemoKeepsToTwentyFiveCharacters(): void
        {
            $event = self::event('X', true, 'CO', '45');
            $event->setMemo('Medicaid w/o CO-45 and a lot more text');
            $this->assertSame(25, mb_strlen($event->getMemo()));
        }
    }
}
