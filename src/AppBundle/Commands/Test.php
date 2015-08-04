<?php

namespace AppBundle\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class Test extends Command {

   //configuration of the command's name, arguments, options, etc
    protected function configure()
    {
      $this->setName('bh:test')
           ->setDescription("Runs the behat tests as specified in the behat.yml configuration file");
      }

    //executes code when command is called
    protected function execute(InputInterface $input, OutputInterface $output)
    {
      //Formatting terminal output
      $header_style = new OutputFormatterStyle('white', 'green', array('bold'));
      $error_style = new OutputFormatterStyle('white', 'red', array('bold'));
      $output->getFormatter()->setStyle('header', $header_style);
      $output->getFormatter()->setStyle('err', $error_style);
      //TODO: FIND IF THIS CLASS IS REALLY NEEDED
      $output->writeln('Test request received');
      }
    }
