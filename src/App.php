<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2022  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Command\ListCommand;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionCommand;
use Dcm\Cli\Service\LocalProjectRepository;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class App
 */
class App extends Application
{
    /**
     * @var \Dcm\Cli\Config
     */
    protected $config;

    /**
     * @var LocalProjectRepository
     */
    protected $localProjectRepository;

    private $output;

    /**
     *
     */
    public function __construct(
        iterable $commands,
        Config $config,
        LocalProjectRepository $localProjectRepository
    ) {
        $this->config = $config;
        $this->localProjectRepository = $localProjectRepository;
        $commands = $commands instanceof \Traversable ? \iterator_to_array($commands) : $commands;

        foreach ($commands as $command) {
            if ($command instanceof \Symfony\Component\Yaml\Command\LintCommand) {
                continue;
            }
            $this->add($command);
        }

        parent::__construct($this->config->getName(), $this->config->getVersion());
    }

    protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output) {
        $this->output = $output;
        return parent::doRunCommand($command, $input, $output);
    }

    /**
     * @return string
     */
    public function getLongVersion()
    {
        $version = parent::getLongVersion();

        if ($project = $this->localProjectRepository->getCurrentProject()) {
            $rows = [
                ['<info>Project Code</info>', $project->getProjectCode()],
                ['<info>Project Domain</info>', $project->getProjectDomain()],
                ['<info>Project Directory</info>', $project->getProjectDirectory()],
            ];
            $table = new Table($this->output);
            $table->setRows($rows);
            $table->render();
        }

        return $version;
    }
    /**
     * @inheritdoc
     */
    protected function getDefaultCommands()
    {
        return [new ListCommand(), new HelpCommand(), new CompletionCommand()];
    }
}