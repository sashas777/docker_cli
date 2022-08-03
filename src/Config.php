<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2022  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli;

use Dcm\Cli\Service\DataObject;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Dcm\Cli\Service\Dotenv;
use Symfony\Component\Yaml\Yaml;

/**
 * Class Config
 */
class Config extends DataObject
{
    private $homeDir;
    private $env;

    /**
     * Path to local configuration file
     */
    const LOCAL_SERVICE_CONFIG_KEY = 'local_services_path';
    /**
     * The name of database container
     */
    const DB_CONTAINER_NAME = 'db';

    /**
     * @var JsonEncoder
     */
    private $serializer;

    /**
     * @var Dotenv
     */
    private $dotenv;

    /**
     * @var Yaml
     */
    private $yaml;

    /**
     * Configuration relative path
     */
    const CONFIG_FILE = DS.'config'.DS.'config.json';
    const BIN_MAGENTO_FILE = DS.'src'.DS.'bin'.DS.'magento';
    const COMPOSER_FILE = DS.'src'.DS.'composer.json';

    /**
     * @param JsonEncoder $serializer
     * @param Dotenv $dotenv
     * @param Yaml $yaml
     * @param array $data
     */
    public function __construct(
        JsonEncoder $serializer,
        Dotenv $dotenv,
        Yaml $yaml,
        array $data = []
    ) {
        $this->serializer = $serializer;
        $this->dotenv = $dotenv;
        $this->yaml = $yaml;
        $config = file_get_contents(CLI_ROOT . static::CONFIG_FILE);
        $data = $this->serializer->decode($config, JsonEncoder::FORMAT, [JsonDecode::ASSOCIATIVE =>true]);
        if (!is_array($data)) {
            $data = [];
        }
        $this->env = getenv();
        parent::__construct($data);
    }

    /**
     * Get the directory where the CLI is normally installed and configured.
     *
     * @param bool $absolute Whether to return an absolute path. If false,
     *                       the path will be relative to the home directory.
     *
     * @return string
     */
    public function getUserConfigDir($absolute = true)
    {
        $path = $this->getData('user_config_dir');
        return $absolute ? $this->getHomeDirectory() . DS . $path : $path;
    }

    /**
     * Returns the user's home directory.
     *
     * @param bool $reset Reset the static cache.
     *
     * @return string The absolute path to the user's home directory
     */
    public function getHomeDirectory($reset = false)
    {
        if (!$reset && isset($this->homeDir)) {
            return $this->homeDir;
        }
        $envVars = ['HOME', 'USERPROFILE'];
        foreach ($envVars as $envVar) {
            $value = getenv($envVar);
            if (array_key_exists($envVar, $this->env)) {
                $value = $this->env[$envVar];
            }
            if (is_string($value) && $value !== '') {
                if (!is_dir($value)) {
                    throw new \RuntimeException(
                        sprintf('Invalid environment variable %s: %s (not a directory)', $envVar, $value)
                    );
                }
                $this->homeDir = realpath($value) ?: $value;
                return $this->homeDir;
            }
        }

        throw new \RuntimeException('Could not determine home directory. Set the HOME environment variable.');
    }

    /**
     * @return bool
     */
    public function isOsX(): bool
    {
        return stripos(PHP_OS, 'Darwin') !== false;
    }

    /**
     * @return bool
     */
    public function isWindows(): bool
    {
        return defined('PHP_WINDOWS_VERSION_BUILD');
    }

    /**
     * @return bool
     */
    public function isLinux(): bool
    {
        return stripos(PHP_OS, 'Linux') !== false;
    }

    /**
     * @return array|null
     */
    public function getDotEnvConfig(): ?array
    {
        if (is_readable($this->getData('env_file'))) {
            return $this->dotenv->parse($this->getData('env_file'));
        }
        return null;
    }

    /**
     * @return array|null
     */
    public function getDockerComposeFile(): ?array
    {
        if (is_readable($this->getData('compose_file'))) {
            return $this->yaml->parseFile($this->getData('compose_file'));
        }
        return null;
    }

    /**
     * @return bool
     */
    public function isMagento(): bool
    {
        if (is_readable(getcwd().static::BIN_MAGENTO_FILE)) {
            return true;
        }
        return false;
    }

    /**
     * @return bool
     */
    public function isComposer(): bool
    {
        if (is_readable(getcwd().static::COMPOSER_FILE)) {
            return true;
        }
        return false;
    }

    private function getLocalConfigFilePath(): string
    {
        return $this->getUserConfigDir(true) . DS. $this->getData('local_config_file');
    }

    /**
     * @param string $key
     * @param string $value
     *
     * @return $this
     */
    public function saveLocalConfig(string $key, string $value): self
    {
        if (!file_exists($this->getLocalConfigFilePath())) {
            file_put_contents(
                $this->getLocalConfigFilePath(),
                $this->serializer->encode([], JsonEncoder::FORMAT, [JsonDecode::ASSOCIATIVE =>true])
            );
        }
        $config = file_get_contents($this->getLocalConfigFilePath());
        $data = $this->serializer->decode($config, JsonEncoder::FORMAT, [JsonDecode::ASSOCIATIVE =>true]);
        if (!is_array($data)) {
            $data = [];
        }
        $data[$key] = $value;
        $jsonConfig = $this->serializer->encode($data, JsonEncoder::FORMAT, [JsonDecode::ASSOCIATIVE =>true]);
        file_put_contents($this->getLocalConfigFilePath(), $jsonConfig);
        return $this;
    }

    /**
     * @param string $key
     *
     * @return string|null
     */
    public function getLocalConfig(string $key): ?string
    {
        if (!is_readable($this->getLocalConfigFilePath())) {
            return null;
        }
        $config = file_get_contents($this->getLocalConfigFilePath());
        $data = $this->serializer->decode($config, JsonEncoder::FORMAT, [JsonDecode::ASSOCIATIVE =>true]);
        if (!is_array($data) || !isset($data[$key])) {
            return null;
        }
        return (string) $data[$key];
    }

    /**
     * @return array|null
     */
    public function getLocalServicesComposeFile(): ?array
    {
        $lsDir = $this->getLocalConfig(Config::LOCAL_SERVICE_CONFIG_KEY);
        $composeFile = $lsDir.DS.$this->getData('compose_file');
        if (is_readable($composeFile)) {
            return $this->yaml->parseFile($composeFile);
        }
        return null;
    }
}