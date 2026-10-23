<?php

/**
 * Saves charge files and their lines in the Gifford staging tables.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Stage;

use OpenEMR\Common\Database\QueryUtils;

final class ChargeBatchRepository
{
    private const DATETIME = 'Y-m-d H:i:s';

    /**
     * The batch saved for a file with these bytes, or null when none was.
     */
    public function findBatchIdBySha256(string $sha256): ?int
    {
        $id = QueryUtils::fetchSingleValue(
            'SELECT `id` FROM `gifford_charge_batch` WHERE `file_sha256` = ?',
            'id',
            [$sha256]
        );

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * Save a file that was read, and all its lines, in one transaction.
     *
     * @param list<StagedLine> $lines
     * @return int the batch id
     */
    public function saveStagedBatch(string $fileName, string $sha256, \DateTimeImmutable $receivedAt, array $lines): int
    {
        $held = count(array_filter($lines, static fn (StagedLine $line): bool => $line->status === LineStatus::Held));
        $at = $receivedAt->format(self::DATETIME);

        return QueryUtils::inTransaction(function () use ($fileName, $sha256, $at, $lines, $held): int {
            $batchId = $this->insertBatch($fileName, $sha256, $at, BatchStatus::Staged, null, count($lines), $held);
            foreach ($lines as $line) {
                QueryUtils::sqlInsert(
                    'INSERT INTO `gifford_charge_line` (`batch_id`, `line_number`, `visit_number`, `mrn`, `accession`,'
                    . ' `date_of_service`, `code`, `raw_json`, `problems_json`, `status`, `updated_at`)'
                    . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $batchId,
                        $line->lineNumber,
                        mb_substr($line->visitNumber, 0, 32),
                        mb_substr($line->mrn, 0, 32),
                        mb_substr($line->accession, 0, 64),
                        $line->dateOfService?->format('Y-m-d'),
                        mb_substr($line->code, 0, 16),
                        json_encode($line->rawValues, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        json_encode($line->problems, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        $line->status->value,
                        $at,
                    ]
                );
            }

            return $batchId;
        });
    }

    /**
     * Save a file that could not be read. $failure must not contain patient data.
     *
     * @return int the batch id
     */
    public function saveFailedBatch(string $fileName, string $sha256, \DateTimeImmutable $receivedAt, string $failure): int
    {
        return $this->insertBatch($fileName, $sha256, $receivedAt->format(self::DATETIME), BatchStatus::Failed, $failure, 0, 0);
    }

    private function insertBatch(
        string $fileName,
        string $sha256,
        string $at,
        BatchStatus $status,
        ?string $failure,
        int $lineCount,
        int $heldCount,
    ): int {
        return QueryUtils::sqlInsert(
            'INSERT INTO `gifford_charge_batch` (`file_name`, `file_sha256`, `received_at`, `status`, `failure`,'
            . ' `line_count`, `held_count`) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [mb_substr($fileName, 0, 255), $sha256, $at, $status->value, $failure, $lineCount, $heldCount]
        );
    }
}
