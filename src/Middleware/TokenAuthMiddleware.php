<?php
namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Psr\Log\LoggerInterface;

class TokenAuthMiddleware
{
    private string $validToken;
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->validToken = $_ENV['B24_AUTH_TOKEN'] ?? '';
        $this->logger = $logger;
    }

    public function __invoke(Request $request, Handler $handler): Response
    {
        $params = $request->getQueryParams();
        $body = $request->getParsedBody();
        $receivedToken = $params['auth']['application_token'] ?? $body['auth']['application_token'] ?? null;

        if (!$receivedToken || $receivedToken !== $this->validToken) {
            $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
            $this->logger->warning("Unauthorized access attempt", [
                'ip' => $ip,
                'received_token' => $receivedToken ? 'provided_but_invalid' : 'missing',
                'url' => (string)$request->getUri()
            ]);

            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Unauthorized'
            ]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        return $handler->handle($request);
    }
}