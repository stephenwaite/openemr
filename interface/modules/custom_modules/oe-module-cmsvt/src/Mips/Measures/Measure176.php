<?php

/**
 * MIPS 176: Tuberculosis Screening Prior to First Course of Biologic and/or Immune
 * Response Modifier Therapy (2026 spec, v10.0).
 *
 * Lists patients 18 or older with a qualifying visit in the year whose visit reason
 * names a Table 1 therapy, or whose fee sheet has G2182, and who have psoriasis (L40)
 * billed in the year (the practice prescribes biologics only for psoriasis). Patients
 * with G2182 in the year are the denominator.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Margaret Haywood <openemr@cmsvt.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Mips\Measures;

use Cmsvt\OpenEMR\Modules\Customizations\Mips\MipsCodeEntry;
use Cmsvt\OpenEMR\Modules\Customizations\Mips\MipsCriterion;
use Cmsvt\OpenEMR\Modules\Customizations\Mips\MipsData;
use Cmsvt\OpenEMR\Modules\Customizations\Mips\MipsEncounter;
use Cmsvt\OpenEMR\Modules\Customizations\Mips\MipsMeasure;
use Cmsvt\OpenEMR\Modules\Customizations\Mips\MipsPatient;
use Cmsvt\OpenEMR\Modules\Customizations\Mips\MipsPeriod;
use Cmsvt\OpenEMR\Modules\Customizations\Mips\MipsReport;
use Cmsvt\OpenEMR\Modules\Customizations\Mips\MipsRepository;
use Cmsvt\OpenEMR\Modules\Customizations\Mips\MipsRole;
use Cmsvt\OpenEMR\Modules\Customizations\Mips\MipsRows;
use Cmsvt\OpenEMR\Modules\Customizations\Mips\MipsStatus;

final readonly class Measure176 implements MipsMeasure
{
    /** No biologic in the 15 months before the new start. */
    public const PRIOR_THERAPY_MONTHS = 15;
    /** TB test within 12 months before the new start. */
    public const TB_MONTHS = 12;
    public const MIN_AGE = 18;

    public const VISIT_CODES = [
        '98000', '98001', '98002', '98003', '98004', '98005', '98006', '98007', '98008',
        '98009', '98010', '98011', '98012', '98013', '98014', '98015', '98016',
        '99202', '99203', '99204', '99205', '99212', '99213', '99214', '99215',
        '99341', '99342', '99344', '99345', '99347', '99348', '99349', '99350',
        '99424', '99426', 'G0402', 'G0468',
    ];

    public const DENOMINATOR_CODE = 'G2182';

    /** TB test CPTs, reference only (not part of the spec's coding). */
    public const TB_TEST_CODES = ['86480', '86481', '86580'];

    /**
     * Table 1 therapies (2026), generic and brand, matched anywhere in the visit
     * reason (so biosimilar suffixes match too). Margaret's list also had "modifier",
     * which matches "modifier 25" in billing notes; it is left out.
     */
    public const THERAPY_TERMS = [
        'abatacept', 'adalimumab', 'anakinra', 'baricitinib', 'brodalumab', 'canakinumab',
        'certolizumab', 'etanercept', 'golimumab', 'guselkumab', 'infliximab', 'ixekizumab',
        'risankizumab', 'rituximab', 'sarilumab', 'secukinumab', 'tildrakizumab', 'tocilizumab',
        'tofacitinib', 'upadacitinib', 'ustekinumab',
        'orencia', 'humira', 'idacio', 'yuflyma', 'hyrimoz', 'cyltezo', 'abrilada', 'yusimry',
        'amjevita', 'hadlima', 'simlandi', 'hulio', 'kineret', 'olumiant', 'siliq', 'ilaris',
        'cimzia', 'enbrel', 'simponi', 'tremfya', 'remicade', 'renflexis', 'avsola', 'inflectra',
        'taltz', 'skyrizi', 'rituxan', 'kevzara', 'cosentyx', 'ilumya', 'actemra', 'tyenne',
        'tofidence', 'xeljanz', 'rinvoq', 'stelara', 'pyzchiva', 'selarsdi', 'wezlana',
        'biologic',
    ];

    private const PSORIASIS_DX = "b.code_type = 'ICD10' AND b.code LIKE 'L40%'";

    public const PRIOR_THERAPY = 'Possible prior therapy in the 15 months before the start';
    public const NO_G2182 = 'G2182 not entered';

    public function id(): string
    {
        return '176';
    }

    public function title(): string
    {
        return 'Tuberculosis Screening Prior to First Course of Biologic and/or Immune Response Modifier Therapy';
    }

    public function denominatorLabel(): string
    {
        return 'G2182 entered in the year';
    }

    public function diagnosisLabel(): string
    {
        return 'Psoriasis Diagnosis History';
    }

    public function notes(): array
    {
        return [
            'Lists patients 18 or older with a qualifying visit whose visit reason names a biologic / immune response modifier (Table 1) or whose fee sheet has G2182, and who have psoriasis (L40) billed in the year. Patients with G2182 in the year are the denominator.',
            'The spec counts only a first course: no such therapy in the 15 months before the new start. "Prior Therapy" lists visits in that window whose reason names a therapy, and any earlier G2182; check the chart before entering G2182.',
            'TB test CPTs are for reference. IGRA and TST results usually arrive as lab results rather than on the fee sheet, so a blank column does not mean no test was done.',
        ];
    }

    public function codeLegend(): array
    {
        return [
            ['G2182', 'Denominator: first-time biologic / immune response modifier therapy'],
            ['M1003', 'Met: TB screening performed and results interpreted within 12 months prior'],
            ['M1004', 'Exception: medical reason for not screening / interpreting results'],
            ['M1005', 'Not Met: TB screening not performed or results not interpreted'],
            ['86480', 'IGRA (QuantiFERON), reference only'],
            ['86481', 'IGRA (T-SPOT.TB), reference only'],
            ['86580', 'TST / PPD, reference only'],
            ['Therapy names searched', implode(', ', self::THERAPY_TERMS)],
        ];
    }

    public function criterion(): MipsCriterion
    {
        return new MipsCriterion('numerator', 'Numerator (TB Screening)', [
            MipsStatus::forCode('M1003', 'Met', MipsRole::Met),
            MipsStatus::forCode('M1004', 'Exception', MipsRole::Exception),
            MipsStatus::forCode('M1005', 'Not Met', MipsRole::NotMet),
        ]);
    }

    public function lookbackStart(MipsPeriod $period): string
    {
        return MipsPeriod::shift($period->start(), -self::PRIOR_THERAPY_MONTHS);
    }

    public function fetch(MipsPeriod $period, MipsRepository $repository): MipsData
    {
        [$therapy, $therapyBinds] = MipsRepository::reasonMatches(self::THERAPY_TERMS);
        $encounters = $repository->qualifyingEncounters(
            $period,
            self::VISIT_CODES,
            "TIMESTAMPDIFF(YEAR, p.DOB, fe.date) >= ?
             AND ($therapy OR EXISTS (SELECT 1 FROM billing AS g
                                       WHERE g.encounter = fe.encounter AND g.pid = fe.pid
                                         AND g.code = ? AND g.activity = 1))
             AND EXISTS (SELECT 1
                           FROM billing AS b
                           JOIN form_encounter AS fe_dx ON fe_dx.encounter = b.encounter AND fe_dx.pid = b.pid
                          WHERE b.pid = p.pid AND b.activity = 1 AND " . self::PSORIASIS_DX . "
                            AND fe_dx.date BETWEEN ? AND ?)",
            [self::MIN_AGE, ...$therapyBinds, self::DENOMINATOR_CODE, $period->startTs(), $period->endTs()]
        );
        $pids = MipsRows::pids($encounters);
        $lookback = $this->lookbackStart($period);
        return new MipsData(
            MipsRows::patientsFromEncounters(
                $encounters,
                $repository->diagnosisHistory($pids, self::PSORIASIS_DX, $period->start(), $period->end())
            ),
            $encounters,
            $repository->trackedCodes(
                $pids,
                [self::DENOMINATOR_CODE, ...$this->criterion()->codes(), ...self::TB_TEST_CODES],
                $lookback,
                $period->end()
            ),
            $repository->reasonMentions($pids, self::THERAPY_TERMS, $lookback, $period->end()),
        );
    }

    public function build(MipsPeriod $period, MipsData $data): MipsReport
    {
        $index = MipsRows::index($data);
        $criterion = $this->criterion();
        $therapyStartLabel = 'First ' . $period->year . ' Therapy Encounter';
        $patients = [];
        foreach ($data->patients as $row) {
            $pid = MipsRows::int($row, 'pid');
            $codes = $index->codes($pid);
            $encounters = $index->encounters($pid);
            $g2182 = MipsCodeEntry::filter($codes, [self::DENOMINATOR_CODE], $period->start(), $period->end());

            // First-course start: the earliest qualifying therapy visit or G2182 in the year.
            $days = array_map(static fn(MipsEncounter $e): string => $e->day(), $encounters);
            if ($g2182 !== []) {
                $days[] = $g2182[0]->day;
            }
            $start = $days === [] ? $period->start() : min($days);
            $dayBefore = MipsPeriod::shift($start, 0, -1);
            $priorFrom = MipsPeriod::shift($start, -self::PRIOR_THERAPY_MONTHS);

            $prior = [];
            foreach ($index->mentions($pid) as $day) {
                if ($day >= $priorFrom && $day <= $dayBefore) {
                    $prior[] = 'Reason ' . $day;
                }
            }
            foreach (MipsCodeEntry::filter($codes, [self::DENOMINATOR_CODE], $priorFrom, $dayBefore) as $entry) {
                $prior[] = $entry->label();
            }

            $numerator = MipsCodeEntry::filter($codes, $criterion->codes(), $period->start(), $period->end());
            $inDenominator = $g2182 !== [];
            $flags = [];
            if (!$inDenominator) {
                $flags[] = self::NO_G2182;
            }
            if ($prior !== []) {
                $flags[] = self::PRIOR_THERAPY;
            }
            $patients[] = MipsPatient::fromRow(
                $row,
                $encounters,
                $inDenominator,
                [$criterion->key => $criterion->status($numerator)],
                [
                    $therapyStartLabel => [$start],
                    'Prior Therapy (15 mo)' => $prior,
                    'G2182' => MipsCodeEntry::labels($g2182),
                    'TB Test CPTs' => MipsCodeEntry::labels(
                        MipsCodeEntry::filter($codes, self::TB_TEST_CODES, MipsPeriod::shift($start, -self::TB_MONTHS), $period->end())
                    ),
                    'Numerator Codes' => MipsCodeEntry::labels($numerator),
                ],
                $flags,
            );
        }
        return new MipsReport(
            $this,
            $period,
            [$criterion],
            $patients,
            [$therapyStartLabel, 'Prior Therapy (15 mo)', 'G2182', 'TB Test CPTs', 'Numerator Codes']
        );
    }
}
