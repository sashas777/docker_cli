<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2022  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli\Service;

use Dcm\Cli\Config;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

/**
 * Class Updater
 */
class Updater
{
    /**
     * Path to a backup file
     * @var string
     */
    private $backupFileName;

    /**
     * Dcm home bin dir
     * @var string
     */
    private $binDir;

    /**
     * Dcm new version phar
     * @var string
     */
    private $pharName;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var HttpRequest
     */
    private $client;

    /**
     * @var JsonEncoder
     */
    private $serializer;

    /**
     * @param Config $config
     * @param HttpRequest $client
     * @param JsonEncoder $serializer
     */
    public function __construct(
        Config $config,
        HttpRequest $client,
        JsonEncoder $serializer
    ) {
        $this->config = $config;
        $this->client = $client;
        $this->serializer = $serializer;
    }

    /**
     * @return string|null
     * @throws \Exception
     */
    public function findLatestVersion(): ?string {
        $url = $this->config->getData('packagist_url');

        $content = $this->client->getHttpContent($url);
        if (!$content) {
            throw new \Exception('Failed to download package information file');
        }
        $packageInfo = $this->serializer->decode($content,JsonEncoder::FORMAT, [JsonDecode::ASSOCIATIVE =>true]);
        $packageName = $this->config->getData('package_name');
        if (!isset($packageInfo['packages']) || !isset($packageInfo['packages'][$packageName])) {
            throw new \Exception('Failed to read package information file: '.$url);
        }
        $versions = $packageInfo['packages'][$packageName];
        return  $this->findInstallableVersion($versions);
    }

    /**
     * @param array $versions
     *
     * @return string|null
     */
    private function findInstallableVersion(array $versions): ?string
    {
        $debug = '';
        foreach ($versions as $version) {
            if (!isset($version['require']['php']) || !isset($version['version'])) {
                continue;
            }
            $cliMinVersion = str_replace('~','', $version['require']['php']);
            if (version_compare(PHP_VERSION, $cliMinVersion, '<')) {
                $debug.=sprintf(
                    '  Version %s requires PHP %s (current PHP version is %s).',
                    $version['version'],
                    $cliMinVersion, PHP_VERSION
                );
                continue;
            }
            $debug.= sprintf(
                '  Version %s requires PHP %s (current PHP version is %s).',
                $version['version'],
                $cliMinVersion,
                PHP_VERSION
            );
            return (string) $version['version'];
        }
        throw new \Exception('No installable versions were found.'.$debug);
    }

    /**
     * @param string $version
     *
     * @return void
     * @throws \Exception
     */
    public function update(string $version): void
    {
        $this->backupPhar();
        $this->downloadPhar($version);
        $this->replacePhar();
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function backupPhar(): void
    {
        $userConfigDir = $this->config->getUserConfigDir();
        $this->binDir = $userConfigDir . DS . 'bin';
        $pharFile = $this->binDir. DS . $this->config->getData('executable');
        $this->backupFileName = $pharFile.$this->config->getData('update_backup_ext');
        if (!is_writable($this->binDir)) {
            throw new \Exception('The home directory is not writeable: '.$this->binDir);
        }

        $result = copy($pharFile, $this->backupFileName);
        if ($result === false) {
            throw new \Exception(sprintf(
                'Unable to backup %s to %s.',
                $pharFile,
                $this->backupFileName
            ));
        }
    }

    /**
     * @param string $version
     *
     * @return void
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    protected function downloadPhar(string $version): void
    {
        $url = sprintf($this->config->getData('release_url'), $version);
        $signatureUrl = sprintf($this->config->getData('signature_url'), $version);
        $this->pharName = $this->binDir . DS . $this->config->getData('phar_name');

        $contents = $this->client->getHttpContent($url);
        if (!$contents) {
            $this->cleanupAfterError();
            throw new \Exception('The download failed');
        }
        if (!file_put_contents($this->pharName, $contents)) {
            $this->cleanupAfterError();
            throw new \Exception('Failed to write to file: '.$this->pharName);
        }
        $signatureContents = $this->client->getHttpContent($signatureUrl);
        if (!$signatureContents) {
            $this->cleanupAfterError();
            throw new \Exception('The signature file download failed');
        }
        $signature = $this->serializer->decode(
            $signatureContents,
            JsonEncoder::FORMAT,
            [JsonDecode::ASSOCIATIVE =>true]
        );

        $pharPath = realpath($this->pharName) ?: $this->pharName;

        if ($signature['sha-256'] !== hash_file('sha256', $pharPath)) {
            $this->cleanupAfterError();
            throw new \Exception('The download was incomplete, or the file is corrupted');
        }

        $phar = new \Phar($pharPath);

        if (!chmod($pharPath, 0755)) {
            throw new \Exception('Failed to make the Phar executable');
        }
    }

    /**
     * @return void
     */
    protected function replacePhar(): void
    {
        $localPharFile = $this->config->getUserConfigDir().
                         DS . 'bin'. DS . $this->config->getData('executable');
        rename($this->pharName, $localPharFile);
    }

    /**
     * @return void
     */
    protected function cleanupAfterError(): void
    {
        if ($this->pharName && file_exists($this->pharName)) {
            @unlink($this->pharName);
        }
    }

    /**
     * Perform an rollback to previous installed version
     *
     * @return bool
     */
    public function rollback(): void
    {
        $userConfigDir = $this->config->getUserConfigDir();
        $this->binDir = $userConfigDir . DS . 'bin';
        $backupPharFile = $this->binDir. DS .
                    $this->config->getData('executable').$this->config->getData('update_backup_ext');
        $localPharFile = $this->binDir. DS . $this->config->getData('executable');
        if (!file_exists($backupPharFile)) {
            throw new \Exception(sprintf(
                'The backup file does not exist: %s.', $backupPharFile
            ));
        }
        rename($backupPharFile, $localPharFile);
    }

    /**
     * @return Config
     */
    public function getConfig(): Config
    {
        return $this->config;
    }
}