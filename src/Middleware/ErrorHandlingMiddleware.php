<?php
namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Response as SlimResponse;
use App\Responses\ApiResponse;
use Psr\Log\LoggerInterface;
use Throwable;

class ErrorHandlingMiddleware
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function __invoke(Request $request, Handler $handler): Response
    {
        try {
            // Пытаемся выполнить запрос дальше по цепочке
            return $handler->handle($request);
        } catch (Throwable $e) {
            // 1. Логируем критическую ошибку с контекстом
            $this->logger->error('Unhandled Exception', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
                'url'     => (string)$request->getUri()
            ]);

            // 2. Отдаем клиенту чистый JSON вместо "оранжевого экрана" PHP
            return ApiResponse::error(
                new SlimResponse(),
                'Internal Server Error. Our team has been notified.',
                500
            );
        }
    }
}