<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2023  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli\Service\DataObject;

/**
 * Class SimpleDataObjectConverter
 */
class SimpleDataObjectConverter
{
    /**
     * Converts an input string from snake_case to upper CamelCase.
     *
     * @param string $input
     * @return string
     */
    public static function snakeCaseToUpperCamelCase($input)
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $input)));
    }
}