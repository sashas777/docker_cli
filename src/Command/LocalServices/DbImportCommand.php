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
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Input\InputArgument;
use Dcm\Cli\Command\AbstractCommandBase;

/**
 * Class DbImportCommand
 */
class DbImportCommand extends AbstractCommandBase
{
    protected static $defaultName = 'services:db:import';
    protected static $defaultDescription = 'Import database from a dump';

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
     * @var string
     */
    private $password;

    /**
     * @var bool
     */
    private $isGzip = false;

    const CREATE_NEW_DB = 'Create a new database';
    const FILE_EXTENSIONS = ['sql', 'gz'];
    const FILE_EXTENSION_GZIP = 'gz';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setHelp(<<<EOF
Use this command to import a database dump: <info>dcm s:db:i {file_path}</info>
EOF
        );
        $this->addArgument('file', InputArgument::REQUIRED, 'Path to an import file');
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
            $filePath = $this->getImportFilePath();
            $dbName = $this->selectDatabase();

            $command = 'cat '.$filePath.' | ';
            if ($this->isGzip) {
                $command = 'gunzip -kc '.$filePath.' | ';
            }
            $command .= 'docker exec -i db /usr/bin/mysql -u '.$this->username.' -p'.$this->password.' '.$dbName;

            $startTime = microtime(true);
            $output->writeln(sprintf('Importing to the <info>%s</info>', $dbName));
            $process = Process::fromShellCommandline($command);
            $process->mustRun();
            $resultTime = microtime(true) - $startTime;
            $output->writeln(sprintf('The import has been completed. Execution time: <info>%s sec</info>', gmdate('H:i:s', (int) $resultTime)));
        } catch (ProcessFailedException $e) {
            if ($output->isVerbose()) {
                $output->writeln($e->getMessage());
            }
            $output->writeln('<error>There was an error during the import. Run command with the -vvv option for more details.</error>');
            return Command::FAILURE;
        } catch (\Exception $e) {
            $output->writeln($e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * @return string
     * @throws \Exception
     */
    private function getImportFilePath(): string
    {
        $filePath = $this->input->getArgument('file');
        if (!is_readable($filePath)) {
            throw new \Exception('The file <error>'.$filePath.'</error> is not readable.');
        }
        $pathParts = pathinfo($filePath);
        if (!in_array($pathParts['extension'], static::FILE_EXTENSIONS)) {
            throw new \Exception('The file extension <error>.'.$pathParts['extension'].'</error> is not supported. Please use one of these: <info>'.implode(',', static::FILE_EXTENSIONS).'</info>');
        }
        if ($pathParts['extension'] == static::FILE_EXTENSION_GZIP) {
            $this->isGzip = true;
        }

        return $filePath;
    }

    /**
     * @return string
     * @throws \Exception
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
        if ($dbList[0] == 'information_schema') {
            array_shift($dbList);
        }
        $dbList[] = static::CREATE_NEW_DB;
        $dbList = array_reverse($dbList);
        if (!count($dbList)) {
            throw new \Exception('There are no databases. Please create a one before dump');
        }

        $question = new ChoiceQuestion(
            'Select a database, please [<comment>'.$dbList[0].'</comment>]:',
            $dbList,
            $dbList[0]
        );
        $dbName = $this->getHelper('question')->ask($this->input, $this->output, $question);
        if ($dbName == static::CREATE_NEW_DB) {
            return $this->createNewDb($dbList);
        }

        return $dbName;
    }

    /**
     * @param array $dbList
     *
     * @return string
     * @throws \Exception
     */
    private function createNewDb(array $dbList): string
    {
        $question = new Question('New database name [<comment>Example: magento2</comment>]: ');
        $question->setMaxAttempts(3);
        $question->setNormalizer(function ($value) {
            return $value ? trim($value) : $value;
        });
        $question->setValidator(function ($answer) use ($dbList) {
            if (!$answer || !preg_match('/^[a-z0-9_]{1,64}$/', $answer)) {
                throw new \Exception('Invalid database name');
            }
            if (in_array($answer, $dbList)) {
                throw new \Exception('The database with the same name '.$answer.' already exists');
            }
            return $answer;
        });
        $dbName = $this->getHelper('question')->ask($this->input, $this->output, $question);

        $this->output->writeln('Creating database <info>'.$dbName.'</info>');
        $command = 'docker exec db /usr/bin/mysql -u '.$this->username.' -p'.$this->password.' -e "CREATE DATABASE '.$dbName.'" ';
        $process = Process::fromShellCommandline($command);
        if (0 !== $process->run()) {
            throw new \Exception('<error>'.$process->getErrorOutput().'</error>');
        }

        $this->output->writeln('The database <info>'.$dbName.'</info> has been created');
        return $dbName;
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
        return $this->updater->getDockerValidation()->isDatabaseRunning();
    }
}