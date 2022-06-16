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

/**
 * Class Config
 */
class Config extends DataObject
{
    private $homeDir;
    private $env;

    /**
     * @var JsonEncoder
     */
    private $serializer;

    /**
     * @var Dotenv
     */
    private $dotenv;

    /**
     * Configuration relative path
     */
    const CONFIG_FILE = '/config/config.json';

    /**
     * @param JsonEncoder $serializer
     * @param Dotenv $dotenv
     * @param array $data
     */
    public function __construct(
        JsonEncoder $serializer,
        Dotenv $dotenv, array
        $data = []
    ) {
        $this->serializer = $serializer;
        $this->dotenv = $dotenv;
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
        return $absolute ? $this->getHomeDirectory() . DIRECTORY_SEPARATOR . $path : $path;
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

}