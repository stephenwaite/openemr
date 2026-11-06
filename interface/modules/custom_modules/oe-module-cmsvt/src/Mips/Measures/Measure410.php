<?php

/**
 * MIPS 410: Psoriasis: Clinical Response to Systemic Medications (2026 spec, v10.0).
 *
 * Lists patients of any age with psoriasis vulgaris (L40.0) and no other psoriasis
 * code (L40.1-L40.9) in the performance year, with a qualifying visit in the year whose reason names a
 * systemic medication or whose fee sheet has G9764. Patients with G9764 in the year
 * are the denominator. The most recent quality data code counts, per the spec.
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

final readonly class Measure410 implements MipsMeasure
{
    /** Months before the year searched for when systemic treatment started (reference only). */
    public const MED_LOOKBACK_MONTHS = 12;
    /** G9765 covers patients treated for less than 6 consecutive months. */
    public const MIN_TREATMENT_MONTHS = 6;

    public const VISIT_CODES = [
        '98000', '98001', '98002', '98003', '98004', '98005', '98006', '98007', '98008',
        '98009', '98010', '98011', '98012', '98013', '98014', '98015', '98016',
        '99202', '99203', '99204', '99205', '99212', '99213', '99214', '99215',
        '99242', '99243', '99244', '99245',
        '99341', '99342', '99344', '99345', '99347', '99348', '99349', '99350',
        '99424', '99426', 'G0438', 'G0439',
    ];

    public const DENOMINATOR_CODE = 'G9764';

    /** 2026 reference medications, generic and brand, matched anywhere in the visit reason. */
    public const MEDICATION_TERMS = [
        'abatacept', 'acitretin', 'adalimumab', 'apremilast', 'bimekizumab', 'brodalumab',
        'certolizumab', 'cyclosporine', 'deucravacitinib', 'etanercept', 'golimumab',
        'guselkumab', 'infliximab', 'ixekizumab', 'methotrexate', 'risankizumab',
        'secukinumab', 'spesolimab', 'tildrakizumab', 'tofacitinib', 'upadacitinib',
        'ustekinumab',
        'abrilada', 'amjevita', 'avsola', 'bimzelx', 'cimzia', 'cosentyx', 'cyltezo',
        'enbrel', 'erelzi', 'gengraf', 'hadlima', 'hulio', 'humira', 'hyrimoz', 'idacio',
        'ilumya', 'inflectra', 'jylamvo', 'neoral', 'orencia', 'otezla', 'otrexup',
        'pyzchiva', 'rasuvo', 'reditrex', 'remicade', 'renflexis', 'rheumatrex', 'rinvoq',
        'sandimmune', 'selarsdi', 'siliq', 'simlandi', 'simponi', 'skyrizi', 'soriatane',
        'sotyktu', 'spevigo', 'stelara', 'taltz', 'tremfya', 'trexall', 'wezlana',
        'xatmep', 'xeljanz', 'yuflyma', 'yusimry',
    ];

    private const VULGARIS_DX = "b.code_type = 'ICD10' AND (b.code LIKE 'L40.0%' OR b.code = 'L400')";
    /**
     * Any other psoriasis code billed in the performance year excludes the patient
     * (Margaret, 2026-10-15: no other concurrent psoriasis diagnosis; earlier years don't count).
     */
    private const OTHER_PSORIASIS_DX = "o.code_type = 'ICD10' AND o.code LIKE 'L40%' AND o.code NOT LIKE 'L40.0%' AND o.code <> 'L400'";

    public const UNDER_SIX_MONTHS = 'Under 6 months on systemic medication at last visit';
    public const NO_G9764 = 'G9764 not entered';

    public function id(): string
    {
        return '410';
    }

    public function title(): string
    {
        return 'Psoriasis: Clinical Response to Systemic Medications';
    }

    public function denominatorLabel(): string
    {
        return 'G9764 entered in the year';
    }

    public function diagnosisLabel(): string
    {
        return 'Psoriasis Vulgaris Diagnosis History';
    }

    public function notes(): array
    {
        return [
            'Lists patients of any age with psoriasis vulgaris (L40.0) and no other psoriasis code (L40.1-L40.9) billed in the performance year, with a qualifying visit whose reason names a systemic medication or whose fee sheet has G9764. Patients with G9764 in the year are the denominator.',
            'The spec counts only systemic medications prescribed by the provider being evaluated; confirm the prescriber before entering G9764.',
            'The most recent quality data code counts, as the spec directs. A patient whose most recent visit has more than one numerator code shows as conflicting so it can be corrected.',
            '"Systemic Med Since" is the earliest visit naming a systemic medication, or G9764, since ' . self::MED_LOOKBACK_MONTHS . ' months before the year. Patients under 6 months at their last visit may qualify for the exception (G9765) if they did not meet a benchmark; check the chart for the start date and any gaps.',
        ];
    }

    public function codeLegend(): array
    {
        return [
            ['G9764', 'Denominator: patient treated with a systemic medication for psoriasis vulgaris'],
            ['G9649', 'Met: assessment tool documented meeting a benchmark (PGA <= 2, BSA < 3%, PASI < 3, or DLQI <= 5)'],
            ['G9765', 'Exception: declined change in medication, alternatives unavailable, contraindications, or treated less than 6 consecutive months'],
            ['G9651', 'Not Met: assessment tool documented not meeting any benchmark, or not documented'],
            ['Medications searched', implode(', ', self::MEDICATION_TERMS)],
        ];
    }

    public function criterion(): MipsCriterion
    {
        return new MipsCriterion('numerator', 'Numerator (Clinical Response)', [
            MipsStatus::forCode('G9649', 'Met', MipsRole::Met),
            MipsStatus::forCode('G9765', 'Exception', MipsRole::Exception),
            MipsStatus::forCode('G9651', 'Not Met', MipsRole::NotMet),
        ], mostRecent: true);
    }

    public function lookbackStart(MipsPeriod $period): string
    {
        return MipsPeriod::shift($period->start(), -self::MED_LOOKBACK_MONTHS);
    }

    public function fetch(MipsPeriod $period, MipsRepository $repository): MipsData
    {
        [$medication, $medicationBinds] = MipsRepository::reasonMatches(self::MEDICATION_TERMS);
        $encounters = $repository->qualifyingEncounters(
            $period,
            self::VISIT_CODES,
            "EXISTS (SELECT 1 FROM billing AS b WHERE b.pid = p.pid AND b.activity = 1 AND " . self::VULGARIS_DX . ")
             AND NOT EXISTS (SELECT 1
                               FROM billing AS o
                               JOIN form_encounter AS fe_o ON fe_o.encounter = o.encounter AND fe_o.pid = o.pid
                              WHERE o.pid = p.pid AND o.activity = 1 AND " . self::OTHER_PSORIASIS_DX . "
                                AND fe_o.date BETWEEN ? AND ?)
             AND ($medication OR EXISTS (SELECT 1 FROM billing AS g
                                          WHERE g.encounter = fe.encounter AND g.pid = fe.pid
                                            AND g.code = ? AND g.activity = 1))",
            [$period->startTs(), $period->endTs(), ...$medicationBinds, self::DENOMINATOR_CODE]
        );
        $pids = MipsRows::pids($encounters);
        $lookback = $this->lookbackStart($period);
        return new MipsData(
            MipsRows::patientsFromEncounters(
                $encounters,
                $repository->diagnosisHistory($pids, self::VULGARIS_DX, $lookback, $period->end())
            ),
            $encounters,
            $repository->trackedCodes($pids, [self::DENOMINATOR_CODE, ...$this->criterion()->codes()], $lookback, $period->end()),
            $repository->reasonMentions($pids, self::MEDICATION_TERMS, $lookback, $period->end()),
        );
    }

    public function build(MipsPeriod $period, MipsData $data): MipsReport
    {
        $index = MipsRows::index($data);
        $criterion = $this->criterion();
        $lastVisitLabel = 'Last ' . $period->year . ' Systemic Med Visit';
        $patients = [];
        foreach ($data->patients as $row) {
            $pid = MipsRows::int($row, 'pid');
            $codes = $index->codes($pid);
            $encounters = $index->encounters($pid);
            $g9764 = MipsCodeEntry::filter($codes, [self::DENOMINATOR_CODE], $period->start(), $period->end());
            $numerator = MipsCodeEntry::filter($codes, $criterion->codes(), $period->start(), $period->end());

            $days = array_map(static fn(MipsEncounter $e): string => $e->day(), $encounters);
            $firstVisit = $days === [] ? $period->start() : min($days);
            $lastVisit = $days === [] ? $period->start() : max($days);
            $starts = $index->mentions($pid);
            $anyG9764 = MipsCodeEntry::filter($codes, [self::DENOMINATOR_CODE], $this->lookbackStart($period), $period->end());
            if ($anyG9764 !== []) {
                $starts[] = $anyG9764[0]->day;
            }
            $since = $starts === [] ? $firstVisit : min($starts);
            $underSix = $lastVisit < MipsPeriod::shift($since, self::MIN_TREATMENT_MONTHS);

            $inDenominator = $g9764 !== [];
            $flags = [];
            if (!$inDenominator) {
                $flags[] = self::NO_G9764;
            }
            if ($underSix) {
                $flags[] = self::UNDER_SIX_MONTHS;
            }
            $patients[] = MipsPatient::fromRow(
                $row,
                $encounters,
                $inDenominator,
                [$criterion->key => $criterion->status($numerator)],
                [
                    $lastVisitLabel => [$lastVisit],
                    'Systemic Med Since' => [$since],
                    'G9764' => MipsCodeEntry::labels($g9764),
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
            [$lastVisitLabel, 'Systemic Med Since', 'G9764', 'Numerator Codes']
        );
    }
}
