<?php

/**
 * Steps 4-6's --file / --date choice of ordfile (7.0.2's OrdFileSelector).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Command;

use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\OrdfileSource;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\StoredOrdfile;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class OrdfileOption
{
    /**
     * The ordfile --file or --date names, else the newest; null (after saying
     * why) when there is none.
     */
    public static function select(InputInterface $input, SymfonyStyle $io, OrdfileSource $source): ?StoredOrdfile
    {
        $file = $input->getOption('file');
        $date = $input->getOption('date');
        if (is_string($file) && $file !== '') {
            $ordfile = $source->byFileName($file);
            $missing = 'No ordfile imports found for filename ' . $file;
        } elseif (is_string($date) && $date !== '') {
            $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if ($day === false || $day->format('Y-m-d') !== $date) {
                $io->error('Date format invalid. Must be in Y-m-d format');
                return null;
            }
            $ordfile = $source->byDate($day);
            $missing = 'No ordfile imports found for date ' . $date;
        } else {
            $ordfile = $source->latest();
            $missing = 'No ordfile imports found';
        }
        if ($ordfile === null) {
            $io->error($missing);
            return null;
        }
        $io->info('Using ordfile ' . basename($ordfile->fileName) . ' (' . count($ordfile->lines) . ' charges)');
        return $ordfile;
    }
}
