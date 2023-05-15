<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2023  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli\Service;

use Dcm\Cli\Config;
use Dcm\Cli\Service\LocalProject\Project;
use Dcm\Cli\Service\LocalProject\ProjectInterface;
use Dcm\Cli\Service\DataObject\DataObjectHelper;

/**
 * Class LocalProjectRepository
 */
class LocalProjectRepository
{
    /**
     *
     */
    const LOCAL_PROJECTS_KEY = 'projects';

    /**
     * @var Config
     */
    private Config $config;
    /**
     * @var DataObjectHelper
     */
    private DataObjectHelper $dataObjectHelper;
    /**
     * @var array
     */
    private array $projects = [];

    /**
     * @param Config $config
     * @param DataObjectHelper $dataObjectHelper
     */
    public function __construct(
        Config $config,
        DataObjectHelper $dataObjectHelper
    ) {
        $this->config = $config;
        $this->dataObjectHelper = $dataObjectHelper;
    }

    /**
     * @param string $code
     *
     * @return ProjectInterface
     */
    public function getProject(string $code): ProjectInterface
    {
        $projects = $this->getProjects();
        if (!isset($projects[$code])) {
            throw new \InvalidArgumentException("The project with code " . $code. " doesn't exist.");
        }

        $projectData = json_decode($projects[$code], true);

        $project = new Project();
        $this->dataObjectHelper->populateWithArray($project, $projectData, ProjectInterface::class);

        return $project;
    }

    /**
     * @param ProjectInterface $project
     *
     * @return ProjectInterface
     */
    public function saveProject(ProjectInterface $project): ProjectInterface
    {
        $this->addProject($project);
        return $this->getProject($project->getProjectCode());
    }

    /**
     * @return ProjectInterface|null
     */
    public function getCurrentProject(): ?ProjectInterface
    {
        $projects = $this->getProjects();
        foreach ($projects as $projectJson) {
            $project = json_decode($projectJson, true);
            if ($project[ProjectInterface::PROJECT_DIRECTORY] == getcwd()) {
                return $this->getProject($project[ProjectInterface::PROJECT_CODE]);
            }
        }
        return null;
    }

    /**
     * @param ProjectInterface $project
     *
     * @return array
     */
    private function addProject(ProjectInterface $project): array
    {
        $this->projects[$project->getProjectCode()] = $project->toJson();
        return $this->saveProjects();
    }

    /**
     * @return array
     */
    private function getProjects(): array
    {
        if (!$this->projects) {
            $projectsJson = $this->config->getLocalConfig(static::LOCAL_PROJECTS_KEY);
            if (!$projectsJson ||!json_decode($projectsJson, true)) {
                $this->projects = [];
            } else {
                $this->projects = json_decode($projectsJson, true);
            }
        }

        return $this->projects;
    }

    /**
     * @return array
     */
    private function saveProjects(): array
    {
        $projects = $this->getProjects();
        $this->config->saveLocalConfig(static::LOCAL_PROJECTS_KEY, json_encode($projects));
        return $projects;
    }
}