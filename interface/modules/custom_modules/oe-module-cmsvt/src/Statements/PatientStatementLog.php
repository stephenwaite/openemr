<?php

/**
 * Records statements sent in patient_statements (see table.sql) and flags
 * patients whose emailed statements keep going unpaid, so they get a printed
 * statement instead.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Statements;

use OpenEMR\Billing\StatementDeliveryMethod;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Events\Billing\PatientStatementSavedEvent;
use OpenEMR\Events\Billing\StatementPrintSuggestionFilterEvent;

final readonly class PatientStatementLog
{
    /** An emailed statement counts as unpaid once it is this old with no payment since. */
    private const UNPAID_AFTER_DAYS = 21;

    /** This many unpaid emailed statements means email is not reaching the patient. */
    private const PRINT_AFTER_UNPAID_EMAILS = 2;

    public function record(PatientStatementSavedEvent $event): void
    {
        QueryUtils::sqlInsert(
            "INSERT INTO patient_statements " .
            "(pid, encounter, statement_date, method, amount, document_id, created_by) " .
            "VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $event->pid,
                $event->encounter,
                $event->statementDate->format('Y-m-d'),
                $event->method->value,
                $event->amount,
                $event->documentId,
                $event->userId,
            ]
        );
    }

    public function suggestPrint(StatementPrintSuggestionFilterEvent $event): void
    {
        // One row per statement: an emailed statement is unpaid if no payment has
        // been posted on its encounter with a check date on or after it was sent.
        $rows = QueryUtils::fetchRecords(
            "SELECT ps.pid, COUNT(*) AS unpaid_count " .
            "FROM patient_statements ps " .
            "LEFT JOIN (" .
            "    SELECT ps2.id AS statement_id, SUM(ar.pay_amount) AS paid_amt " .
            "    FROM patient_statements ps2 " .
            "    JOIN ar_activity ar ON ar.pid = ps2.pid AND ar.encounter = ps2.encounter " .
            "        AND ar.deleted IS NULL AND ar.pay_amount > 0 " .
            "    JOIN ar_session ars ON ars.session_id = ar.session_id " .
            "        AND ars.check_date >= ps2.statement_date " .
            "    GROUP BY ps2.id" .
            ") paid ON paid.statement_id = ps.id " .
            "WHERE ps.method = ? " .
            "AND ps.statement_date <= CURDATE() - INTERVAL " . self::UNPAID_AFTER_DAYS . " DAY " .
            "AND (paid.paid_amt IS NULL OR paid.paid_amt = 0) " .
            "GROUP BY ps.pid " .
            "HAVING COUNT(*) >= ?",
            [StatementDeliveryMethod::Email->value, self::PRINT_AFTER_UNPAID_EMAILS]
        );
        foreach ($rows as $row) {
            $pid = $row['pid'] ?? null;
            $count = $row['unpaid_count'] ?? null;
            if (is_numeric($pid) && is_numeric($count)) {
                $event->suggestPrint((int) $pid, (int) $count);
            }
        }
    }
}
