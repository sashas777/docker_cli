<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2022  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli\Command\Project;

use Dcm\Cli\Service\LocalProject\Project;
use Dcm\Cli\Service\Updater;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Dcm\Cli\Command\AbstractCommandBase;
use Dcm\Cli\Service\LocalProjectRepository;

/**
 * Class ProjectListCommand
 */
class ProjectListCommand extends AbstractCommandBase
{
    /**
     * @var string
     */
    protected static $defaultName = 'project:list';
    /**
     * @var string
     */
    protected static $defaultDescription = 'List all projects';

    /**
     * @var LocalProjectRepository
     */
    private $localProjectRepository;

    /**
     * @param Updater $updater
     * @param LocalProjectRepository $localProjectRepository
     * @param string|null $name
     */
    public function __construct(
        Updater $updater,
        LocalProjectRepository $localProjectRepository,
        string $name = null
    ) {
        $this->localProjectRepository = $localProjectRepository;
        parent::__construct($updater, $name);
    }
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setHelp(<<<EOF
Use this command to get information about all projects.
EOF
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projects = $this->localProjectRepository->getAllProjects();
        $rows = [];
        /** @var Project $project */
        foreach ($projects as $project) {
            $rows[] = [$project->getProjectCode(), $project->getProjectDomain(), $project->getProjectDirectory()];
        }

        $table = new Table($output);
        $table->setHeaders(['Code', 'Domain', 'Directory'])->setRows($rows);
        $table->render();
        $output->writeln('');

        return Command::SUCCESS;
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return $this->updater->getDockerValidation()->isLocalServicesSetup();
    }
}