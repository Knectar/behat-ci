<?php

namespace AppBundle\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

//Prints help for all commands with bh -h or bh -help,
//Since it does nothing on its own, will print a helpful error message.
class Help extends Command {

   //configuration of the command's name, arguments, options, etc
    protected function configure()
    {
      $this->setName('bh')
           ->setDescription("bh is run with subarguments bh:schedule or bh:trigger. bh:schedule is meant to run on beanstalk post deploy commands. bh:trigger is meant to be called by cron on the server.")
           ->addArgument('bh:schedule', InputArgument::OPTIONAL, "Writes to bhqueue.txt indicating that tests should be run (also to generate a new configuration file as needed). To be run on beanstalk post-deploy with --e flag indicating environment")
           ->addArgument('bh:trigger', InputArgument::OPTIONAL, "Reads from bhqueue.txt/environments.yml to create new behat configuration file as needed and runs tests");
      }

    //executes code when command is called
    protected function execute(InputInterface $input, OutputInterface $output)
    {
      //Formatting terminal output
      $header_style = new OutputFormatterStyle('white', 'green', array('bold'));
      $error_style = new OutputFormatterStyle('white', 'red', array('bold'));
      $output->getFormatter()->setStyle('header', $header_style);
      $output->getFormatter()->setStyle('err', $error_style);
      $output->writeln('<err>Bh must be run with a subcommand (e.g., "bh:schedule"). Run bh --help for more details.</err>');
      }
    }
