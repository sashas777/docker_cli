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
 * Class ConfigShowCommand
 */
class ConfigShowCommand extends AbstractAliasCommand
{
    protected static $defaultName = 'magento:c:show';
    protected static $defaultDescription = 'bin/magento config:show. Alias: <info>dcm m:c:show</info>';

    /**
     * @param Updater $updater
     * @param string|null $name
     */
    public function __construct(
        Updater $updater,
        string $name = null
    ) {
        $commandInline = 'docker-compose exec -u www cli bin/magento config:show';
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
Use this command to execute bin/magento config:show
EOF
        );
    }

    /**
     * Disable when no env or bin/magento not exists
     * @return bool
     */
    public function isEnabled()
    {
        return $this->updater->getMagentoValidation()->isMagento();
    }
}