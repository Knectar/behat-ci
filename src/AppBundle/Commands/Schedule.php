<?php

namespace AppBundle\Commands;

use AppBundle\Commands\BehatCi;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

/**
 * Generates behat config file and schedules test in queue.
 */
class Schedule extends BehatCi
{

    protected function getLogger()
    {
        //create logger
        $logger = $this->getContainer()->get('logger');

        return $logger;
    }

    protected function getYamlParser()
    {
        //Create yml parser
        $yaml = new Parser();

        return $yaml;
    }

    /**
     * Grabs locations from settings.yml and confirms existance of files at their specified paths.
     * @param Parser $yamlParser
     * @param string $file
     * @return string
     */
    protected function getLocation($yamlParser, $file)
    {
        $config = $this->settings();
        try {
            switch ($file) {
                case 'behat':
                    $location = (isset($config['locations']['behat']) ? $config['locations']['behat'] : $_SERVER['HOME'].'/.composer/vendor/bin/behat');
                    if (!file_exists($location)) {
                        throw new ParseException($file.' not found at '.$location.'. Please set the absolute path to your behat binary in settings.yml');
                    } else {
                        $this->getLogger()->debug($file.' found in '.$location.' per settings.yml');
                    }
                    break;
                case 'profiles.yml':
                case 'projects.yml':
                    if (file_exists($_SERVER['HOME'].'/'.$file)) {
                        $this->getLogger()->debug('Found '.$file.' in '.$_SERVER['HOME']);
                        $location = $_SERVER['HOME'].'/'.$file;
                    } elseif (file_exists('/etc/behat-ci/'.$file)) {
                        $this->getLogger()->debug('Found '.$file.' in /etc/behat-ci/');
                        $location = '/etc/behat-ci/'.$file;
                    } else {
                        $location = ($config['locations'][$file] === $file) ?
                          dirname(__FILE__).'/../../../'.$file : $config['locations'][$file];
                            $this->getLogger()->debug($file.' found in '.$location.' per settings.yml');
                    }
                    if (!isset($location)) {
                        throw new ParseException("File: ".$file." not found. \n\nPlease create it in /etc/behat-ci/ ".$file.", or ".$_SERVER['HOME']." . /.behat_ci/".$file);
                    }
                    break;
                default:
                    $location = null;
            }
        } catch (ParseException $e) {
            $this->getLogger()->error(sprintf("Error: Parsing Files \n %s", $e->getMessage()));
            exit(1);
        }

        return $location;
    }

    /**
     * Configuration of the command's name, arguments, options, etc
     **/
    protected function configure()
    {
        $this->setName('schedule')
            ->setDescription("Writes to bhqueue.txt indicating that tests should be run (also to generate a new configuration file as needed). To be run on beanstalk post-deploy commands with the -e flag specifying environments")
            ->addArgument('repo_name', InputArgument::REQUIRED, "The name of the project repo (%REPO_NAME% in Beanstalk post-deployment)")
            ->addOption(
                'branch',
                'b',
                InputOption::VALUE_OPTIONAL,
                'The environment/branch. use --branch=all for all instances/environments',
                1
            )
            ->addOption(
                'revision',
                'r',
                InputOption::VALUE_OPTIONAL,
                'Revision id from beanstalk/any other source. Defaults to 0',
                1
            );
    }

    //executes code when command is called. writes to the queue and generates config file
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getLogger()->debug('Schedule called');
        $this->formatOutput($output);

        $project = $input->getArgument('repo_name');
        $env = $input->getOption('branch');
        $revision = $input->getOption('revision');

        if ($this->readConfigFiles($project, $env, $input, $output)) {
                $config = $this->settings();
                $bhQ = $config['locations']['queue'];
            //write timestamp, project name, instance to queue.
            try {
                $queue = fopen($bhQ.'.txt', "a");
            } catch (Exception $e) {
                    $this->getLogger()->error(sprintf("Can not find the queue file:\n %s", $e->getMessage));
                    exit(1);
            }
            if ($revision) {
                fwrite($queue, '/tmp/'.$project.'_'.$env.'.yml generated and prepared for testing on '.date("D M j G:i:s")." with revision ID ".$revision."\n");
            } else {
                fwrite($queue, '/tmp/'.$project.'_'.$env.'.yml generated and prepared for testing on '.date("D M j G:i:s")." with revision ID 0 \n");
            }
            $projectYmlList = array();
            $this->getLogger()->info('Queued Tests for '.$project.' on branch '.$env);
            $output->writeln('Schedule request complete');
            fclose($queue);

            return true;
        }

