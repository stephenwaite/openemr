<?php

/**
 * MIPS 509: Melanoma: Tracking and Evaluation of Recurrence (2026 spec, v10.0).
 *
 * Lists living patients with melanoma (C43) or melanoma in situ (D03) billed in the
 * five years before the performance year through its end; those with a qualifying
 * visit in the year are the denominator. M1386 isn't tracked; its reference coding
 * (the excision CPTs) is used in its place.
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
use Cmsvt\OpenEMR\Modules\Customizations\Mips\MipsMeasure;
use Cmsvt\OpenEMR\Modules\Customizations\Mips\MipsPatient;
use Cmsvt\OpenEMR\Modules\Customizations\Mips\MipsPeriod;
use Cmsvt\OpenEMR\Modules\Customizations\Mips\MipsReport;
use Cmsvt\OpenEMR\Modules\Customizations\Mips\MipsRepository;
use Cmsvt\OpenEMR\Modules\Customizations\Mips\MipsRole;
use Cmsvt\OpenEMR\Modules\Customizations\Mips\MipsRows;
use Cmsvt\OpenEMR\Modules\Customizations\Mips\MipsStatus;

final readonly class Measure509 implements MipsMeasure
{
    public const LOOKBACK_YEARS = 5;

    public const VISIT_CODES = [
        '99202', '99203', '99204', '99205', '99211', '99212', '99213', '99214', '99215',
        '99242', '99243', '99244', '99245',
    ];

    /** M1386 reference coding (excisional surgery), searched across the lookback. */
    public const EXCISION_CODES = [
        '11600', '11601', '11602', '11603', '11604', '11606',
        '11620', '11621', '11622', '11623', '11624', '11626',
        '11640', '11641', '11642', '11643', '11644', '11646',
        '17311', '17312', '17313', '17314', '17315',
    ];

    private const MELANOMA_DX = "b.code_type = 'ICD10' AND (b.code LIKE 'C43%' OR b.code LIKE 'D03%')";
    private const HISTORY_DX = "b.code_type = 'ICD10' AND (b.code LIKE 'C43%' OR b.code LIKE 'D03%' OR b.code LIKE 'Z85.820%')";

    public const NO_EXCISION = 'No excision CPT in the lookback';

    public function id(): string
    {
        return '509';
    }

    public function title(): string
    {
        return 'Melanoma: Tracking and Evaluation of Recurrence';
    }

    public function denominatorLabel(): string
    {
        return 'qualifying visit in the year';
    }

    public function diagnosisLabel(): string
    {
        return 'Melanoma Diagnosis History';
    }

    public function notes(): array
    {
        return [
            'Lists every living patient with melanoma (C43) or melanoma in situ (D03) billed in the five years before the performance year through its end. Only those with a qualifying visit in the year are in the denominator and the rates.',
            'Quality data codes count only in the performance year; excision CPTs are searched across the lookback. The diagnosis history also shows Z85.820 (personal history of melanoma).',
        ];
    }

    public function codeLegend(): array
    {
        return [
            ['M1388', 'Criteria 1 Met: exam for recurrence performed'],
            ['M1392', 'Criteria 1 & 2 Exception: refusal / lost to follow-up'],
            ['M1390', 'Criteria 1 Not Met: no documented exam'],
            ['M1391', 'Criteria 2 (inverse) Met: recurrent melanoma diagnosed'],
            ['M1393', 'Criteria 2 (inverse) Not Met: no recurrence'],
            ['Excision CPTs', 'M1386 reference coding, used in place of M1386 (' . self::LOOKBACK_YEARS . '-year lookback): ' . implode(', ', self::EXCISION_CODES)],
        ];
    }

    /**
     * @return list<MipsCriterion>
     */
    public function criteria(): array
    {
        return [
            new MipsCriterion('crit1', 'Criteria 1 (Exam for Recurrence)', [
                MipsStatus::forCode('M1388', 'Met', MipsRole::Met),
                MipsStatus::forCode('M1392', 'Exception', MipsRole::Exception),
                MipsStatus::forCode('M1390', 'Not Met', MipsRole::NotMet),
            ]),
            new MipsCriterion('crit2', 'Criteria 2 (Recurrence, inverse)', [
                MipsStatus::forCode('M1391', 'Recurrence', MipsRole::Met),
                MipsStatus::forCode('M1393', 'No Recurrence', MipsRole::NotMet),
                MipsStatus::forCode('M1392', 'Exception', MipsRole::Exception),
            ], inverse: true),
        ];
    }

    public function lookbackStart(MipsPeriod $period): string
    {
        return ($period->year - self::LOOKBACK_YEARS) . '-01-01';
    }

    public function fetch(MipsPeriod $period, MipsRepository $repository): MipsData
    {
        $from = $this->lookbackStart($period) . ' 00:00:00';
        $patients = $repository->records(
            "SELECT p.pid, p.lname, p.fname, p.mname, p.sex, p.DOB
               FROM patient_data AS p
              WHERE " . MipsRepository::ALIVE . "
                AND EXISTS (SELECT 1
                              FROM billing AS b
                              JOIN form_encounter AS fe ON fe.encounter = b.encounter AND fe.pid = b.pid
                             WHERE b.pid = p.pid AND b.activity = 1 AND fe.date BETWEEN ? AND ?
                               AND " . self::MELANOMA_DX . ")
           ORDER BY p.lname, p.fname, p.pid",
            [$from, $period->endTs()]
        );
        $pids = MipsRows::pids($patients);
        if ($pids === []) {
            return new MipsData([], [], []);
        }
        $history = $repository->diagnosisHistory($pids, self::HISTORY_DX, $this->lookbackStart($period), $period->end());
        foreach ($patients as $i => $row) {
            $patients[$i]['dx_history'] = $history[MipsRows::int($row, 'pid')] ?? '';
        }
        $criteria = $this->criteria();
        return new MipsData(
            $patients,
            $repository->qualifyingEncounters(
                $period,
                self::VISIT_CODES,
                'fe.pid IN (' . MipsRepository::placeholders($pids) . ')',
                $pids
            ),
            $repository->trackedCodes(
                $pids,
                array_values(array_unique([...$criteria[0]->codes(), ...$criteria[1]->codes(), ...self::EXCISION_CODES])),
                $this->lookbackStart($period),
                $period->end()
            ),
        );
    }

    public function build(MipsPeriod $period, MipsData $data): MipsReport
    {
        $index = MipsRows::index($data);
        [$crit1, $crit2] = $this->criteria();
        $patients = [];
        foreach ($data->patients as $row) {
            $pid = MipsRows::int($row, 'pid');
            $codes = $index->codes($pid);
            $inYear = MipsCodeEntry::filter($codes, [...$crit1->codes(), ...$crit2->codes()], $period->start(), $period->end());
            $excisions = MipsCodeEntry::filter($codes, self::EXCISION_CODES, $this->lookbackStart($period), $period->end());
            $encounters = $index->encounters($pid);
            $inDenominator = $encounters !== [];
            $patients[] = MipsPatient::fromRow(
                $row,
                $encounters,
                $inDenominator,
                ['crit1' => $crit1->status($inYear), 'crit2' => $crit2->status($inYear)],
                [
                    'Excision CPTs' => MipsCodeEntry::labels($excisions),
                    'Criteria 1 Codes' => MipsCodeEntry::labels(MipsCodeEntry::filter($inYear, $crit1->codes(), $period->start(), $period->end())),
                    'Criteria 2 Codes' => MipsCodeEntry::labels(MipsCodeEntry::filter($inYear, $crit2->codes(), $period->start(), $period->end())),
                ],
                $inDenominator && $excisions === [] ? [self::NO_EXCISION] : [],
            );
        }
        return new MipsReport($this, $period, [$crit1, $crit2], $patients, ['Excision CPTs', 'Criteria 1 Codes', 'Criteria 2 Codes']);
    }
}
