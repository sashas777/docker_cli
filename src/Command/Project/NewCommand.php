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

/**
 * Class NewCommand
 */
class NewCommand extends Command
{
    protected static $defaultName = 'project:new';
    protected static $defaultDescription = 'Create a new docker local instance project';

    const SELECT_VERSION_STRING = 'Specify a version';
    const DO_NOT_USE_STRING = 'Do not use %s';

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
     * @var Config
     */
    private $config;

    /**
     * @var JsonEncoder
     */
    private $serializer;

    /**
     * @var HttpRequest
     */
    private $client;

    /**
     * @param Config $config
     * @param JsonEncoder $serializer
     * @param HttpRequest $client
     * @param string|null $name
     */
    public function __construct(
        Config $config,
        JsonEncoder $serializer,
        HttpRequest $client,
        string $name = null
    ) {
        $this->config = $config;
        $this->serializer = $serializer;
        $this->client = $client;
        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setHelp(<<<EOF
Use this command to create a new project. 
The command will generate docker-compose.yml file and necessary configuration files: .env and global.env.
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
            $this->checkIsInProjectDir($projectDir);
            $projectDomain = $this->getProjectDomain();
            $projectCode = $this->getProjectCode($projectDomain);
            $containers = $this->getContainerList();

            $output->writeln('<info>Creating a project  "' . $projectCode . '" at "' . $projectDir . '"</info>');
            $table = new Table($output);
            $rows = [];
            foreach ($containers as $container) {
                $host = ($container['hostname'] ? sprintf($container['hostname'], $projectDomain ): 'N/A');
                $rows[]= [$container['name'], $container['version'], $host];
            }
            $table->setHeaders(['Service', 'Version', 'Host'])->setRows($rows);
            $table->render();
            $question = new ConfirmationQuestion('Continue? <info>(Y/n)</info> ', true);
            $confirm = $this->getQuestionHelper()->ask($this->input, $this->output, $question);
            if (!$confirm) {
                return Command::SUCCESS;
            }
            $this->downloadAndMergeFiles($containers);
            $this->downloadGlobalEnv();
            $this->saveEnvConfig($projectDomain, $projectCode);
            @mkdir($projectDir.DS.'src', 0777, true);

        } catch (\Exception $e) {
            $output->writeln('<error>Failed to configure the project: '.$e->getMessage().'</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Created a project '.$projectCode. ' in '.$projectDir. '</info>');

        $hostsFile = '/etc/hosts';
        if ($this->config->isWindows()) {
            $hostsFile = 'C:\Windows\System32\Drivers\etc\hosts';
        }
        $output->writeln('<info>Please, add the new domain to your hosts file: 127.0.0.1 '.$projectDomain. ' </info> (<comment>'.$hostsFile.'</comment>)');
        $output->writeln('<info>To run the environment use the command docker-compose up -d from the directory '.$projectDir);

        return Command::SUCCESS;
    }

    /**
     * @param array $containers
     *
     * @return void
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function downloadAndMergeFiles(array $containers): void
    {
        $projectDir = $this->getProjectDir();
        $services = '';
        foreach ($containers as $containerKey => $container) {
            $url = sprintf($this->config->getData('local_part'), $containerKey);
            $httpContent = $this->client->getHttpContent($url);
            $service = sprintf($httpContent, $container['version']);
            $services.=$service;
        }

        $httpContent = $this->client->getHttpContent($this->config->getData('local_base'));
        $dockerCompose = sprintf($httpContent, $services);
        file_put_contents($projectDir.DS.$this->config->getData('compose_file'), $dockerCompose);
    }

    /**
     * @return void
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function downloadGlobalEnv(): void
    {
        $projectDir = $this->getProjectDir();
        $defaultConfig = $this->config->getData('env_default_config');
        $timezone = $defaultConfig['timezone'];
        $question = new Question('Please enter a timezone for containers [<comment>'.$timezone.'</comment>]: ', $timezone);
        $timezone = $this->getQuestionHelper()->ask($this->input, $this->output, $question);

        $magentoRunMode = $defaultConfig['magento_run_mode'];
        $question = new Question('Magento operation mode [<comment>'.$magentoRunMode.'</comment>]: ', $magentoRunMode);
        $magentoRunMode = $this->getQuestionHelper()->ask($this->input, $this->output, $question);

        $phpMemoryLimit = $defaultConfig['php_memory_limit'];
        $question = new Question('Please enter a timezone for containers [<comment>'.$phpMemoryLimit.'</comment>]: ', $phpMemoryLimit);
        $phpMemoryLimit = $this->getQuestionHelper()->ask($this->input, $this->output, $question);

        $this->output->writeln('<comment><href=https://devdocs.magento.com/guides/v2.4/install-gde/prereq/connect-auth.html>Get your Magento authentication keys</></comment>');
        $magentoComposerUsername = $defaultConfig['magento_composer_username'];
        $question = new Question('Magento Access Public Key: ');
        $magentoComposerUsername = $this->getQuestionHelper()->ask($this->input, $this->output, $question);

        $magentoComposerPassword = $defaultConfig['magento_composer_password'];
        if ($magentoComposerUsername) {
            $question = new Question('Magento Access Private Key: ');
            $magentoComposerPassword = $this->getQuestionHelper()->ask($this->input, $this->output, $question);
        }

        $httpContent = $this->client->getHttpContent($this->config->getData('global_env'));
        $globalEnv = sprintf(
            $httpContent,
            $timezone,
            $magentoRunMode,
            $phpMemoryLimit,
            $magentoComposerUsername,
            $magentoComposerPassword
        );

        file_put_contents($projectDir.DS.$this->config->getData('global_env_file'), $globalEnv);
    }

    /**
     * @param string $projectDomain
     * @param string $projectCode
     *
     * @return void
     */
    private function saveEnvConfig(string $projectDomain, string $projectCode): void
    {
        $projectDir = $this->getProjectDir();
        $env = <<<EOF
COMPOSE_CONVERT_WINDOWS_PATHS=1
WEBSITE_DOMAIN=$projectDomain
PROJECT_NAME=$projectCode
EOF;
        file_put_contents($projectDir.DS.$this->config->getData('env_file'), $env);
    }

    /**
     * @param string $projectDir
     *
     * @return void
     * @throws \Exception
     */
    private function checkIsInProjectDir(string $projectDir): void
    {
        if (is_file($projectDir.DS.$this->config->getData('env_file'))) {
            throw new \Exception('the selected directory has a project '.$projectDir );
        }
    }

    /**
     * @return QuestionHelper
     */
    private function getQuestionHelper(): QuestionHelper
    {
        return $this->getHelper('question');
    }

    /**
     * @return array
     * @throws \Exception
     */
    private function getContainerList(): array
    {
        $containerList = [];

        $services = $this->config->getData('docker_env_services');

        foreach ($services as $service => $serviceInfo) {
            $versions = $this->getDockerContainerVersions($serviceInfo);
            $defaultAnswer = (isset($versions[2]) ? $versions[2] : $versions[1]);

            if ($service=='varnish') {
                continue;
                //todo Need to test docker-compose configurations
            }
            $question = new ChoiceQuestion(
                'Choose a version of the <info>'.$serviceInfo['name'].'</info>, please [<comment>'.$defaultAnswer.'</comment>]:',
                $versions,
                $defaultAnswer
            );
            $selectedVersion = $this->getQuestionHelper()->ask($this->input, $this->output, $question);
            if ($selectedVersion == static::SELECT_VERSION_STRING) {

                $selectedVersion = $this->customVersion($serviceInfo);
            }

            $containerList[$service] = array_merge(['version' => $selectedVersion], $serviceInfo);
        }
        return $containerList;
    }

    /**
     * @param array $serviceInfo
     *
     * @return string
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function customVersion(array $serviceInfo): string
    {
        $question = new Question(
            'Specify a version of the <info>'.$serviceInfo['name'].'</info>, please: '
        );
        $question->setMaxAttempts(3);
        $answer = $this->getQuestionHelper()->ask($this->input, $this->output, $question);
        if (!$answer) {
            throw new \Exception('The version is empty for the service '.$serviceInfo['repo']);
        }

        $url = sprintf($this->config->getData('dockerhub_specific_tag_pattern'), $serviceInfo['repo'], $answer);
        $httpContent = $this->client->getHttpContent($url);

        return $answer;
    }

    /**
     * @param array $serviceInfo
     * @param $pageSize
     *
     * @return array
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function getDockerContainerVersions(array $serviceInfo, $pageSize = 20): array
    {
        $simplifiedVersions = [sprintf( static::DO_NOT_USE_STRING, $serviceInfo['name']), static::SELECT_VERSION_STRING];
        $url = sprintf($this->config->getData('dockerhub_tag_pattern'), $serviceInfo['repo'], $pageSize);

        $httpContent = $this->client->getHttpContent($url);
        if (!$httpContent) {
            $this->output->writeln('<warning>There is an issue to fetch tags for: '.$serviceInfo['repo'].'</warning>');
        }

        $versions =  $this->serializer->decode($httpContent,JsonEncoder::FORMAT, [JsonDecode::ASSOCIATIVE =>true]);

        if (!isset($versions['results']) || !is_array($versions['results'])) {
            $this->output->writeln('<warning>There is no tags found for: '.$serviceInfo['repo'].'</warning>');
            return $simplifiedVersions;
        }

        foreach ($versions['results'] as $version) {
            if ($serviceInfo['version_pattern'] &&  preg_match($serviceInfo['version_pattern'], $version['name'])) {
                $simplifiedVersions[] =  $version['name'];
            } elseif (!$serviceInfo['version_pattern']) {
                $simplifiedVersions[] =  $version['name'];
            }
        }

        return $simplifiedVersions;
    }

    /**
     * @return string
     */
    private function getProjectDomain(): string
    {
        $question = new Question('New project domain [<comment>Example: mag22.test</comment>]: ');
        $question->setMaxAttempts(3);
        $question->setNormalizer(function ($value) {
            return $value ? trim($value) : $value;
        });
        $question->setValidator(function ($answer) {
            if (!$answer || !preg_match('/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/', $answer)) {
                throw new \Exception('Invalid project domain');
            }
            return $answer;
        });

        return $this->getQuestionHelper()->ask($this->input, $this->output, $question);
    }

    /**
     * @param string $projectDomain
     *
     * @return string
     */
    private function getProjectCode(string $projectDomain): string
    {
        $projectDomain = preg_replace('/[^a-z0-9-]/', '', str_replace('.','-', $projectDomain));
        $question = new Question('New project code [<comment>'.$projectDomain.'</comment>]: ', $projectDomain);
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
            if (!is_dir($answer)) {
                throw new \Exception('The directory does not exist or not writeable: '.$answer );
            }

            return $answer;
        });

        $this->projectDir = $this->getQuestionHelper()->ask($this->input, $this->output, $question);
        return $this->projectDir;
    }
}