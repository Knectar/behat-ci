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
        $logger = $this->getContainer()->get('logger');

        $e=$input->getOption('branch');
        //Make sure the input is a proper environment
        if($e!='all' && $e!='dev' && $e!='production'){
          $output->writeln('<error>Please enter a valid environment! (dev, production, all)<error>');
          return false;
        } else {
          try{
            //Create the yaml parser
            $yaml = new Parser();
            //read queue location from config.yml
            $config = $yaml->parse(file_get_contents(dirname(__FILE__) . '/../../../config.yml'));
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
      
    }
