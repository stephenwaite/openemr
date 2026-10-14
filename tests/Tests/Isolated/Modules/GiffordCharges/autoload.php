<?php

/**
 * Registers the Gifford charge import module's namespace for isolated tests,
 * which run without the module system that normally does it.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

$giffordModuleSrc = dirname(__DIR__, 5) . '/interface/modules/custom_modules/oe-module-gifford-charges/src/';
if (!is_dir($giffordModuleSrc)) {
    throw new RuntimeException('oe-module-gifford-charges source not found at ' . $giffordModuleSrc);
}
spl_autoload_register(static function (string $class) use ($giffordModuleSrc): void {
    $prefix = 'Cmsvt\\OpenEMR\\Modules\\GiffordCharges\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = $giffordModuleSrc . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
