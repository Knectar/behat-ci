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

class Schedule extends Command {

   //configuration of the command's name, arguments, options, etc
    protected function configure()
    {
      $this->setName('schedule')
           ->setDescription("Writes to bhqueue.txt indicating that tests should be run (also to generate a new configuration file as needed). To be run on beanstalk post-deploy commands with the -e flag specifying environments")
           ->addArgument('repo_name', InputArgument::REQUIRED, "The name of the project repo (%REPO_NAME% in Beanstalk post-deployment)")
           ->addOption('e',
                        null,
                        InputOption::VALUE_OPTIONAL,
                        'Environment. use -e=all for both dev and production',
                        1
                      );
      }

    //executes code when command is called
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $e=$input->getOption('e');
        //Make sure the input is a proper environment
        if($e!='all' && $e!='dev' && $e!='production'){
          $output->writeln('<error>Please enter a valid environment! (dev, production, all)<error>');
          return 1;
        } else {
          //write timestamp, project name, instance to queue.
          $projName = $input->getArgument('repo_name');
          $newQueueTestOnly = fopen("bhqueue.txt", "a") or die("Unable to open file!");
          fwrite($newQueueTestOnly, "Test scheduled on: " . date("D M j G:i:s") . " for project:" . $projName ." on environment:".$e."\n");
          $output->writeln('Schedule request received');
          fclose($newQueueTestOnly);
          return 0;
        }
      }
    }
