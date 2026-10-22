<?php

/**
 * Step 5 (COBOL rri247 and rri247x): each account's insurances and relate
 * codes for the biller to look over, two automatic corrections (an adult
 * female guarantor who is her own subscriber gets K), the w2 listing, and the
 * accounts whose subscriber doesn't match the guarantor.
 *
 * Changes from 7.0.2: a blank secondary relate code counts as blank (7.0.2
 * compared it with ' ', which the database gives back as ''), and names are
 * compared without spaces (the account file has "DOE; JANE" for a subscriber
 * and "DOE;JANE" for the guarantor). 7.0.2 returned before the mismatch check
 * whenever w2 had lines; the command here always runs it.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Review;

use Cmsvt\OpenEMR\Modules\RriBilling\Account\AccountRecord;
use Cmsvt\OpenEMR\Modules\RriBilling\Account\LegacyCode;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\AccountFile;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\OrdfileLine;

final class RelateReview
{
    private const SELF_PAY = '001';
    private const ADULT_FEMALE = 'K';

    /** @var list<array{string, string, string, string}> type, relate, name, account */
    private array $rows = [];

    /** @var list<string> */
    private array $w2 = [];

    public function __construct(private readonly AccountFile $accounts)
    {
    }

    /**
     * @param array<int, OrdfileLine> $firstCharges each account's first charge
     */
    public function review(array $firstCharges): RelateReviewResult
    {
        $this->rows = [];
        $this->w2 = [];
        $missing = [];
        $mismatches = [];
        foreach ($firstCharges as $first) {
            $account = $this->accounts->find(str_pad($first->ord8, 8, '0', STR_PAD_LEFT));
            if ($account === null) {
                $missing[] = $first->ord8;
                continue;
            }
            if (LegacyCode::same($account->prins, self::SELF_PAY)) {
                continue;
            }
            $this->relate($account);
            array_push($mismatches, ...self::mismatches($account));
        }
        return new RelateReviewResult($this->rows, $this->w2, $mismatches, $missing);
    }

    private function relate(AccountRecord $account): void
    {
        if ($account->prrelate === self::ADULT_FEMALE && ($account->serelate === self::ADULT_FEMALE || trim($account->serelate) === '')) {
            return;
        }
        if ($account->prrelate === self::ADULT_FEMALE) {
            $this->secondary($account);
        } elseif (self::sameName($account->prname, $account->garname) && $account->relate === self::ADULT_FEMALE) {
            $account->prrelate = self::ADULT_FEMALE;
            $this->accounts->save($account);
            $this->secondary($account);
        } else {
            $this->rows[] = ['PRIMARY', $account->prrelate, $account->prname, str_pad($account->actno, 8, '0', STR_PAD_LEFT)];
            if (trim($account->serelate) === '' || $account->serelate === self::ADULT_FEMALE) {
                $this->w2[] = AccountFileText::line($account);
            } else {
                $this->secondary($account);
            }
        }
    }

    private function secondary(AccountRecord $account): void
    {
        if ($account->serelate === self::ADULT_FEMALE) {
            $this->w2[] = AccountFileText::line($account);
            return;
        }
        if (self::sameName($account->sename, $account->garname) && $account->relate === self::ADULT_FEMALE) {
            $this->w2[] = AccountFileText::line($account);
            $account->serelate = self::ADULT_FEMALE;
            $this->accounts->save($account);
            return;
        }
        if (LegacyCode::same($account->seins, self::SELF_PAY)) {
            $this->w2[] = AccountFileText::line($account);
            return;
        }
        $this->rows[] = ['SECOND', $account->serelate, $account->sename, str_pad($account->actno, 8, '0', STR_PAD_LEFT)];
    }

    /**
     * rri247x: the subscriber isn't the guarantor or the relate codes differ.
     *
     * @return list<array{string, string}> the guarantor's line and the subscriber's
     */
    private static function mismatches(AccountRecord $account): array
    {
        $actno = str_pad($account->actno, 8, '0', STR_PAD_LEFT);
        $found = [];
        if (!(self::sameName($account->prname, $account->garname) && $account->relate === $account->prrelate)) {
            $found[] = ['PRIMARY ' . $account->garname . ' ' . $account->relate . ' ' . $actno, 'PRIMARY ' . $account->prname . ' ' . $account->prrelate];
        }
        if (
            !LegacyCode::same($account->seins, self::SELF_PAY)
            && !(self::sameName($account->sename, $account->garname) && $account->relate === $account->serelate)
        ) {
            $found[] = ['SECONDARY ' . $account->garname . ' ' . $account->relate . ' ' . $actno, 'SECONDARY ' . $account->sename . ' ' . $account->serelate];
        }
        return $found;
    }

    private static function sameName(string $a, string $b): bool
    {
        return preg_replace('/\s+/', '', $a) === preg_replace('/\s+/', '', $b);
    }
}
