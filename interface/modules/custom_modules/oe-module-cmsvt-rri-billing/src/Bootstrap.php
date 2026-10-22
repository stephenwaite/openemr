<?php

/**
 * Subscribes the RRI billing module to OpenEMR events.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling;

use Cmsvt\OpenEMR\Modules\RriBilling\Command\Rri245Command;
use Cmsvt\OpenEMR\Modules\RriBilling\Command\Rri247Command;
use Cmsvt\OpenEMR\Modules\RriBilling\Command\Rri248Command;
use Cmsvt\OpenEMR\Modules\RriBilling\Command\Rri250Command;
use Cmsvt\OpenEMR\Modules\RriBilling\Globals\RriGlobals;
use OpenEMR\Events\Command\CommandRunnerFilterEvent;
use OpenEMR\Events\Globals\GlobalsInitializedEvent;
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
            (new RriGlobals())->register(...)
        );
        $this->eventDispatcher->addListener(
            CommandRunnerFilterEvent::EVENT_NAME,
            static function (CommandRunnerFilterEvent $event): void {
                $event->setCommand(Rri248Command::class, new Rri248Command());
                $event->setCommand(Rri245Command::class, new Rri245Command());
                $event->setCommand(Rri247Command::class, new Rri247Command());
                $event->setCommand(Rri250Command::class, new Rri250Command());
            }
        );
    }
}
