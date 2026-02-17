<?php
use Slim\App;
use App\Controllers\DealController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

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

    // Рабочие эндпоинты
    $app->get('/inbound/from-source', [DealController::class, 'inboundFromSource']);
};