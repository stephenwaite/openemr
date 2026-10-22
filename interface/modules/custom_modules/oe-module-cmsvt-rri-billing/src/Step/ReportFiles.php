<?php

/**
 * Writes a step's report files (<name>-<time>) to the module's errors folder,
 * and shows them.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Step;

use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class ReportFiles
{
    public function __construct(
        private SymfonyStyle $io,
        private string $directory,
        private string $stamp,
    ) {
    }

    /**
     * @param list<string> $lines
     * @return ?string the file's path, or null when it couldn't be written
     */
    public function write(string $name, array $lines, bool $show): ?string
    {
        if ($show) {
            $this->io->writeln($lines);
        }
        if (!is_dir($this->directory) && !mkdir($this->directory, 0770, true) && !is_dir($this->directory)) {
            $this->io->error('Could not create ' . $this->directory);
            return null;
        }
        $path = $this->directory . $name . '-' . $this->stamp;
        if (file_put_contents($path, $lines === [] ? '' : implode("\n", $lines) . "\n") === false) {
            $this->io->error('Could not write ' . $path);
            return null;
        }
        $this->io->info('Created ' . $path);
        return $path;
    }
}
