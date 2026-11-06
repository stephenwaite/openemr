<?php

/**
 * Stages the charge files in Gifford's drop directory.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Stage;

use Cmsvt\OpenEMR\Modules\GiffordCharges\Parse\ChargeRowParser;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Reader\ChargeFileException;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Reader\ChargeFileReader;
use Psr\Clock\ClockInterface;

/**
 * Reads each new charge file in the drop directory, saves it as a batch with one line
 * per row, and moves it out of the way:
 *
 * - a file whose bytes were seen before is moved to processed/ without saving anything;
 * - a file that cannot be read (wrong type, missing columns) is saved as a failed batch
 *   and moved to failed/;
 * - any other file is saved with its lines (held when they have problems) and moved to
 *   processed/.
 *
 * Moved files are renamed "<batch id>-<name>". Only .xlsx, .csv and .txt files are read, and
 * none modified in the last $settleSeconds, so a file still arriving by SFTP waits for
 * the next run. Nothing here posts charges.
 */
final readonly class ChargeFileStager
{
    public const PROCESSED_DIR = 'processed';
    public const FAILED_DIR = 'failed';

    public function __construct(
        private ChargeFileReader $reader,
        private ChargeRowParser $parser,
        private ChargeBatchRepository $repository,
        private ClockInterface $clock,
        private int $settleSeconds = 120,
    ) {
    }

    /**
     * Stage every waiting file in $dropDir, oldest name first.
     *
     * @return list<StagedFile>
     * @throws StageException when the directory cannot be read or a file cannot be moved
     */
    public function run(string $dropDir): array
    {
        $dropDir = rtrim($dropDir, '/');
        if ($dropDir === '' || !is_dir($dropDir) || !is_readable($dropDir)) {
            throw new StageException('Charge file directory is missing or not readable');
        }

        $results = [];
        foreach ($this->waitingFiles($dropDir) as $name) {
            $results[] = $this->stageFile($dropDir, $name);
        }

        return $results;
    }

    private function stageFile(string $dropDir, string $name): StagedFile
    {
        $path = $dropDir . '/' . $name;
        $sha256 = hash_file('sha256', $path);
        if ($sha256 === false) {
            throw new StageException('Charge file could not be read: ' . $name);
        }
        $now = $this->clock->now();

        $seenBatchId = $this->repository->findBatchIdBySha256($sha256);
        if ($seenBatchId !== null) {
            $movedTo = $this->move($path, self::PROCESSED_DIR, $seenBatchId . '-resent-' . $now->format('Ymd\THis') . '-' . $name);
            return new StagedFile($name, FileOutcome::AlreadySeen, $seenBatchId, 0, 0, $movedTo);
        }

        try {
            $rows = $this->reader->read($path);
        } catch (ChargeFileException $e) {
            $batchId = $this->repository->saveFailedBatch($name, $sha256, $now, $e->getMessage());
            $movedTo = $this->move($path, self::FAILED_DIR, $batchId . '-' . $name);
            return new StagedFile($name, FileOutcome::Failed, $batchId, 0, 0, $movedTo);
        }

        $lines = [];
        foreach ($rows as $row) {
            $lines[] = StagedLine::fromRow($row, $this->parser->parse($row));
        }
        $batchId = $this->repository->saveStagedBatch($name, $sha256, $now, $lines);
        $held = count(array_filter($lines, static fn (StagedLine $line): bool => $line->status === LineStatus::Held));
        $movedTo = $this->move($path, self::PROCESSED_DIR, $batchId . '-' . $name);

        return new StagedFile($name, FileOutcome::Staged, $batchId, count($lines), $held, $movedTo);
    }

    /**
     * Names of the .xlsx, .csv and .txt files in $dropDir that have stopped changing, sorted.
     *
     * @return list<string>
     */
    private function waitingFiles(string $dropDir): array
    {
        $entries = scandir($dropDir);
        if ($entries === false) {
            throw new StageException('Charge file directory could not be listed');
        }
        $settledBefore = $this->clock->now()->getTimestamp() - $this->settleSeconds;

        $names = [];
        foreach ($entries as $name) {
            if (str_starts_with($name, '.') || preg_match('/\.(xlsx|csv|txt)$/i', $name) !== 1) {
                continue;
            }
            $path = $dropDir . '/' . $name;
            if (!is_file($path)) {
                continue;
            }
            $modified = filemtime($path);
            if ($modified === false || $modified > $settledBefore) {
                continue;
            }
            $names[] = $name;
        }
        sort($names, SORT_STRING);

        return $names;
    }

    /**
     * Move $path into $subdir beside it as $targetName; a name already taken there gets
     * "-2", "-3", ... before its extension.
     *
     * @return string the new path
     */
    private function move(string $path, string $subdir, string $targetName): string
    {
        $dir = dirname($path) . '/' . $subdir;
        if (!is_dir($dir) && !mkdir($dir, 0770) && !is_dir($dir)) {
            throw new StageException('Could not create ' . $subdir . '/ in the charge file directory');
        }

        $target = $dir . '/' . $targetName;
        $extension = pathinfo($targetName, PATHINFO_EXTENSION);
        $stem = $extension === '' ? $targetName : substr($targetName, 0, -strlen($extension) - 1);
        for ($n = 2; file_exists($target); $n++) {
            $target = $dir . '/' . $stem . '-' . $n . ($extension === '' ? '' : '.' . $extension);
        }

        if (!rename($path, $target)) {
            throw new StageException('Could not move a charge file into ' . $subdir . '/');
        }

        return $target;
    }
}
