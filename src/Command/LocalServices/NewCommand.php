<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2022  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli\Command\LocalServices;

use Dcm\Cli\Config;
use Dcm\Cli\Service\HttpRequest;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Class to generate local services
 */
class NewCommand extends Command
{
    protected static $defaultName = 'services:new';
    protected static $defaultDescription = 'Generate docker-compose.yml file for local services';

    const LOCAL_SERVICES_NEW_DIR = 'local_services';
    const TRAEFIK_KEY = 'traefik';
    const DB_KEY = 'db';
    const PORTAINER_KEY = 'portainer';

    /**
     * @var
     */
    private $lsDir;
    /**
     * @var Config
     */
    private $config;

    /**
     * @var HttpRequest
     */
    private $client;

    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @param Config $config
     * @param HttpRequest $client
     * @param string|null $name
     */
    public function __construct(
        Config $config,
        HttpRequest $client,
        string $name = null
    ) {
        $this->config = $config;
        $this->client = $client;
        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setHelp(<<<EOF
Use this command to create a local services stack for all projects.
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
            $lsDir = $this->getLocalServicesDir();

            $containers = $this->getContainerList();
            $output->writeln('Generating docker-compose.yml in <info>' . $lsDir . '</info>');
            $table = new Table($output);
            $rows = [];
            $hostsFileDomains = '';
            foreach ($containers as $container) {
                $rows[]= [$container['name']];
                if (isset($container['domain'])) {
                    $hostsFileDomains.="127.0.0.1 ".$container['domain']."\r\n";
                }

            }
            $table->setHeaders(['Service'])->setRows($rows);
            $table->render();
            $question = new ConfirmationQuestion('Continue? <info>(Y/n)</info> ', true);
            $confirm = $this->getQuestionHelper()->ask($this->input, $this->output, $question);
            if (!$confirm) {
                return Command::SUCCESS;
            }
            $this->downloadAndMergeFiles($containers);

        } catch (\Exception $e) {
            $output->writeln('<error>Failed to create the local services: '.$e->getMessage().'</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Generated Local Services docker-compose files in '.$lsDir. '</info>');
        $hostsFile = '/etc/hosts';
        if ($this->config->isWindows()) {
            $hostsFile = 'C:\Windows\System32\Drivers\etc\hosts';
        }
        $output->writeln($hostsFileDomains);
        $output->writeln('<info>Please, add domains above to your hosts file: </info> (<comment>'.$hostsFile.'</comment>)');
        $output->writeln('<info>Database host: </info><comment>127.0.0.1:3306</comment>');
        if (array_key_exists(static::PORTAINER_KEY, $containers)) {
            $output->writeln('<info>Portainer password: </info><comment>admin</comment>');
        }
        $output->writeln('To run the local services use the command docker-compose up -d from the directory <info>'.$lsDir.'</info>');

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
        $lsDir = $this->getLocalServicesDir();
        $services = '';
        foreach ($containers as $containerKey => $container) {
            $url = $container['url'];
            $httpContent = $this->client->getHttpContent($url);

            if ($containerKey==static::TRAEFIK_KEY) {
                $this->createTraefikConfig();
                $services.=$httpContent;
            }else  if ($containerKey==static::DB_KEY) {
                $this->createDbConfig();
                $dbRootPass = 'magento2';
                $question = new Question('MariaDB root password [<comment>'.$dbRootPass.'</comment>]: ', $dbRootPass);
                $dbRootPass = $this->getQuestionHelper()->ask($this->input, $this->output, $question);

                $dbName = 'magento2';
                $question = new Question('MariaDB new database name [<comment>'.$dbName.'</comment>]: ', $dbName);
                $dbName = $this->getQuestionHelper()->ask($this->input, $this->output, $question);

                $dbUsername = 'magento2';
                $question = new Question('MariaDB new user username [<comment>'.$dbUsername.'</comment>]: ', $dbUsername);
                $dbUsername = $this->getQuestionHelper()->ask($this->input, $this->output, $question);

                $dbPass = 'magento2';
                $question = new Question('MariaDB '.$dbUsername.' password [<comment>'.$dbPass.'</comment>]: ', $dbPass);
                $dbPass = $this->getQuestionHelper()->ask($this->input, $this->output, $question);

                $httpContent = sprintf(
                    $httpContent,
                    $dbRootPass,
                    $dbName,
                    $dbUsername,
                    $dbPass
                );

                $services.=$httpContent;
            } else {
                $services.=$httpContent;
            }
        }

        $httpContent = $this->client->getHttpContent($this->config->getData('local_services_base'));
        $dockerCompose = sprintf($httpContent, $services);
        file_put_contents($lsDir.DIRECTORY_SEPARATOR.$this->config->getData('compose_file'), $dockerCompose);
    }

    /**
     * @return void
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function createTraefikConfig(): void
    {
        $lsDir = $this->getLocalServicesDir();
        @mkdir($lsDir.DIRECTORY_SEPARATOR.'traefik');
        $httpContent = $this->client->getHttpContent($this->config->getData('traefik_dynamic'));
        file_put_contents($lsDir.DIRECTORY_SEPARATOR.'traefik'.DIRECTORY_SEPARATOR.'dynamic.yml', $httpContent);
        $httpContent = $this->client->getHttpContent($this->config->getData('traefik_app'));
        file_put_contents($lsDir.DIRECTORY_SEPARATOR.'traefik'.DIRECTORY_SEPARATOR.'traefik.yml', $httpContent);
    }

    /**
     * @return void
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function createDbConfig(): void
    {
        $lsDir = $this->getLocalServicesDir();
        @mkdir($lsDir.DIRECTORY_SEPARATOR.'mysql');
        $httpContent = $this->client->getHttpContent($this->config->getData('mysql_config'));
        file_put_contents($lsDir.DIRECTORY_SEPARATOR.'mysql'.DIRECTORY_SEPARATOR.'custom-config.cnf', $httpContent);
    }

    /**
     * @return array
     * @throws \Exception
     */
    private function getContainerList(): array
    {
        $containerList = [];
        $services = $this->config->getData('docker_local_services');
        foreach ($services as $service => $serviceInfo) {
            $confirm = false;
            if ($serviceInfo['is_required']) {
                $containerList[$service] = $serviceInfo;
                continue;
            }
            $question = new ConfirmationQuestion(
                'Do you want to use <info>'.$serviceInfo['name'].'</info> (y/N) ', $confirm);
            $confirm = $this->getQuestionHelper()->ask($this->input, $this->output, $question);
            if ($confirm) {
                $containerList[$service] = $serviceInfo;
            }

            $containerList[$service] = $serviceInfo;
        }
        return $containerList;
    }

    /**
     * @return string
     */
    private function getLocalServicesDir(): string
    {
        if ($this->lsDir) {
            return $this->lsDir;
        }
        $newDirectoryPath = $this->config->getHomeDirectory().DIRECTORY_SEPARATOR.static::LOCAL_SERVICES_NEW_DIR;
        $question = new Question('New Local Services directory [<comment>'.$newDirectoryPath.'</comment>]: ', $newDirectoryPath);
        $question->setMaxAttempts(3);
        $question->setNormalizer(function ($value) {
            return trim($value);
        });
        $question->setValidator(function ($answer) {
            if (@mkdir($answer) && !is_writable($answer)) {
                throw new \Exception('The was an error creating: '.$answer );
            }
            return $answer;
        });

        $this->lsDir = $this->getQuestionHelper()->ask($this->input, $this->output, $question);

        return $this->lsDir;
    }

    /**
     * @return QuestionHelper
     */
    private function getQuestionHelper(): QuestionHelper
    {
        return $this->getHelper('question');
    }
}