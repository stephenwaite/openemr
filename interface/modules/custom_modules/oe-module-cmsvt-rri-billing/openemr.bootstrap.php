<?php

/**
 * RRI billing module bootstrap.
 *
 * Imports RRMC's daily charge file (the fixed-width ##/++/$$ export first built for
 * Rutland Radiologists) into a site as patients, policies, encounters and billing
 * lines, through console steps a biller runs by hand. Ported from the 2024
 * oe-module-cmsvt-rri-billing that ran on OpenEMR 7.0.2; the oncology (eis) and
 * radiology rules are per-site settings.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

use Cmsvt\OpenEMR\Modules\RriBilling\Bootstrap;
use OpenEMR\Core\ModulesClassLoader;
use OpenEMR\Core\OEGlobalsBag;

$globalsBag = OEGlobalsBag::getInstance();
$classLoader = new ModulesClassLoader($globalsBag->getProjectDir());
$classLoader->registerNamespaceIfNotExists(
    'Cmsvt\\OpenEMR\\Modules\\RriBilling\\',
    __DIR__ . DIRECTORY_SEPARATOR . 'src'
);

(new Bootstrap($globalsBag->getKernel()->getEventDispatcher()))->subscribeToEvents();
