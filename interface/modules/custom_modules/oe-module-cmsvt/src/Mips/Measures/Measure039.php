<?php

/**
 * MIPS 039: Screening for Osteoporosis for Women Aged 65-85 Years of Age (2026 spec, v10.0).
 *
 * Lists women 65-85 on the date of a qualifying visit in the performance year
 * (telehealth included). Met (G8399) when a DXA order is on file (control ID
 * containing BDENSE, transmitted by the end of the year or not yet transmitted:
 * the spec's "ever performed") or an act_osteo clinical rule entry is marked
 * complete in the year; otherwise Not Met (G8400). Excluded from the denominator:
 * hospice (G9690) billed in the year, or an osteoporosis diagnosis on a qualifying
 * visit (M1153), unless the patient is Met, the more advantageous result (as in
 * Margaret's report).
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

final readonly class Measure039 implements MipsMeasure
{
    public const MIN_AGE = 65;
    public const MAX_AGE = 85;

    /** 98000-98016 are telehealth visits, which count. */
    public const VISIT_CODES = [
        '98000', '98001', '98002', '98003', '98004', '98005', '98006', '98007', '98008',
        '98009', '98010', '98011', '98012', '98013', '98014', '98015', '98016',
        '99202', '99203', '99204', '99205', '99212', '99213', '99214', '99215',
    ];

    public const HOSPICE_CODE = 'G9690';
    /** Matched anywhere in a procedure order's control ID. */
    public const DXA_CONTROL_ID = 'BDENSE';
    public const RULE_ITEM = 'act_osteo';

    /** Osteoporosis (M1153 reference coding): M80 with fracture, M81.0, M81.6, M81.8. */
    public const OSTEOPOROSIS_DX = [
        'M80.00XA', 'M80.00XD', 'M80.00XG', 'M80.00XK', 'M80.00XP', 'M80.00XS', 'M80.011A', 'M80.011D', 'M80.011G',
        'M80.011K', 'M80.011P', 'M80.011S', 'M80.012A', 'M80.012D', 'M80.012G', 'M80.012K', 'M80.012P', 'M80.012S',
        'M80.019A', 'M80.019D', 'M80.019G', 'M80.019K', 'M80.019P', 'M80.019S', 'M80.021A', 'M80.021D', 'M80.021G',
        'M80.021K', 'M80.021P', 'M80.021S', 'M80.022A', 'M80.022D', 'M80.022G', 'M80.022K', 'M80.022P', 'M80.022S',
        'M80.029A', 'M80.029D', 'M80.029G', 'M80.029K', 'M80.029P', 'M80.029S', 'M80.031A', 'M80.031D', 'M80.031G',
        'M80.031K', 'M80.031P', 'M80.031S', 'M80.032A', 'M80.032D', 'M80.032G', 'M80.032K', 'M80.032P', 'M80.032S',
        'M80.039A', 'M80.039D', 'M80.039G', 'M80.039K', 'M80.039P', 'M80.039S', 'M80.041A', 'M80.041D', 'M80.041G',
        'M80.041K', 'M80.041P', 'M80.041S', 'M80.042A', 'M80.042D', 'M80.042G', 'M80.042K', 'M80.042P', 'M80.042S',
        'M80.049A', 'M80.049D', 'M80.049G', 'M80.049K', 'M80.049P', 'M80.049S', 'M80.051A', 'M80.051D', 'M80.051G',
        'M80.051K', 'M80.051P', 'M80.051S', 'M80.052A', 'M80.052D', 'M80.052G', 'M80.052K', 'M80.052P', 'M80.052S',
        'M80.059A', 'M80.059D', 'M80.059G', 'M80.059K', 'M80.059P', 'M80.059S', 'M80.061A', 'M80.061D', 'M80.061G',
        'M80.061K', 'M80.061P', 'M80.061S', 'M80.062A', 'M80.062D', 'M80.062G', 'M80.062K', 'M80.062P', 'M80.062S',
        'M80.069A', 'M80.069D', 'M80.069G', 'M80.069K', 'M80.069P', 'M80.069S', 'M80.071A', 'M80.071D', 'M80.071G',
        'M80.071K', 'M80.071P', 'M80.071S', 'M80.072A', 'M80.072D', 'M80.072G', 'M80.072K', 'M80.072P', 'M80.072S',
        'M80.079A', 'M80.079D', 'M80.079G', 'M80.079K', 'M80.079P', 'M80.079S', 'M80.08XA', 'M80.08XD', 'M80.08XG',
        'M80.08XK', 'M80.08XP', 'M80.08XS', 'M80.0AXA', 'M80.0B1A', 'M80.0B1D', 'M80.0B1G', 'M80.0B1K', 'M80.0B1P',
        'M80.0B1S', 'M80.0B2A', 'M80.0B2D', 'M80.0B2G', 'M80.0B2K', 'M80.0B2P', 'M80.0B2S', 'M80.0B9A', 'M80.0B9D',
        'M80.0B9G', 'M80.0B9K', 'M80.0B9P', 'M80.0B9S', 'M80.80XA', 'M80.80XD', 'M80.80XG', 'M80.80XK', 'M80.80XP',
        'M80.80XS', 'M80.811A', 'M80.811D', 'M80.811G', 'M80.811K', 'M80.811P', 'M80.811S', 'M80.812A', 'M80.812D',
        'M80.812G', 'M80.812K', 'M80.812P', 'M80.812S', 'M80.819A', 'M80.819D', 'M80.819G', 'M80.819K', 'M80.819P',
        'M80.819S', 'M80.821A', 'M80.821D', 'M80.821G', 'M80.821K', 'M80.821P', 'M80.821S', 'M80.822A', 'M80.822D',
        'M80.822G', 'M80.822K', 'M80.822P', 'M80.822S', 'M80.829A', 'M80.829D', 'M80.829G', 'M80.829K', 'M80.829P',
        'M80.829S', 'M80.831A', 'M80.831D', 'M80.831G', 'M80.831K', 'M80.831P', 'M80.831S', 'M80.832A', 'M80.832D',
        'M80.832G', 'M80.832K', 'M80.832P', 'M80.832S', 'M80.839A', 'M80.839D', 'M80.839G', 'M80.839K', 'M80.839P',
        'M80.839S', 'M80.841A', 'M80.841D', 'M80.841G', 'M80.841K', 'M80.841P', 'M80.841S', 'M80.842A', 'M80.842D',
        'M80.842G', 'M80.842K', 'M80.842P', 'M80.842S', 'M80.849A', 'M80.849D', 'M80.849G', 'M80.849K', 'M80.849P',
        'M80.849S', 'M80.851A', 'M80.851D', 'M80.851G', 'M80.851K', 'M80.851P', 'M80.851S', 'M80.852A', 'M80.852D',
        'M80.852G', 'M80.852K', 'M80.852P', 'M80.852S', 'M80.859A', 'M80.859D', 'M80.859G', 'M80.859K', 'M80.859P',
        'M80.859S', 'M80.861A', 'M80.861D', 'M80.861G', 'M80.861K', 'M80.861P', 'M80.861S', 'M80.862A', 'M80.862D',
        'M80.862G', 'M80.862K', 'M80.862P', 'M80.862S', 'M80.869A', 'M80.869D', 'M80.869G', 'M80.869K', 'M80.869P',
        'M80.869S', 'M80.871A', 'M80.871D', 'M80.871G', 'M80.871K', 'M80.871P', 'M80.871S', 'M80.872A', 'M80.872D',
        'M80.872G', 'M80.872K', 'M80.872P', 'M80.872S', 'M80.879A', 'M80.879D', 'M80.879G', 'M80.879K', 'M80.879P',
        'M80.879S', 'M80.88XA', 'M80.88XD', 'M80.88XG', 'M80.88XK', 'M80.88XP', 'M80.88XS', 'M80.8B1A', 'M80.8B1D',
        'M80.8B1G', 'M80.8B1K', 'M80.8B1P', 'M80.8B1S', 'M80.8B2A', 'M80.8B2D', 'M80.8B2G', 'M80.8B2K', 'M80.8B2P',
        'M80.8B2S', 'M80.8B9A', 'M80.8B9D', 'M80.8B9G', 'M80.8B9K', 'M80.8B9P', 'M80.8B9S', 'M81.0', 'M81.6',
        'M81.8',
    ];

    public const HOSPICE = 'Hospice (G9690) in the year';
    public const OSTEOPOROSIS = 'Osteoporosis diagnosis on a qualifying visit';
    public const NOT_COMPLETE = 'Osteo entry not marked complete';

    public function id(): string
    {
        return '039';
    }

    public function title(): string
    {
        return 'Screening for Osteoporosis for Women Aged 65-85 Years of Age';
    }

    public function denominatorLabel(): string
    {
        return 'not excluded, or Met';
    }

    public function diagnosisLabel(): string
    {
        return 'Osteoporosis Diagnosis History';
    }

    public function notes(): array
    {
        return [
            'Lists women ' . self::MIN_AGE . '-' . self::MAX_AGE . ' on the date of a qualifying visit in the performance year (telehealth included).',
            'Met (G8399): a procedure order whose control ID contains ' . self::DXA_CONTROL_ID . ', transmitted by the end of the year or not yet transmitted (a DXA ever performed), or a "' . self::RULE_ITEM . '" entry (Patient Reminders > rule data) marked complete in the year. Otherwise Not Met (G8400).',
            'Excluded from the denominator: hospice (G9690) billed in the year, or an osteoporosis diagnosis (M1153) on a qualifying visit. A patient who is Met stays in, as the more advantageous result.',
        ];
    }

    public function codeLegend(): array
    {
        return [
            ['G8399', 'Met: documented results of a central DXA ever performed'],
            ['G8400', 'Not Met: central DXA results not documented, reason not given'],
            ['G9690', 'Exclusion: hospice services any time during the measurement period'],
            ['M1153', 'Exclusion: osteoporosis diagnosis on the date of the visit (M80 with fracture, M81.0, M81.6, M81.8: ' . count(self::OSTEOPOROSIS_DX) . ' codes)'],
            ['Visit codes', implode(', ', self::VISIT_CODES)],
        ];
    }

    public function criterion(): MipsCriterion
    {
        return new MipsCriterion(
            'numerator',
            'Numerator (DXA)',
            [MipsStatus::forCode('G8399', 'Met', MipsRole::Met)],
            absent: new MipsStatus('Not Met (G8400)', MipsRole::NotMet, 'G8400'),
        );
    }

    public function fetch(MipsPeriod $period, MipsRepository $repository): MipsData
    {
        $encounters = $repository->qualifyingEncounters(
            $period,
            self::VISIT_CODES,
            "UPPER(p.sex) IN ('F', 'FEMALE')
             AND TIMESTAMPDIFF(YEAR, p.DOB, fe.date) BETWEEN " . self::MIN_AGE . ' AND ' . self::MAX_AGE,
            []
        );
        $pids = MipsRows::pids($encounters);
        $dxCodes = [...self::OSTEOPOROSIS_DX, ...array_map(static fn(string $c): string => str_replace('.', '', $c), self::OSTEOPOROSIS_DX)];
        return new MipsData(
            MipsRows::patientsFromEncounters(
                $encounters,
                $repository->diagnosisHistory($pids, self::dxCondition('b'), $period->start(), $period->end())
            ),
            $encounters,
            $repository->trackedCodes($pids, [self::HOSPICE_CODE, ...$dxCodes], $period->start(), $period->end()),
            [],
            $repository->ruleEntries($pids, [self::RULE_ITEM], $period->start(), $period->end()),
            $repository->procedureOrders($pids, self::DXA_CONTROL_ID, $period->end()),
        );
    }

    public function build(MipsPeriod $period, MipsData $data): MipsReport
    {
        $index = MipsRows::index($data);
        $criterion = $this->criterion();
        $ordersLabel = 'DXA Orders (' . self::DXA_CONTROL_ID . ')';
        $entriesLabel = 'Osteo Entries ' . $period->year;
        $entriesByPid = [];
        foreach ($data->ruleEntries as $row) {
            $entriesByPid[MipsRows::int($row, 'pid')][] = $row;
        }
        $ordersByPid = [];
        foreach ($data->orders as $row) {
            $ordersByPid[MipsRows::int($row, 'pid')][] = $row;
        }
        $osteoporosis = array_map(static fn(string $c): string => str_replace('.', '', $c), self::OSTEOPOROSIS_DX);

        $patients = [];
        foreach ($data->patients as $row) {
            $pid = MipsRows::int($row, 'pid');
            $encounters = $index->encounters($pid);
            $visits = array_map(static fn(MipsEncounter $e): int => $e->encounter, $encounters);

            $hospice = false;
            $osteoDx = false;
            foreach ($index->codes($pid) as $entry) {
                $hospice = $hospice || ($entry->code === self::HOSPICE_CODE && $period->contains($entry->day));
                $osteoDx = $osteoDx || (in_array($entry->encounter, $visits, true)
                    && in_array(str_replace('.', '', $entry->code), $osteoporosis, true));
            }

            $met = [];
            $orders = [];
            foreach ($ordersByPid[$pid] ?? [] as $order) {
                $day = substr(MipsRows::str($order, 'date_transmitted'), 0, 10);
                $orders[] = MipsRows::str($order, 'control_id') . ' ' . ($day === '' ? 'not transmitted' : $day);
                $met[] = new MipsCodeEntry('G8399', $day === '' ? $period->end() : $day, 0);
            }
            $labels = [];
            $incomplete = false;
            foreach ($entriesByPid[$pid] ?? [] as $entry) {
                $day = substr(MipsRows::str($entry, 'date'), 0, 10);
                $complete = strtoupper(trim(MipsRows::str($entry, 'complete')));
                $result = trim(MipsRows::str($entry, 'result'));
                $labels[] = $day . ' ' . ($complete === '' ? 'completion blank' : 'complete: ' . $complete)
                    . ($result === '' ? '' : ', result: ' . $result);
                if ($complete === 'YES') {
                    $met[] = new MipsCodeEntry('G8399', $day, 0);
                } else {
                    $incomplete = true;
                }
            }

            $status = $criterion->status($met);
            $flags = [];
            if ($hospice) {
                $flags[] = self::HOSPICE;
            }
            if ($osteoDx) {
                $flags[] = self::OSTEOPOROSIS;
            }
            if ($incomplete && $met === []) {
                $flags[] = self::NOT_COMPLETE;
            }
            $patients[] = MipsPatient::fromRow(
                $row,
                $encounters,
                $status->role === MipsRole::Met || (!$hospice && !$osteoDx),
                [$criterion->key => $status],
                [$ordersLabel => $orders, $entriesLabel => $labels],
                $flags,
            );
        }
        return new MipsReport($this, $period, [$criterion], $patients, [$ordersLabel, $entriesLabel]);
    }

    /** An osteoporosis diagnosis on billing alias $alias; codes match with or without the dot. */
    public static function dxCondition(string $alias): string
    {
        $codes = array_map(static fn(string $code): string => "'" . str_replace('.', '', $code) . "'", self::OSTEOPOROSIS_DX);
        return "$alias.code_type = 'ICD10' AND REPLACE($alias.code, '.', '') IN (" . implode(',', $codes) . ')';
    }
}
