<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2022  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli\Command\Project;

use Dcm\Cli\Service\Updater;
use Dcm\Cli\Service\Validation\Docker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Input\InputOption;
use Dcm\Cli\Command\AbstractCommandBase;

/**
 * Class TunnelCommand
 */
class TunnelCommand extends AbstractCommandBase
{
    /**
     *
     */
    const COMMAND_RUNNING_CONTAINERS_JSON = 'docker compose ps --filter status=running --format json';
    /**
     *
     */
    const COMMAND_GET_ID_BY_CONTAINER_NAME = 'docker-compose ps -q ';
    /**
     *
     */
    const OPTION_CONTAINER = 'container';
    /**
     *
     */
    const OPTION_USER = 'user';

    /**
     * @var string
     */
    protected static $defaultName = 'project:tunnel';
    /**
     * @var string
     */
    protected static $defaultDescription = 'Tunnel into a container. Example for CLI container: <info>dcm p:tun -c cli</info>';

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
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // bind the input against the command specific arguments/options
        $input->bind($this->getDefinition());
        //options
        //@todo replace names when project code stored
        $userInputContainer = $input->getOption('container');
        $userValue = $input->getOption('user');
        $selectedContainer = false;
        $selectedContainerName = $userInputContainer;

        if($userInputContainer) {
            try {
                $process = Process::fromShellCommandline(static::COMMAND_GET_ID_BY_CONTAINER_NAME.' '.$userInputContainer);
                $process->mustRun();
                $selectedContainer = trim($process->getOutput());
            } catch (\Exception $exception) {
                //skip and ask for selection
            }
        }

        if (!$selectedContainer) {
            $process = Process::fromShellCommandline(static::COMMAND_RUNNING_CONTAINERS_JSON);
            $process->mustRun();
            $runningContainers = json_decode($process->getOutput(), true);

            $rows = [];
            $selection = [];
            foreach ($runningContainers as $container) {
                $rows[] = [$container['Name'], $container['ID']];
                $selection[$container['ID']] = $container['Name'];
            }
            $table = new Table($output);
            $table->setHeaders(['Name', 'ID'])->setRows($rows);
            $table->render();
            $output->writeln('');

            $question = new ChoiceQuestion(
                'Select a container, please:',
                $selection
            );

            $selectedContainer = $this->getHelper('question')->ask($input, $output, $question);
            $selectedContainerName = $selection[$selectedContainer];
        }

        // add www user for CLI container
        $user = '';

        //@todo change after project info stored
        if (strpos($selectedContainerName, Docker::CONTAINER_NAME_CLI) !== false) {
            // when cli params used
            if ($userInputContainer && !$userValue)  {
                $user = ' -u www';
            }else if ($userValue) {
                $user = ' -u '.trim($userValue);
            } else{
                $question = new ConfirmationQuestion('Login as a www user? <info>(Y/n)</info> ', true);
                if ($this->getHelper('question')->ask($input, $output, $question)) {
                    $user = ' -u www';
                }
            }
        }

        try {
            $commandInline = 'docker exec'.$user.' -it '.$selectedContainer.' bash';
            $command = explode(' ', $commandInline);
            $process = new Process($command);
            $process->setTimeout(10*60);
            $process->setTty(true);
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            if ($output->isVerbose()) {
                $output->writeln($e->getMessage());
            }
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->addOption(
            static::OPTION_CONTAINER,
            'c',
            InputOption::VALUE_OPTIONAL,
            'Container',
            false
        )->addOption(
            static::OPTION_USER,
            'u',
            InputOption::VALUE_OPTIONAL,
            'Container User',
            false
        );
        $this->setHelp(<<<EOF
Use this command to tunnel into a container
EOF
        );
    }

    /**
     * Disable when no env file in the folder
     * @return bool
     */
    public function isEnabled()
    {
        return $this->updater->getDockerValidation()->isCliRunning();
    }
}