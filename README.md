Behat Test Automator
================


## Introduction
Behat Test Automator is an efficient way to have behat testing run automatically when a project is updated. It will also generate a custom behat.yml configuration file for you. All you have to do is specify the profiles.

## System requirements
* git
* [Composer](https://getcomposer.org/)
* The latest version of [Symfony](http://symfony.com/ "Symfony 2")
* PHP 5.5+ (date.timezone must be defined in php.ini for these commands to work properly)

## Installation
1. Clone the repository with `git clone`
2. In the project directory, run `composer install` (No database configuration needed.)
3. Run `composer update` to make sure we have the latest versions of Symfony components.
4. That is all, we can now run this application's commands!

## Configuration

There are 2 files that control how where and on what devices a test runs on.

_profiles.yml_ is a list of device profiles. Feel free to add more according to the current standards and what is available on sauce labs.

_projects.yml_ is a list of projects that can be test against. It will contain an entry per project to run the code against.

Each entry will be structured like so.

```yml
whitetest: #the overall project. uses the beanstalkapp mancine name
  environments: # should match the branch
    -dev:
        base_url: http://example.com
    -production:
        base_url: http://example.com
  profiles: #to run as set in profiles.yml
    - win-chrome
    - android
    - iPhone
```

## Usage
All commands must be run in the project directory and prefixed with app/console.
For details on the commands in the console, run

    app/console bh --help

The application relies on 2 key commands,

    app/console bh:schedule
Will be called when changes are pushed. This command updates bhqueue.txt, which tells the application that tests need to be run.

    app/console bh:trigger
Is meant to be called periodically (every minute) by cron on the server. It checks bhqueue to see if a change has been made in the project and if testing needs to be done. If a new behat.yml configuration file is needed, it will read in the profiles from bhqueue and grab the necessary variables from the environments.yml to generate the profiles in a new configuration file and then run the testing command on the server.


TODO: Later
