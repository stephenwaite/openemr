<?php

/**
 * Gifford charge import module bootstrap.
 *
 * Imports Gifford Medical Center's radiology charge files (delivered by SFTP)
 * into site 2400 as patients, policies, encounters and billing lines, after
 * each file is reviewed and approved.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

use OpenEMR\Core\ModulesClassLoader;
use OpenEMR\Core\OEGlobalsBag;

$classLoader = new ModulesClassLoader(OEGlobalsBag::getInstance()->getProjectDir());
$classLoader->registerNamespaceIfNotExists(
    'Cmsvt\\OpenEMR\\Modules\\GiffordCharges\\',
    __DIR__ . DIRECTORY_SEPARATOR . 'src'
);
