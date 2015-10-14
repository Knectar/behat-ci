<?php
/**
 * File gets settings.yml file and makes them into universaly avaible varaibles.
 *
 **/

namespace AppBundle;

use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Dumper;

/**
 * Class Settings.
 * Gets settings.yml file and makes them into universaly avaible varaibles.
 */
class Settings
{
    //todo: public $output = new ConsoleOutput();
    private static $yml = null;
    private static $config = null;


    protected function getYamlParser()
    {
        //Create yml parser
        $yaml = new Parser();
        return $yaml;
    }
    public function __construct($options)
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
        return $config = array_merge($config, $yml);
    }
}

