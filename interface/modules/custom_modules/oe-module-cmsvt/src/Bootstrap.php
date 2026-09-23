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

use Cmsvt\OpenEMR\Modules\Customizations\Command\UpdateX12SftpPasswordCommand;
use OpenEMR\Events\Command\CommandRunnerFilterEvent;
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
    }

    public function registerCommands(CommandRunnerFilterEvent $event): void
    {
        $event->setCommand(UpdateX12SftpPasswordCommand::class, new UpdateX12SftpPasswordCommand());
    }
}
