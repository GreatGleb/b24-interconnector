<?php

namespace App\Controllers;

use App\Services\QueueServiceInterface;
use App\Responses\DealResponder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class DealController
{
    public function __construct(
        private QueueServiceInterface $queueService,
        private DealResponder $responder
    ) {}

    public function inboundFromSource(Request $request, Response $response): Response
    {
        $dto = $request->getAttribute('deal_dto');
        $this->queueService->addToQueue($dto);

        return $this->responder->respond($response, $dto);
    }
}