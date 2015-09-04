<?php

namespace AppBundle\Commands;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Exception\ParseException;
use Psr\Log\LoggerInterface;

class Schedule extends ContainerAwareCommand {

  protected function getLogger(){
    $logger = $this->getContainer()->get('logger');
    return $logger;
  }

  protected function getYamlParser(){
    //Create yml parser
    $yaml = new Parser();
    return $yaml;
  }

  //Grabs locations from settings.yml and confirms existance of files at their specified paths
  protected function getLocation($yamlParser, $file){
    switch($file){
      case 'behat':
        $config = $this->getYamlParser()->parse(file_get_contents(dirname(__FILE__) . '/../../../settings.yml'));
        $location = $config['locations']['behat'] === '/home/sites/.composer/vendor/bin' ? $_SERVER['HOME'].'/.composer/vendor/bin': $config['locations']['behat'];
        if(!file_exists($location.'/behat')){
          $logger->info('Behat not found at '.$location.'. Please set the absolute path to your behat binary in settings.yml');
          die('Behat not found at '.$location.'. Please set the absolute path to your behat binary in settings.yml');
        }
      case 'profiles.yml':
      case 'projects.yml':
        if(file_exists($_SERVER['HOME'] . '/' . $file)){
          $this->getLogger()->debug('Found '.$file.' in '.$_SERVER['HOME']);
          $location = $_SERVER['HOME'] . '/' . $file;
        } else if (file_exists('/etc/behat-ci/'.$file)){
          $this->getLogger()->debug('Found '.$file.' in /etc/behat-ci/');
          $location = '/etc/behat-ci/'.$file;
        } else {
          //If the paths aren't set by the user, they must be in the app directory.
          //Read from file paths set in settings.yml.
          $config = $this->getYamlParser()->parse(file_get_contents(dirname(__FILE__) . '/../../../settings.yml'));
          $location = ($config['locations'][$file] === $file ? dirname(__FILE__)  . '/../../../'.$file : $config['locations'][$file]);
          $this->getLogger()->debug($file.' found in '.$location.' per settings.yml');
    }
  }
  return $location;
   //configuration of the command's name, arguments, options, etc
    protected function configure()
    {
      $this->setName('schedule')
           ->setDescription("Writes to bhqueue.txt indicating that tests should be run (also to generate a new configuration file as needed). To be run on beanstalk post-deploy commands with the -e flag specifying environments")
           ->addArgument('repo_name', InputArgument::REQUIRED, "The name of the project repo (%REPO_NAME% in Beanstalk post-deployment)")
           ->addOption('branch',
                        'b',
                        InputOption::VALUE_OPTIONAL,
                        'The environment/branch. use --branch=all for both dev and production',
                        1
                      );
      }

    //executes code when command is called
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->readConfigFiles($input->getArgument('repo_name'), $input->getOption('branch'), $input, $output);
        //Make sure the input is a proper environment
        if($e!='all' && $e!='dev' && $e!='production'){
          $output->writeln('<error>Please enter a valid environment! (dev, production, all)<error>');
          return false;
        } else {
          try{
            //Create the yaml parser
            $yaml = new Parser();
            //read queue location from config.yml
            $config = $yaml->parse(file_get_contents(dirname(__FILE__) . '/../../../settings.yml'));
            $bhQ = $config['locations']['queue'];
          } catch (ParseException $e) {
              $logger->error("Unable to parse the YAML string: %s");
              printf("Unable to parse the YAML string: %s", $e->getMessage());
          }
          //write timestamp, project name, instance to queue.
          $projName = $input->getArgument('repo_name');
          $newQueueTestOnly = fopen($bhQ.'.txt', "a") or die("Unable to open file!");
          fwrite($newQueueTestOnly, "Test scheduled on: " . date("D M j G:i:s") . " for project:" . $projName ." on environment:".$e."\n");
          $logger->info('Queued Tests for '.$projName.' on branch '.$e);
          $output->writeln('Schedule request received');
          fclose($newQueueTestOnly);
          return true;
        }
      }

      protected function readConfigFiles($project, $branch, InputInterface $input, OutputInterface $output){
        $yaml = new Parser();
        $config = $yaml->parse(file_get_contents(dirname(__FILE__) . '/../../../settings.yml'));
        $logger = $this->getContainer()->get('logger');
        try{
          $logger->info('Schedule Called');
        } catch (Exception $e){
          $output->writeln('Could not write to /var/log');
        }
        $behatLocation = $config['locations']['behat'] === '/home/sites/.composer/vendor/bin' ? $_SERVER['HOME'].'/.composer/vendor/bin': $config['locations']['behat'];
        if(!file_exists($behatLocation.'/behat')){
          $logger->info('Behat not found at '.$behatLocation.'. Please set the absolute path to your behat binary in settings.yml');
          die('Behat not found at '.$behatLocation.'. Please set the absolute path to your behat binary in settings.yml');
        }
      }

      protected function configAsArrays($project, $env, $profile, OutputInterface $output, $test=true){
        if(!file_exists('/etc/behat-ci')){
          $this->getLogger()->debug('Creating directory etc/behat-ci/');
          $this->getLogger()->debug(shell_exec('mkdir -p /etc/behat-ci/'));
        }

        try {
          $projectsLocation = $this->getLocation($this->getYamlParser(), 'projects.yml');
          $profilesLocation = $this->getLocation($this->getYamlParser(), 'profiles.yml');
          $projects = $this->getYamlParser()->parse(file_get_contents($projectsLocation));
          $profiles = $this->getYamlParser()->parse(file_get_contents($profilesLocation));
        } catch (ParseException $e) {
            $this->getLogger()->error("Unable to parse the YAML string: %s");
            printf("Unable to parse the YAML string: %s", $e->getMessage());
        }
        //Generate the .yml config and run the tests
        $this->generate($project, $env, $profile, $profiles, $projects, $output, $test);
      }
    }
