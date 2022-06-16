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
 project
  project:new   Create a new docker local instance project
 services
  services:new  Generate docker-compose.yml file for local services
 self
  self:update   Updates Dcm to the latest version
```