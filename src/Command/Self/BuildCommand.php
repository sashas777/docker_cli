<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2022  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli\Command\Self;

use Dcm\Cli\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

/**
 * Class BuildCommand
 */
class BuildCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'selfbuild';
    /**
     * @var string
     */
    protected static $defaultDescription = 'Build a new package of the Docker Container Manager CLI';

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var Config
     */
    private $config;

    /**
     * Disable when a Phar build run from another Phar.
     * @return bool
     */
    public function isEnabled()
    {
        return !extension_loaded('Phar') || !\Phar::running(false);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        $this->config = new Config();
        $serializer = new Serializer([], [new JsonEncoder()]);

        if (!file_exists(CLI_ROOT . '/vendor')) {
            $output->writeln(sprintf('<error>Directory not found: %s/vendor</error>', CLI_ROOT));
            return Command::FAILURE;
        }

        $boxConfig = [];
        $outputFilename = $this->config->getData('release_relative_path');
        $boxConfig['output'] = CLI_ROOT.'/'.$outputFilename;

        $boxArgs = [CLI_ROOT . '/vendor/bin/box', 'compile', '--no-interaction'];
        if ($output->isVeryVerbose()) {
            $boxArgs[] = '-vvv';
        } elseif ($output->isVerbose()) {
            $boxArgs[] = '-vv';
        } else {
            $boxArgs[] = '-v';
        }

        if (!empty($boxConfig)) {
            $originalConfig = $serializer->decode(
                file_get_contents(CLI_ROOT . $this->config->getData('box_config_relative_path')),
                JsonEncoder::FORMAT
            );
            $boxConfig = array_merge($originalConfig, $boxConfig);
            $boxConfig['base-path'] = CLI_ROOT;
            $tmpJson = tempnam(sys_get_temp_dir(), 'cli-box-');
            file_put_contents($tmpJson, $serializer->encode($boxConfig, JsonEncoder::FORMAT));
            $boxArgs[] = '--config=' . $tmpJson;
        }

        $output->writeln('<info>Building Phar package using Box</info>');

        $process = new Process($boxArgs, CLI_ROOT);
        $process->run(function ($type, $buffer) {
            if (Process::ERR === $type) {
                echo 'ERR > '.$buffer;
                $this->output->writeln('<error/>'.$buffer.'<error>');
            } else {
                if (!$this->output->isQuiet()) {
                    $this->output->writeln($buffer);
                }
            }
        });

        /* Remove the temporary file. */
        if (!empty($tmpJson)) {
            unlink($tmpJson);
        }

        if (!file_exists($boxConfig['output'])) {
            $output->writeln(sprintf('<error>Build failed: file not found: %s</error>', $boxConfig['output']));
            return Command::FAILURE;
        }
        $sha1 = sha1_file($boxConfig['output']);
        $sha256 = hash_file('sha256', $boxConfig['output']);
        $size = filesize($boxConfig['output']);

        $output->writeln('<info>The package was built successfully:</info>');

        $releaseInfo = [
            'size' => sprintf(FormatterHelper::formatMemory($size)),
            'sha-1' => sprintf($sha1),
            'sha-256' => sprintf($sha256),
        ];
        file_put_contents(
            CLI_ROOT . $this->config->getData('release_signature_relative_path'),
            $serializer->encode($releaseInfo, JsonEncoder::FORMAT)
        );
        
        $output->writeln($releaseInfo);

        return Command::SUCCESS;
    }
}