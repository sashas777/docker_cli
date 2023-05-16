<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2022  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli\Service\Validation;

use Dcm\Cli\Service\Images\Database;
use Dcm\Cli\Service\LocalProjectRepository;
use Symfony\Component\Process\Process;
use Dcm\Cli\Config;
use Dcm\Cli\Service\Images\PhpCli;

/**
 * Class Docker
 */
class Docker
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var LocalProjectRepository
     */
    private $localProjectRepository;

    /**
     * @var null
     */
    private $cliStatus = null;

    /**
     * @var null
     */
    private $dbStatus = null;

    /**
     * @param Config $config
     * @param LocalProjectRepository $localProjectRepository
     */
    public function __construct(
        Config $config,
        LocalProjectRepository $localProjectRepository
    ) {
        $this->config = $config;
        $this->localProjectRepository = $localProjectRepository;
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

        $getProcessIdCommand = 'docker-compose ps -q '.PhpCli::SERVICE_NAME;

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

    public function isDatabaseRunning(): bool
    {
        if ($this->dbStatus !== null) {
            return $this->dbStatus;
        }

        try {
            $getProcessIdRunningCommand = 'docker ps -q --no-trunc -f name='.Database::SERVICE_NAME;
            $process = Process::fromShellCommandline($getProcessIdRunningCommand);
            $process->mustRun();
            $runningProcessId = $process->getOutput();
            if (!$runningProcessId) {
                $this->dbStatus = false;
                return $this->dbStatus;
            }
        } catch (\Exception $e) {
            $this->dbStatus = false;
            return $this->dbStatus;
        }
        $this->dbStatus = true;
        return $this->dbStatus;
    }

    /**
     * @return bool
     */
    public function isComposerFileExists(): bool
    {
       return is_readable($this->config->getData('compose_file'));
    }

    /**
     * @return bool
     */
    public function isProjectCanStart(): bool
    {
        return $this->isProjectCommandAllowed() && !$this->isCliRunning();
    }

    /**
     * @return bool
     */
    public function isNewProjectCommandAllowed(): bool
    {
        return !$this->localProjectRepository->getCurrentProject()
               && !$this->isComposerFileExists()
               && $this->isLocalServicesSetup();
    }

    /**
     * @return bool
     */
    public function isProjectCommandAllowed(): bool
    {
        return $this->localProjectRepository->getCurrentProject()
               && $this->isComposerFileExists()
               && $this->isLocalServicesSetup();
    }

    /**
     * @return bool
     */
    public function isProjectImportCommandAllowed(): bool
    {
        return !$this->localProjectRepository->getCurrentProject()
               && $this->isComposerFileExists()
               && $this->isLocalServicesSetup();
    }

    /**
     * @return bool
     */
    public function isLocalServicesSetup(): bool
    {
        return (bool) $this->config->getLocalServicesComposeFile();
    }
}