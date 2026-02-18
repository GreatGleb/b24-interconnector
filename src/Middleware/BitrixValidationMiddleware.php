<?php

namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Response as SlimResponse;
use App\Responses\ApiResponse;
use App\Services\BitrixRequestExtractor;

class BitrixValidationMiddleware
{
    public function __construct(
        private BitrixRequestExtractor $extractor
    ) {}

    public function __invoke(Request $request, Handler $handler): Response
    {
        $dto = $this->extractor->extract($request);

        if (!$dto) {
            return ApiResponse::error(new SlimResponse(), 'Validation failed: ID is missing', 400);
        }

        // Кладем DTO в "карман" запроса, чтобы достать его в контроллере
        $request = $request->withAttribute('deal_dto', $dto);

        return $handler->handle($request);
    }
}