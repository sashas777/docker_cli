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
 * Class Composer
 */
class Composer
{
    const COMPOSER_FILE = DS.'src'.DS.'composer.json';

    /**
     * @var \Dcm\Cli\Service\Validation\Docker
     */
    private $dockerValidation;

    /**
     * @var null
     */
    private $isComposer = null;

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
    public function isComposer(): bool
    {
        if ($this->isComposer !== null) {
            return $this->isComposer;
        }

        if (!$this->dockerValidation->isCliRunning()) {
            $this->isComposer = false;
            return $this->isComposer;
        }

        if (!is_readable(getcwd().static::COMPOSER_FILE)) {
            $this->isComposer = false;
            return $this->isComposer;
        }

        $this->isComposer = true;
        return $this->isComposer;
    }
}