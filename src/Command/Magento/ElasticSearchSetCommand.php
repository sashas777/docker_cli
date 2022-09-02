<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2022  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli\Command\Magento;

use Dcm\Cli\Command\AbstractAliasCommand;
use Dcm\Cli\Service\Updater;

/**
 * Class ElasticSearchSetCommand
 */
class ElasticSearchSetCommand extends AbstractAliasCommand
{
    const ELASTICSEARCH_SERVICE_NAME = 'elasticsearch';
    const ELASTICSEARCH_SERVICE_PROPERTY_HOSTNAME = 'hostname';
    protected static $defaultName = 'magento:c:elastic';

    /**
     * @param Updater $updater
     * @param string|null $name
     */
    public function __construct(
        Updater $updater,
        string $name = null
    ) {
        parent::__construct($updater, $name);

        $esHost = null;

        if (!$this->isEnabled()) {
            return;
        }

        $composeConfig = $this->config->getDockerComposeFile();

        foreach ($composeConfig['services'] as $serviceName => $serviceInfo) {
            if ($serviceName == static::ELASTICSEARCH_SERVICE_NAME) {
                foreach ($serviceInfo as $property => $value) {
                    if ($property== static::ELASTICSEARCH_SERVICE_PROPERTY_HOSTNAME) {
                        $esHost = $this->parseEnvVar($value);
                    }
                }
            }
        }
        $this->setDescription('Set catalog/search/elasticsearch7_server_hostname: <info>'. $esHost.'</info>');

        $commandInline = 'docker-compose exec -u www cli bin/magento config:set catalog/search/elasticsearch7_server_hostname '.$esHost;
        $command = explode(' ', $commandInline);
        $this->setCommand($command);
    }

    /**
     * @param string $input
     *
     * @return string
     */
    private function parseEnvVar(string $input): string
    {
        $envConfig = $this->config->getDotEnvConfig();
        foreach ($envConfig as $key=>$value) {
            $input = str_replace('${'.$key.'}', $value, $input);
        }
        return $input;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setHelp(<<<EOF
Use this command to execute bin/magento config:set catalog/search/elasticsearch7_server_hostname {host}
EOF
        );
    }

    /**
     * Disable when no env or bin/magento not exists
     * @return bool
     */
    public function isEnabled()
    {
        return is_array($this->config->getDotEnvConfig()) && $this->config->isMagento();
    }
}