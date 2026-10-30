<?php

/**
 * Dispatched after a patient statement is saved to the patient's documents.
 *
 * Listeners can use it to keep their own record of statements sent.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Events\Billing;

use DateTimeImmutable;
use OpenEMR\Billing\StatementDeliveryMethod;
use Symfony\Contracts\EventDispatcher\Event;

final class PatientStatementSavedEvent extends Event
{
    public const EVENT_NAME = 'billing.patient_statement.saved';

    public function __construct(
        public readonly int $pid,
        public readonly int $encounter,
        public readonly DateTimeImmutable $statementDate,
        public readonly StatementDeliveryMethod $method,
        public readonly float $amount,
        public readonly int $documentId,
        public readonly int $userId,
    ) {
    }
}
