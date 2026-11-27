<?php

/**
 * Signs in to the Vermont Medicaid portal with the X12 partner's login, sending nothing.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Command;

use Cmsvt\OpenEMR\Modules\Customizations\Billing\ClaimOptions;
use Cmsvt\OpenEMR\Modules\Customizations\Billing\VtMedicaidPortal;
use Cmsvt\OpenEMR\Modules\Customizations\Globals\CmsvtGlobals;
use OpenEMR\BC\ServiceContainer;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Core\OEGlobalsBag;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class CheckVtMedicaidPortalCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('cmsvt:vtmedicaid-check')
            ->setDescription('Sign in to the Vermont Medicaid portal as the claim upload does and open its Upload Files page; sends nothing')
            ->addUsage('--site=default')
            ->addOption('site', null, InputOption::VALUE_REQUIRED, 'Name of site', 'default');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $partners = QueryUtils::fetchRecords('SELECT `name`, `x12_sftp_host`, `x12_sftp_login`, `x12_sftp_pass` FROM `x12_partners` ORDER BY `id`');
        $partners = array_values(array_filter(
            $partners,
            static fn (array $p): bool => is_string($p['x12_sftp_host'] ?? null) && VtMedicaidPortal::isPortalHost($p['x12_sftp_host'])
        ));
        if (count($partners) !== 1) {
            $output->writeln(sprintf('<error>Expected one X12 partner with SFTP host %s, found %d.</error>', VtMedicaidPortal::HOST, count($partners)));
            return Command::FAILURE;
        }
        $partner = $partners[0];
        $login = is_string($partner['x12_sftp_login'] ?? null) ? $partner['x12_sftp_login'] : '';
        $encrypted = is_string($partner['x12_sftp_pass'] ?? null) ? $partner['x12_sftp_pass'] : '';
        $password = $encrypted === '' ? '' : ServiceContainer::getCrypto()->decryptFromDatabase($encrypted);

        $result = (new VtMedicaidPortal(ClaimOptions::portalClient(), $login, $password))->check();
        foreach ($result->messages as $message) {
            $output->writeln($result->isDelivered() ? '<info>' . $message . '</info>' : '<error>' . $message . '</error>');
        }
        if (!OEGlobalsBag::getInstance()->getBoolean(CmsvtGlobals::CLAIM_VTMEDICAID_PORTAL)) {
            $output->writeln('Note: "Claims: Send Vermont Medicaid Through Its Portal" is off for this site, so claim files still go by SFTP.');
        }

        return $result->isDelivered() ? Command::SUCCESS : Command::FAILURE;
    }
}
