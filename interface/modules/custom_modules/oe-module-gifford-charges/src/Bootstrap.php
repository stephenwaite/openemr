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

use Cmsvt\OpenEMR\Modules\GiffordCharges\Command\ImportPayersCommand;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Globals\GiffordGlobals;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Review\GiffordMenu;
use OpenEMR\Events\Command\CommandRunnerFilterEvent;
use OpenEMR\Events\Globals\GlobalsInitializedEvent;
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
    }
}
