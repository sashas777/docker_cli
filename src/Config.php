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
use Symfony\Component\Serializer\Serializer;

/**
 * Class Config
 */
class Config extends DataObject
{
    /**
     * Configuration relative path
     */
    const CONFIG_FILE = '/config/config.json';

    /**
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $serializer = new Serializer([], [new JsonEncoder()]);
        $config = file_get_contents(CLI_ROOT . static::CONFIG_FILE);
        $data = $serializer->decode($config, JsonEncoder::FORMAT);
        if (!is_array($data)) {
            $data = [];
        }
        parent::__construct($data);
    }
}