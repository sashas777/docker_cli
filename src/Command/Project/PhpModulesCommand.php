<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2022  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli\Command\Project;

use Dcm\Cli\Command\AbstractAliasCommand;
use Dcm\Cli\Config;

/**
 * Class PhpModulesCommand
 */
class PhpModulesCommand extends AbstractAliasCommand
{
    protected static $defaultName = 'project:php:modules';
    protected static $defaultDescription = 'List php modules for the CLI container.';

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
        $commandInline = 'docker-compose exec -u www cli php -m';
        $command = explode(' ', $commandInline);
        $this->setCommand($command);
        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setHelp(<<<EOF
Use this command to view php -m information
EOF
        );
    }

    /**
     * Disable when no env file in th efolder
     * @return bool
     */
    public function isEnabled()
    {
        return is_array($this->config->getDockerComposeFile());
    }
}