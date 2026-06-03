<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2026  Edeavour Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli\Command\Magento;

use Dcm\Cli\Command\AbstractAliasCommand;
use Dcm\Cli\Service\Updater;
use Dcm\Cli\Service\Images\OpenSearch;

/**
 * Class OpenSearchSetCommand
 */
class OpenSearchSetCommand extends AbstractAliasCommand
{
    protected static $defaultName = 'magento:c:opensearch';

    /**
     * @param Updater $updater
     * @param string|null $name
     */
    public function __construct(
        Updater $updater,
        string $name = null
    ) {
        parent::__construct($updater, $name);

        $osHost = null;

        if (!$this->isEnabled()) {
            return;
        }

        $composeConfig = $this->config->getDockerComposeFile();

        if (isset($composeConfig['services'])) {
            foreach ($composeConfig['services'] as $serviceName => $serviceInfo) {
                if ($serviceName == OpenSearch::SERVICE_NAME) {
                    foreach ($serviceInfo as $property => $value) {
                        if ($property == OpenSearch::OPENSEARCH_SERVICE_PROPERTY_HOSTNAME) {
                            $osHost = $value;
                        }
                    }
                }
            }
        }
        $this->setDescription('Set catalog/search/opensearch_server_hostname: <info>'. $osHost.'</info>');

        $commandInline = 'docker-compose exec -u www cli bin/magento config:set catalog/search/opensearch_server_hostname '.$osHost;
        $command = explode(' ', $commandInline);
        $this->setCommand($command);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setHelp(<<<EOF
Use this command to execute bin/magento config:set catalog/search/opensearch_server_hostname {host}
EOF
        );
    }

    /**
     * Disable when no env or bin/magento not exists
     * @return bool
     */
    public function isEnabled()
    {
        return $this->updater->getMagentoValidation()->isMagento();
    }
}
