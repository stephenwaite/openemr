<?php

/**
 * Isolated tests for the MIPS 509 melanoma report: code windows, criteria
 * status, denominator counts and the site switch for its menu entry.
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

    use Cmsvt\OpenEMR\Modules\Customizations\Globals\CmsvtGlobals;
    use Cmsvt\OpenEMR\Modules\Customizations\Menu\CmsvtMenu;
    use Cmsvt\OpenEMR\Modules\Customizations\Mips\Mips509Measure;
    use Cmsvt\OpenEMR\Modules\Customizations\Mips\Mips509Report;
    use OpenEMR\Core\OEGlobalsBag;
    use OpenEMR\Menu\MenuEvent;
    use PHPUnit\Framework\Attributes\DataProvider;
    use PHPUnit\Framework\Attributes\Test;
    use PHPUnit\Framework\TestCase;
    use stdClass;

    final class Mips509ReportTest extends TestCase
    {
        /**
         * @return array<string, array{string, string, bool}>
         *
         * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
         */
        public static function windowProvider(): array
        {
            return [
                'quality code in the year' => ['M1388', '2026-12-31 16:00:00', true],
                'quality code the year before' => ['M1388', '2025-12-31 16:00:00', false],
                'quality code the year after' => ['M1393', '2027-01-01 08:00:00', false],
                'excision at the start of the lookback' => ['11602', '2021-01-01 08:00:00', true],
                'excision before the lookback' => ['11602', '2020-12-31 08:00:00', false],
                'untracked code' => ['99213', '2026-03-01 08:00:00', false],
            ];
        }

        #[Test]
        #[DataProvider('windowProvider')]
        public function codesCountOnlyInTheirWindow(string $code, string $encounterDate, bool $expected): void
        {
            $this->assertSame($expected, (new Mips509Measure(2026))->counts($code, $encounterDate));
        }

        #[Test]
        public function criteriaTakeTheBestCode(): void
        {
            $this->assertSame('Met (M1388)', Mips509Measure::criteria1Status(['M1390', 'M1392', 'M1388']));
            $this->assertSame('Exception (M1392)', Mips509Measure::criteria1Status(['M1390', 'M1392']));
            $this->assertSame(Mips509Measure::NOT_REPORTED, Mips509Measure::criteria1Status(['M1393']));
            $this->assertSame('Recurrence (M1391)', Mips509Measure::criteria2Status(['M1393', 'M1391']));
            $this->assertSame('No Recurrence (M1393)', Mips509Measure::criteria2Status(['M1392', 'M1393']));
            $this->assertSame('Exception (M1392)', Mips509Measure::criteria2Status(['M1392', 'M1388']));
        }

        #[Test]
        public function onlyPatientsWithAQualifyingVisitAreCounted(): void
        {
            $patients = [
                ['pid' => '1', 'lname' => 'Alvarez', 'fname' => 'Renata', 'mname' => '', 'sex' => 'Female', 'DOB' => '1960-02-01', 'dx_history' => 'C43.9 (2023-05-01)'],
                ['pid' => '2', 'lname' => 'Brook', 'fname' => 'Ada', 'mname' => '', 'sex' => 'Female', 'DOB' => '1955-07-09', 'dx_history' => 'D03.9 (2022-01-10)'],
            ];
            $encounters = [
                ['pid' => '1', 'encounter' => '501', 'date' => '2026-03-14 09:00:00', 'age' => '66', 'visit_codes' => '99213'],
            ];
            $codes = [
                ['pid' => '1', 'encounter' => '310', 'code' => '11602', 'encounter_date' => '2023-05-01 10:00:00'],
                ['pid' => '1', 'encounter' => '400', 'code' => 'M1390', 'encounter_date' => '2025-06-01 10:00:00'],
                ['pid' => '1', 'encounter' => '501', 'code' => 'm1388', 'encounter_date' => '2026-03-14 09:00:00'],
                ['pid' => '1', 'encounter' => '501', 'code' => 'M1393', 'encounter_date' => '2026-03-14 09:00:00'],
                ['pid' => '2', 'encounter' => '620', 'code' => 'M1392', 'encounter_date' => '2026-04-02 09:00:00'],
            ];

            $report = Mips509Report::build(new Mips509Measure(2026), $patients, $encounters, $codes);

            $this->assertCount(2, $report->patients);
            $this->assertSame(1, $report->denominatorCount());
            $this->assertSame(1, $report->encounterCount());
            $this->assertSame(0, $report->missingExcisionCount());

            $first = $report->patients[0];
            $this->assertTrue($first->inDenominator());
            $this->assertSame(['11602 (2023-05-01)'], $first->excisions);
            $this->assertSame(['M1388 2026-03-14'], $first->criteria1Codes);
            $this->assertSame('Met (M1388)', $first->criteria1Status);
            $this->assertSame('No Recurrence (M1393)', $first->criteria2Status);
            $this->assertSame(['M1388 2026-03-14', 'M1393 2026-03-14'], $first->encounters[0]->measureCodes);

            // Coded but no qualifying visit: listed, not in the denominator or the totals.
            $second = $report->patients[1];
            $this->assertFalse($second->inDenominator());
            $this->assertSame('Exception (M1392)', $second->criteria1Status);
            $this->assertSame(1, $report->criteria1Counts['Met (M1388)']);
            $this->assertSame(0, $report->criteria1Counts['Exception (M1392)']);
            $this->assertSame(1, array_sum($report->criteria2Counts));
        }

        #[Test]
        public function menuEntryOnlyWhereEnabled(): void
        {
            $this->assertSame(['Report Results'], $this->clinicLabels([]));
            $this->assertSame(
                ['Report Results', 'MIPS 509: Melanoma Tracking'],
                $this->clinicLabels([CmsvtGlobals::MIPS_509_REPORT => '1'])
            );
            $this->assertSame(
                ['Report Results'],
                $this->clinicLabels([CmsvtGlobals::MIPS_509_REPORT => '1', CmsvtGlobals::MENU_CHANGES_DISABLED => '1'])
            );
        }

        /**
         * @param array<string, string> $globals
         * @return list<string>
         */
        private function clinicLabels(array $globals): array
        {
            $results = new stdClass();
            $results->label = 'Report Results';
            $results->url = '/interface/reports/report_results.php';
            $results->target = 'rep';
            $results->children = [];
            $clinic = new stdClass();
            $clinic->label = 'Clinic';
            $clinic->children = [$results];
            $reports = new stdClass();
            $reports->label = 'Reports';
            $reports->children = [$clinic];
            $event = new MenuEvent([$reports]);

            // xl() on the new entry: skip the translation lookup, which needs a database.
            $shared = OEGlobalsBag::getInstance();
            $hadSetting = $shared->has('disable_translation');
            $previous = $shared->getBoolean('disable_translation');
            $shared->set('disable_translation', true);
            try {
                (new CmsvtMenu(new OEGlobalsBag($globals)))->apply($event);
            } finally {
                if ($hadSetting) {
                    $shared->set('disable_translation', $previous);
                } else {
                    $shared->remove('disable_translation');
                }
            }

            $labels = [];
            foreach ($clinic->children as $child) {
                if (is_string($child->label ?? null)) {
                    $labels[] = $child->label;
                }
            }
            return $labels;
        }
    }
}
