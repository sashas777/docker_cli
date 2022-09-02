<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2022  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli\Command\Magento;

use Dcm\Cli\Command\AbstractAliasCommand;
use Dcm\Cli\Service\Updater;

/**
 * Class ConfigSetCommand
 */
class ConfigSetCommand extends AbstractAliasCommand
{
    protected static $defaultName = 'magento:c:set';
    protected static $defaultDescription = 'bin/magento config:sensitive:set. Alias: <info>dcm m:c:set  [--scope="..."] [--scope-code="..."] path value</info>';

    /**
     * @param Updater $updater
     * @param string|null $name
     */
    public function __construct(
        Updater $updater,
        string $name = null
    ) {
        $commandInline = 'docker-compose exec -u www cli bin/magento config:sensitive:set';
        $command = explode(' ', $commandInline);
        $this->setCommand($command);
        parent::__construct($updater, $name);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setHelp(<<<EOF
Use this command to execute bin/magento config:sensitive:set [--scope="..."] [--scope-code="..."] path value
<info>--scope</info> 	The scope of the configuration. The possible values are default, website, or store. The default is default.
<info>--scope-code</info> 	The scope code of configuration (website code or store view code)
<info>-le</info> 	        Locks the value / changes it at the app/etc/env.php file.
<info>-lc</info> 	        Locks the value / changes it at the app/etc/config.php file. The -lc option overwrites -le if you specify both options.
<info>path</info>        	Required. The configuration path
<info>value</info>      	Required. The value of the configuration
EOF
        );
    }

    /**
     * Disable when no env or bin/magento not exists
     * @return bool
     */
    public function isEnabled()
    {
        return is_array($this->config->getDotEnvConfig()) && $this->config->isMagento();
    }
}