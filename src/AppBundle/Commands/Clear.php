<?php

namespace AppBundle\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class Clear extends Command {

   //configuration of the command's name, arguments, options, etc
    protected function configure()
    {
      $this->setName('clear')
           ->setDescription("Clears the queue. Use flag --log to clear the log as well")
           ->addOption(
              'log',
              null,
              InputOption::VALUE_NONE,
              'If set, clears the log as well../'
           );
      }

    //executes code when command is called
    protected function execute(InputInterface $input, OutputInterface $output)
    {
      //Formatting terminal output
      file_put_contents('/etc/bhqueue.txt', "");
      $output->writeln('Queue Cleared');
      if ($input->getOption('log')) {
        file_put_contents('/var/log/behat-ci.log', "");
        $output->writeln('Queue Log Cleared');
       }
      return 0;
      }
    }
