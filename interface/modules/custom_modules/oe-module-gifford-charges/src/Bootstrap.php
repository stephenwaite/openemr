<?php

/**
 * Subscribes the Gifford charge import module to OpenEMR events.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges;

use Cmsvt\OpenEMR\Modules\GiffordCharges\Billing\GiffordClaimRules;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Command\ImportPayersCommand;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Globals\GiffordGlobals;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Review\GiffordMenu;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\Events\Billing\Claim837PRulesEvent;
use OpenEMR\Events\Command\CommandRunnerFilterEvent;
use OpenEMR\Events\Globals\GlobalsInitializedEvent;
use OpenEMR\Menu\MenuEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final readonly class Bootstrap
{
    /** After the site's own claim rules (the CMS module's listen at 0), so this wraps them. */
    private const CLAIM_RULES_PRIORITY = -10;

    public function __construct(private EventDispatcherInterface $eventDispatcher, private OEGlobalsBag $globals)
    {
    }

    public function subscribeToEvents(): void
    {
        $this->eventDispatcher->addListener(
            GlobalsInitializedEvent::EVENT_HANDLE,
            (new GiffordGlobals())->register(...)
        );
        $this->eventDispatcher->addListener(
            MenuEvent::MENU_UPDATE,
            (new GiffordMenu())->apply(...)
        );
        $this->eventDispatcher->addListener(
            CommandRunnerFilterEvent::EVENT_NAME,
            static function (CommandRunnerFilterEvent $event): void {
                $event->setCommand(ImportPayersCommand::class, new ImportPayersCommand());
            }
        );
        $globals = $this->globals;
        $this->eventDispatcher->addListener(
            Claim837PRulesEvent::EVENT_NAME,
            static function (Claim837PRulesEvent $event) use ($globals): void {
                $facilityId = $globals->getInt(GiffordGlobals::FACILITY_ID);
                if (GiffordClaimRules::isGiffordEncounter($event->claim, $facilityId)) {
                    $event->setRules(new GiffordClaimRules($event->getRules(), $facilityId));
                }
            },
            self::CLAIM_RULES_PRIORITY
        );
    }
}
