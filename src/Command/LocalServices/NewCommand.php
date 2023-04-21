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
use Dcm\Cli\Service\Updater;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Dcm\Cli\Command\AbstractCommandBase;

/**
 * Class to generate local services
 */
class NewCommand extends AbstractCommandBase
{
    protected static $defaultName = 'services:new';
    protected static $defaultDescription = 'Generate docker-compose.yml file for local services';

    const LOCAL_SERVICES_NEW_DIR = 'local_services';

    /**
     * @var String
     * Local Services Directory
     */
    private $lsDir;

    /**
     * @var Boolean
     * Is a new Local Services config
     */
    private $isNew = true;

    /**
     * @var String
     * Project Main Domain
     */
    private $mainDomain;

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
     * @param Updater $updater
     * @param HttpRequest $client
     * @param string|null $name
     */
    public function __construct(
        Updater $updater,
        HttpRequest $client,
        string $name = null
    ) {
        $this->client = $client;
        parent::__construct($updater, $name);
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
        $this->input  = $input;
        $this->output = $output;

        try {
            $lsDir = $this->getLocalServicesDir();

            if (!$this->isNew) {
                $output->writeln('<info>Saved Local Services configuration from ' . $lsDir . '</info>');
                return Command::SUCCESS;
            }

            $this->mainDomain = $this->chooseMainDomain();
            $containers = $this->generateYml();

            $this->createDbConfig();
            $yaml = $this->config->getYaml()->dump($containers, 4,2);
            file_put_contents($lsDir . DS . $this->config->getData('compose_file'), $yaml);
            $this->config->saveLocalConfig(Config::LOCAL_SERVICE_CONFIG_KEY, $this->lsDir);
        } catch (\Exception $e) {
            $output->writeln('<error>Failed to create the Local Services: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Created Local Services docker-compose files in ' . $lsDir . '</info>');
        $output->writeln('<info>Database: </info><comment>root:pass@127.0.0.1:3306</comment>');
        $output->writeln('<info>Portainer password: </info><comment>admin</comment>');
        $output->writeln('To run the local services use the command docker-compose up -d from the directory <info>' . $lsDir . '</info>');

        return Command::SUCCESS;
    }

    /**
     * @return string
     */
    private function chooseMainDomain(): string
    {
        $question   = new Question('Local environment domain [<comment>'.Config::DEFAULT_DOMAIN.'</comment>]: ', Config::DEFAULT_DOMAIN);
        $question->setValidator(function ($answer) {
            if (!preg_match("/^[a-z0-9.]+$/", $answer)) {
                throw new \Exception('Allowed characters: [a-z 1-9 .] ' );
            }
            return $answer;
        });

        $localDomain = $this->getQuestionHelper()->ask($this->input, $this->output, $question);
        $this->config->saveLocalConfig(Config::LOCAL_ENV_DOMAIN_KEY, $localDomain);
        return $localDomain;
    }

    /**
     * @return array
     * @throws \Exception
     */
    private function generateYml(): array
    {
        $containers = $this->getContainerList();

        $this->output->writeln('Generating docker-compose.yml in <info>' . $this->lsDir . '</info>');
        $table            = new Table($this->output);
        $rows             = [];
        $hostsFileDomains = '';
        foreach ($containers['services'] as $service) {
            if (isset($service['labels'])) {
                foreach ($service['labels'] as $label) {
                    if (strpos($label,Config::DEFAULT_DOMAIN)) {
                        $label = str_replace(Config::DEFAULT_DOMAIN, $this->mainDomain, $label);

                        preg_match('/`(.*?)`/', $label, $match);
                        $hostsFileDomains .= "127.0.0.1 " . $match[1] . "\r\n";
                        $rows[] = [$service['container_name'], $match[1]];
                    }
                }
            }
        }

        $table->setHeaders(['Service','Host'])->setRows($rows);
        $table->render();
        $question = new ConfirmationQuestion('Continue? <info>(Y/n)</info> ', true);
        $confirm  = $this->getQuestionHelper()->ask($this->input, $this->output, $question);
        if (!$confirm) {
            throw new \Exception('Operation cancelled');
        }

        $hostsFile = '/etc/hosts';
        if ($this->config->isWindows()) {
            $hostsFile = 'C:\Windows\System32\Drivers\etc\hosts';
        }
        $this->output->writeln('');
        $this->output->writeln($hostsFileDomains);
        $this->output->writeln('<info>Please, add domains above to your hosts file: </info> (<comment>' . $hostsFile . '</comment>)');

        return $containers;
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
        @mkdir($lsDir . DS . 'mysql');
        $httpContent = $this->client->getHttpContent($this->config->getData('mysql_config'));
        file_put_contents($lsDir . DS . 'mysql' . DS . 'custom-config.cnf', $httpContent);
    }

    /**
     * @return array
     * @throws \Exception
     */
    private function getContainerList(): array
    {
        $dockerCompose = $this->client->getHttpContent($this->config->getData('local_services_base'));
        return $this->config->getYaml()->parse($dockerCompose);
    }

    /**
     * @return string
     */
    private function getLocalServicesDir(): string
    {
        if ($this->lsDir) {
            return $this->lsDir;
        }

        $question = new ConfirmationQuestion('Do you have local services installed? <info>(y/N)</info> ', false);
        $confirm = $this->getQuestionHelper()->ask($this->input, $this->output, $question);

        if ($confirm) {
            $question = new Question('Local Services directory path [<comment>Example: /home/user/local_services</comment>]: ');
            $question->setMaxAttempts(3);
            $question->setValidator(function ($answer) {
                if (!($answer) || !is_dir($answer) || !is_writable($answer) || !file_exists($answer.DS.'docker-compose.yml')) {
                    throw new \Exception('Unable to find local services at '.$answer);
                }
                return $answer;
            });
            $this->lsDir = $this->getQuestionHelper()->ask($this->input, $this->output, $question);
            $this->config->saveLocalConfig(Config::LOCAL_SERVICE_CONFIG_KEY, $this->lsDir);
            $this->isNew = false;
            /* get main domain from existing local services */
            $localDomain = $this->chooseMainDomain();
            return $this->lsDir;
        }

        $newDirectoryPath = $this->config->getHomeDirectory().DS.static::LOCAL_SERVICES_NEW_DIR;
        $question = new Question('Local Services directory [<comment>'.$newDirectoryPath.'</comment>]: ', $newDirectoryPath);
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

    /**
     * Disable when local services already created
     * @return bool
     */
    public function isEnabled()
    {
        return !$this->updater->getDockerValidation()->isLocalServicesSetup();
    }
}