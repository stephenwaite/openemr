<?php

/**
 * Whether a suggested diagnosis posts as it is or waits for a coder.
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
 * The diagnoses on a Gifford claim are coded from the radiologist's report, finding first.
 * A suggestion read from the report posts without a coder when its first code is high
 * confidence, every code is valid and it passes the coders' workbook checks. The codes
 * Gifford sent for the visit post instead on a film taken during surgery (theirs is the
 * surgical diagnosis), and when there is no suggestion or its first code is not high
 * confidence, if they pass the same checks. A coder confirms the rest on Fees > Gifford
 * Diagnoses.
 */
final class DxRule
{
    public const HIGH = 'high';

    /** Gifford's location for films taken during surgery. */
    public const SURGERY_LOCATION = 'GMC.OR';

    public const MEDICAID = 'medicaid';
    public const MEDICARE = 'medicare';
    public const MEDICARE_ADVANTAGE = 'medicare advantage';
    public const OTHER = 'other';

    /**
     * Which codes the accession posts with, or why a coder has to confirm.
     *
     * @param list<array{code: string, confidence: string}> $suggested in claim order
     * @param list<string> $gifford the codes Gifford sent for the visit
     * @param list<string> $invalid the suggested and Gifford codes that are not valid for coding
     * @param list<string> $cpts the accession's procedure codes
     * @param string $payerClass one of the constants, from payerClass()
     * @param string $location Gifford's location for the study
     */
    public static function decide(array $suggested, array $gifford, array $invalid, array $cpts, string $payerClass, string $location, DxLists $lists): DxDecision
    {
        $fallback = match (true) {
            $location === self::SURGERY_LOCATION => 'film taken during surgery',
            $suggested === [] => 'no suggestion',
            $suggested[0]['confidence'] !== self::HIGH => 'first code is not high confidence',
            default => null,
        };
        if ($fallback === null) {
            $codes = array_slice(array_column($suggested, 'code'), 0, DxRepository::MAX_CODES);
            $why = self::problems($codes, $invalid, $cpts, $payerClass, $lists);

            return $why === [] ? DxDecision::suggestion($codes) : DxDecision::coder($why);
        }
        $codes = array_slice($gifford, 0, DxRepository::MAX_CODES);
        if ($codes === []) {
            return DxDecision::coder([$fallback, 'Gifford sent no diagnosis']);
        }
        $why = self::problems($codes, $invalid, $cpts, $payerClass, $lists);

        return $why === []
            ? DxDecision::gifford($codes)
            : DxDecision::coder([$fallback, ...array_map(static fn (string $w): string => "Gifford's: " . $w, $why)]);
    }

    /**
     * What the checks have against posting these codes; [] when nothing.
     *
     * @param non-empty-list<string> $codes in claim order
     * @param list<string> $invalid
     * @param list<string> $cpts
     * @return list<string>
     */
    private static function problems(array $codes, array $invalid, array $cpts, string $payerClass, DxLists $lists): array
    {
        $first = $codes[0];
        $why = [];
        $bad = array_values(array_intersect($codes, $invalid));
        if ($bad !== []) {
            $why[] = 'not valid for coding: ' . implode(', ', $bad);
        }
        if ($lists->cannotBePrimary($first)) {
            $why[] = 'first code cannot be primary';
        }
        if ($payerClass === self::MEDICAID && $lists->unspecified($first)) {
            $why[] = 'first code is unspecified (Medicaid primary)';
        }
        if (in_array($payerClass, [self::MEDICARE, self::MEDICARE_ADVANTAGE], true) && $lists->supportsExam($cpts, $codes) === false) {
            $why[] = 'no code on the Medicare list for the exam';
        }

        return $why;
    }

    /**
     * Gifford's primary payer mnemonic as one of the payer classes.
     */
    public static function payerClass(string $mnemonic): string
    {
        $m = strtoupper($mnemonic);

        return match (true) {
            str_starts_with($m, 'MCD') => self::MEDICAID,
            $m === 'MCR-AB' => self::MEDICARE,
            str_contains($m, 'MCR') => self::MEDICARE_ADVANTAGE,
            default => self::OTHER,
        };
    }

    /**
     * An ICD-10-CM code upper-cased with its dot (R05.9); '' when it isn't shaped like one.
     */
    public static function normalize(string $code): string
    {
        $c = preg_replace('/[^A-Z0-9]/', '', strtoupper($code)) ?? '';
        if (preg_match('/^[A-Z][0-9][0-9A-Z][0-9A-Z]{0,4}$/', $c) !== 1) {
            return '';
        }

        return strlen($c) === 3 ? $c : substr($c, 0, 3) . '.' . substr($c, 3);
    }
}
