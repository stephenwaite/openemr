<?php

/**
 * The coders' workbook lists the diagnosis rule checks a suggestion against.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Dx;

/**
 * Read from the JSON file gifford-dx-lists.py writes from RadiologyDiags.ods: VT Medicaid's
 * cannot-be-primary and unspecified codes, and the Medicare LCD diagnoses for each exam the
 * workbook covers. Codes in the file have no dot.
 */
final readonly class DxLists
{
    /**
     * @param array<string, true> $notPrimary
     * @param array<string, true> $unspecified
     * @param array<string, array{codes: array<string, true>, ranges: list<array{string, string}>}> $exams by CPT code
     */
    private function __construct(
        private array $notPrimary,
        private array $unspecified,
        private array $exams,
    ) {
    }

    /**
     * @throws \InvalidArgumentException when the file isn't a lists file
     */
    public static function fromFile(string $path): self
    {
        $json = is_file($path) ? file_get_contents($path) : false;
        $data = $json === false ? null : json_decode($json, true);
        if (!is_array($data) || !is_array($data['not_primary'] ?? null) || !is_array($data['unspecified'] ?? null) || !is_array($data['exams'] ?? null)) {
            throw new \InvalidArgumentException($path . ' is not a lists file from gifford-dx-lists.py');
        }

        return self::fromArray($data);
    }

    /**
     * @param array<array-key, mixed> $data the decoded file
     */
    public static function fromArray(array $data): self
    {
        $set = static function (mixed $codes): array {
            $found = [];
            foreach (is_array($codes) ? $codes : [] as $code) {
                if (is_string($code) && $code !== '') {
                    $found[$code] = true;
                }
            }
            return $found;
        };
        $exams = [];
        foreach (is_array($data['exams'] ?? null) ? $data['exams'] : [] as $exam) {
            if (!is_array($exam)) {
                continue;
            }
            $ranges = [];
            foreach (is_array($exam['ranges'] ?? null) ? $exam['ranges'] : [] as $range) {
                if (is_array($range) && is_string($range[0] ?? null) && is_string($range[1] ?? null)) {
                    $ranges[] = [$range[0], $range[1]];
                }
            }
            $entry = ['codes' => $set($exam['codes'] ?? null), 'ranges' => $ranges];
            foreach (is_array($exam['cpts'] ?? null) ? $exam['cpts'] : [] as $cpt) {
                if (is_string($cpt) || is_int($cpt)) {
                    $exams['c' . $cpt] ??= $entry;
                }
            }
        }

        return new self($set($data['not_primary'] ?? null), $set($data['unspecified'] ?? null), $exams);
    }

    public function cannotBePrimary(string $code): bool
    {
        return isset($this->notPrimary[str_replace('.', '', $code)]);
    }

    public function unspecified(string $code): bool
    {
        return isset($this->unspecified[str_replace('.', '', $code)]);
    }

    /**
     * Whether any of the codes is on the Medicare list of the first of these exams the
     * workbook covers; null when it covers none of them. A range "E24.2 - E24.4" takes
     * every code from the first through any code under the last.
     *
     * @param list<string> $cpts
     * @param list<string> $codes
     */
    public function supportsExam(array $cpts, array $codes): ?bool
    {
        foreach ($cpts as $cpt) {
            $exam = $this->exams['c' . $cpt] ?? null;
            if ($exam === null) {
                continue;
            }
            foreach ($codes as $code) {
                $c = str_replace('.', '', $code);
                if (isset($exam['codes'][$c])) {
                    return true;
                }
                foreach ($exam['ranges'] as [$low, $high]) {
                    if (strcmp($low, $c) <= 0 && (strcmp($c, $high) <= 0 || str_starts_with($c, $high))) {
                        return true;
                    }
                }
            }
            return false;
        }

        return null;
    }
}
