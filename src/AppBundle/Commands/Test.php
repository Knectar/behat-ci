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

class Test extends Trigger {
   //configuration of the command's name, arguments, options, etc
    protected function configure()
    {
      $this->setName('tests')
           ->setDescription("Used to run tests manually. Instead of scheduling and triggering tests with beanstalk post-deploy commands and cron, 'bh test' can be run to generate a .yml config and run tests for the project and environments given.")
           ->addArgument('project_name', InputArgument::REQUIRED, "The name of the project repo (%REPO_NAME% in Beanstalk post-deployment)")
           ->addOption('branch',
                        'b',
                        InputOption::VALUE_OPTIONAL,
                        'Environment. use --e=all for both dev and production',
                        1
                      )
           ->addOption('profile',
                       'p',
                       InputOption::VALUE_OPTIONAL,
                       'Environment. use --e=all for both dev and production',
                       1
                     );
      }

    //executes code when command is called
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->formatOutput($output);
        $p=$input->getArgument('project_name');
        $b=$input->getOption('branch');
        $profile=$input->getOption('profile');
        //Make sure the input is a proper environment
        if($b!='all' && $b!='dev' && $b!='production'){
          $output->writeln('<error>Please enter a valid environment! (dev, production, all)<error>');
          $output->writeln($profile);
          if(!$profile){
            $this->bhTrigger($p, $e, $profile, $output);
          } else {
              if($e == 'all'){
                  //generates/runs tests for both dev and prod
                  $this->bhTrigger($p, 'dev', NULL, $output);
                  $this->bhTrigger($p, 'production', NULL, $output);
              }else{
                  $this->bhTrigger($p, $e, NULL, $output);
              }
          }
          return 1;
        }
      }
    }
