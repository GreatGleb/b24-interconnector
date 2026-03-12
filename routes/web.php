<?php

use App\Controllers\DealController;
use App\Middleware\ReceiverValidationMiddleware;
use App\Middleware\SourceValidationMiddleware;
use App\Middleware\TokenAuthMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    // Тестовый корневой роут
    $app->get('/', function (Request $request, Response $response) {
        $response->getBody()->write(json_encode([
            'status' => 'online',
            'message' => 'B24 Interconnector API is running',
            'timestamp' => date('Y-m-d H:i:s')
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->group('/webhooks', function (RouteCollectorProxy $group) {
        // Группа для Источника (Винилам -> Винипол)
        $group->map(['GET', 'POST'], '/source/onDealCreate/{token}', [DealController::class, 'handleWebhook'])
            ->add(SourceValidationMiddleware::class);

        // Группа для Приемника (Винипол -> Винилам)
        $group->map(['GET', 'POST'], '/receiver/onDealUpdate/{token}', [DealController::class, 'handleWebhook'])
            ->add(ReceiverValidationMiddleware::class);
    })->add(TokenAuthMiddleware::class);
};