<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2022  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli\Command\LocalServices;

use Dcm\Cli\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Class DbDumpCommand
 */
class DbDumpCommand extends Command
{
    protected static $defaultName = 'services:db:dump';
    protected static $defaultDescription = 'Create a database dump file';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var string
     */
    private $username = 'root';

    /**
     * @var
     */
    private $password;

    /**
     * @param Config $config
     * @param string|null $name
     */
    public function __construct(
        Config $config,
        string $name = null
    ) {
        $this->config = $config;
        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setHelp(<<<EOF
Use this command to create a database dump and save it locally
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
        $this->password =  $this->parseConnectionInfo('environment');;
        $this->input = $input;
        $this->output = $output;

        try {
            $dbName = $this->selectDatabase();
            $filename = $dbName.'_'.date('YmdHis').'.sql.gz';

            $command = 'docker exec db /usr/bin/mysqldump -u '.$this->username.' -p'.$this->password.' --single-transaction ';
            $command.= $dbName.' | gzip -9 > '.$filename;
            $startTime = microtime(true);
            $output->writeln(sprintf('Creating backup: <info>%s</info>', $filename));
            $process = Process::fromShellCommandline($command);
            $process->mustRun();
            $resultTime = microtime(true) - $startTime;
            $output->writeln(sprintf('Completed. Execution time: <info>%s sec</info>', gmdate('H:i:s', (int) $resultTime)));
        } catch (ProcessFailedException $e) {
            if ($output->isVerbose()) {
                $output->writeln($e->getMessage());
            }
            return Command::FAILURE;
        } catch (\Exception $e) {
            $output->writeln($e->getMessage());
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }

    /**
     * @return string
     */
    private function selectDatabase(): string
    {
        $command = 'docker exec db /usr/bin/mysql -u '.$this->username.' -p'.$this->password.' -e "SHOW DATABASES;" ';
        $process = Process::fromShellCommandline($command);
        $process->mustRun();
        $output = $process->getOutput();
        if (!is_string($output)) {
            throw new \Exception('There was an issue to fetch database list, the result type is '.gettype($output));
        }
        $dbList = explode("\n", trim($output));
        // strip the word Database
        array_shift($dbList);
        if (!count($dbList)) {
            throw new \Exception('There are no databases. Please create a one before dump');
        }

        $question = new ChoiceQuestion(
            'Select a database, please [<comment>'.$dbList[0].'</comment>]:',
            $dbList,
            $dbList[0]
        );
        return $this->getHelper('question')->ask($this->input, $this->output, $question);
    }

    /**
     * @param $connectionProperty
     *
     * @return string|null
     */
    private function parseConnectionInfo($connectionProperty): ?string
    {
        $composeConfig = $this->config->getLocalServicesComposeFile();
        foreach ($composeConfig['services'] as $serviceName => $serviceInfo) {
            if ($serviceName !== Config::DB_CONTAINER_NAME) {
                continue;
            }
            foreach ($serviceInfo as $property => $value) {
                if ($property == $connectionProperty) {
                    if ($connectionProperty == 'ports') {
                        // host:container
                        $ports = explode(":", $value[0]);
                        return $ports[0];
                    }elseif ($connectionProperty == 'environment') {
                        foreach ($value as $inlineVar) {
                            $varArray = explode("=", $inlineVar);
                            if ($varArray[0] == 'MYSQL_ROOT_PASSWORD') {
                                return $varArray[1];
                            }
                        }
                    }
                }
            }
        }
        return null;
    }

    /**
     * Disable when local services wasn't created yet
     * @return bool
     */
    public function isEnabled()
    {
        return is_array($this->config->getLocalServicesComposeFile());
    }
}