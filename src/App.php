<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2022  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Command\ListCommand;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionCommand;

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
     *
     */
    public function __construct()
    {
        $this->config = new Config();
        $this->addCommands($this->getCommands());
        parent::__construct($this->config->getName(), $this->config->getVersion());
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultCommands()
    {
        return [new ListCommand(), new HelpCommand(), new CompletionCommand()];
    }

    /**
     * @return \Symfony\Component\Console\Command\Command[]
     */
    private function getCommands(): array
    {
        $commands[] = new Command\Self\BuildCommand();
        $commands[] = new Command\Self\UpdateCommand();
        $commands[] = new Command\Self\InstallCommand();
        return $commands;
    }
}