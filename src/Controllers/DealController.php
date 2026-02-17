<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\QueueService;

class DealController
{
    public function inboundFromSource(Request $request, Response $response)
    {
        $params = $request->getQueryParams();
        $dealId = $params['id'] ?? $params['data']['FIELDS']['ID'] ?? null;

        if (!$dealId) {
            $response->getBody()->write(json_encode(['error' => 'Deal ID not found']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $queue = new QueueService();
        $queue->addToQueue($dealId, 'source', $params);

        $response->getBody()->write(json_encode([
            'status' => 'accepted',
            'deal_id' => $dealId
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}