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
 composer
  composer:exec        Execute a composer command. Alias: dcm c:e
 magento
  magento:bin          Execute bin/magento {option} from the CLI container as the www user. Alias: dcm m:b
  magento:c:elastic    Set catalog/search/elasticsearch7_server_hostname: elasticsearch.metal.local.sashas.pw
  magento:c:set        bin/magento config:sensitive:set. Alias: dcm m:c:set  [--scope="..."] [--scope-code="..."] path value
  magento:c:show       bin/magento config:show. Alias: dcm m:c:show
  magento:cc           bin/magento cache:clear command. Alias: dcm m:cc
  magento:reindex      bin/magento indexer:reindex command. Alias: dcm m:re
  magento:su           bin/magento setup:upgrade. Alias: dcm m:su
 project
  project:chown        Reset project file ownership to www user.
  project:exec         Execute a command inside CLI container. Short version: dcm p:e
  project:info         Project information for each service
  project:new          Create a new docker local instance project
  project:php:modules  List php modules for the CLI container.
  project:restart      Restart docker containers. Short version: dcm p:r
  project:start        Start docker containers. Short version: dcm p:sta
  project:status       Lists status of containers.
  project:stop         Stop docker containers. Short version: dcm p:sto
  project:update       Update docker containers. Short version: dcm p:u. Restart container after this command.
 self
  self:build           Build a new package of the Docker Container Manager CLI
  self:install         [self-install] Install or update CLI configuration files
  self:update          [self-update|selfupdate] Updates Dcm to the latest version
 services
  services:db:dump     Create a database dump file
  services:db:import   Import database from a dump
  services:info        Local services information for each service
```

## New Release Instructions
- config.json
- compile
- git tag