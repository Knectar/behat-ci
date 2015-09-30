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

class Trigger extends Schedule {

    //configuration of the command's name, arguments, options, etc
    protected function configure()
    {
      $this->setName('trigger')
           ->setDescription("Reads from bhqueue.txt and runs behat tests and specified by the .yml config file generated by the schedule command");
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
        //Array of projects=>environments from the queue
        $projectList = $this->readQueue($bhQ.'.txt');
        //Removed scheduled tests from queue
        file_put_contents($bhQ.'.txt', "");
        $projectsLocation = $this->getLocation($this->getYamlParser(), 'projects.yml');
        //Read the projects.yml file
        $projects = $this->getYamlParser()->parse(file_get_contents($projectsLocation));
        //Go match the project specified in the command to the settings in projects.yml
        foreach($projectList as $p => $e){
          foreach($e as $env => $rid){
            //Check if there are behat-params for the specified project
            $behatFlags = array_key_exists('behat-params', $projects[$p]) ? $projects[$p]['behat-params'] : null;
            if($e == 'all'){
               //Get all the environments for the project from projects.yml
               foreach($projects[$p]['environments'] as $environment){
                  //  $this->test($p, $projects, $environment, $this->additionalParamsStringBuilder($behatFlags, $rid), $rid, $output);
               }
            } else {
                $this->test($p, $projects, $env, $this->additionalParamsStringBuilder($behatFlags, $rid), $output);
            }
          }

        }
        return true;
      }

    }

    //Forms a map array of projects => environments and revision ids from the queue by parsing each line of the queue string
    protected function readQueue($queue)
    {
      $projectYmlList = array();
      $file = fopen($queue, "r") or exit("Unable to open file!");
      while(!feof($file)){
        $lineinQueue = fgets($file);
        //Grab the project .yml file name in isolation from bhqueue and its associated environments
        $pStringOffsetEnd = strrpos($lineinQueue, "_");
        $projectName = substr($lineinQueue,  5, $pStringOffsetEnd-strlen($lineinQueue));
        $environmentName = substr($lineinQueue, $pStringOffsetEnd + 1, strrpos($lineinQueue, ".yml") - $pStringOffsetEnd -1);
        $revisionId = substr($lineinQueue, strrpos($lineinQueue, "ID") + 3, strlen($lineinQueue));
        //add the project name to the array (if we haven't already,there could be multiple pushes per minute)
        if(!in_array($projectName, $projectYmlList) && strlen($projectName)>0){
          $projectYmlList[$projectName][$environmentName] = $revisionId;
          // var_dump($projectYmlList);
        }
      }
      fclose($file);
      return $projectYmlList;

    }

    //Adds the revision id to the output filename if output formatting is specified
    protected function additionalParamsStringBuilder($additionalBehatParameters, $revisionId){
      if($additionalBehatParameters==null){
        return null;
      }
      $addFlagString = ' ';
      foreach($additionalBehatParameters as $flag => $param){
        if($flag == 'out'){
          $time = date('d-M-His') . "-";
          $pathToOutput = substr($param, 0, strrpos($param, "."));
          $fileExtension = substr($param, strrpos($param, "."), strlen($param));
          $addFlagString = $addFlagString . ' --' . $flag. ' ' . $pathToOutput.$time.$revisionId.$fileExtension;
        } else {
          $addFlagString = $addFlagString . '--' .$flag. ' '.$param;
        }
    }
    return $addFlagString;
  }

    protected function test($project, $projects, $env, $additionalParams, $output)
    {
      $behatLocation = $this->getLocation($this->getYamlParser(), 'behat');
      //Run the behat testing command.
      if($additionalParams){
        echo shell_exec($behatLocation.'/behat -c /tmp/'.$project.'_'.$env.'.yml'.$additionalParams);
        $this->getLogger()->info(shell_exec($behatLocation.'/behat -c /tmp/'.$project.'_'.$env.'.yml'.$additionalParams));
        foreach($projects[$project]['profiles'] as $r){
          $this->getLogger()->info('Running tests on '.$r.' for '.$project.'...');
          $this->getLogger()->info(shell_exec($behatLocation.'/behat -c /tmp/'.$project.'_'.$env.'.yml -p '.$r. ' '.$additionalParams));
        }
      } else {
        echo shell_exec($behatLocation.'/behat -c /tmp/'.$project.'_'.$env.'.yml');
        $this->getLogger()->info(shell_exec($behatLocation.'/behat -c /tmp/'.$project.'_'.$env.'.yml'));
        foreach($projects[$project]['profiles'] as $r){
          $this->getLogger()->info('Running tests on '.$r.' for '.$project.'...');
          $this->getLogger()->info(shell_exec($behatLocation.'/behat -c /tmp/'.$project.'_'.$env.'.yml -p '.$r));
        }
      }

      //Remove the file after tests have been run
      shell_exec('rm /tmp/'.$project.'_'.$env.'.yml');
    }


  }
