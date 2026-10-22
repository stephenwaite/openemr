<?php

/**
 * RRMC's charge rules (the COBOL rrmc008, as the 7.0.2 module's parser applied
 * them), run over the whole file in order:
 *
 * - a charge whose CDM and code aren't in the charge master is dropped and
 *   reported (the biller adds it to the charge master and runs step 1 again);
 * - a few HCPCS codes are billed as their CPT for specific CDMs;
 * - a $0 charge master fee drops the charge, unless it is an AUC line (GL 0);
 * - RVOC's professional component modifier 26 is removed, and a repeated
 *   modifier is cleared;
 * - bilateral studies on certain CDMs become an RT/LT pair. The pairing state
 *   runs across the file, not per account, as it always has.
 *
 * Messages keep the 7.0.2 wording so billers see what they are used to.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Rules;

use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeFileAccount;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeLine;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\ChargeMaster;

final class ChargeRules
{
    /** CDMs whose bilateral studies are billed as an RT/LT pair. */
    private const BILATERAL_CDMS = ['0111', '1204', '1283', '1284', '1285', '3030', '3085', '5024', '5282'];

    /** CDM => [code RRMC sends => CPT to bill]. */
    private const HCPCS_TO_CPT = [
        '6250' => ['C8901' => '74185'],
        '6251' => ['C8900' => '74185'],
        '6252' => ['C8902' => '74185'],
        '6327' => ['C8908' => '77049'],
        '6254' => ['C8909' => '71555'],
        '6263' => ['C8912' => '73725'],
        '6267' => ['C8912' => '73725'],
    ];

    private const AUC_GL_CODE = '0';
    private const RVOC = 'RVOC';
    private const PROFESSIONAL_COMPONENT = '26';
    private const BILATERAL = '50';

    /** @var list<string> */
    private array $messages = [];

    /** The previous bilateral charge was billed RT and waits for its LT. */
    private bool $bilateralOpen = false;

    public function __construct(private readonly ChargeMaster $chargeMaster)
    {
    }

    /**
     * @param list<ChargeFileAccount> $accounts in file order
     */
    public function apply(array $accounts): ChargeRulesResult
    {
        $this->messages = [];
        $this->bilateralOpen = false;
        $result = [];
        foreach ($accounts as $account) {
            $charges = [];
            foreach ($account->charges as $charge) {
                $kept = $this->charge($account, $charge);
                if ($kept !== null) {
                    $charges[] = $kept;
                }
            }
            $result[] = $account->withCharges($charges);
        }
        return new ChargeRulesResult($result, $this->messages);
    }

    private function charge(ChargeFileAccount $account, ChargeLine $charge): ?ChargeLine
    {
        if ($charge->cpt !== '' && $charge->hcpcs !== '') {
            $this->messages[] = 'for ' . $account->visitNumber . ' rrmc sent us both a hcpcs ' . $charge->hcpcs
                . ' AND a cpt ' . $charge->cpt . ' with mod ' . $charge->modifier1 . ' for DOS ' . $charge->dateOfServiceRaw;
        }
        $code = $charge->cpt !== '' ? $charge->cpt : $charge->hcpcs;
        $entry = $this->chargeMaster->find($charge->cdm, $code);
        if ($entry === null) {
            $this->undefinedProcedure($account, $charge);
            return null;
        }

        $cpt = $charge->cpt;
        $replacement = self::HCPCS_TO_CPT[$charge->cdm][$code] ?? null;
        if ($replacement !== null) {
            $this->messages[] = 'RRMC SENT US ' . $charge->cpt . ' ' . $charge->hcpcs . ' CHANGING THIS TO CPT ' . $replacement;
            $cpt = $replacement;
        }

        if ($entry->isZeroDollar() && $charge->glCode !== self::AUC_GL_CODE) {
            $this->messages[] = 'Zero dollar charge found for mrn ' . $account->mrn . ' CDM ' . $charge->cdm
                . ' CPT ' . $cpt . ' HCPCS ' . $charge->hcpcs . ' DOS ' . $charge->dateOfServiceRaw
                . ' safe to ignore, usually is but DBL CK!';
            return null;
        }

        [$modifier1, $modifier2, $professionalComponentRemoved] = self::modifiers($charge);

        if (in_array($charge->cdm, self::BILATERAL_CDMS, true)) {
            // 7.0.2 compared the first modifier with two spaces, which only the
            // RVOC 26 removal above produced (fields are read trimmed).
            if ($professionalComponentRemoved && $modifier1 === '' && $this->bilateralOpen) {
                $this->messages[] = 'CHANGING BILAT STUDY TO RT LT, MEDREC ' . $account->mrn . ' CDM ' . $charge->cdm
                    . ' CPT ' . $cpt . ' MOD1    DOS ' . $charge->dateOfServiceRaw;
                $this->bilateralOpen = false;
                $modifier1 = 'LT';
            } elseif ($modifier1 === self::BILATERAL) {
                // RVOC sometimes sends 50 on both studies of the pair.
                $modifier1 = $this->bilateralOpen ? 'LT' : 'RT';
                $this->bilateralOpen = !$this->bilateralOpen;
            }
        }

        return $charge->withCptAndModifiers($cpt, $modifier1, $modifier2);
    }

    /**
     * @return array{string, string, bool} first and second modifier, and whether
     *                                     RVOC's 26 was removed leaving no first modifier
     */
    private static function modifiers(ChargeLine $charge): array
    {
        $modifier1 = $charge->modifier1;
        $modifier2 = $charge->modifier2;
        $removed = false;
        if ($charge->location === self::RVOC && $modifier1 === self::PROFESSIONAL_COMPONENT) {
            $modifier1 = $modifier2;
            $removed = $modifier2 === '';
        }
        if ($modifier1 === $modifier2) {
            $modifier2 = '';
        }
        return [$modifier1, $modifier2, $removed];
    }

    private function undefinedProcedure(ChargeFileAccount $account, ChargeLine $charge): void
    {
        $this->messages[] = 'UNDEFINED PROCEDURE FOR MRN ' . $account->mrn . ' CDM ' . $charge->cdm . ' CPT ' . $charge->cpt
            . ' HCPCS ' . $charge->hcpcs . ' DOS ' . $charge->dateOfServiceRaw;
        if ($charge->glCode !== self::AUC_GL_CODE) {
            $this->messages[] = '** STOP! And ADD THIS CDM-CPT IN THE 52 TO CAPTURE THE NEW RRMC CDM, re-run qqq thank you. '
                . $charge->location . ' **';
        } elseif ($charge->location === self::RVOC) {
            $this->messages[] = '** STOP and ADD THIS CDM-CPT IN THE 52 TO CAPTURE THE new RVOC CDM, re-run qqq thank you. '
                . $charge->location . ' **';
        } else {
            $this->messages[] = "DON'T WORRY ABOUT IT DUE TO RRMC AUC";
        }
    }
}
