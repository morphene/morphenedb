<?php

use Phalcon\Loader;

$loader = new Loader();

$loader->registerNamespaces([
    'MorpheneDB\Models'      => $config->application->modelsDir,
    'MorpheneDB\Controllers' => $config->application->controllersDir,
    'MorpheneDB\Helpers'     => $config->application->helpersDir,
    'MorpheneDB'             => $config->application->libraryDir
]);

$loader->registerDirs(array(
    '../app/helpers'
));

$loader->register();

// Use composer autoloader to load vendor classes
require_once BASE_PATH . '/vendor/autoload.php';
