behat-ci
================


## Introduction
Behat continuous integration CLI is an efficient way to manage Behat configurations and have Behat testing run automatically when a project is updated. It will also generate a custom Behat.yml configuration file for you. All you have to do is specify the profiles.

## System requirements
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
The smooth running of behat-ci relies on several files working in synchrony.

_bhqueue.txt_ is a simple text file that keeps track of when tests are scheduled to be run, written to by the `bh schedule` command.

_profiles.yml_ is a list of device profiles as well as default Behat configuration. Feel free to add more and customize according to the current standards and what is available on sauce labs. See the sample files for use as a template.

_projects.yml_ is a list of your projects that to run tests against. It will contain an entry per project, structured as follows:

```yml
whitetest: #the overall project name. uses the beanstalkapp machine name
  environments: # should match the branch
    -dev:
        base_url: http://example.com
        features: /path/to/project/features/directory
    -production:
        base_url: http://example.com
        features: /path/to/project/features/directory
  profiles: #to run as set in profiles.yml
    - win-chrome
    - android
    - iPhone
  behat-params: #optional additional parameters
        format: html
        out: /path/to/output.html
```

Note: by default, projects.yml, profiles.yml, bhqueue, and bhqueuelog are located in the /etc/behat-ci/ directory. Trigger will look for profiles.yml and projects.yml to be set up in /etc/behat-ci or in the user's home directory. Otherwise the absolute paths to the files must be set in config.yml so the command knows where to look.

## Usage
For details on the commands in the console, simply run

    bh

The application relies on 2 key commands,

    bh schedule
Is to be called when changes are pushed (or manually). This command will
1. Update bhqueue.txt, which tells the application that tests need to be run.
2. Generate the .yml configuration file required to run customized Behat configurations. This file will be stored in /tmp and disposed of by the 'trigger' command. You may use behat-ci to just generate behat.yml files and execute behat testing manually by running `bh schedule $projname -b $branchname` followed by `behat -c /tmp/project_env.yml`

    bh trigger
Is meant to be called periodically (every minute) by cron on the server. It checks bhqueue to see if a change has been made in the project and if testing needs to be done. It will run the Behat tests as needed with the specified flags/parameters.

To run tests manually in a single command, run

    bh test
with the same input arguments as bh schedule, and the application will perform schedule and trigger consecutively.
