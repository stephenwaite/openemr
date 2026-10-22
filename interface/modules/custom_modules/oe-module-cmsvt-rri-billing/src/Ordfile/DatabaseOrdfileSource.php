<?php

/**
 * Ordfiles in cmsvt_ordfile / cmsvt_ordfile_record (7.0.2's OrdFileSelector).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Ordfile;

use OpenEMR\Common\Database\QueryUtils;
use Psr\Clock\ClockInterface;

final readonly class DatabaseOrdfileSource implements OrdfileSource
{
    public function __construct(private ClockInterface $clock)
    {
    }

    public function latest(): ?StoredOrdfile
    {
        return $this->load(QueryUtils::querySingleRow('SELECT * FROM `cmsvt_ordfile` ORDER BY `ordfile_id` DESC LIMIT 1', [], false));
    }

    public function byFileName(string $fileName): ?StoredOrdfile
    {
        return $this->load(QueryUtils::querySingleRow(
            'SELECT * FROM `cmsvt_ordfile` WHERE `filename` = ? OR `filename` LIKE ? ORDER BY `ordfile_id` DESC LIMIT 1',
            [$fileName, '%/' . $fileName],
            false
        ));
    }

    public function byDate(\DateTimeImmutable $date): ?StoredOrdfile
    {
        return $this->load(QueryUtils::querySingleRow(
            'SELECT * FROM `cmsvt_ordfile` WHERE DATE(`date_created`) = ? ORDER BY `ordfile_id` DESC LIMIT 1',
            [$date->format('Y-m-d')],
            false
        ));
    }

    /**
     * @param array<mixed>|false $ordfile
     */
    private function load(array|false $ordfile): ?StoredOrdfile
    {
        if ($ordfile === false || !is_numeric($ordfile['ordfile_id'] ?? null)) {
            return null;
        }
        $id = (int) $ordfile['ordfile_id'];
        $today = $this->clock->now();
        $lines = [];
        foreach (QueryUtils::fetchRecords('SELECT * FROM `cmsvt_ordfile_record` WHERE `ordfile_id` = ? ORDER BY `ordfile_record_id`', [$id]) as $row) {
            $text = static fn(string $column): string => is_scalar($row[$column] ?? null) ? (string) $row[$column] : '';
            $dateT = \DateTimeImmutable::createFromFormat('!Ymd', $text('dateT'));
            $lines[(int) $text('ordfile_record_id')] = new OrdfileLine(
                $text('ord8'),
                $text('ord3'),
                $text('proc'),
                // CHAR(1) drops the space of a charge; '-' is a cancel.
                $text('ind') === '-' ? '-' : ' ',
                $text('ref'),
                $text('iopat'),
                $text('dateA'),
                $dateT === false ? $today : $dateT,
                $text('dateAdmit'),
                $text('order'),
                $text('clinical'),
                $text('docp'),
                $text('admitDiag'),
                $text('visitNo'),
                $text('mod2'),
                $text('mod3'),
                $text('mod4'),
                $text('dateE'),
                $text('cpt'),
                $text('accession'),
            );
        }
        $name = $ordfile['filename'] ?? null;
        return new StoredOrdfile($id, is_string($name) ? $name : '', $lines);
    }
}
