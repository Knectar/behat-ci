<?php
namespace AppBundle\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Dumper;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

/**
 * Class Settings.
 * Gets settings.yml file and makes them into universally amiable variables.
 */
class BehatCi extends ContainerAwareCommand
{
    //todo: public $output = new ConsoleOutput();
    var $yml;
    var $config;

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
     *  Returns settings array.
    */
    public function settings()
    {
        // set defaults from settings.yml
        $config = [
            'locations' => [
                'queue' => '/etc/bhqueue',
                'projects.yml' => 'projects.yml',
                'profiles.yml' => 'profiles.yml',
                'behat' => '/home/josh/.composer/vendor/bin',
                'project_base' => '/srv/www/'
            ],
        ];
        $yml = ($yml = null) ? $this->getYamlParser()->parse(file_get_contents(dirname(__FILE__).'/../../../settings.yml')) : $yml;
        /* todo: output errors
        if ($output->isDebug()) {
            $output->writeln("Settings:");
            $output->writeln(var_export($config, true));
        }
         */
        return $config = array_merge($config, (array) $yml);
    }
}
