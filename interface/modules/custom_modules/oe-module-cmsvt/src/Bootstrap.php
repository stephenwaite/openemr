<?php

/**
 * Wires the CMS Vermont customizations into OpenEMR's event system.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations;

use Cmsvt\OpenEMR\Modules\Customizations\Billing\BillingManagerDefaults;
use Cmsvt\OpenEMR\Modules\Customizations\Billing\CollectionsReportOptions;
use Cmsvt\OpenEMR\Modules\Customizations\Command\IncreaseFeesCommand;
use Cmsvt\OpenEMR\Modules\Customizations\Command\UpdateX12SftpPasswordCommand;
use Cmsvt\OpenEMR\Modules\Customizations\Eligibility\EligibilityProviderOverride;
use Cmsvt\OpenEMR\Modules\Customizations\Encounter\EncounterFormLabels;
use Cmsvt\OpenEMR\Modules\Customizations\Encounter\EncounterReportOptions;
use Cmsvt\OpenEMR\Modules\Customizations\Globals\CmsvtGlobals;
use Cmsvt\OpenEMR\Modules\Customizations\Labs\LabOptions;
use Cmsvt\OpenEMR\Modules\Customizations\Menu\CmsvtMenu;
use Cmsvt\OpenEMR\Modules\Customizations\Statements\PatientStatementLog;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\Events\Billing\BillingManagerDefaultsFilterEvent;
use OpenEMR\Events\Billing\CollectionsReportFilterEvent;
use OpenEMR\Events\Billing\EligibilityRequestFilterEvent;
use OpenEMR\Events\Billing\PatientStatementSavedEvent;
use OpenEMR\Events\Billing\StatementPrintSuggestionFilterEvent;
use OpenEMR\Events\Command\CommandRunnerFilterEvent;
use OpenEMR\Events\Core\TemplatePageEvent;
use OpenEMR\Events\Encounter\EncounterReportFilterEvent;
use OpenEMR\Events\Globals\GlobalsInitializedEvent;
use OpenEMR\Events\Orders\Hl7ResultsImportFilterEvent;
use OpenEMR\Events\Orders\LabResultsListFilterEvent;
use OpenEMR\Menu\MenuEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final readonly class Bootstrap
{
    public function __construct(private EventDispatcherInterface $eventDispatcher)
    {
    }

    public function subscribeToEvents(): void
    {
        $this->eventDispatcher->addListener(
            CommandRunnerFilterEvent::EVENT_NAME,
            $this->registerCommands(...)
        );
        $this->eventDispatcher->addListener(
            GlobalsInitializedEvent::EVENT_HANDLE,
            (new CmsvtGlobals())->register(...)
        );
        $globals = OEGlobalsBag::getInstance();
        $this->eventDispatcher->addListener(
            EligibilityRequestFilterEvent::EVENT_NAME,
            (new EligibilityProviderOverride($globals))->apply(...)
        );
        $this->eventDispatcher->addListener(
            BillingManagerDefaultsFilterEvent::EVENT_NAME,
            (new BillingManagerDefaults($globals))->apply(...)
        );
        $this->eventDispatcher->addListener(
            CollectionsReportFilterEvent::EVENT_NAME,
            (new CollectionsReportOptions($globals))->apply(...)
        );
        $this->eventDispatcher->addListener(
            EncounterReportFilterEvent::EVENT_NAME,
            (new EncounterReportOptions($globals))->apply(...)
        );
        $this->eventDispatcher->addListener(
            TemplatePageEvent::RENDER_EVENT,
            (new EncounterFormLabels($globals))->apply(...)
        );
        $this->eventDispatcher->addListener(
            MenuEvent::MENU_UPDATE,
            (new CmsvtMenu())->apply(...)
        );
        $labOptions = new LabOptions($globals);
        $this->eventDispatcher->addListener(
            Hl7ResultsImportFilterEvent::EVENT_NAME,
            $labOptions->applyToImport(...)
        );
        $this->eventDispatcher->addListener(
            LabResultsListFilterEvent::EVENT_NAME,
            $labOptions->applyToList(...)
        );
        $statementLog = new PatientStatementLog();
        $this->eventDispatcher->addListener(
            PatientStatementSavedEvent::EVENT_NAME,
            $statementLog->record(...)
        );
        $this->eventDispatcher->addListener(
            StatementPrintSuggestionFilterEvent::EVENT_NAME,
            $statementLog->suggestPrint(...)
        );
    }

    public function registerCommands(CommandRunnerFilterEvent $event): void
    {
        $event->setCommand(UpdateX12SftpPasswordCommand::class, new UpdateX12SftpPasswordCommand());
        $event->setCommand(IncreaseFeesCommand::class, new IncreaseFeesCommand());
    }
}
