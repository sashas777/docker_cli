<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2022  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

declare(strict_types=1);

namespace Dcm\Cli\Command\Self;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCommand extends Command
{
    protected static $defaultName = 'self:update';
    protected static $defaultDescription = 'Updates Dcm to the latest version';

    protected function configure(): void
    {
        $this->setAliases(['self-update']);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Update');
        return Command::SUCCESS;
    }
}