#!/usr/bin/env php
<?php
// application.php

require __DIR__.'/vendor/autoload.php';

use Acme\Console\Command\GreetCommand;
use Symfony\Component\Console\Application;

$application = new Application();
$application->run();
