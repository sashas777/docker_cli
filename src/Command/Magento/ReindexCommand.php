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
 * Class ReindexCommand
 */
class ReindexCommand extends AbstractAliasCommand
{
    protected static $defaultName = 'magento:reindex';
    protected static $defaultDescription = 'bin/magento indexer:reindex command. Alias: <info>dcm m:re</info>';

    /**
     * @param Updater $updater
     * @param string|null $name
     */
    public function __construct(
        Updater $updater,
        string $name = null
    ) {
        $commandInline = 'docker-compose exec -u www cli bin/magento indexer:reindex';
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
Use this command to execute bin/magento indexer:reindex
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