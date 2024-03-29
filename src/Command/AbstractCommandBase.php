<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2022  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli\Command;

use Dcm\Cli\Config;
use Dcm\Cli\Service\Updater;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Class AbstractCommandBase
 */
abstract class AbstractCommandBase extends Command
{
    /** @var bool */
    private static $checkedUpdates;

    /**
     * @var Updater
     */
    protected $updater;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @param Updater $updater
     * @param string|null $name
     */
    public function __construct(
        Updater $updater,
        string $name = null
    ) {
        $this->updater = $updater;
        $this->config = $updater->getConfig();
        parent::__construct($name);
    }
    /**
     * {@inheritdoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $this->checkUpdates($input, $output);
        parent::interact($input, $output);
    }

    /**
     * Check for updates.
     */
    protected function checkUpdates(InputInterface $input, OutputInterface $output)
    {
        if (static::$checkedUpdates) {
            return;
        }
        static::$checkedUpdates = true;

        $currentVersion = $this->config->getData('version');

        if (!extension_loaded('Phar') || !($localPhar = \Phar::running(false))) {
            return;
        }

        if (!is_writable($localPhar)) {
            return;
        }

        // Determine time, after which updates can be checked.
        $timestamp = time();
        $embargoTime = $timestamp - (int) $this->config->getData('update_check_interval');
        $lastChecked = (int) $this->config->getLocalConfig('update_last_checked');

        if ($lastChecked > $embargoTime) {
            return;
        }

        try {
            $newVersion = $this->updater->findLatestVersion();
        } catch (\Exception $e) {
            return;
        }

        if (version_compare($currentVersion, $newVersion, '>=')) {
            return;
        }

        /** @var \Symfony\Component\Console\Helper\QuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            sprintf(
                'New version available <info>%s</info> update? (current <info>%s</info>) <comment>(Y/n)</comment> ',
                $newVersion,
                $currentVersion
            ),
            true
        );
        if (!$questionHelper->ask($input, $output, $question)) {
            return;
        }
        try {
            $this->updater->update($newVersion);
        } catch (\Exception $e) {
            $output->writeln('<error>Failed to update: ' . $e->getMessage().'</error>');
            return;
        }

        $output->writeln(sprintf(
            'Successfully updated to the version <info>%s</info>. Re-run the command, please.',
            $newVersion
        ));

        $this->config->saveLocalConfig('update_last_checked', (string) $timestamp);
        exit(0);
    }
}