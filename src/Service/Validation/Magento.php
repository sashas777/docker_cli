<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2022  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli\Service\Validation;

use Dcm\Cli\Service\Validation\Docker;

/**
 * Class Magento
 */
class Magento
{
    const BIN_MAGENTO_FILE = DS.'src'.DS.'bin'.DS.'magento';

    /**
     * @var \Dcm\Cli\Service\Validation\Docker
     */
    private $dockerValidation;

    /**
     * @var null
     */
    private $isMagento = null;

    /**
     * @param \Dcm\Cli\Service\Validation\Docker $dockerValidation
     */
    public function __construct(
        Docker $dockerValidation
    ) {
        $this->dockerValidation = $dockerValidation;
    }

    /**
     * @return bool
     */
    public function isMagento(): bool
    {
        if ($this->isMagento !== null) {
            return $this->isMagento;
        }

        if (!$this->dockerValidation->isCliRunning()) {
            $this->isMagento = false;
            return $this->isMagento;
        }

        if (!is_readable(getcwd().static::BIN_MAGENTO_FILE)) {
            $this->isMagento = false;
            return $this->isMagento;
        }
        $this->isMagento = true;
        return $this->isMagento;
    }
}