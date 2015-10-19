<?php
namespace AppBundle\Commands;

use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

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
    public function settings()
    {
        // set defaults from settings.yml
        $config = [
            'locations' => [
                'queue' => '/etc/bhqueue',
                'projects.yml' => '/etc/behat-ci/projects.yml',
                'profiles.yml' => '/etc/behat-ci/profiles.yml',
                'behat' => $_SERVER['HOME'].'/.composer/vendor/bin',
                'project_base' => '/home/jacob/Knectar/',
                'saucelabs' => false,
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
        $this->config = array_merge($config, (array) $yml);
        return $this->config;
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
