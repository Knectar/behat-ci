<?php

namespace AppBundle\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Dumper;

class Trigger extends Command {

   //configuration of the command's name, arguments, options, etc
    protected function configure()
    {
      $this->setName('trigger')
           ->setDescription("Reads from bhqueue.txt/profiles.yml/projects.yml to create new behat configuration file as needed and runs tests");
    }

    //executes code when command is called
    protected function execute(InputInterface $input, OutputInterface $output)
    {
      //Formatting terminal output
      $header_style = new OutputFormatterStyle('white', 'green', array('bold'));
      $error_style = new OutputFormatterStyle('white', 'red', array('bold'));
      $output->getFormatter()->setStyle('header', $header_style);
      $output->getFormatter()->setStyle('err', $error_style);
      $output->writeln('Trigger request received');

      //bhqueue file name
      $bhQ = 'bhqueue.txt';
      //Check if there are tests scheduled, i.e., queue file is not empty
      if (file_get_contents($bhQ) != ''){
        $projectList = $this->readQueue($bhQ);
        foreach($projectList as $p => $e){
          if($e == 'all'){
              //generates/runs tests for both dev and prod
              $this->bhGen($p, 'dev', $output);
              $this->bhGen($p, 'production', $output);
          }else{
              $this->bhGen($p, $e, $output);
          }
        }
        //Write the scheduled tests to the log, remove from queue
        file_put_contents("bhqueuelog.txt", file_get_contents($bhQ), FILE_APPEND);
        file_put_contents($bhQ, "");
        //shell_exec($commandtoruntests);
      }

    }

    //Forms a map array of projects => environments from the queue
    protected function readQueue($queue){
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
    protected function bhGen($project, $env, OutputInterface $output){
      //Create yml parser
      $yaml = new Parser();
      //Create the yml dumper to convert the array to string
      $dumper = new Dumper();
      //Read in profiles.yml and projects.yml as arrays
      try {
        //TODO: Update file path as needed.
          $profiles = $yaml->parse(file_get_contents('profiles.yml'));
          $projects = $yaml->parse(file_get_contents('projects.yml'));
      } catch (ParseException $e) {
          printf("Unable to parse the YAML string: %s", $e->getMessage());
      }

      //Key-value matching variables in project to profile and then to the output yml
        $behatYaml = array();
        //Fill in the baseurl
        $profiles['default']['extensions']['Behat\MinkExtension\Extension']['base_url'] = $projects[$project]['environments'][$env]['base_url'];
        //Fill in path to the features directory of the project
        $profiles['default']['paths']['features'] = '/www/srv/'.$project.'/'.$env.'/.behat';
        //Add the default profile to the generated yaml
        $behatYaml['default'] = $profiles['default'];
        //Get the list of tests to be run and add each of their profiles to the generated yaml
        $profileList = $projects[$project]['profiles'];
        foreach($profileList as $t){
          $behatYaml[$t] = $profiles[$t];
        }

        //Dump into yaml string
        $behatYamlString = $dumper->dump($behatYaml, 7);

        //create the yml file in /tmp
        file_put_contents('/tmp/'.$project.'_'.$env.'.yml', $behatYamlString);
        $output->writeln('<header>Generated config file for '.$project.' for env '.$env.' in /tmp</header>');

        //Run the behat testing command.
        shell_exec('behat -c /tmp/'.$project.'_'.$env.'.yml');

        //Remove the file after tests have been run
        //shell_exec('rm /tmp/'.$project.'_'.$env.'.yml');

    }
  }
