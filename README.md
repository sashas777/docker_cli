# Docker Container Manager CLI
[![Latest Stable Version](https://poser.pugx.org/thesgroup/docker-container-manager/v)](//packagist.org/packages/thesgroup/docker-container-manager) [![Total Downloads](https://poser.pugx.org/thesgroup/docker-container-manager/downloads)](//packagist.org/packages/thesgroup/docker-container-manager) [![Latest Unstable Version](https://poser.pugx.org/thesgroup/docker-container-manager/v/unstable)](//packagist.org/packages/thesgroup/docker-container-manager) [![License](https://poser.pugx.org/thesgroup/docker-container-manager/license)](//packagist.org/packages/thesgroup/docker-container-manager)

Command line interface for docker local environment

## Installation
```bash
wget https://raw.githubusercontent.com/sashas777/docker_cli/master/install/installer -O - -q | php --
```
After the installation, you can use the CLI by the command alias: `dcm` 

## Commands
```
 magento
  magento:bin         Runs bin/magento {option} from the CLI container as the www user. Short version: dcm m:b
  magento:cc          Runs bin/magento cache:clear command. Short version: dcm m:cc
  magento:su          Runs bin/magento setup:upgrade command. Short version: dcm m:su
 project
  project:info        Project information for each service
  project:new         Create a new docker local instance project
  project:restart     Restart docker containers. Short version: dcm p:r
  project:start       Start docker containers. Short version: dcm p:sta
  project:stop        Stop docker containers. Short version: dcm p:sto
  project:update      Update docker containers. Short version: dcm p:u. Restart container after this command.
 self
  self:build          Build a new package of the Docker Container Manager CLI
  self:install        [self-install] Install or update CLI configuration files
  self:update         [self-update|selfupdate] Updates Dcm to the latest version
 services
  services:db:dump    Create a database dump file
  services:db:import  Import database from a dump
  services:info       Local services information for each service
```