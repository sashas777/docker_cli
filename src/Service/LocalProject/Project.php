<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2023  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli\Service\LocalProject;

use Dcm\Cli\Service\DataObject;

/**
 * Class Project
 */
class Project extends DataObject implements ProjectInterface
{
    /**
     * @return string|null
     */
    public function getProjectCode(): ?string {
        return $this->getData(ProjectInterface::PROJECT_CODE);
    }

    /**
     * @param string $code
     *
     * @return Project
     */
    public function setProjectCode(string $code): Project {
        return $this->setData(ProjectInterface::PROJECT_CODE, $code);
    }

    /**
     * @return string|null
     */
    public function getProjectDomain(): ?string {
        return $this->getData(ProjectInterface::PROJECT_DOMAIN);
    }

    /**
     * @param string $domain
     *
     * @return Project
     */
    public function setProjectDomain(string $domain): Project {
        return $this->setData(ProjectInterface::PROJECT_DOMAIN, $domain);
    }

    /**
     * @return string|null
     */
    public function getProjectDirectory(): ?string {
        return $this->getData(ProjectInterface::PROJECT_DIRECTORY);
    }

    /**
     * @param string $directory
     *
     * @return Project
     */
    public function setProjectDirectory(string $directory): Project {
        return $this->setData(ProjectInterface::PROJECT_DIRECTORY, $directory);
    }
}