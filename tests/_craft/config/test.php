<?php

use craft\helpers\ArrayHelper;

$_SERVER['REMOTE_ADDR'] = '1.1.1.1';
$_SERVER['REMOTE_PORT'] = 654321;

$basePath = dirname(dirname(dirname(__DIR__)));

$srcPath = $basePath.'/src';
$vendorPath = $basePath.'/vendor';

// Load the config
$config = ArrayHelper::merge(
    require $srcPath.'/config/main.php',
    require $srcPath.'/config/common.php',
    require $srcPath.'/config/web.php'
);

$config['vendorPath'] = $vendorPath;

$config = ArrayHelper::merge($config, [
    'components' => [
        'sites' => [
            'currentSite' => 'default'
        ]
    ],
]);

return ArrayHelper::merge($config, [
    'class' => craft\web\Application::class,
    'id'=>'craft-test',
    'basePath' => $srcPath
]);