        return false;
    }

    protected function readConfigFiles($project, $env, InputInterface $input, OutputInterface $output)
    {
        try {
            $this->getLogger()->info('Schedule Called');
        } catch (Exception $e) {
            $output->writeln('Could not write to /var/log');
        }
        if (!file_exists('/etc/behat-ci')) {
            $this->getLogger()->debug('Creating directory etc/behat-ci/');
            $this->getLogger()->debug(shell_exec('mkdir -p /etc/behat-ci/'));
        }
        //Read projects and profiles and parse them as arrays
        try {
            $projectsLocation = $this->getLocation($this->getYamlParser(), 'projects.yml');
            $profilesLocation = $this->getLocation($this->getYamlParser(), 'profiles.yml');
            //get projects.yml as array
            $projects = $this->getYamlParser()->parse(file_get_contents($projectsLocation));
            if (!array_key_exists($project, $projects)) {
                $output->writeln('<error>.'.$project.' is not defined in projects.yml!<error>');
                $this->getLogger()->info($project.' is not defined in projects.yml!');
                die();
            }
            if (!array_key_exists($env, $projects[$project]['environments']) && $env != 'all') {
                throw new ParseException($env.' is not a defined environment for project '.$project.'<error>');
            }
            //gets profiles.yml as array
            $profiles = $this->getYamlParser()->parse(file_get_contents($profilesLocation));
        } catch (ParseException $e) {
            $error = sprintf("Unable to parse the YAML string: %s", $e->getMessage());
            $output->writeln('<error>'.$error.'<error>');
            $this->getLogger()->error($error);
            exit(1);
        }

        $this->generate($project, $env, $profiles, $projects, $output);

        return true;
    }
    /**
     * Generates behat config file based on projects and profiles.
     */
    protected function generate($project, $env, $profiles, $projects, OutputInterface $output)
    {
        //Key-value matching variables in project to profile and then to the output yml
        $behatYaml = array();

        if (array_key_exists('suites', $profiles['default'])) {
            if(!$this->config['saucelabs']){
                foreach($profiles as $p => $i){
                  if(array_key_exists('wd_host', $profiles[$p]['extensions']['Behat\MinkExtension']['selenium2'])){
                      $profiles[$p]['extensions']['Behat\MinkExtension']['selenium2']['wd_host'] =  'http://localhost:4444/wd/';
                  }
                }
            }
            //Fill in the baseurl (Behat 3)
            $profiles['default']['extensions']['Behat\MinkExtension']['base_url'] = $projects[$project]['environments'][$env]['base_url'];
            //Fill in path to the features directory of the project in default suite
            if (array_key_exists('features', $projects[$project]['environments'][$env])) {
                array_push($profiles['default']['suites']['default']['paths'], $projects[$project]['environments'][$env]['features']);
            } else {
                array_push($profiles['default']['suites']['default']['paths'], $this->config['locations']['project_base'].$project.'/'.$env.'/.behat');
            }
            //Checks if drupal root specified (Behat 3)
            if (array_key_exists('Drupal\DrupalExtension', $profiles['default'])) {
                $profiles['default']['extensions']['Drupal\DrupalExtension']['drupal']['drupal_root'] = $projects[$project]['environments'][$env]['drupal_root'];
            }
            //Check for Twig output/emuse BehatHTMLFormatter
            if (array_key_exists('formatters', $profiles['default']) && array_key_exists('twigOutputPath', $projects[$project])) {
                $profiles['default']['formatters']['html']['output_path'] = $projects[$project]['twigOutputPath'].'/'.$project;
                if (array_key_exists('emuse\BehatHTMLFormatter\BehatHTMLFormatterExtension', $profiles['default']['extensions'])) {
                    $profiles['default']['extensions']['emuse\BehatHTMLFormatter\BehatHTMLFormatterExtension']['file_name'] = 'index';
                }
                foreach ($projects[$project]['profiles'] as $p){
                  if(array_key_exists('emuse\BehatHTMLFormatter\BehatHTMLFormatterExtension', $profiles[$p]['extensions'])) {
                    $time = date('Y-m-d-His');
                    $profiles[$p]['extensions']['emuse\BehatHTMLFormatter\BehatHTMLFormatterExtension']['file_name'] = $project.'-'.$env.'-'.$p.'-'.$time;
                    $profiles[$p]['formatters']['html']['output_path'] = $projects[$project]['twigOutputPath'];
                  }
                }
            } else {
                // Fill in the baseurl (Behat 2)
                $profiles['default']['extensions']['Behat\MinkExtension\Extension']['base_url'] = $projects[$project]['environments'][$env]['base_url'];
                if (array_key_exists('features', $projects[$project]['environments'][$env])) {
                    $profiles['default']['suites']['default']['paths'] = $projects[$project]['environments'][$env]['features'];
                } elseif (array_key_exists('alias', $projects[$project][$env])) {
                    // if there is an alias load the alais's files not the enviroments alais
                    $profiles['default']['paths']['features'] = $this->config['locations']['project_base'].$project.'/'.$projects[$project]['environments'][$env]['alias'].'/.behat';
                } else {
                    // Fill in path to the features directory of the project
                    $profiles['default']['paths']['features'] = $this->config['locations']['project_base'].$project.'/'.$env.'/.behat';
                }
            }
            //Add the default profile to the generated yaml
            $behatYaml['default'] = $profiles['default'];
            //Get the list of tests to be run and add each of their profiles to the generated yaml
            $profileList = $projects[$project]['profiles'];
            foreach ($profileList as $t) {
                $profiles[$t]['extensions']['Behat\MinkExtension']['selenium2']['capabilities']['name'] = $project.' '.$env.' on '.$t;
                $behatYaml[$t] = $profiles[$t];
            }
            //Create the yml dumper to convert the array to string
            $dumper = new Dumper();
            //Dump into yaml string
            $behatYamlString = $dumper->dump($behatYaml, 7);

            //create the yml file in /tmp
            file_put_contents('/tmp/'.$project.'_'.$env.'.yml', $behatYamlString);
            if (file_exists('/tmp/'.$project.'_'.$env.'.yml')) {
                $this->getLogger()->info('Generated the file /tmp/'.$project.'_'.$env.'.yml');
            } else {
                $this->getLogger()->info('FAILED the file /tmp/'.$project.'_'.$env.'.yml');
            }
            $output->writeln('<header>Generated config file for '.$project.' for env '.$env.' in /tmp</header>');
        }
    }

    /**
     * Formatting terminal output.
     */
    protected function formatOutput(OutputInterface $output)
    {
        $headerStyle = new OutputFormatterStyle('white', 'green', array('bold'));
        $errorStyle = new OutputFormatterStyle('white', 'red', array('bold'));
        $output->getFormatter()->setStyle('header', $headerStyle);
        $output->getFormatter()->setStyle('err', $errorStyle);
    }
}
