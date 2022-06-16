<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2022  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli\Command\Self;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Dcm\Cli\Config;
use Dcm\Cli\Service\Updater;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Class UpdateCommand
 */
class UpdateCommand extends Command
{
    protected static $defaultName = 'self:update';
    protected static $defaultDescription = 'Updates Dcm to the latest version';

    /**
     * @var Updater
     */
    private $updater;

    /**
     * @var Config
     */
    private $config;

    /**
     * @param Updater $updater
     * @param Config $config
     * @param string|null $name
     */
    public function __construct(Updater $updater, Config $config, string $name = null)
    {
        $this->updater = $updater;
        $this->config = $config;
        parent::__construct($name);
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setAliases(['self-update', 'selfupdate']);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $applicationName = $this->config->getData('name');
        $currentVersion = $this->config->getData('version');

        if (!extension_loaded('Phar') || !($localPhar = \Phar::running(false))) {
            $output->writeln(sprintf(
                '<error>This instance of the %s was not installed as a Phar archive.</error>',
                $applicationName
            ));
            return Command::FAILURE;
        }

        $output->writeln(sprintf(
            'Checking for %s updates (current version: <info>%s</info>)',
            $applicationName,
            $currentVersion
        ));

        if (!is_writable($localPhar)) {
            $output->writeln('<error>Cannot update as the Phar file is not writable: ' . $localPhar.'</error>');
            return Command::FAILURE;
        }

        try {
            $newVersion = $this->updater->findLatestVersion();
        } catch (\Exception $e) {
            $output->writeln('<error>Failed to find a new version: ' . $e->getMessage().'</error>');
            return Command::FAILURE;
        }

        if ($currentVersion == $newVersion) {
            $output->writeln('<info>No updates found</info>');
            return Command::SUCCESS;
        }
        $output->writeln(sprintf('Version <info>%s</info> is available.', $newVersion));
        if (version_compare($currentVersion, $newVersion, '>')) {
            $output->writeln('<comment>'.sprintf(
                    ' The new version is lower than the current installed version (new version is %s, current version is %s).',
                    $newVersion,
                    $currentVersion,
                    PHP_VERSION
                ).'</comment>');
        }

        /** @var \Symfony\Component\Console\Helper\QuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');
        $question = new ConfirmationQuestion(sprintf('Update to version <info>%s</info>? (Y/n) ', $newVersion), true);
        if (!$questionHelper->ask($input, $output, $question)) {
            return Command::SUCCESS;
        }
        $output->writeln(sprintf('Updating to version %s', $newVersion));

        $output->writeln(sprintf(
            'The %s has been successfully updated to version <info>%s</info>',
            $applicationName,
            $newVersion
        ));

        try {
            $this->updater->update($newVersion);
        } catch (\Exception $e) {
            $output->writeln('<error>Failed to update: ' . $e->getMessage().'</error>');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}