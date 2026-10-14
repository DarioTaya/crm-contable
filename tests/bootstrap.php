<?php

$basePath = realpath(__DIR__.'/..');

$_ENV['APP_BASE_PATH'] = $basePath;
$_SERVER['APP_BASE_PATH'] = $basePath;

$loader = require __DIR__.'/../vendor/autoload.php';

$loader->setPsr4('App\\', [$basePath.'/app']);
$loader->setPsr4('Tests\\', [$basePath.'/tests']);
$loader->setPsr4('Database\\Factories\\', [$basePath.'/database/factories']);
$loader->setPsr4('Database\\Seeders\\', [$basePath.'/database/seeders']);

return $loader;
