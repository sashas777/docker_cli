<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2022  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli\Command\LocalServices;

use Dcm\Cli\Config;
use Dcm\Cli\Service\Images\Database;
use Dcm\Cli\Service\Updater;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Dcm\Cli\Command\AbstractCommandBase;

/**
 * Class InfoCommand
 */
class InfoCommand extends AbstractCommandBase
{
    protected static $defaultName = 'services:info';
    protected static $defaultDescription = 'Local services information for each service';

    /**
     * @param Updater $updater
     * @param string|null $name
     */
    public function __construct(
        Updater $updater,
        string $name = null
    ) {
        parent::__construct($updater, $name);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setHelp(<<<EOF
Use this command to get information about local services.
EOF
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $composeConfig = $this->config->getLocalServicesComposeFile();

        $lsDir = $this->config->getLocalConfig(Config::LOCAL_SERVICE_CONFIG_KEY);
        $output->writeln('Local Service Directory:<info>' . $lsDir . '</info>');

        $skipProperties = ['labels', 'restart', 'volumes', 'command'];

        foreach ($composeConfig['services'] as $serviceName => $serviceInfo) {
            $output->writeln('Service <info>' . $serviceName . '</info>');

            $rows = [];
            foreach ($serviceInfo as $property => $value) {
                if (in_array($property, $skipProperties)) {
                    continue;
                }
                if (is_array($value)) {
                    $rows[] = [$property, json_encode($value)];
                } else {
                    $rows[] = [$property, $value];
                }
            }
            if ($serviceName == Database::SERVICE_NAME) {
                $rows[] = ['Status', $this->updater->getDockerValidation()->isDatabaseRunning() ? '<info>Running</info>' : '<error>Stopped</error>'];
            }

            $table = new Table($output);
            $table->setHeaders(['Property', 'Values'])->setRows($rows);
            $table->render();
            $output->writeln('');
        }

        return Command::SUCCESS;
    }

    /**
     * Disable when local services wasn't created yet
     * @return bool
     */
    public function isEnabled()
    {
        return is_array($this->config->getLocalServicesComposeFile());
    }
}