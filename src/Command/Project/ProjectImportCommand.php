<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2022  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli\Command\Project;

use Dcm\Cli\Config;
use Dcm\Cli\Service\Images\PhpFpm;
use Dcm\Cli\Service\LocalProject\Project;
use Dcm\Cli\Service\Updater;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Dcm\Cli\Command\AbstractCommandBase;
use Dcm\Cli\Service\LocalProjectRepository;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Class ProjectImportCommand
 */
class ProjectImportCommand extends AbstractCommandBase
{
    /**
     * @var string
     */
    protected static $defaultName = 'project:import';
    /**
     * @var string
     */
    protected static $defaultDescription = 'Import an existing project in local registry';

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
Use this command to import a project based on an existing docker-compose.yml file.
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
        $projectDir = getcwd();
        $output->writeln('<info>Importing the project from ' . $projectDir . '/'.$this->config->getData('compose_file').'</info>');

        try {
            $projectCode = $this->getProjectCodeFromComposeFile($projectDir.DS.$this->config->getData('compose_file'));
            $projectDomain = $projectCode.'.'.$this->config->getLocalConfig(Config::LOCAL_ENV_DOMAIN_KEY);

            $rows[] = [$projectCode, $projectDomain, $projectDir];
            $table = new Table($output);
            $table->setHeaders(['Code ', 'Domain', 'Directory'])->setRows($rows);
            $table->render();
            $output->writeln('');

            $question = new ConfirmationQuestion('Continue? <info>(Y/n)</info> ', true);
            $confirm = $this->getHelper('question')->ask($input, $output, $question);
            if (!$confirm) {
                return Command::SUCCESS;
            }
            $project = new Project();
            $project->setProjectDirectory($projectDir)->setProjectCode($projectCode)->setProjectDomain($projectDomain);
            $this->localProjectRepository->saveProject($project);
        } catch (\Exception $e) {
            $output->writeln('<error>Failed to import the project: '.$e->getMessage().'</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>The project '.$projectCode. ' has imported.</info>');
        return Command::SUCCESS;
    }

    /**
     * @param $dockerComposePath
     *
     * @return string
     * @throws \Exception
     */
    private function getProjectCodeFromComposeFile($dockerComposePath): string
    {
        $containers = $this->config->getYaml()->parse(file_get_contents($dockerComposePath));
        foreach ($containers['services'] as $service => $serviceInfo) {
            if ($service == PhpFpm::SERVICE_NAME) {
                if (!isset($serviceInfo['container_name'])) {
                    throw new \Exception('The docker-compose file have an incorrect/outdated format. Rename it and use the project:new command to generate a newest version, please.');
                }
                return str_replace( PhpFpm::CONTAINER_NAME_SUFFIX,'', $serviceInfo['container_name']);
            }
        }
    }

    /**
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->updater->getDockerValidation()->isProjectImportCommandAllowed();
    }
}