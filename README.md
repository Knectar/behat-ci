behat-ci
================


## Introduction
Behat continuous integration CLI is an efficient way to have behat testing run automatically when a project is updated. It will also generate a custom behat.yml configuration file for you. All you have to do is specify the profiles. With the 'bh test' command, you can also run tests manually against any project from any directory on the server.

## System requirements
* git
* [Composer](https://getcomposer.org/)
* The latest stable version of [Symfony](http://symfony.com/ "Symfony 2")
* PHP 5.5+ (date.timezone must be defined in php.ini for these commands to work properly)

## Installation
1. Include behat-ci in your global composer.json with `composer global require knectar/behat-ci`
2. Run `composer global update` to install (No database configuration needed.)
3. Run `composer install` in the directory where the app was saved. (usually /home/user/.composer/vendor/knectar/behat-ci/app)
4. Update your system PATH to include the application's app directory (path/to/global/.composer/vendor/knectar/behat-ci/app)
5. That is all, we can now run this application's commands!

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

Note: by default, projects.yml, profiles.yml, bhqueue, and bhqueuelog are located in the behat-ci/ directory. Trigger will look for profiles.yml and projects.yml to be set up in /etc/ or in the user's home directory. Otherwise the absolute paths to the files must be set in config.yml so the command knows where to look.

## Usage
For details on the commands in the console, simply run

    bh

The application relies on 2 key commands,

    bh schedule
Will be called when changes are pushed. This command updates bhqueue.txt, which tells the application that tests need to be run.

    bh trigger
Is meant to be called periodically (every minute) by cron on the server. It checks bhqueue to see if a change has been made in the project and if testing needs to be done. If a new behat.yml configuration file is needed, it will read in the profiles from bhqueue and grab the necessary variables from the environments.yml to generate the profiles in a new configuration file and then run the testing command on the server.

To run tests manually, run

    bh test
with the same input arguments as bh schedule
