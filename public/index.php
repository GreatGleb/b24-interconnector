<?php
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

$basePath = str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']);
$app->setBasePath($basePath);

// Подключаем роуты
$routes = require __DIR__ . '/../routes/web.php';
$routes($app);

$app->run();