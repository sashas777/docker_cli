<?php
declare(strict_types=1);

namespace Dcm\Cli\Command\Self;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCommand extends Command
{
    protected static $defaultName = 'selfupdate';
    protected static $defaultDescription = 'Updates Dcm to the latest version';

    protected function configure(): void
    {
        $this->setAliases(array('self-update'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Update');
        return Command::SUCCESS;
    }
}