<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2022  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli\Command\Composer;

use Dcm\Cli\Command\AbstractAliasCommand;
use Dcm\Cli\Service\Updater;

/**
 * Class ComposerCommand
 */
class ComposerCommand extends AbstractAliasCommand
{
    protected static $defaultName = 'composer:exec';
    protected static $defaultDescription = 'Execute a composer command. Alias: <info>dcm c:e</info>';

    /**
     * @param Updater $updater
     * @param string|null $name
     */
    public function __construct(
        Updater $updater,
        string $name = null
    ) {
        $commandInline = 'docker-compose exec -u www cli composer';
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
Use this command to execute any command inside the cli container for composer.
EOF
        );
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return $this->updater->getComposerValidation()->isComposer();
    }
}