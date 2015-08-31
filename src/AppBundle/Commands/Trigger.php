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
use Symfony\Component\Yaml\Dumper;

class Trigger extends ContainerAwareCommand {

    protected function getLogger(){
      $logger = $this->getContainer()->get('logger');
      return $logger;
    }

    protected function getYamlParser(){
      //Create yml parser
      $yaml = new Parser();
      return $yaml;
    }

    //Grabs locations from settings.yml
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

  }

    //configuration of the command's name, arguments, options, etc
    protected function configure()
    {
      $this->setName('trigger')
           ->setDescription("Reads from bhqueue.txt/profiles.yml/projects.yml to create new behat configuration file as needed and runs tests");
    }

    //executes code when command is called
    protected function execute(InputInterface $input, OutputInterface $output)
    {
      $this->getLogger()->debug('Trigger called');
      $this->formatOutput($output);

      try {
        //read queue location from settings.yml
        $config = $this->getYamlParser()->parse(file_get_contents(dirname(__FILE__) . '/../../../settings.yml'));
        $bhQ = $config['locations']['queue'];
      } catch (ParseException $e) {
          printf("Unable to parse the YAML string: %s", $e->getMessage());
          return false;
      }
      //Check if there are tests scheduled, i.e., queue file is not empty
      if (file_get_contents($bhQ.'.txt') != ''){
        $projectList = $this->readQueue($bhQ.'.txt');
        //Removed scheduled tests from queue
        file_put_contents($bhQ.'.txt', "");
        foreach($projectList as $p => $e){
          if($e == 'all'){
              //generates/runs tests for both dev and prod
              $this->bhTrigger($p, 'dev', NULL, $output);
              $this->bhTrigger($p, 'production', NULL, $output);
          }else{
              $this->bhTrigger($p, $e, NULL, $output);
          }
        }
        return true;
      }

    }

    //Forms a map array of projects => environments from the queue by parsing each line of the queue string
    protected function readQueue($queue)
    {
      $projectList = array();
      $file = fopen($queue, "r") or exit("Unable to open file!");
      while(!feof($file)){
        $lineinQueue = fgets($file);
        //Grab the project name in isolation from bhqueue and its associated environments
        $pStringOffsetStart = strpos($lineinQueue, "project:") + 8;
        $pStringOffsetEnd = (strrpos($lineinQueue, " on ")) - $pStringOffsetStart;
        $projectName = substr($lineinQueue,  $pStringOffsetStart, $pStringOffsetEnd);
        $environmentName = substr($lineinQueue, strpos($lineinQueue, "environment:") + 12);
        $environmentName = preg_replace('~[\r\n]+~', '', $environmentName);
        //add the project name to the array (if we haven't already,there could be multiple pushes per minute)
        if(!in_array($projectName, $projectList) && strlen($projectName)>0){
          $projectList[$projectName] = $environmentName;
        }
      }
      fclose($file);
      return $projectList;
    }

    //Generates a yml configuration using projects.yml and profiles.yml file given a project and environment
    protected function bhTrigger($project, $env, $profile, OutputInterface $output, $test=true)
    {
      //Read in profiles.yml and projects.yml as arrays
      //Find the location of the .yml files and parse them as strings. Configs in home directory will overwrite any global configs in /etc/
      if(!file_exists('/etc/behat-ci')){
        $this->getLogger()->debug('Creating directory etc/behat-ci/');
        $this->getLogger()->debug(shell_exec('mkdir -p /etc/behat-ci/'));
      }

      //grab the behat binary location

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

    protected function generate($project, $env, $profile, $profiles, $projects, OutputInterface $output, $test)
    {
    //Key-value matching variables in project to profile and then to the output yml
      $behatYaml = array();
      //Checks if drupal root specified (Behat 3)
      if(array_key_exists('suites', $profiles['default'])){
        //Fill in the baseurl (Behat 2)
        $profiles['default']['extensions']['Behat\MinkExtension']['base_url'] = $projects[$project]['environments'][$env]['base_url'];
        //Fill in path to the features directory of the project in default suite
        $profiles['default']['suites']['default']['paths'] = '/srv/www/'.$project.'/'.$env.'/.behat';
        if(array_key_exists('Drupal\DrupalExtension', $profiles['default'])){
          $profiles['default']['extensions']['Drupal\DrupalExtension']['drupal']['drupal_root'] = $projects[$project]['environments'][$env]['drupal_root'];
        }
      } else {
        //Fill in the baseurl (Behat 2)
        $profiles['default']['extensions']['Behat\MinkExtension\Extension']['base_url'] = $projects[$project]['environments'][$env]['base_url'];
        //Fill in path to the features directory of the project
        $profiles['default']['paths']['features'] = '/srv/www/'.$project.'/'.$env.'/.behat';
      }
      //Add the default profile to the generated yaml
      $behatYaml['default'] = $profiles['default'];
      //Get the list of tests to be run and add each of their profiles to the generated yaml
      $profileList = $projects[$project]['profiles'];
      foreach($profileList as $t){
        $profiles[$t]['extensions']['Behat\MinkExtension\Extension']['selenium2']['capabilities']['name'] = $project. ' ' . $env . ' on ' . $t;
        $behatYaml[$t] = $profiles[$t];
      }
      //Create the yml dumper to convert the array to string
      $dumper = new Dumper();
      //Dump into yaml string
      $behatYamlString = $dumper->dump($behatYaml, 7);

      //create the yml file in /tmp
      file_put_contents('/tmp/'.$project.'_'.$env.'.yml', $behatYamlString);
      if(file_exists('/tmp/'.$project.'_'.$env.'.yml')){
        $this->getLogger()->info('Generated the file /tmp/'.$project.'_'.$env.'.yml');
      } else {
        $this->getLogger()->info('FAILED the file /tmp/'.$project.'_'.$env.'.yml');
      }
      $output->writeln('<header>Generated config file for '.$project.' for env '.$env.' in /tmp</header>');
      if($test){
        $this->test($project, $env, $profile, $profileList, $output);
      }
    }

    protected function test($project, $env, $profile, $profileList, $output)
    {
      $behatLocation = $this->getLocation($this->getYamlParser(), 'behat');
      //Run the behat testing command.
      echo shell_exec($behatLocation.'/behat -c /tmp/'.$project.'_'.$env.'.yml');
      $this->getLogger()->info(shell_exec($behatLocation.'/behat -c /tmp/'.$project.'_'.$env.'.yml'));
      //Run test on a single profile if specified
      if($profile){
          $this->getLogger()->info('Running tests on '.$r.' for '.$project);
          if(!shell_exec($behatLocation.'/behat -c /tmp/'.$project.'_'.$env.'.yml -p '.$profile.' --format failed')){
            $output->writeln('<error>'.$profile.' is not a valid profile.</error>');
            $this->getLogger()->error($profile.' is not a valid profile.');
          }
      } else { //else run all the profiles
        foreach($profileList as $r){
          $this->getLogger()->info('Running tests on '.$r.' for '.$project.'...');
          $this->getLogger()->info(shell_exec($behatLocation.'/behat -c /tmp/'.$project.'_'.$env.'.yml -p '.$r.' --format failed'));
        }
      }
      //Remove the file after tests have been run
      shell_exec('rm /tmp/'.$project.'_'.$env.'.yml');
    }

    protected function formatOutput(OutputInterface $output)
    {
      //Formatting terminal output
      $header_style = new OutputFormatterStyle('white', 'green', array('bold'));
      $error_style = new OutputFormatterStyle('white', 'red', array('bold'));
      $output->getFormatter()->setStyle('header', $header_style);
      $output->getFormatter()->setStyle('err', $error_style);
    }

  }
