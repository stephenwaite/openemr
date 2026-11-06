<?php

/**
 * CMS Vermont customizations module bootstrap.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

use Cmsvt\OpenEMR\Modules\Customizations\Bootstrap;
use OpenEMR\Core\ModulesClassLoader;
use OpenEMR\Core\OEGlobalsBag;

$globalsBag = OEGlobalsBag::getInstance();
$classLoader = new ModulesClassLoader($globalsBag->getProjectDir());
$classLoader->registerNamespaceIfNotExists(
    'Cmsvt\\OpenEMR\\Modules\\Customizations\\',
    __DIR__ . DIRECTORY_SEPARATOR . 'src'
);

(new Bootstrap($globalsBag->getKernel()->getEventDispatcher()))->subscribeToEvents();
