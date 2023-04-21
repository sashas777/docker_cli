<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2022  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli\Service\Validation;

use Symfony\Component\Process\Process;
use Dcm\Cli\Config;

/**
 * Class Docker
 */
class Docker
{
    const CONTAINER_NAME_CLI = 'cli';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var null
     */
    private $cliStatus = null;

    /**
     * @param Config $config
     */
    public function __construct(
        Config $config
    ) {
        $this->config = $config;
    }

    /**
     * @return bool
     */
    public function isCliRunning(): bool
    {
        if (!$this->IsComposerFileExists()) {
            return false;
        }
        if ($this->cliStatus !== null) {
            return $this->cliStatus;
        }

        $getProcessIdCommand = 'docker-compose ps -q '.static::CONTAINER_NAME_CLI;

        try {
            $process = Process::fromShellCommandline($getProcessIdCommand);
            $process->mustRun();
            $processId = $process->getOutput();
            if (!$processId) {
                $this->cliStatus = false;
                return $this->cliStatus;
            }
            $getProcessIdRunningCommand = 'docker ps -q --no-trunc | grep '.$processId;
            $process = Process::fromShellCommandline($getProcessIdRunningCommand);
            $process->mustRun();
            $runningProcessId = $process->getOutput();
            if (!$runningProcessId) {
                $this->cliStatus = false;
                return $this->cliStatus;
            }
        } catch (\Exception $e) {
            $this->cliStatus = false;
            return $this->cliStatus;
        }
        $this->cliStatus = true;
        return $this->cliStatus;
    }

    /**
     * @return bool
     */
    public function IsComposerFileExists(): bool
    {
       return is_readable($this->config->getData('compose_file'));
    }

    /**
     * @return bool
     */
    public function IsLocalServicesSetup(): bool
    {
        return (bool) $this->config->getLocalServicesComposeFile();
    }
}