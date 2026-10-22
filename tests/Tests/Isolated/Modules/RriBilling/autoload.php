<?php

/**
 * Registers the RRI billing module's namespace for isolated tests, which run
 * without the module system that normally does it.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

$rriModuleSrc = dirname(__DIR__, 5) . '/interface/modules/custom_modules/oe-module-cmsvt-rri-billing/src/';
if (!is_dir($rriModuleSrc)) {
    throw new RuntimeException('oe-module-cmsvt-rri-billing source not found at ' . $rriModuleSrc);
}
spl_autoload_register(static function (string $class) use ($rriModuleSrc): void {
    $prefix = 'Cmsvt\\OpenEMR\\Modules\\RriBilling\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = $rriModuleSrc . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
