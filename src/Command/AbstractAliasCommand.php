<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2022  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Class AbstractAliasCommand
 */
abstract class AbstractAliasCommand extends AbstractCommandBase
{
    /**
     * @var array
     */
    protected $command = [];

    /**
     * @return array
     */
    public function getCommand():array
    {
        return $this->command;
    }

    /**
     * @param array $command
     *
     * @return $this
     */
    public function setCommand(array $command): self
    {
        $this->command = $command;
        return $this;
    }

    /**
     * Executes the current command.
     *
     * This method is not abstract because you can use this class
     * as a concrete class. In this case, instead of defining the
     * execute() method, you set the code to execute by passing
     * a Closure to the setCode() method.
     *
     * @return int 0 if everything went fine, or an exit code
     *
     * @throws LogicException When this abstract method is not implemented
     *
     * @see setCode()
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $argv = $argv ?? $_SERVER['argv'] ?? [];
        // strip the application and command
        array_shift($argv);
        array_shift($argv);

        $command = array_merge($this->getCommand(), $argv);

        try {
            $output->writeln('<info>Executing command:</info> '.implode(' ', $command));
            $startTime = microtime(true);
            $output->writeln('');
            $process = new Process($command);
            $process->setTimeout(10*60);
            $process->setTty(true);
            $process->mustRun();
            $resultTime = microtime(true) - $startTime;
            $output->writeln('');
            $output->writeln(sprintf('Execution time: <info>%s sec</info>', gmdate('H:i:s', (int) $resultTime)));
        } catch (ProcessFailedException $e) {
            if ($output->isVerbose()) {
                $output->writeln($e->getMessage());
            }
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }

    /**
     * Runs the command without validation of arguments and options
     *
     * @return int The command exit code
     */
    public function run(InputInterface $input, OutputInterface $output)
    {
        // add the application arguments and options
        $this->mergeApplicationDefinition();

        $this->initialize($input, $output);

        if ($input->isInteractive()) {
            $this->interact($input, $output);
        }

        // The command name argument is often omitted when a command is executed directly with its run() method.
        // It would fail the validation if we didn't make sure the command argument is present,
        // since it's required by the application.
        if ($input->hasArgument('command') && null === $input->getArgument('command')) {
            $input->setArgument('command', $this->getName());
        }

        $input->validate();

        $statusCode = $this->execute($input, $output);

        if (!\is_int($statusCode)) {
            throw new \TypeError(sprintf('Return value of "%s::execute()" must be of the type int, "%s" returned.', static::class, get_debug_type($statusCode)));
        }
        return is_numeric($statusCode) ? (int) $statusCode : 0;
    }
}