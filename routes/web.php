<?php

use App\Controllers\DealController;
use App\Middleware\ReceiverValidationMiddleware;
use App\Middleware\SourceValidationMiddleware;
use App\Middleware\TokenAuthMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

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

    // Входящие из Источника (Винилам -> Винипол)
    $app->get('/webhooks/source/onDealCreate/{token}', [DealController::class, 'handleWebhook'])
        ->add(SourceValidationMiddleware::class)
        ->add(TokenAuthMiddleware::class);

    // Обратка из Приемника (Винипол -> Винилам)
    $app->get('/webhooks/receiver/onDealUpdate/{token}', [DealController::class, 'handleWebhook'])
        ->add(ReceiverValidationMiddleware::class)
        ->add(TokenAuthMiddleware::class);
};