<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2022  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli\Command\Project;

use Dcm\Cli\Command\AbstractAliasCommand;
use Dcm\Cli\Service\Updater;

/**
 * Class StopCommand
 */
class StopCommand extends AbstractAliasCommand
{
    protected static $defaultName = 'project:stop';
    protected static $defaultDescription = 'Stop docker containers. Short version: <info>dcm p:sto</info>';

    /**
     * @param Updater $updater
     * @param string|null $name
     */
    public function __construct(
        Updater $updater,
        string $name = null
    ) {
        $commandInline = 'docker-compose stop';
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
Use this command to stop docker containers for the project.
EOF
        );
    }

    /**
     * Disable when no env file in th efolder
     * @return bool
     */
    public function isEnabled()
    {
        if (!$this->updater->getDockerValidation()->isProjectCommandAllowed()) {
            return false;
        }
        return !$this->updater->getDockerValidation()->isProjectCanStart();
    }
}