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
     * @return string
     */
    public function getLongVersion()
    {
        $version = parent::getLongVersion();
        if (is_array($envConfig = $this->config->getDotEnvConfig())) {
            $version.="\n\nCurrent Environment: <info>".$envConfig['PROJECT_NAME']."</info>";
            $version.="\nEnvironment Domain: <info>".$envConfig['WEBSITE_DOMAIN']."</info>";
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