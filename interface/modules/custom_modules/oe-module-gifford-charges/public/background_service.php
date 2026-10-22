<?php

/**
 * Entry point of the Gifford_Charge_Import background service (see table.sql): the
 * site's background services cron runs it hourly while the module is enabled.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges;

use Cmsvt\OpenEMR\Modules\GiffordCharges\Globals\GiffordGlobals;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Parse\ChargeRowParser;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Payer\PayerMap;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Post\ChargeLineRepository;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Post\ChargePoster;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Post\PostSettings;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Post\PriceBook;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Post\SiteWriter;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Reader\ChargeFileReader;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Stage\ChargeBatchRepository;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Stage\ChargeFileStager;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Stage\FileOutcome;
use OpenEMR\BC\ServiceContainer;
use OpenEMR\Core\ModulesClassLoader;
use OpenEMR\Core\OEGlobalsBag;
use Psr\Log\LoggerInterface;

/**
 * Stage every new charge file in the configured directory, then post the lines that are
 * ready. Logs counts only; a directory, move or database problem is thrown for the
 * background runner to log.
 * table.sql names it as Cmsvt\OpenEMR\Modules\GiffordCharges\runBackgroundService.
 */
function runBackgroundService(): void
{
    $globals = OEGlobalsBag::getInstance();
    // The module's bootstrap registers this too when the module is enabled; a background
    // run must not depend on it.
    (new ModulesClassLoader($globals->getProjectDir()))->registerNamespaceIfNotExists(
        'Cmsvt\\OpenEMR\\Modules\\GiffordCharges\\',
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src'
    );
    $logger = ServiceContainer::getLogger();

    stageNewFiles($globals, $logger);
    postReadyLines($globals, $logger);
}

function stageNewFiles(OEGlobalsBag $globals, LoggerInterface $logger): void
{
    $dropDir = trim($globals->getString(GiffordGlobals::DROP_DIR));
    if ($dropDir === '') {
        $logger->notice('Gifford charge import: no charge file directory is set; no files read');
        return;
    }

    $stager = new ChargeFileStager(
        new ChargeFileReader(),
        new ChargeRowParser(),
        new ChargeBatchRepository(),
        ServiceContainer::getClock(),
    );
    foreach ($stager->run($dropDir) as $file) {
        $context = [
            'file' => $file->fileName,
            'outcome' => $file->outcome->name,
            'batch' => $file->batchId,
            'lines' => $file->lineCount,
            'held' => $file->heldCount,
        ];
        // The batch table records every file; a file that could not be read also
        // needs a person, so it is logged at the default (WARNING) level.
        if ($file->outcome === FileOutcome::Failed) {
            $logger->warning('Gifford charge import: a charge file could not be read; see gifford_charge_batch', $context);
        } else {
            $logger->info('Gifford charge import: file handled', $context);
        }
    }
}

/**
 * Held lines are tried again every run, so one held for an unmapped payer posts on the
 * run after the payer is mapped.
 */
function postReadyLines(OEGlobalsBag $globals, LoggerInterface $logger): void
{
    $settings = PostSettings::load(
        $globals->getInt(GiffordGlobals::FACILITY_ID),
        $globals->getInt(GiffordGlobals::BILLING_FACILITY_ID)
    );
    if ($settings === null) {
        $logger->notice('Gifford charge import: the facility or billing facility is not set; lines are staged but not posted');
        return;
    }
    $poster = new ChargePoster(
        new ChargeLineRepository(),
        new ChargeRowParser(),
        new PayerMap(),
        new PriceBook(),
        new SiteWriter(),
        ServiceContainer::getClock(),
    );
    $logger->info('Gifford charge import: posting run', $poster->run($settings)->toArray());
}
