<?php

/**
 * The old billing system's insurance rules (COBOL rrmc006 P2-2 and P2-3,
 * lines 1394-1650), applied to an account once its primary and secondary
 * insurances are chosen. They rewrite payer codes, policy numbers, groups and
 * relationships for specific payers: Medicare (003) and railroad Medicare (028),
 * Medicaid of Vermont (004), BCBS of Vermont (002) and out of state (268), NYS
 * Empire (149), Medigap (062), Medicomp (005), CNA work comp (121) and others.
 *
 * Ported rule for rule from the 7.0.2 module, in the same order and with its
 * comparisons: payer codes compare as numbers when both are numeric ("3" is
 * "003"), as PHP's == did. Setting a policy number from an empty value leaves the
 * old one, as the 7.0.2 record's assignFromString() did. One change: for a
 * BCBS 268 YLS policy with NYS secondary 116 the 7.0.2 code threw a TypeError
 * while clearing the secondary policy; here it completes the swap.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Account;

final class InsuranceRules
{
    private const MEDICARE = '003';
    private const MEDICAID_VT = '004';
    private const MEDICOMP = '005';
    private const RAILROAD_MEDICARE = '028';
    private const MEDIGAP = '062';
    private const HSA = '075';
    private const MISC = '076';
    private const NYS_EMPIRE_PERSONAL = '116';
    private const CNA_WORK_COMP = '121';
    private const NYS_EMPIRE = '149';
    private const AARP = '245';
    private const BCBS_OUT_OF_STATE = '268';
    private const BCBS_VT = '002';
    private const BCBS_VT_SECOND = '023';
    private const SELF_PAY = '001';
    private const SAME_AS_PRIMARY = '020';
    private const CIGNA = '715';

    /** Other BCBS plans moved to BCBS out of state (25 BS-NENY ... 96 BS CONN; 234-244 below). */
    private const OTHER_BCBS = ['025', '029', '031', '044', '033', '075', '094', '096'];
    private const BCBS_VT_PREFIXES = ['FAC', 'EVT', 'FVT', 'VEI', 'FAO'];

    /** @var list<string> */
    private array $messages = [];

    /**
     * @param string $mrn the patient's MRN, for messages
     * @return list<string> errfile01 lines
     */
    public function apply(AccountRecord $account, string $mrn): array
    {
        $this->messages = [];

        // Medicaid of VT uses the patient's relationship.
        if (self::is($account->prins, self::MEDICAID_VT)) {
            $account->prrelate = $account->relate;
        }
        if (self::is($account->seins, self::MEDICAID_VT)) {
            $account->serelate = $account->relate;
        }

        // Policy numbers are packed, except AARP Medicare Complete's.
        if (!self::is($account->prins, self::AARP)) {
            $account->pripol = $account->pripol->packed();
        }
        if (!self::is($account->seins, self::AARP)) {
            $account->secpol = $account->secpol->packed();
        }

        // Railroad Medicare by policy prefix.
        if (self::is($account->prins, self::MEDICARE) && self::isRailroad($account->pripol)) {
            $account->prins = self::RAILROAD_MEDICARE;
        }
        if (self::is($account->seins, self::MEDICARE) && self::isRailroad($account->secpol)) {
            $account->seins = self::RAILROAD_MEDICARE;
        }

        // Medicare keeps a group only with a Medigap secondary.
        if (self::is($account->prins, self::MEDICARE) && !self::is($account->seins, self::MEDIGAP)) {
            $account->prgroup = '';
        }
        if (self::is($account->seins, self::MEDICARE)) {
            $account->segroup = '';
        }

        $account->proffice = self::is($account->prins, self::CNA_WORK_COMP) ? 'X100' : '';

        if (self::is($account->pripol->toString(), $account->prgroup)) {
            $account->prgroup = '';
        }
        if (self::is($account->secpol->toString(), $account->segroup)) {
            $account->segroup = '';
        }

        // Medicare with Medicomp on the same first nine characters: the secondary
        // takes the primary's suffix.
        if (
            self::is($account->prins, self::MEDICARE) && self::is($account->seins, self::MEDICOMP)
            && self::is($account->pripol->part1, $account->secpol->part1)
        ) {
            $account->secpol = $account->secpol->withPart2($account->pripol->part2);
        }

        // BCBS rules work on the primary policy as it stands here: a three-letter
        // prefix, nine more characters, and the rest.
        $pripol = $account->pripol->toString();
        $prefix = strlen($pripol) >= 3 ? substr($pripol, 0, 3) : $pripol;
        $middle = strlen($pripol) >= 12 ? substr($pripol, 3, 9) : '';
        $suffix = strlen($pripol) > 12 ? substr($pripol, 12) : '';
        $alphaPrefix = self::isAlpha($prefix, 0) && self::isAlpha($prefix, 1) && self::isAlpha($prefix, 2);

        // 7.0.2 tested !empty($prins), which is also false for "0".
        $prins = $account->prins;
        if (
            $prins !== '' && $prins !== '0' && $alphaPrefix && is_numeric($middle)
            && (self::isOneOf($prins, self::OTHER_BCBS) || self::isNumberBetween($prins, 233, 245))
        ) {
            $account->prins = self::BCBS_OUT_OF_STATE;
            $account->prassign = 'A';
        }

        if (self::is($account->prins, self::BCBS_OUT_OF_STATE) && str_starts_with($prefix, 'ZI')) {
            $account->prins = self::BCBS_VT;
        }
        if (self::is($account->prins, self::BCBS_OUT_OF_STATE) && in_array($prefix, self::BCBS_VT_PREFIXES, true)) {
            $account->prins = self::BCBS_VT;
        }

        if (self::is($account->prins, self::BCBS_OUT_OF_STATE) && self::is($account->seins, self::NYS_EMPIRE)) {
            $account->prins = $account->seins;
            $account->seins = self::SELF_PAY;
            $account->pripol = self::assigned($account->pripol, $account->secpol->toString());
            $this->messages[] = 'SWITCHEROO FOR BCBS EMPIRE ' . $account->garname . ' ' . $mrn . ' ' . $account->pripol->toString();
        }

        if (self::is($account->prins, self::BCBS_OUT_OF_STATE) && $prefix === 'YLS') {
            $account->prins = self::NYS_EMPIRE;
            $account->pripol = self::assigned($account->pripol, $middle);
        }

        if (
            self::is($account->prins, self::BCBS_VT)
            && !(in_array($prefix, self::BCBS_VT_PREFIXES, true) || str_starts_with($prefix, 'ZI'))
        ) {
            $account->prins = self::BCBS_OUT_OF_STATE;
        }

        if (self::is($account->prins, self::BCBS_VT) && !str_starts_with($middle, 'V')) {
            $this->messages[] = 'ADDING V TO 002 POLICY FOR ' . $account->garname . ' ' . $mrn . ' ' . $account->pripol->toString();
            $account->pripol = self::assigned($account->pripol, $prefix . 'V' . $middle . substr($suffix, 0, 3));
        }

        if (self::is($account->prins, self::BCBS_OUT_OF_STATE) && !$alphaPrefix) {
            $account->prins = self::MISC;
            $account->prassign = 'U';
        }

        // Empire plan: BCBS 268 primary with a YLS policy and NYS personal secondary.
        if (
            self::is($account->prins, self::BCBS_OUT_OF_STATE) && $prefix === 'YLS'
            && self::is($account->seins, self::NYS_EMPIRE_PERSONAL)
        ) {
            $account->prins = self::NYS_EMPIRE_PERSONAL;
            $account->seins = self::SELF_PAY;
            $account->pripol = self::assigned($account->pripol, $account->secpol->toString());
            $account->prgroup = $account->segroup;
            $account->secpol = PolicyNumber::none();
            $account->segroup = '';
        }

        if (
            self::is($account->seins, self::BCBS_OUT_OF_STATE)
            && is_numeric(substr($prefix, 0, 1)) && is_numeric(substr($prefix, 1, 1)) && is_numeric(substr($prefix, 2, 1))
        ) {
            $account->seassign = 'U';
        }

        if (self::is($account->prins, self::MISC) && $alphaPrefix) {
            $account->seins = self::BCBS_OUT_OF_STATE;
        }

        if (self::is($account->prins, self::MISC) && self::is($account->seins, self::MISC)) {
            $account->seins = self::HSA;
        }

        if (self::is($account->prins, self::BCBS_VT) && self::is($account->seins, self::BCBS_VT)) {
            $account->seins = self::BCBS_VT_SECOND;
        }

        if (self::is($account->prins, self::MEDICARE) && self::is($account->seins, self::CIGNA)) {
            $account->seins = self::MEDIGAP;
            $account->prgroup = '0099001     ';
            $account->seassign = 'A';
        }

        if (self::is($account->prins, $account->seins) && $account->prassign === 'A') {
            $account->seins = self::SAME_AS_PRIMARY;
            $this->messages[] = 'PRI AND SEC SAME INS ' . $account->garname . ' ' . $mrn . ' ' . $account->pripol->toString();
        }

        // P2-3.
        $account->actno = $mrn;
        $account->namekey = substr($account->garname, 0, 3);

        if (in_array($account->pripol->part1, ['X', 'UNK'], true)) {
            $account->pripol = PolicyNumber::none();
        }
        if (in_array($account->secpol->part1, ['X', 'UNK'], true)) {
            $account->secpol = PolicyNumber::none();
        }
        if (in_array(trim($account->prgroup), ['X', 'UNK', 'NA'], true)) {
            $account->prgroup = '';
        }
        if (in_array(trim($account->segroup), ['X', 'UNK', 'NA'], true)) {
            $account->segroup = '';
        }

        if (self::is($account->prins, self::MEDICAID_VT)) {
            $account->prname = $account->garname;
            $account->prrelate = $account->relate;
        }
        if (self::is($account->seins, self::MEDICAID_VT)) {
            $account->sename = $account->garname;
            $account->serelate = $account->relate;
        }

        // Medicare patients were born last century.
        if (self::is($account->prins, self::MEDICARE) && str_starts_with($account->dob, '20')) {
            $account->dob = '19' . substr($account->dob, 2);
        }

        if (self::is($account->prins, self::BCBS_OUT_OF_STATE) && $account->prassign === 'U') {
            $account->prassign = 'A';
        }
        if (self::is($account->seins, self::BCBS_OUT_OF_STATE) && $account->seassign === 'U') {
            $account->seassign = 'A';
        }

        return $this->messages;
    }

    private static function is(string $a, string $b): bool
    {
        return LegacyCode::same($a, $b);
    }

    /**
     * @param list<string> $codes
     */
    private static function isOneOf(string $code, array $codes): bool
    {
        return LegacyCode::oneOf($code, $codes);
    }

    /** Strictly between, read as a whole number ("234" .. "244" for 233 and 245). */
    private static function isNumberBetween(string $code, int $low, int $high): bool
    {
        $number = (int) $code;
        return $number > $low && $number < $high;
    }

    private static function isAlpha(string $value, int $position): bool
    {
        return ctype_alpha(substr($value, $position, 1));
    }

    private static function isRailroad(PolicyNumber $policy): bool
    {
        if ($policy->length() < 2) {
            return false;
        }
        $start = substr($policy->part1, 0, 2);
        return in_array($start, ['WA', 'MA', 'WD'], true) || str_starts_with($start, 'A');
    }

    /** The old record's assignFromString(): an empty value leaves the policy as it was. */
    private static function assigned(PolicyNumber $current, string $value): PolicyNumber
    {
        return $value === '' ? $current : PolicyNumber::fromString($value);
    }
}
