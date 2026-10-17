<?php

/**
 * Isolated tests for the MIPS quality measures report: status rules, the 176, 410
 * and 509 measures, the shared counts and rates, and the site switch for the menu.
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
    use Cmsvt\OpenEMR\Modules\Customizations\Mips\Measures\Measure176;
    use Cmsvt\OpenEMR\Modules\Customizations\Mips\Measures\Measure410;
    use Cmsvt\OpenEMR\Modules\Customizations\Mips\Measures\Measure509;
    use Cmsvt\OpenEMR\Modules\Customizations\Mips\MipsCodeEntry;
    use Cmsvt\OpenEMR\Modules\Customizations\Mips\MipsCriterion;
    use Cmsvt\OpenEMR\Modules\Customizations\Mips\MipsData;
    use Cmsvt\OpenEMR\Modules\Customizations\Mips\MipsMeasure;
    use Cmsvt\OpenEMR\Modules\Customizations\Mips\MipsMeasures;
    use Cmsvt\OpenEMR\Modules\Customizations\Mips\MipsPeriod;
    use Cmsvt\OpenEMR\Modules\Customizations\Mips\MipsRole;
    use Cmsvt\OpenEMR\Modules\Customizations\Mips\MipsStatus;
    use OpenEMR\Core\OEGlobalsBag;
    use OpenEMR\Menu\MenuEvent;
    use PHPUnit\Framework\Attributes\Test;
    use PHPUnit\Framework\TestCase;
    use stdClass;

    final class MipsReportTest extends TestCase
    {
        /**
         * @return array<mixed>
         */
        private static function patient(int $pid, string $lname): array
        {
            return ['pid' => (string) $pid, 'lname' => $lname, 'fname' => 'Pat', 'mname' => '', 'sex' => 'Female', 'DOB' => '1960-01-01', 'dx_history' => ''];
        }

        /**
         * @return array<mixed>
         */
        private static function visit(int $pid, int $encounter, string $date, string $codes = '99213'): array
        {
            return ['pid' => (string) $pid, 'encounter' => (string) $encounter, 'date' => $date . ' 09:00:00', 'age' => '66', 'visit_codes' => $codes];
        }

        /**
         * @return array<mixed>
         */
        private static function code(int $pid, int $encounter, string $code, string $date): array
        {
            return ['pid' => (string) $pid, 'encounter' => (string) $encounter, 'code' => $code, 'encounter_date' => $date . ' 09:00:00'];
        }

        #[Test]
        public function bestCodeAndMostRecentRules(): void
        {
            $best = new MipsCriterion('c', 'C', [
                MipsStatus::forCode('M1388', 'Met', MipsRole::Met),
                MipsStatus::forCode('M1392', 'Exception', MipsRole::Exception),
                MipsStatus::forCode('M1390', 'Not Met', MipsRole::NotMet),
            ]);
            $this->assertSame('Met (M1388)', $best->status([new MipsCodeEntry('M1390', '2026-01-02', 1), new MipsCodeEntry('M1388', '2026-01-01', 2)])->label);
            $this->assertSame(MipsStatus::NOT_REPORTED, $best->status([new MipsCodeEntry('G9649', '2026-01-02', 1)])->label);

            $recent = (new Measure410())->criterion();
            $this->assertSame('Not Met (G9651)', $recent->status([
                new MipsCodeEntry('G9649', '2026-02-01', 1),
                new MipsCodeEntry('G9651', '2026-06-01', 2),
            ])->label);
            $this->assertSame(MipsStatus::CONFLICTING, $recent->status([
                new MipsCodeEntry('G9651', '2026-02-01', 1),
                new MipsCodeEntry('G9649', '2026-06-01', 2),
                new MipsCodeEntry('G9765', '2026-06-01', 2),
            ])->label);
        }

        #[Test]
        public function monthShiftsAndPeriod(): void
        {
            $period = new MipsPeriod(2026);
            $this->assertSame('2024-10-01', MipsPeriod::shift($period->start(), -15));
            $this->assertSame('2026-03-13', MipsPeriod::shift('2026-03-14', 0, -1));
            $this->assertTrue($period->contains('2026-12-31'));
            $this->assertFalse($period->contains('2027-01-01'));
        }

        #[Test]
        public function measure509CountsOnlyPatientsWithAQualifyingVisit(): void
        {
            $data = new MipsData(
                [self::patient(1, 'Amelano'), self::patient(2, 'Bmelano')],
                [self::visit(1, 501, '2026-03-14')],
                [
                    self::code(1, 310, '11602', '2023-05-01'),
                    self::code(1, 400, 'M1390', '2025-06-01'),
                    self::code(1, 501, 'm1388', '2026-03-14'),
                    self::code(1, 501, 'M1393', '2026-03-14'),
                    self::code(2, 620, 'M1392', '2026-04-02'),
                ],
            );
            $report = (new Measure509())->build(new MipsPeriod(2026), $data);

            $this->assertSame(1, $report->denominatorCount());
            $first = $report->patients[0];
            $this->assertSame(['11602 2023-05-01'], $first->columns['Excision CPTs']);
            $this->assertSame(['M1388 2026-03-14'], $first->columns['Criteria 1 Codes']);
            $this->assertSame('Met (M1388)', $first->statuses['crit1']->label);
            $this->assertSame('No Recurrence (M1393)', $first->statuses['crit2']->label);
            $this->assertSame(['M1388 2026-03-14', 'M1393 2026-03-14'], $first->encounters[0]->measureCodes);
            $this->assertSame([], $first->flags);

            $second = $report->patients[1];
            $this->assertFalse($second->inDenominator);
            $this->assertSame('Exception (M1392)', $second->statuses['crit1']->label);

            [$crit1, $crit2] = $report->criteria;
            $this->assertSame(['all' => 1, 'denominator' => 1], $report->counts($crit1)['Met (M1388)']);
            $this->assertSame(['all' => 1, 'denominator' => 0], $report->counts($crit1)['Exception (M1392)']);
            $this->assertSame(100.0, $report->completeness($crit1));
            $this->assertSame(100.0, $report->performanceRate($crit1));
            // Inverse: M1391 is "met", so no recurrence gives 0 %.
            $this->assertSame(0.0, $report->performanceRate($crit2));
        }

        #[Test]
        public function measure509FlagsADenominatorPatientWithoutExcision(): void
        {
            $data = new MipsData([self::patient(3, 'Cmelano')], [self::visit(3, 700, '2026-12-31')], []);
            $report = (new Measure509())->build(new MipsPeriod(2026), $data);

            $this->assertSame([Measure509::NO_EXCISION], $report->patients[0]->flags);
            $this->assertSame(MipsStatus::NOT_REPORTED, $report->patients[0]->statuses['crit1']->label);
            $this->assertSame(0.0, $report->completeness($report->criteria[0]));
            $this->assertNull($report->performanceRate($report->criteria[0]));
        }

        #[Test]
        public function measure176FirstCourseAndTbWindow(): void
        {
            $data = new MipsData(
                [self::patient(10, 'Apso'), self::patient(11, 'Bpso')],
                [self::visit(10, 900, '2026-05-10'), self::visit(11, 950, '2026-03-01')],
                [
                    // Patient 10: G2182 earlier than the keyword visit, so the start is 2026-04-02.
                    self::code(10, 880, 'G2182', '2026-04-02'),
                    self::code(10, 700, '86480', '2025-03-01'),   // before start - 12 months: outside
                    self::code(10, 710, '86480', '2025-06-01'),   // inside the TB window
                    self::code(10, 900, 'M1003', '2026-05-10'),
                    // Patient 11: no G2182 this year, an earlier one in the 15-month window.
                    self::code(11, 600, 'G2182', '2025-01-15'),
                    self::code(11, 950, 'M1005', '2026-03-01'),
                ],
                [
                    ['pid' => '11', 'encounter_date' => '2025-11-20 10:00:00'],
                    ['pid' => '11', 'encounter_date' => '2026-03-01 09:00:00'],
                ],
            );
            $report = (new Measure176())->build(new MipsPeriod(2026), $data);

            $a = $report->patients[0];
            $this->assertTrue($a->inDenominator);
            $this->assertSame(['2026-04-02'], $a->columns['First 2026 Therapy Encounter']);
            $this->assertSame(['86480 2025-06-01'], $a->columns['TB Test CPTs']);
            $this->assertSame([], $a->columns['Prior Therapy (15 mo)']);
            $this->assertSame('Met (M1003)', $a->statuses['numerator']->label);
            $this->assertSame([], $a->flags);

            $b = $report->patients[1];
            $this->assertFalse($b->inDenominator);
            $this->assertSame(['Reason 2025-11-20', 'G2182 2025-01-15'], $b->columns['Prior Therapy (15 mo)']);
            $this->assertSame([Measure176::NO_G2182, Measure176::PRIOR_THERAPY], $b->flags);

            $counts = $report->counts($report->criteria[0]);
            $this->assertSame(['all' => 1, 'denominator' => 1], $counts['Met (M1003)']);
            $this->assertSame(['all' => 1, 'denominator' => 0], $counts['Not Met (M1005)']);
            $this->assertSame([Measure176::NO_G2182 => 1, Measure176::PRIOR_THERAPY => 1], $report->flagCounts());
        }

        #[Test]
        public function measure410MostRecentCodeAndTreatmentLength(): void
        {
            $data = new MipsData(
                [self::patient(20, 'Apv'), self::patient(21, 'Bpv')],
                [
                    self::visit(20, 1001, '2026-09-01'),
                    self::visit(20, 1000, '2026-02-01'),
                    self::visit(21, 1100, '2026-04-01'),
                ],
                [
                    self::code(20, 1000, 'G9764', '2026-02-01'),
                    self::code(20, 1000, 'G9651', '2026-02-01'),
                    self::code(20, 1001, 'G9649', '2026-09-01'),
                    self::code(21, 1100, 'G9764', '2026-04-01'),
                    self::code(21, 1100, 'G9651', '2026-04-01'),
                ],
                [
                    ['pid' => '20', 'encounter_date' => '2025-03-01 09:00:00'],
                    ['pid' => '21', 'encounter_date' => '2026-01-15 09:00:00'],
                ],
            );
            $report = (new Measure410())->build(new MipsPeriod(2026), $data);

            $a = $report->patients[0];
            $this->assertSame('Met (G9649)', $a->statuses['numerator']->label);
            $this->assertSame(['2026-09-01'], $a->columns['Last 2026 Systemic Med Visit']);
            $this->assertSame(['2025-03-01'], $a->columns['Systemic Med Since']);
            $this->assertSame([], $a->flags);

            $b = $report->patients[1];
            $this->assertSame('Not Met (G9651)', $b->statuses['numerator']->label);
            $this->assertSame([Measure410::UNDER_SIX_MONTHS], $b->flags);

            $criterion = $report->criteria[0];
            $this->assertSame(100.0, $report->completeness($criterion));
            $this->assertSame(50.0, $report->performanceRate($criterion));
            $this->assertArrayHasKey(MipsStatus::CONFLICTING, $report->counts($criterion));
        }

        #[Test]
        public function measuresAreFoundByNumber(): void
        {
            $this->assertSame(['176', '410', '509'], array_map(static fn(MipsMeasure $m): string => $m->id(), MipsMeasures::all()));
            $this->assertSame('410', MipsMeasures::find('410')->id());
            $this->assertSame('176', MipsMeasures::find('nope')->id());
        }

        #[Test]
        public function menuEntryOnlyWhereEnabled(): void
        {
            $this->assertSame(['Report Results'], $this->clinicLabels([]));
            $this->assertSame(
                ['Report Results', 'MIPS Quality Measures'],
                $this->clinicLabels([CmsvtGlobals::MIPS_REPORTS => '1'])
            );
            $this->assertSame(
                ['Report Results'],
                $this->clinicLabels([CmsvtGlobals::MIPS_REPORTS => '1', CmsvtGlobals::MENU_CHANGES_DISABLED => '1'])
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
