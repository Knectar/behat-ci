<?php
namespace AppBundle\Commands;

use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

/**
 * Class Settings.
 * Gets settings.yml file and makes them into universally amiable variables.
 */
class BehatCi extends ContainerAwareCommand
{
    public $yml = null;
    public $config = null;

    /**
     *  Returns settings array.
     *  @return array
     */
    public function settings($key = null)
    {
        // set defaults from settings.yml
        $config = [
            'locations' => [
                'queue' => '/etc/bhqueue',
                'projects.yml' => 'projects.yml',
                'profiles.yml' => 'profiles.yml',
                'behat' => $_SERVER['HOME'].'/.composer/vendor/bin',
                'project_base' => '/srv/www/',
            ],
        ];
        try {
            $yml = (is_null($this->yml)) ? $this->getYamlParser()->parse(file_get_contents(dirname(__FILE__).'/../../../settings.yml')) : $this->yml;
            if (is_null($yml)) {
                throw new ParseException("Can't access Settings file.");
            }
        } catch (ParseException $e) {
                $this->getLogger()->error($e->getMessage());
                exit(1);
        }
        $config = array_merge($config, (array) $yml);

        return $config;
    }

    /**
     * adds debug code
     * @return string
     */
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
}
