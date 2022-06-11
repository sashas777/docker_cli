<?php
/*
 * @author     The S Group <support@sashas.org>
 * @copyright  2022  Sashas IT Support Inc. (https://www.sashas.org)
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */
declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\Console\Command\Command;
use Dcm\Cli\App;

return function(ContainerConfigurator $configurator) {
    $services = $configurator->services()
                             ->defaults()
                             ->autowire()
                             ->autoconfigure();
    $services->instanceof(Command::class)
             ->tag('console.command');

    $services->load('Dcm\\Cli\\', '../src/*')
             ->lazy()
             ->exclude('../src/{Tests}')
             ->public();


    $services->load('Symfony\\Component\\HttpClient\\', '../vendor/symfony/http-client');
    $services->load('Symfony\\Component\\Serializer\\', '../vendor/symfony/serializer');
    $services->load('Symfony\\Component\\Filesystem\\', '../vendor/symfony/filesystem');
    $services->load('Symfony\\Component\\Process\\', '../vendor/symfony/process');
    $services->load('Symfony\\Component\\Finder\\', '../vendor/symfony/finder');
    $services->load('Stecman\\Component\\Symfony\\Console\\BashCompletion\\', '../vendor/stecman/symfony-console-completion/src');
    
    $services->set(App::class)
             ->public()
             ->args([tagged_iterator('console.command')]);
};