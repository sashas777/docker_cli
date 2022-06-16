<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2022  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli\Command\Magento;

use Dcm\Cli\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Class BinMagentoCommand
 */
class BinMagentoCommand extends Command
{
    protected static $defaultName = 'magento:bin';
    protected static $defaultDescription = 'Run bin/magento from cli container';

    /**
     * @var Config
     */
    private $config;

    /**
     * @param Config $config
     * @param string|null $name
     */
    public function __construct(
        Config $config,
        string $name = null
    ) {
        $this->config = $config;
        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
       $this->addArgument('option', InputArgument::IS_ARRAY, 'bin/magento command');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $argumentsArray = [];
        foreach ($input->getArgument('option') as $argument) {
            $argumentsArray[]=$argument;
        }
        $arguments = implode(' ', $argumentsArray);

        $process = new Process(['docker-compose exec -u www cli bin/magento '.$arguments]);
        $process->run();

        if (!$process->isSuccessful()) {
            $error = sprintf('The command "%s" failed.'."\n Exit Code: <error>%s (%s)</error>\nWorking directory: %s",
                $process->getCommandLine(),
                $process->getExitCode(),
                $process->getExitCodeText(),
                $process->getWorkingDirectory()
            );
            $output->writeln($error);
            return Command::FAILURE;
        }

        $output->writeln($process->getOutput());
        return Command::SUCCESS;
    }

    /**
     * Disable when a Phar build run from another Phar.
     * @return bool
     */
    public function isEnabled()
    {
        return is_array($this->config->getDotEnvConfig());
    }
}