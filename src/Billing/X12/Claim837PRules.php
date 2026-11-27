<?php

/**
 * Payer- and practice-specific decisions in the 837P professional claim.
 *
 * X125010837P asks these at each point where billing services commonly need
 * something other than the stock output. DefaultClaim837PRules reproduces
 * the stock behavior; a module can supply its own through
 * Claim837PRulesEvent. One instance is used per claim, so an implementation
 * may collect warnings as it is asked and return them from warnings().
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Billing\X12;

use OpenEMR\Billing\Claim;

interface Claim837PRules
{
    /**
     * Loop 1000A NM109 when a third-party submitter name is used.
     * Null keeps the submitter user's federal tax ID.
     */
    public function submitterIdentifier(Claim $claim): ?string;

    /**
     * Report the encounter's onset date as DTP*304 (Last Seen), with a
     * supervising provider, as routine foot care requires.
     */
    public function requiresLastSeenDate(Claim $claim): bool;

    /** Send loop 2310A from the referring provider fields even without a referral on the encounter. */
    public function requiresReferringProvider(Claim $claim): bool;

    /**
     * Report prior payers' posted adjustments (CO/OA and itemized PR) on
     * secondary claims. False reports only each line's remaining patient
     * responsibility, as PR-3.
     */
    public function reportsPriorPayerAdjustments(Claim $claim): bool;

    /** Send REF*X4 (CLIA number) when the claim has one. */
    public function sendsClia(Claim $claim): bool;

    /** Send the EPSDT referral as NTE*ADD instead of CRC*ZZ. */
    public function sendsEpsdtAsNote(Claim $claim): bool;

    /**
     * Send loop 2310C (service facility).
     *
     * @param bool $default the stock decision
     */
    public function sendsServiceFacility(Claim $claim, bool $default): bool;

    /** Send loops 2320/2330 and 2430 for other payer $ins (1 and up). */
    public function sendsOtherPayer(Claim $claim, int $ins): bool;

    /** Other payer's identifier in NM109 of loop 2330B and SVD01; null keeps its payer ID. */
    public function otherPayerIdentifier(Claim $claim, int $ins): ?string;

    /**
     * Send loop 2420A (line rendering provider).
     *
     * @param bool $default the stock decision
     */
    public function sendsLineRenderingProvider(Claim $claim, int $prockey, bool $default): bool;

    /** Send loop 2410 (drug identification) for a line that has an NDC. */
    public function sendsNdc(Claim $claim, int $prockey): bool;

    /**
     * Reason code to report for a prior payer's line adjustment in CAS.
     */
    public function lineAdjustmentReason(Claim $claim, int $ins, string $groupCode, string $reasonCode): string;

    /**
     * Line adjustments to report for prior payer $ins in addition to the posted ones.
     *
     * @return list<array{group: string, reason: string, amount: float}>
     */
    public function extraLineAdjustments(Claim $claim, int $ins, int $prockey): array;

    /**
     * Warnings for the claim log, collected once the claim is generated.
     *
     * @return list<string>
     */
    public function warnings(Claim $claim): array;
}
