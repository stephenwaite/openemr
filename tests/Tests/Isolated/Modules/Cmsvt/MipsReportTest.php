<?php

/**
 * Isolated tests for the MIPS quality measures report: status rules, the 039, 176,
 * 178, 180, 410 and 509 measures, the shared counts and rates, the site's measure list
 * and the site switch for the menu.
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
    use Cmsvt\OpenEMR\Modules\Customizations\Mips\Measures\Measure039;
    use Cmsvt\OpenEMR\Modules\Customizations\Mips\Measures\Measure176;
    use Cmsvt\OpenEMR\Modules\Customizations\Mips\Measures\Measure178;
    use Cmsvt\OpenEMR\Modules\Customizations\Mips\Measures\Measure180;
    use Cmsvt\OpenEMR\Modules\Customizations\Mips\Measures\Measure410;
    use Cmsvt\OpenEMR\Modules\Customizations\Mips\Measures\Measure509;
    use Cmsvt\OpenEMR\Modules\Customizations\Mips\Measures\RheumatoidArthritisMeasure;
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
        public function rheumatoidArthritisDenominatorAndRuleEntries(): void
        {
            $data = new MipsData(
                [self::patient(30, 'Ara'), self::patient(31, 'Bra'), self::patient(32, 'Cra'), self::patient(33, 'Dra')],
                [
                    // 30: two visits in the year 90 days apart (Feb 1 to May 2).
                    self::visit(30, 3001, '2026-05-02'),
                    self::visit(30, 3000, '2026-02-01'),
                    // 31: one visit in the year, the other exactly 90 days before it, the year before.
                    self::visit(31, 3100, '2026-01-10'),
                    // 32: two visits 89 days apart, none the year before.
                    self::visit(32, 3201, '2026-04-04'),
                    self::visit(32, 3200, '2026-01-05'),
                    // 33: one visit only.
                    self::visit(33, 3300, '2026-06-01'),
                ],
                [],
                [
                    ['pid' => '31', 'encounter_date' => '2025-10-12 09:00:00'],
                ],
                [
                    ['pid' => '30', 'date' => '2026-05-02 10:00:00', 'item' => 'act_rafunc', 'complete' => 'YES', 'result' => 'HAQ-II 0.5'],
                    ['pid' => '31', 'date' => '2026-01-10 10:00:00', 'item' => 'act_rafunc', 'complete' => 'NO', 'result' => ''],
                    ['pid' => '32', 'date' => '2026-01-05 10:00:00', 'item' => 'act_rafunc', 'complete' => 'YES', 'result' => ''],
                ],
            );
            $report = (new Measure178())->build(new MipsPeriod(2026), $data);
            [$a, $b, $c, $d] = $report->patients;

            $this->assertTrue($a->inDenominator);
            $this->assertSame('Met (act_rafunc)', $a->statuses['numerator']->label);
            $this->assertSame(['2026-02-01', '2026-05-02'], $a->columns['RA Visits 2025-2026']);
            $this->assertSame(['2026-05-02 complete: YES, result: HAQ-II 0.5'], $a->columns['Functional Status Entries 2026']);
            $this->assertSame([], $a->flags);

            $this->assertTrue($b->inDenominator, 'A visit the year before counts toward the 90 days');
            $this->assertSame('Not Met (no completed entry)', $b->statuses['numerator']->label);
            $this->assertSame([RheumatoidArthritisMeasure::NOT_COMPLETE], $b->flags);

            $this->assertFalse($c->inDenominator, '89 days is not enough');
            $this->assertSame([RheumatoidArthritisMeasure::NO_SEPARATED_VISIT], $c->flags);
            $this->assertFalse($d->inDenominator);

            $criterion = $report->criteria[0];
            $this->assertSame(2, $report->denominatorCount());
            $this->assertSame(100.0, $report->completeness($criterion));
            $this->assertSame(50.0, $report->performanceRate($criterion));
            $this->assertArrayNotHasKey(MipsStatus::NOT_REPORTED, $report->counts($criterion));

            $this->assertSame('act_glucocorticoid', (new Measure180())->ruleItem());
            $this->assertSame('Numerator (Glucocorticoid Management)', (new Measure180())->criterion()->label);
        }

        #[Test]
        public function measure039DxaAndExclusions(): void
        {
            $data = new MipsData(
                [self::patient(40, 'Aos'), self::patient(41, 'Bos'), self::patient(42, 'Cos'), self::patient(43, 'Dos'), self::patient(44, 'Eos')],
                [
                    self::visit(40, 4000, '2026-03-01'),
                    self::visit(41, 4100, '2026-04-01'),
                    self::visit(42, 4200, '2026-05-01'),
                    self::visit(43, 4300, '2026-06-01'),
                    self::visit(44, 4400, '2026-07-01'),
                ],
                [
                    // 42: osteoporosis on the visit, dotless; 43: hospice; 44: osteoporosis on another (non-qualifying) visit.
                    self::code(42, 4200, 'M810', '2026-05-01'),
                    self::code(43, 4399, 'G9690', '2026-08-01'),
                    self::code(44, 4499, 'M81.0', '2026-08-01'),
                    // 40 is Met, so its osteoporosis diagnosis doesn't take it out.
                    self::code(40, 4000, 'M81.0', '2026-03-01'),
                ],
                [],
                [
                    ['pid' => '41', 'date' => '2026-04-01 10:00:00', 'item' => 'act_osteo', 'complete' => 'YES', 'result' => 'T-score -1.2'],
                    ['pid' => '44', 'date' => '2026-07-01 10:00:00', 'item' => 'act_osteo', 'complete' => 'NO', 'result' => ''],
                ],
                [
                    ['pid' => '40', 'control_id' => '12345BDENSE', 'date_transmitted' => '2019-06-01 08:00:00'],
                ],
            );
            $report = (new Measure039())->build(new MipsPeriod(2026), $data);
            [$a, $b, $c, $d, $e] = $report->patients;

            $this->assertSame('Met (G8399)', $a->statuses['numerator']->label, 'A DXA ordered years ago counts: ever performed');
            $this->assertSame(['12345BDENSE 2019-06-01'], $a->columns['DXA Orders (BDENSE)']);
            $this->assertTrue($a->inDenominator);
            $this->assertSame([Measure039::OSTEOPOROSIS], $a->flags);

            $this->assertSame('Met (G8399)', $b->statuses['numerator']->label, 'A completed act_osteo entry in the year');
            $this->assertTrue($b->inDenominator);

            $this->assertFalse($c->inDenominator);
            $this->assertSame('Not Met (G8400)', $c->statuses['numerator']->label);
            $this->assertSame([Measure039::OSTEOPOROSIS], $c->flags);

            $this->assertFalse($d->inDenominator);
            $this->assertSame([Measure039::HOSPICE], $d->flags);

            $this->assertTrue($e->inDenominator, 'Osteoporosis only on a visit that isn\'t a qualifying one');
            $this->assertSame('Not Met (G8400)', $e->statuses['numerator']->label);
            $this->assertSame([Measure039::NOT_COMPLETE], $e->flags);

            $criterion = $report->criteria[0];
            $this->assertSame(3, $report->denominatorCount());
            $this->assertSame(100.0, $report->completeness($criterion));
            $this->assertSame(66.67, $report->performanceRate($criterion));
            $this->assertCount(316, Measure039::OSTEOPOROSIS_DX);
        }

        #[Test]
        public function rheumatoidArthritisDiagnoses(): void
        {
            $this->assertCount(310, RheumatoidArthritisMeasure::DX_CODES);
            $this->assertSame(RheumatoidArthritisMeasure::DX_CODES, array_values(array_unique(RheumatoidArthritisMeasure::DX_CODES)));
            foreach (['M05.A', 'M05.79', 'M06.9'] as $code) {
                $this->assertContains($code, RheumatoidArthritisMeasure::DX_CODES);
            }
            // Adult-onset Still's disease and inflammatory polyarthropathy aren't RA for these measures.
            $this->assertSame([], array_values(array_filter(
                RheumatoidArthritisMeasure::DX_CODES,
                static fn(string $code): bool => str_starts_with($code, 'M06.1') || str_starts_with($code, 'M06.4')
            )));
            $this->assertStringContainsString("REPLACE(d.code, '.', '') IN ('M05A',", RheumatoidArthritisMeasure::dxCondition('d'));
        }

        /**
         * @param list<MipsMeasure> $measures
         * @return list<string>
         */
        private static function ids(array $measures): array
        {
            return array_map(static fn(MipsMeasure $m): string => $m->id(), $measures);
        }

        #[Test]
        public function measuresAreFoundByNumber(): void
        {
            $this->assertSame(['039', '176', '178', '180', '410', '509'], self::ids(MipsMeasures::all()));
            $this->assertSame('410', MipsMeasures::find('410')->id());
            $this->assertSame('039', MipsMeasures::find('nope')->id());

            $this->assertSame(['176', '410', '509'], self::ids(MipsMeasures::enabled('176,410,509')));
            $this->assertSame(['178', '180'], self::ids(MipsMeasures::enabled(' 180, 178 ')));
            $this->assertSame(['039', '176', '178', '180', '410', '509'], self::ids(MipsMeasures::enabled('')));
            $this->assertSame(['039', '176', '178', '180', '410', '509'], self::ids(MipsMeasures::enabled('999')));
            $this->assertSame(['039', '178', '180'], self::ids(MipsMeasures::enabled('039,178,180')));

            $rheum = MipsMeasures::enabled('178,180');
            $this->assertSame('178', MipsMeasures::find('410', $rheum)->id(), 'A measure the site does not show falls back to its first');
            $this->assertSame('180', MipsMeasures::find('180', $rheum)->id());
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
