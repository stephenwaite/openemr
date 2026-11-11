<?php

/**
 * Rotates the SFTP password stored for an X12 partner (e.g. the NGS clearinghouse login).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Command;

use OpenEMR\BC\ServiceContainer;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Core\OEGlobalsBag;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

final class UpdateX12SftpPasswordCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('cmsvt:x12-sftp-password')
            ->setDescription('Update the encrypted SFTP password of the X12 partner with the given SFTP login')
            ->addUsage('--site=default --login=<sftp login>')
            ->addUsage('--site=default --login=<sftp login> --password-stdin < password.txt')
            ->addOption('site', null, InputOption::VALUE_REQUIRED, 'Name of site', 'default')
            ->addOption('login', null, InputOption::VALUE_REQUIRED, 'x12_partners.x12_sftp_login of the partner to update')
            ->addOption('password-stdin', null, InputOption::VALUE_NONE, 'Read the new password from STDIN instead of prompting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $login = $input->getOption('login');
        if (!is_string($login) || $login === '') {
            $output->writeln('<error>--login is required.</error>');
            return Command::INVALID;
        }

        if (!OEGlobalsBag::getInstance()->getBoolean('auto_sftp_claims_to_x12_partner')) {
            $output->writeln('<error>auto_sftp_claims_to_x12_partner is not enabled for this site; nothing to update.</error>');
            return Command::FAILURE;
        }

        $password = $this->readPassword($input, $output);
        if ($password === '') {
            $output->writeln('<error>The new password is empty.</error>');
            return Command::INVALID;
        }

        $partnerIds = QueryUtils::fetchTableColumn(
            'SELECT `id` FROM `x12_partners` WHERE `x12_sftp_login` = ?',
            'id',
            [$login]
        );
        if (count($partnerIds) !== 1) {
            $output->writeln(sprintf(
                '<error>Expected exactly one X12 partner with that SFTP login, found %d.</error>',
                count($partnerIds)
            ));
            return Command::FAILURE;
        }

        // Must match X12RemoteTracker and EDI270, which read it back with decryptFromDatabase().
        QueryUtils::sqlStatementThrowException(
            'UPDATE `x12_partners` SET `x12_sftp_pass` = ? WHERE `id` = ?',
            [ServiceContainer::getCrypto()->encryptForDatabase($password), $partnerIds[0]]
        );

        $output->writeln('<info>SFTP password updated.</info>');
        return Command::SUCCESS;
    }

    private function readPassword(InputInterface $input, OutputInterface $output): string
    {
        if ($input->getOption('password-stdin') === true) {
            $line = fgets(STDIN);
            return $line === false ? '' : rtrim($line, "\r\n");
        }

        $question = (new Question('New SFTP password: '))->setHidden(true)->setHiddenFallback(false);
        $answer = (new QuestionHelper())->ask($input, $output, $question);
        return is_string($answer) ? $answer : '';
    }
}
