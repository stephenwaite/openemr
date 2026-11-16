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
 * A suggestion read from the report posts without a coder only when its first code is high
 * confidence, every code is valid, it passes the coders' workbook checks, and its first code
 * is among the codes Gifford sent for the visit: two sources agree. A coder confirms the
 * rest on Fees > Gifford Diagnoses.
 */
final class DxRule
{
    public const HIGH = 'high';

    public const MEDICAID = 'medicaid';
    public const MEDICARE = 'medicare';
    public const MEDICARE_ADVANTAGE = 'medicare advantage';
    public const OTHER = 'other';

    /**
     * Why a coder has to confirm; [] when the suggestion posts as it is.
     *
     * @param list<array{code: string, confidence: string}> $suggested in claim order
     * @param list<string> $gifford the codes Gifford sent for the visit
     * @param list<string> $invalid the suggested codes that are not valid for coding
     * @param list<string> $cpts the accession's procedure codes
     * @param string $payerClass one of the constants, from payerClass()
     * @return list<string>
     */
    public static function reasons(array $suggested, array $gifford, array $invalid, array $cpts, string $payerClass, DxLists $lists): array
    {
        if ($suggested === []) {
            return ['no suggestion'];
        }
        $codes = array_column($suggested, 'code');
        $first = $codes[0];
        $why = [];
        if ($gifford === []) {
            $why[] = 'Gifford sent no diagnosis';
        } elseif (!in_array($first, $gifford, true)) {
            $why[] = "first code is not among Gifford's";
        }
        if ($suggested[0]['confidence'] !== self::HIGH) {
            $why[] = 'first code is not high confidence';
        }
        if ($invalid !== []) {
            $why[] = 'not valid for coding: ' . implode(', ', $invalid);
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
