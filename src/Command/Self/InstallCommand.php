<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2022  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli\Command\Self;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Dcm\Cli\Config;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Dcm\Cli\Service\Snippeter;

class InstallCommand extends Command
{
    protected static $defaultName = 'self:install';
    protected static $defaultDescription = 'Install or update CLI configuration files';

    private static $container;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @param string|null $name
     */
    public function __construct(string $name = null)
    {
        $this->config = new Config();
        parent::__construct($name);
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setAliases(['self-install']);
        $cliName = $this->config->getData('name');
        $this->setHelp(<<<EOT
This command automatically installs shell configuration for the {$cliName},
adding autocompletion support and handy aliases. Bash and ZSH are supported.
EOT
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
        $configDir = $this->config->getUserConfigDir();
        $this->output = $output;

        $output->write('Copying resource files...');
        $requiredFiles = [
            'shell-config.rc',
            'shell-config-bash.rc',
        ];
        $fs = new Filesystem();
        try {
            foreach ($requiredFiles as $requiredFile) {
                if (($contents = file_get_contents(CLI_ROOT . DIRECTORY_SEPARATOR . $requiredFile)) === false) {
                    throw new \RuntimeException(sprintf('Failed to read file: %s', CLI_ROOT . '/' . $requiredFile));
                }
                $fs->dumpFile($configDir . DIRECTORY_SEPARATOR . $requiredFile, $contents);
            }
        } catch (\Exception $e) {
            $output->writeln('<error>'.$this->indentAndWrap($e->getMessage()).'</error>');
            return Command::FAILURE;
        }
        $output->writeln(' <info>done</info>');
        $output->writeln('');

        if (getenv('SHELL') !== false) {
            $shellType = str_replace('.exe', '', basename(getenv('SHELL')));
            $this->debug('Detected shell type: ' . $shellType);
        }

        $output->write('Setting up autocompletion...');
        try {
            $args = [
                '--generate-hook' => true,
                '--program' => $this->config->getData('short_name'),
            ];
            if ($shellType) {
                $args['--shell-type'] = $shellType;
            }
            $buffer = new BufferedOutput();
            $exitCode = $this->runCommand('_completion', $buffer, $args);

            if ($exitCode === 0 && ($autoCompleteHook = $buffer->fetch())) {
                $fs->dumpFile($configDir . '/autocompletion.sh', $autoCompleteHook);
                $output->writeln(' <info>done</info>');
            }
        } catch (\Exception $e) {
            if (!$this->isTerminal(STDOUT)) {
                $output->writeln(' <info>skipped</info> (not a terminal)');
            } elseif ($shellType === null) {
                $output->writeln(' <info>skipped</info> (unsupported shell)');
            }
            else {
                $output->writeln(' <comment>error</comment>');
                $output->writeln($this->indentAndWrap($e->getMessage()));
            }
        }
        $output->writeln('');
        if (!$shellType) {
            $output->writeln('<error> Unsupported shell: '.$shellType.'</error>');
            return Command::FAILURE;
        }

        $shellConfigFile = $this->findShellConfigFile($shellType);

        if ($shellConfigFile !== false) {
            $output->writeln(sprintf('Selected shell configuration file: <info>%s</info>', $this->getShortPath($shellConfigFile)));
            if (file_exists($shellConfigFile)) {
                $currentShellConfig = file_get_contents($shellConfigFile);
                if ($currentShellConfig === false) {
                    $output->writeln('Failed to read file: <error>' . $shellConfigFile . '</error>');
                    return Command::FAILURE;
                }
            }
            $output->writeln('');
        }

        $configDirRelative = $this->config->getUserConfigDir(false);
        $rcDestination = $configDirRelative . DIRECTORY_SEPARATOR . 'shell-config.rc';
        $suggestedShellConfig = 'HOME=${HOME:-' . escapeshellarg($this->config->getHomeDirectory()) . '}';
        $suggestedShellConfig .= PHP_EOL . sprintf(
                'export PATH=%s:"$PATH"',
                '"$HOME/"' . escapeshellarg($configDirRelative . '/bin')
            );
        $suggestedShellConfig .= PHP_EOL . sprintf(
                'if [ -f %1$s ]; then . %1$s; fi',
                '"$HOME/"' . escapeshellarg($rcDestination)
            );

        if (strpos($currentShellConfig, $suggestedShellConfig) !== false) {
            $output->writeln('Already configured: <info>' . $this->getShortPath($shellConfigFile) . '</info>');
            $output->writeln('');
            $output->writeln($this->getRunAdvice($shellConfigFile, $configDir . '/bin'));
            return Command::SUCCESS;
        }

        /** @var \Symfony\Component\Console\Helper\QuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');
        $modify = false;
        $create = false;
        if ($shellConfigFile !== false) {
            $confirmText = file_exists($shellConfigFile)
                ? 'Do you want to update the file automatically?'
                : 'Do you want to create the file automatically?';
            $confirmText .= ' <question>' . ($confirmText ? '[Y/n]' : '[y/N]') . '</question> ';
            $question = new ConfirmationQuestion($confirmText, true);
            if ($questionHelper->ask($input, $output, $question)) {
                $modify = true;
                $create = !file_exists($shellConfigFile);
            }
            $output->writeln('');
        }

        $appName = (string) $this->config->getData('name');
        $begin = '# BEGIN SNIPPET: ' . $appName . ' configuration' . PHP_EOL;
        $end = ' # END SNIPPET';
        $beginPattern = '/^' . preg_quote('# BEGIN SNIPPET:') . '[^\n]*' . preg_quote($appName) . '[^\n]*$/m';

        if ($shellConfigFile === false || !$modify) {
            if ($shellConfigFile !== false) {
                $output->writeln(sprintf(
                    'To set up the CLI, add the following lines to: <comment>%s</comment>',
                    $shellConfigFile
                ));
            } else {
                $output->writeln(
                    'To set up the CLI, add the following lines to your shell configuration file:'
                );
            }
            $output->writeln($begin . $suggestedShellConfig . $end);
            return Command::FAILURE;
        }

        $newShellConfig = (new Snippeter())->updateSnippet($currentShellConfig, $suggestedShellConfig, $begin, $end, $beginPattern);
        if (file_exists($shellConfigFile)) {
            copy($shellConfigFile, $shellConfigFile . '.cli.bak');
        }
        if (!file_put_contents($shellConfigFile, $newShellConfig)) {
            $output->writeln(sprintf('Failed to write to configuration file: <error>%s</error>', $shellConfigFile));
            return Command::FAILURE;
        }

        if ($create) {
            $output->writeln('Configuration file created successfully: <info>' . $this->getShortPath($shellConfigFile) . '</info>');
        } else {
            $output->writeln('Configuration file updated successfully: <info>' . $this->getShortPath($shellConfigFile) . '</info>');
        }

        $output->writeln('');
        $output->writeln($this->getRunAdvice($shellConfigFile, $configDir . '/bin'));

        return Command::SUCCESS;
    }

    /**
     * @param string $shellConfigFile
     * @param string $binDir
     * @param bool|null $inPath
     * @param bool $newTerminal
     *
     * @return string[]
     */
    private function getRunAdvice($shellConfigFile, $binDir, $inPath = null, $newTerminal = false)
    {
        $advice = [
            sprintf('To use the %s,%s run:', $this->config->getData('name'), $newTerminal ? ' open a new terminal, and' : '')
        ];
        if ($inPath === null) {
            $inPath = $this->inPath($binDir);
        }
        if (!$inPath) {
            $sourceAdvice = sprintf('    <info>source %s</info>', $this->formatSourceArg($shellConfigFile));
            $sourceAdvice .= ' # (make sure your shell does this by default)';
            $advice[] = $sourceAdvice;
        }
        $advice[] = sprintf('    <info>%s</info>', $this->config->getData('executable'));

        return $advice;
    }

    /**
     * Check if a directory is in the PATH.
     *
     * @param string $dir
     *
     * @return bool
     */
    private function inPath(string $dir): bool
    {
        $PATH = getenv('PATH');
        $realpath = realpath($dir);
        if (!$PATH || !$realpath) {
            return false;
        }

        return in_array($realpath, explode(':', $PATH));
    }

    /**
     * Transform a filename into an argument for the 'source' command.
     *
     * This is only shown as advice to the user.
     *
     * @param string $filename
     *
     * @return string
     */
    private function formatSourceArg(string $filename): string
    {
        $arg = $filename;

        // Replace the home directory with ~, if not on Windows.
        if (DIRECTORY_SEPARATOR !== '\\') {
            $realpath = realpath($filename);
            $homeDir = $this->config->getHomeDirectory();
            if ($realpath && strpos($realpath, $homeDir) === 0) {
                $arg = '~/' . ltrim(substr($realpath, strlen($homeDir)), '/');
            }
        }

        // Ensure the argument isn't a basename ('source' would look it up in
        // the PATH).
        if ($arg === basename($filename)) {
            $arg = '.' . DIRECTORY_SEPARATOR . $filename;
        }

        // Crude argument escaping (escapeshellarg() would prevent tilde
        // substitution).
        return str_replace(' ', '\\ ', $arg);
    }

    /**
     * @param $name
     * @param OutputInterface $output
     * @param array $arguments
     *
     * @return int
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
     */
    private function runCommand($name, OutputInterface $output, array $arguments = []): int
    {
        $application = $this->getApplication();
        /** @var Command $command */
        $command = $application->find($name);
        $cmdInput = new ArrayInput(['command' => $name] + $arguments);
        $this->debug('Running command: ' . $name);
        // Give the other command an entirely new service container, because the
        // "input" and "output" parameters, and all their dependents, need to
        // change.
        $container = self::$container;
        self::$container = null;
        try {
            $result = $command->run($cmdInput, $output);
        } finally {
            // Restore the old service container.
            self::$container = $container;
        }

        return $result;
    }

    /**
     * Finds a shell configuration file for the user.
     *
     * @param string|null $shellType The shell type.
     *
     * @return string|false
     *   The absolute path to a shell config file, or false on failure.
     */
    protected function findShellConfigFile($shellType)
    {
        // Default to Bash filenames.
        $candidates = [
            '.bashrc',
            '.bash_profile',
        ];

        // OS X ignores .bashrc if .bash_profile is present.
        if ($this->config->isOsX()) {
            $candidates = [
                '.bash_profile',
                '.bashrc',
            ];
        }

        // Use .zshrc on ZSH.
        if ($shellType === 'zsh' || (empty($shellType) && getenv('ZSH'))) {
            $candidates = ['.zshrc'];
        }

        // Pick the first of the candidate files that already exists.
        $homeDir = $this->config->getHomeDirectory();
        foreach ($candidates as $candidate) {
            if (file_exists($homeDir . DIRECTORY_SEPARATOR . $candidate)) {
                $this->debug('Found existing config file: ' . $homeDir . DIRECTORY_SEPARATOR . $candidate);
                return $homeDir . DIRECTORY_SEPARATOR . $candidate;
            }
        }

        if (!is_writable($homeDir)) {
            $this->output->writeln(' <warning>The home directory is not writeable: '.$homeDir.'</warning>');
            return false;
        }

        // If none of the files exist (yet), and the home directory is writable,
        // then create a new file based on the shell type.
        if ($shellType === 'bash') {
            if ($this->config->isOsX()) {
                $this->debug('OS X: defaulting to ~/.bash_profile');
                return $homeDir . DIRECTORY_SEPARATOR . '.bash_profile';
            }
            $this->debug('Defaulting to ~/.bashrc');
            return $homeDir . DIRECTORY_SEPARATOR . '.bashrc';
        } elseif ($shellType === 'zsh') {
            $this->debug('Defaulting to ~/.zshrc');
            return $homeDir . DIRECTORY_SEPARATOR . '.zshrc';
        }
        $this->output->writeln(' <error>The shel config file is not exist and shell type not supported: '.$shellType.'</error>');
        return false;
    }

    /**
     * @param string $text
     *
     * @return void
     */
    private function debug(string $text): void
    {
        $this->output->writeln('<options=reverse>DEBUG</> ' . $text, OutputInterface::VERBOSITY_DEBUG);
    }

    /**
     * Shorten a filename for display.
     *
     * @param string $filename
     *
     * @return string
     */
    private function getShortPath(string $filename): string
    {
        if (getcwd() === dirname($filename)) {
            return basename($filename);
        }
        $homeDir = $this->config->getHomeDirectory();
        if (strpos($filename, $homeDir) === 0) {
            return str_replace($homeDir, '~', $filename);
        }

        return $filename;
    }

    /**
     * Indents and word-wraps a string.
     *
     * @param string $str
     * @param int    $indent
     * @param int    $width
     *
     * @return string
     */
    private function indentAndWrap(string $str, int $indent = 4, int $width = 75): string
    {
        $spaces = str_repeat(' ', $indent);
        $wrapped = wordwrap($str, $width - $indent, PHP_EOL);

        return $spaces . preg_replace('/\r\n|\r|\n/', '$0' . $spaces, $wrapped);
    }

    /**
     * @param resource|int $descriptor
     *
     * @return bool
     */
    protected function isTerminal($descriptor)
    {
        /** @noinspection PhpComposerExtensionStubsInspection */
        return !function_exists('posix_isatty') || posix_isatty($descriptor);
    }
}