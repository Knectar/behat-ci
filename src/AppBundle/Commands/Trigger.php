<?php

namespace AppBundle\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Triggers tests that have been scheduled to run.
 */
class Trigger extends Schedule
{

    //configuration of the command's name, arguments, options, etc
    protected function configure()
    {
        $this->setName('trigger')
            ->setDescription("Reads from bhqueue.txt and runs behat tests and specified by the .yml config file generated by the schedule command");
    }

    //executes code when command is called
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->formatOutput($output);
        $config = $this->settings();
        $bhQ = $config['locations']['queue'];

        //Check if there are tests scheduled, i.e., queue file is not empty
        if (file_get_contents($bhQ.'.txt') != '') {
            //Array of projects=>environments from the queue
            $projectList = $this->readQueue($bhQ.'.txt');
            //Removed scheduled tests from queue
            file_put_contents($bhQ.'.txt', "");
            $projectsLocation = $this->getLocation($this->getYamlParser(), 'projects.yml');
            //Read the projects.yml file
            $projects = $this->getYamlParser()->parse(file_get_contents($projectsLocation));
            //Go match the project specified in the command to the settings in projects.yml
            foreach ($projectList as $p => $e) {
                foreach ($e as $env => $rid) {
                    //Check if there are behat-params for the specified project
                    $behatFlags = array_key_exists('behat-params', $projects[$p]) ? $projects[$p]['behat-params'] : null;
                    if ($e == 'all') {
                        //Get all the environments for the project from projects.yml
                        foreach ($projects[$p]['environments'] as $environment) {
                            $this->test($p, $projects, $environment, $this->additionalParamsStringBuilder($behatFlags, $rid, $environment), $rid);
                        }
                    } else {
                        $this->test($p, $projects, $env, $this->additionalParamsStringBuilder($behatFlags, $rid, $env));
                    }
                }
            }

            return true;
        }
    }

    //Forms a map array of projects => environments and revision ids from the queue by parsing each line of the queue string

    /**
     * @param string $queue
     */
    protected function readQueue($queue)
    {
        $projectYmlList = array();
        $file = fopen($queue, "r") or exit("Unable to open file!");
        while (!feof($file)) {
            $lineinQueue = fgets($file);
            //Grab the project .yml file name in isolation from bhqueue and its associated environments
            $pStringOffsetEnd = strrpos($lineinQueue, "_");
            $projectName = substr($lineinQueue, 5, $pStringOffsetEnd - strlen($lineinQueue));
            $environmentName = substr($lineinQueue, $pStringOffsetEnd + 1, strrpos($lineinQueue, ".yml") - $pStringOffsetEnd - 1);
            $revisionId = substr($lineinQueue, strrpos($lineinQueue, "ID") + 3, strlen($lineinQueue));
            //add the project name to the array (if we haven't already,there could be multiple pushes per minute)
            if (!in_array($projectName, $projectYmlList) && strlen($projectName) > 0) {
                $projectYmlList[$projectName][$environmentName] = $revisionId;
                // var_dump($projectYmlList);
            }
        }
        fclose($file);

        return $projectYmlList;
    }

    /**
     * Adds the revision id to the output filename if output formatting is specified
     **/
    protected function additionalParamsStringBuilder($additionalBehatParameters, $revisionId, $environment)
    {
        if ($additionalBehatParameters == null) {
            return null;
        }
        $addFlagString = ' ';
        foreach ($additionalBehatParameters as $flag => $param) {
            if ($flag == 'out') {
                $time = date('YmdHis');
                $pathToOutput = substr($param, 0, strrpos($param, "."));
                $fileExtension = substr($param, strrpos($param, "."), strlen($param));
                $revisionId = substr(preg_replace('~[\r\n]+~', '', $revisionId), 0, 6);
                $addFlagString = $addFlagString.' --'.$flag.' '.$pathToOutput.'-'.$environment.'-'.$time.'-'.$revisionId;
            } else {
                $addFlagString = $addFlagString.'--'.$flag.' '.$param;
            }
        }

        return $addFlagString;
    }

    /**
     * @param null|string $additionalParams
     */
    protected function test($project, $projects, $env, $additionalParams = null)
    {
        $projectsLocation = $this->getLocation($this->getYamlParser(), 'projects.yml');
        $projects = $this->getYamlParser()->parse(file_get_contents($projectsLocation));
        $notifications = array_key_exists('notify', $projects[$project]) ? true : false;
        $behatLocation = $this->getLocation($this->getYamlParser(), 'behat');
        //Run the behat testing command.
        try {
            if (!is_null($additionalParams)) {
                echo $behatLocation.'/behat -c /tmp/'.$project.'_'.$env.'.yml'.$additionalParams;
                $test = shell_exec($behatLocation.'/behat -c /tmp/'.$project.'_'.$env.'.yml'.$additionalParams);
                $this->getLogger()->info(shell_exec($behatLocation.'/behat -c /tmp/'.$project.'_'.$env.'.yml'.$additionalParams));
                foreach ($projects[$project]['profiles'] as $r) {
                    $this->getLogger()->info('Running tests on '.$r.' for '.$project.'...');
                    $this->getLogger()->info(shell_exec($behatLocation.'/behat -c /tmp/'.$project.'_'.$env.'.yml -p '.$r.' '.$additionalParams));
                    if ($notifications) {
                        // todo: Email($project, $projects, 'Testing of '.$project.' running on '.$r.' complete');
                        $this->slack('Testing of '.$project.' running on '.$r.' complete', $projects[$project]['notify']['slack']['user'], $projects[$project]['notify']['slack']['endpoint'], $projects[$project]['notify']['slack']['target']);
                    }
                }
            } else {
                $test = shell_exec($behatLocation.'/behat -c /tmp/'.$project.'_'.$env.'.yml');
                $this->getLogger()->info(shell_exec($behatLocation.'/behat -c /tmp/'.$project.'_'.$env.'.yml'));
                foreach ($projects[$project]['profiles'] as $r) {
                    $this->getLogger()->info('Running tests on '.$r.' for '.$project.'...');
                    $this->getLogger()->info(shell_exec($behatLocation.'/behat -c /tmp/'.$project.'_'.$env.'.yml -p '.$r));
                    if ($notifications) {
                        // todo: notifyEmail($project, $projects, 'Testing of '.$project.' running on '.$r.' complete');
                        $this->slack('Testing of '.$project.' running on '.$r.' complete', $projects[$project]['notify']['slack']['user'], $projects[$project]['notify']['slack']['endpoint'], $projects[$project]['notify']['slack']['target']);
                    }
                }
            }
        } catch(ParseException $e) {
            $this->getLogger()->error("Test Failed: ".$e->message());
        }
        //Remove the file after tests have been run
        shell_exec('rm /tmp/'.$project.'_'.$env.'.yml');
    }
/**
    protected function notifyEmail($project, $projects, $subject)
    {
        $emails = $projects[$project]['notify']['email'];
        foreach ($emails as $e) {
            Notify\Email::send($e, $subject, 'Tests have been run');
        }

    }
*/
    /**
     * Send Slack notification.
     * */
    protected function slack($message, $username, $endpoint, $target = null)
    {
            $payload = [];
        if ($target !== null) {
            $payload['channel'] = $target;
        }
            $payload['text'] = $message;
            $payload['username'] = $username;
            // You can get your webhook endpoint from your Slack settings
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, 'payload='.json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            curl_close($ch);
        if ($result === 'ok') {
            return true;
        }

            return false;
    }
}
