<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2022  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli\Command\Project;

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
    protected static $defaultName = 'project:info';
    protected static $defaultDescription = 'Project information for each service';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setHelp(<<<EOF
Use this command to get information about existing project from a project directory.
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
        $composeConfig = $this->config->getDockerComposeFile();
        foreach ($composeConfig['services'] as $serviceName => $serviceInfo) {
            $output->writeln('Service <info>' . $serviceName . '</info>');

            $rows = [];
            foreach ($serviceInfo as $property => $value) {
                if (is_array($value)) {
                    $rows[] = [$property,implode("\n", $value)];
                } else {
                    $rows[] = [$property, $value];
                }
            }

            $table = new Table($output);
            $table->setHeaders(['Property', 'Values'])->setRows($rows);
            $table->render();
            $output->writeln('');
        }

        return Command::SUCCESS;
    }

    /**
     * Disable when no env file in the folder
     * @return bool
     */
    public function isEnabled()
    {
        return is_array($this->config->getDockerComposeFile());
    }
}