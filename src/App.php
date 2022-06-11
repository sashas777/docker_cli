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
    public function __construct(iterable $commands, Config $config)
    {
        $this->config = $config;
        $commands = $commands instanceof \Traversable ? \iterator_to_array($commands) : $commands;

        foreach ($commands as $command) {
            $this->add($command);
        }

        parent::__construct($this->config->getName(), $this->config->getVersion());
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultCommands()
    {
        return [new ListCommand(), new HelpCommand(), new CompletionCommand()];
    }

}