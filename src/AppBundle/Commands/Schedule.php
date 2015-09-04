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
          $this->getLogger()->info('Behat not found at '.$location.'. Please set the absolute path to your behat binary in settings.yml');
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
      $this->getLogger()->debug('Schedule called');
      $this->formatOutput($output);

      $project = $input->getArgument('repo_name');
      $env = $input->getOption('branch');

        if($this->readConfigFiles($project, $env, $input, $output)){
          try{
            //read queue location from config.yml
            $config =  $this->getYamlParser()->parse(file_get_contents(dirname(__FILE__) . '/../../../settings.yml'));
            $bhQ = $config['locations']['queue'];
          } catch (ParseException $e) {
              $this->getLogger()->error("Unable to parse the YAML string: %s");
              printf("Unable to parse the YAML string: %s", $e->getMessage());
          }
          //write timestamp, project name, instance to queue.

          $queue = fopen($bhQ.'.txt', "a") or die("Unable to open file!");
          fwrite($queue, '/tmp/'.$project.'_'.$env.'.yml generated and prepared for testing on ' . date("D M j G:i:s") . "\n");
          $projectYmlList = array();
          $this->getLogger()->info('Queued Tests for '.$project.' on branch '.$env);
          $output->writeln('Schedule request complete');
          fclose($queue);

          return true;
        }
        return false;

      }

      protected function readConfigFiles($project, $env, InputInterface $input, OutputInterface $output){
        $config =  $this->getYamlParser()->parse(file_get_contents(dirname(__FILE__) . '/../../../settings.yml'));
        try{
          $this->getLogger()->info('Schedule Called');
        } catch (Exception $e){
          $output->writeln('Could not write to /var/log');
        }
        $behatLocation = $config['locations']['behat'] === '/home/sites/.composer/vendor/bin' ? $_SERVER['HOME'].'/.composer/vendor/bin': $config['locations']['behat'];
        if(!file_exists($behatLocation.'/behat')){
          $this->getLogger()->info('Behat not found at '.$behatLocation.'. Please set the absolute path to your behat binary in settings.yml');
          die('Behat not found at '.$behatLocation.'. Please set the absolute path to your behat binary in settings.yml');
        }

        if(!file_exists('/etc/behat-ci')){
          $this->getLogger()->debug('Creating directory etc/behat-ci/');
          $this->getLogger()->debug(shell_exec('mkdir -p /etc/behat-ci/'));
        }
        //Read projects and profiles and parse them as arrays
        try {
          $projectsLocation = $this->getLocation($this->getYamlParser(), 'projects.yml');
          $profilesLocation = $this->getLocation($this->getYamlParser(), 'profiles.yml');
          //get projects.yml as array
          $projects = $this->getYamlParser()->parse(file_get_contents($projectsLocation));
          if(!array_key_exists($project, $projects)){
            $output->writeln('<error>.'.$project.' is not defined in projects.yml!<error>');
            $this->getLogger()->info($project.' is not defined in projects.yml!');
            die();
          }
          if(!array_key_exists($env, $projects[$project]['environments'])){
            $output->writeln('<error>.'.$env.' is not a defined environment for project '.$project.'<error>');
            $this->getLogger()->info($env.' is not a defined environment for project '.$project);
            die();
          }
          //gets profiles.yml as array
          $profiles = $this->getYamlParser()->parse(file_get_contents($profilesLocation));
        } catch (ParseException $e) {
            $this->getLogger()->error("Unable to parse the YAML string: %s");
            printf("Unable to parse the YAML string: %s", $e->getMessage());
        }

          $this->generate($project, $env, $profiles, $projects, $output);
          return true;
      }

      protected function generate($project, $env, $profiles, $projects, OutputInterface $output)
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
