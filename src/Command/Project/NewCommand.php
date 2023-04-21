<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2022  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli\Command\Project;

use Dcm\Cli\Config;
use Dcm\Cli\Service\HttpRequest;
use Dcm\Cli\Service\Images\RabbitMq;
use Dcm\Cli\Service\Updater;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Helper\Table;
use Dcm\Cli\Command\AbstractCommandBase;
use Dcm\Cli\Service\Images\PhpCli;
use Dcm\Cli\Service\Images\PhpFpm;

/**
 * Class NewCommand
 */
class NewCommand extends AbstractCommandBase
{
    protected static $defaultName = 'project:new';
    protected static $defaultDescription = 'Create a new docker local instance project';

    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var string|null
     */
    private $projectDir;

    /**
     * @var JsonEncoder
     */
    private $serializer;

    /**
     * @var HttpRequest
     */
    private $client;

    /**
     * @param Updater $updater
     * @param JsonEncoder $serializer
     * @param HttpRequest $client
     * @param string|null $name
     */
    public function __construct(
        Updater $updater,
        JsonEncoder $serializer,
        HttpRequest $client,
        string $name = null
    ) {
        $this->serializer = $serializer;
        $this->client = $client;
        parent::__construct($updater, $name);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setHelp(<<<EOF
Use this command to create a new project. 
The command will generate docker-compose.yml file and necessary configuration files.
An interactive form will be presented with the available options.
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
        $this->input = $input;
        $this->output = $output;

        try {
            $projectDir = $this->getProjectDir();
            $projectCode = $this->getProjectCode();
            $projectDomain = $this->config->getLocalConfig(Config::LOCAL_ENV_DOMAIN_KEY);

            $output->writeln('<info>Initializing a project  ' . $projectCode . '.'.$projectDomain.' in ' . $projectDir . '</info>');
            $containers = $this->getContainerList();
            $containers = $this->updateVersionsAndDomains($containers, $projectCode);

            $table = new Table($output);
            $rows = [];
            foreach ($containers['services'] as $service => $container) {
                $host = (isset($container['hostname']) ? sprintf($container['hostname'], $projectDomain ) : 'N/A');
                $rows[]= [$service, $container['image'], $host];
            }
            $table->setHeaders(['Service', 'Version', 'Host'])->setRows($rows);
            $table->render();
            $question = new ConfirmationQuestion('Continue? <info>(Y/n)</info> ', true);
            $confirm = $this->getQuestionHelper()->ask($this->input, $this->output, $question);
            if (!$confirm) {
                return Command::SUCCESS;
            }

            @mkdir($projectDir.DS.'src', 0777, true);
            @mkdir($projectDir.DS.'dev', 0777, true);
            $yaml = $this->config->getYaml()->dump($containers, 4,2);
            file_put_contents($projectDir . DS . $this->config->getData('compose_file'), $yaml);

        } catch (\Exception $e) {
            $output->writeln('<error>Failed to create a project: '.$e->getMessage().'</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>The project '.$projectCode. ' has been created in '.$projectDir. '</info>');

        $hostsFile = '/etc/hosts';
        if ($this->config->isWindows()) {
            $hostsFile = 'C:\Windows\System32\Drivers\etc\hosts';
        }
        $output->writeln('<info>Please, add the new domain to your hosts file: 127.0.0.1 ' . $projectCode . '.'.$projectDomain.' </info> (<comment>'.$hostsFile.'</comment>)');
        $output->writeln('<info>To run the environment use the command docker-compose up -d from the directory '.$projectDir);

        return Command::SUCCESS;
    }

    private function getContainerList(): array
    {
        $question = new ConfirmationQuestion('Use Varnish cache? <info>(y/N)</info> ', false);
        $confirm = $this->getQuestionHelper()->ask($this->input, $this->output, $question);
        $dockerCompose = $this->client->getHttpContent($this->config->getData('local_base'));
        if ($confirm) {
            $dockerCompose = $this->client->getHttpContent($this->config->getData('local_varnish_base'));
        }
        return $this->config->getYaml()->parse($dockerCompose);
    }

    /**
     * @return QuestionHelper
     */
    private function getQuestionHelper(): QuestionHelper
    {
        return $this->getHelper('question');
    }

    private function updateVersionsAndDomains(array $containers, string $projectCode): array
    {
        $domainContainerProperties = ['environment', 'labels'];
        $mainDomain = $this->config->getLocalConfig(Config::LOCAL_ENV_DOMAIN_KEY);
        $defaultProjectDomain = Config::DEFAULT_PROJECT_CODE.'.'.Config::DEFAULT_DOMAIN;
        $projectDomain = $projectCode.'.'. $mainDomain;
        foreach ($containers['services'] as $service => $serviceInfo) {
            if (isset($serviceInfo['container_name'])) {
                $containers['services'][$service]['container_name'] = str_replace(Config::DEFAULT_PROJECT_CODE, $projectCode, $serviceInfo['container_name']);
            }
            if (isset($serviceInfo['hostname'])) {
                $containers['services'][$service]['hostname'] = str_replace($defaultProjectDomain, $projectDomain, $serviceInfo['hostname']);
            }

            foreach ($serviceInfo as $containerProperty => $propertyValue) {
                if (in_array($containerProperty, $domainContainerProperties) && is_array($propertyValue)) {
                    foreach ($propertyValue as $valueKey => $valueString) {
                        $containers['services'][$service][$containerProperty][$valueKey] = str_replace($defaultProjectDomain, $projectDomain, $serviceInfo[$containerProperty][$valueKey]);
                        $containers['services'][$service][$containerProperty][$valueKey] = str_replace(Config::DEFAULT_PROJECT_CODE, $projectCode, $serviceInfo[$containerProperty][$valueKey]);
                        $containers['services'][$service][$containerProperty][$valueKey] = str_replace(Config::DEFAULT_DOMAIN, $mainDomain, $serviceInfo[$containerProperty][$valueKey]);
                    }
                }
            }

            $selectedVersion =  $this->selectVersion($service, $serviceInfo);
            if (isset($serviceInfo['image'])) {
                $containers['services'][$service]['image'] = strtok($serviceInfo['image'], ':').':'.$selectedVersion;
            }
        }

        return $containers;
    }

    private function selectVersion(string $service, array $serviceInfo): string
    {
        $versions = $this->getDockerContainerVersions($service, $serviceInfo);
        $defaultAnswer = $versions[0];

        $question = new ChoiceQuestion(
            'Choose a version of the <info>'.$service.'</info>, please [<comment>'.$defaultAnswer.'</comment>]:',
            $versions,
            $defaultAnswer
        );

        return $this->getQuestionHelper()->ask($this->input, $this->output, $question);
    }

    private function getDockerContainerVersions(string $serviceName, array $serviceInfo, $pageSize = 20): array
    {
        $repo =  strtok($serviceInfo['image'], ':');
        if ($serviceName == RabbitMq::SERVICE_NAME) {
            $repo = 'library/'.$repo; // issue with RabbitMQ specifically
        }
        $imageVersions = [];
        $url = sprintf($this->config->getData('dockerhub_tag_pattern'), $repo, $pageSize);

        //@todo cache result for 24 hours.
        $httpContent = $this->client->getHttpContent($url);
        if (!$httpContent) {
            $this->output->writeln('<warning>There is an issue to fetch tags for: '.$repo.'</warning>');
        }

        $versions =  $this->serializer->decode($httpContent,JsonEncoder::FORMAT, [JsonDecode::ASSOCIATIVE =>true]);

        if (!isset($versions['results']) || !is_array($versions['results'])) {
            $this->output->writeln('<warning>There is no tags found for: '.$repo.'</warning>');
            return $imageVersions;
        }

        foreach ($versions['results'] as $version) {
            //skip arm builds
            if (strpos($version['name'], 'arm64v8') === 0) {
                continue;
            }
            if ($serviceName == PhpCli::SERVICE_NAME && strpos($version['name'], PhpCli::SERVICE_NAME) === false) {
              continue;
            }
            if ($serviceName == PhpFpm::SERVICE_NAME && strpos($version['name'], PhpFpm::SERVICE_NAME) === false) {
                continue;
            }
            if ($serviceName == RabbitMq::SERVICE_NAME && preg_match('/[a-z\-]+$/', $version['name'])) {
                continue;
            }
            $imageVersions[] =  $version['name'];
        }
        arsort($imageVersions);
        //reset array keys
        $imageVersions = array_values($imageVersions);
        return $imageVersions;
    }

    private function getProjectCode(): string
    {
        $projectDomain = $this->config->getLocalConfig(Config::LOCAL_ENV_DOMAIN_KEY);

        $question = new Question('Project code [<comment>The domain will be: {code}.'.$projectDomain.'</comment>]: ', $projectDomain);
        $question->setMaxAttempts(3);
        $question->setNormalizer(function ($value) {
            return $value ? trim($value) : $value;
        });
        $question->setValidator(function ($answer) {
            if (!$answer || !preg_match('/^[a-z0-9\-]+$/', $answer)) {
                throw new \Exception('Invalid project code');
            }
            return $answer;
        });

        return $this->getQuestionHelper()->ask($this->input, $this->output, $question);
    }

    /**
     * @return string
     */
    private function getProjectDir(): string
    {
        if ($this->projectDir) {
            return $this->projectDir;
        }

        $question = new Question('New project directory [<comment>'.getcwd().'</comment>]: ', getcwd());
        $question->setMaxAttempts(3);
        $question->setNormalizer(function ($value) {
            return trim($value);
        });
        $question->setValidator(function ($answer) {
            if (!is_dir($answer) && !mkdir($answer)) {
                throw new \Exception('The directory or path is not writeable: '.$answer );
            }
            if (is_file($answer.DS.$this->config->getData('compose_file'))) {
                throw new \Exception('The directory already has a project');
            }

            return $answer;
        });

        $this->projectDir = $this->getQuestionHelper()->ask($this->input, $this->output, $question);
        return $this->projectDir;
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return !$this->updater->getDockerValidation()->IsComposerFileExists() || !$this->updater->getDockerValidation()->IsLocalServicesSetup();
    }
}