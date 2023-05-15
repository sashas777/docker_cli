<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2023  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli\Service\LocalProject;

/**
 * Interface ProjectInterface
 */
interface ProjectInterface
{
    /**
     *
     */
    const PROJECT_CODE = 'project_code';
    /**
     *
     */
    const PROJECT_DOMAIN = 'project_domain';
    /**
     *
     */
    const PROJECT_DIRECTORY = 'project_directory';

    /**
     * @return string|null
     */
    public function getProjectCode(): ?string;

    /**
     * @param string $code
     *
     * @return Project
     */
    public function setProjectCode(string $code): Project;

    /**
     * @return string|null
     */
    public function getProjectDomain(): ?string;

    /**
     * @param string $domain
     *
     * @return Project
     */
    public function setProjectDomain(string $domain): Project;

    /**
     * @return string|null
     */
    public function getProjectDirectory(): ?string;

    /**
     * @param string $directory
     *
     * @return Project
     */
    public function setProjectDirectory(string $directory): Project;
}