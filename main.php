<?php

require_once 'src/bootstrap.php';

use Showcases\Doom\App\Application;
use Showcases\Doom\App\Config;

$config = new Config();
if ($argc > 1) { $config->wadPath = (string)$argv[1]; }
if ($argc > 2) { $config->startupMap = (string)$argv[2]; $config->startInMenu = false; }
$app = new Application($config);
$app->run();
