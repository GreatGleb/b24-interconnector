<?php
use Slim\Factory\AppFactory;
use DI\ContainerBuilder;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Psr\Log\LoggerInterface;

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$containerBuilder = new ContainerBuilder();

$containerBuilder->addDefinitions([
    Capsule::class => function () {
        $capsule = new Capsule;
        $capsule->addConnection([
            'driver'    => 'mysql',
            'host'      => $_ENV['DB_HOST'] ?? '127.0.0.1',
            'database'  => $_ENV['DB_NAME'] ?? '',
            'username'  => $_ENV['DB_USER'] ?? '',
            'password'  => $_ENV['DB_PASS'] ?? '',
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
        ]);

        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        return $capsule;
    },
]);

$containerBuilder->addDefinitions([
    LoggerInterface::class => function () {
        $logger = new Logger('b24_app');

        // Указываем путь к файлу логов.
        // __DIR__ . '/../logs/app.log' создаст файл в корне проекта в папке logs
        $handler = new StreamHandler(__DIR__ . '/../logs/app.log', Logger::DEBUG);

        $logger->pushHandler($handler);
        return $logger;
    },
    \App\Services\QueueServiceInterface::class => \DI\autowire(\App\Services\QueueService::class),
    \App\Services\BitrixRequestExtractor::class => \DI\autowire(\App\Services\BitrixRequestExtractor::class),
]);

$container = $containerBuilder->build();
$container->get(Capsule::class);

$app = AppFactory::createFromContainer($container);

$app->add(App\Middleware\ErrorHandlingMiddleware::class);

$app->addRoutingMiddleware();
$app->addErrorMiddleware(false, false, false);

$app->setBasePath(str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']));

$routes = require __DIR__ . '/../routes/web.php';
$routes($app);

$app->run();