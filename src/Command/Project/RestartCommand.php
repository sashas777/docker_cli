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

class RestartCommand extends AbstractAliasCommand
{
    protected static $defaultName = 'project:restart';
    protected static $defaultDescription = 'Restart docker containers. Short version: <info>dcm p:r</info>';

    /**
     * @param Updater $updater
     * @param string|null $name
     */
    public function __construct(
        Updater $updater,
        string $name = null
    ) {
        $commandInline = 'docker-compose restart';
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
Use this command to restart docker containers for the project.
EOF
        );
    }

    /**
     * Disable when no env file in th efolder
     * @return bool
     */
    public function isEnabled()
    {
        return $this->updater->getDockerValidation()->isCliRunning();
    }
}