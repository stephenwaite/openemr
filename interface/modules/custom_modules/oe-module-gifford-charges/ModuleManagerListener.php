<?php

/**
 * Module Manager actions for the Gifford charge import: the hourly import runs only
 * while the module is enabled.
 *
 * Module Manager instantiates this class by name and logs anything it throws; the
 * action it reports has already happened.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

use OpenEMR\BC\ServiceContainer;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Core\AbstractModuleActionListener;

class ModuleManagerListener extends AbstractModuleActionListener
{
    private const SERVICE = 'Gifford_Charge_Import';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param string $methodName the Module Manager action (enable, disable, unregister, ...)
     * @param $modId
     * @return string the action status
     */
    public function moduleManagerAction($methodName, $modId, string $currentActionStatus = 'Success'): string
    {
        match ($methodName) {
            'enable' => $this->setServiceActive(true),
            'disable' => $this->setServiceActive(false),
            'unregister' => QueryUtils::sqlStatementThrowException(
                'DELETE FROM `background_services` WHERE `name` = ?',
                [self::SERVICE]
            ),
            default => null,
        };

        return $currentActionStatus;
    }

    public static function getModuleNamespace(): string
    {
        return 'Cmsvt\\OpenEMR\\Modules\\GiffordCharges\\';
    }

    public static function initListenerSelf(): ModuleManagerListener
    {
        return new self();
    }

    private function setServiceActive(bool $active): void
    {
        QueryUtils::sqlStatementThrowException(
            'UPDATE `background_services` SET `active` = ? WHERE `name` = ?',
            [$active ? 1 : 0, self::SERVICE]
        );
        ServiceContainer::getLogger()->info('Gifford charge import background service switched', ['active' => $active]);
    }
}
