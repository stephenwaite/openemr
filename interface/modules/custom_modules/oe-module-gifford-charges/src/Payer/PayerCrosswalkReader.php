<?php

/**
 * Reads the payer crosswalk CSV: one row per Gifford payer mnemonic.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Payer;

use Cmsvt\OpenEMR\Modules\GiffordCharges\Model\Address;
use League\Csv\Reader as CsvReader;

/**
 * A header is matched on its name up to the first " (", so
 * "electronic (Y = ..., N = paper)" is the electronic column. Other columns (the match
 * scores, alternates, notes) are ignored. Every bad row is reported, not only the first.
 */
final class PayerCrosswalkReader
{
    public const REQUIRED_COLUMNS = [
        'gifford_mnemonic',
        'gifford_name',
        'electronic',
        'payer_id_to_use',
        'ins_type_code',
        'x12_partner',
        'street',
        'city',
        'state',
        'zip',
    ];

    /**
     * @return list<PayerMapping> in file order
     * @throws PayerCrosswalkException when the file can't be read or any row is bad
     */
    public function read(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new PayerCrosswalkException('Payer crosswalk not found or not readable');
        }
        try {
            $table = [];
            foreach (CsvReader::from($path, 'r')->getRecords() as $record) {
                $table[] = array_map(
                    static fn (mixed $cell): string => is_string($cell) ? trim($cell) : '',
                    array_values($record)
                );
            }
        } catch (\Throwable $e) {
            throw new PayerCrosswalkException('Payer crosswalk could not be read as .csv', [], $e);
        }
        if ($table === []) {
            throw new PayerCrosswalkException('Payer crosswalk is empty');
        }

        $header = array_map(self::columnName(...), array_shift($table));
        $missing = array_values(array_diff(self::REQUIRED_COLUMNS, $header));
        if ($missing !== []) {
            throw new PayerCrosswalkException('Payer crosswalk is missing columns: ' . implode(', ', $missing));
        }
        $position = array_flip($header);

        $mappings = [];
        $problems = [];
        $seen = [];
        foreach ($table as $index => $cells) {
            // Line 1 is the header.
            $line = $index + 2;
            $get = static fn (string $column): string => $cells[$position[$column]] ?? '';
            if (implode('', $cells) === '') {
                continue;
            }
            try {
                $mapping = $this->parseRow($get);
            } catch (PayerCrosswalkException $e) {
                $problems[] = 'Line ' . $line . ': ' . $e->getMessage();
                continue;
            }
            if (isset($seen[$mapping->mnemonic])) {
                $problems[] = 'Line ' . $line . ': ' . $mapping->mnemonic . ' is also on line ' . $seen[$mapping->mnemonic];
                continue;
            }
            $seen[$mapping->mnemonic] = $line;
            $mappings[] = $mapping;
        }

        if ($problems !== []) {
            throw new PayerCrosswalkException('Payer crosswalk has rows that cannot be imported', $problems);
        }

        return $mappings;
    }

    /**
     * @param \Closure(string): string $get
     */
    private function parseRow(\Closure $get): PayerMapping
    {
        $mnemonic = strtoupper($get('gifford_mnemonic'));
        if ($mnemonic === '') {
            throw new PayerCrosswalkException('no gifford_mnemonic');
        }
        $name = preg_replace('/\s+/', ' ', $get('gifford_name')) ?? '';
        if ($name === '') {
            throw new PayerCrosswalkException($mnemonic . ' has no gifford_name');
        }
        $type = $get('ins_type_code');
        if (preg_match('/^[1-9]\d*$/', $type) !== 1) {
            throw new PayerCrosswalkException($mnemonic . ': ins_type_code must be a number, not "' . $type . '"');
        }
        $partner = $get('x12_partner');

        $electronic = match (strtoupper($get('electronic'))) {
            'Y' => true,
            'N' => false,
            default => throw new PayerCrosswalkException($mnemonic . ': electronic must be Y or N'),
        };
        if (!$electronic) {
            if ($partner !== '') {
                throw new PayerCrosswalkException($mnemonic . ' is a paper payer but has an x12_partner');
            }
            return new PayerMapping($mnemonic, $name, (int) $type, null);
        }

        $payerId = $get('payer_id_to_use');
        if ($payerId === '') {
            throw new PayerCrosswalkException($mnemonic . ' is electronic but has no payer_id_to_use');
        }
        // "111462 Optum iEDI": the partner id, then its name for people reading the file.
        if (preg_match('/^(\d+)(\s|$)/', $partner, $matches) !== 1) {
            throw new PayerCrosswalkException($mnemonic . ' is electronic but x12_partner does not start with a partner id');
        }
        $zip = $get('zip');
        if ($zip !== '' && preg_match('/^\d{5}(\d{4})?$/', $zip) !== 1) {
            throw new PayerCrosswalkException($mnemonic . ': zip must be 5 or 9 digits, not "' . $zip . '"');
        }

        return new PayerMapping($mnemonic, $name, (int) $type, new ElectronicPayer(
            $payerId,
            (int) $matches[1],
            new Address($get('street'), '', $get('city'), strtoupper($get('state')), $zip),
        ));
    }

    private static function columnName(string $header): string
    {
        $cut = strpos($header, ' (');
        return strtolower(trim($cut === false ? $header : substr($header, 0, $cut)));
    }
}
