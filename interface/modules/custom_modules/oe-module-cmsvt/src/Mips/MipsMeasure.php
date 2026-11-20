<?php

/**
 * A MIPS quality measure the report can run: how it finds its patients and how it
 * turns their codes into statuses.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Mips;

interface MipsMeasure
{
    /** The measure number, e.g. "509". */
    public function id(): string;

    public function title(): string;

    /** What puts a listed patient in the denominator, for the summary line. */
    public function denominatorLabel(): string;

    /** Heading of the diagnosis history column. */
    public function diagnosisLabel(): string;

    /**
     * Notes shown above the tables: how patients are found and what to review.
     *
     * @return list<string>
     */
    public function notes(): array;

    /**
     * Codes tracked, for the legend: [code, description] pairs.
     *
     * @return list<array{string, string}>
     */
    public function codeLegend(): array;

    public function fetch(MipsPeriod $period, MipsRepository $repository): MipsData;

    /** Pure: builds the report from rows, so it can be tested without a database. */
    public function build(MipsPeriod $period, MipsData $data): MipsReport;
}
