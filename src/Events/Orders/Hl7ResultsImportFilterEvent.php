<?php

/**
 * Lets listeners adjust how lab results (HL7 ORU) are matched and filed.
 *
 * Dispatched by receive_hl7_results.inc.php. With no listener, results are
 * imported exactly as before.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Events\Orders;

use Symfony\Contracts\EventDispatcher\Event;

final class Hl7ResultsImportFilterEvent extends Event
{
    public const EVENT_NAME = 'orders.hl7_results_import.filter';

    private bool $matchPatientByPubpid = false;
    private bool $externalIdFromVisitNumber = false;
    private bool $createEncounterForResultsOnlyOrder = true;
    private bool $notifyProviderOfResultsOnlyOrder = true;

    /** Match the patient on PID-3 (the lab's copy of our MRN, patient_data.pubpid) alone. */
    public function matchPatientByPubpid(): bool
    {
        return $this->matchPatientByPubpid;
    }

    public function setMatchPatientByPubpid(bool $match): void
    {
        $this->matchPatientByPubpid = $match;
    }

    /** For orders created from results, store the visit number (PID-18) as the external ID instead of OBR-3. */
    public function externalIdFromVisitNumber(): bool
    {
        return $this->externalIdFromVisitNumber;
    }

    public function setExternalIdFromVisitNumber(bool $fromVisitNumber): void
    {
        $this->externalIdFromVisitNumber = $fromVisitNumber;
    }

    /** Create an encounter for a results-only order when none is found near the report date. */
    public function createEncounterForResultsOnlyOrder(): bool
    {
        return $this->createEncounterForResultsOnlyOrder;
    }

    public function setCreateEncounterForResultsOnlyOrder(bool $create): void
    {
        $this->createEncounterForResultsOnlyOrder = $create;
    }

    /** Send the ordering provider a notice when an order is created from results. */
    public function notifyProviderOfResultsOnlyOrder(): bool
    {
        return $this->notifyProviderOfResultsOnlyOrder;
    }

    public function setNotifyProviderOfResultsOnlyOrder(bool $notify): void
    {
        $this->notifyProviderOfResultsOnlyOrder = $notify;
    }
}
