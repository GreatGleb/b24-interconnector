<?php
namespace App\Responses;

use Psr\Http\Message\ResponseInterface as Response;

class ApiResponse
{
    public static function success(Response $response, array $data = [], int $status = 200): Response
    {
        return self::render($response, [
            'success'   => true,
            'result'    => $data,
            'timestamp' => time()
        ], $status);
    }

    public static function error(Response $response, string $message, int $status = 400): Response
    {
        return self::render($response, [
            'success' => false,
            'error'   => [
                'message' => $message,
                'code'    => $status
            ]
        ], $status);
    }

    private static function render(Response $response, array $payload, int $status): Response
    {
        $response->getBody()->write(json_encode($payload));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}