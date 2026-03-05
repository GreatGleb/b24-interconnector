<?php

namespace App\Services;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

class BitrixClient
{
    private Client $client;

    public function __construct(
        private LoggerInterface $logger
    ) {
        $this->client = new Client(['timeout' => 10.0]);
    }

    public function call(string $webhookUrl, string $method, array $params = [], string $httpMethod = 'POST')
    {
        try {
            $url = rtrim($webhookUrl, '/') . '/' . $method . '.json';
            $httpMethod = strtoupper($httpMethod);

            // Конфигурируем запрос в зависимости от метода
            $options = [];
            if ($httpMethod === 'GET') {
                // Для GET параметры идут в query string (?id=3430)
                $options['query'] = $params;
            } else {
                // Для POST используем form_params (наиболее стабильно для B24)
                $options['form_params'] = $params;
            }

            // Выполняем запрос (Guzzle умеет $this->client->request('GET', ...))
            $response = $this->client->request($httpMethod, $url, $options);

            $result = json_decode($response->getBody()->getContents(), true);

            if (isset($result['error'])) {
                throw new \Exception("Bitrix API Error: " . ($result['error_description'] ?? $result['error']));
            }

            return $result;

        } catch (\Throwable $e) {
            $this->logger->error("Bitrix Request Failed: " . $e->getMessage(), [
                'method' => $method,
                'http_method' => $httpMethod
            ]);
            throw $e;
        }
    }

    /**
     * Добавляет комментарий в таймлайн сущности (Лид или Сделка)
     */
    public function addTimelineComment(string $webhookUrl, string $entityType, int $entityId, string $message): void
    {
        $this->call($webhookUrl, 'crm.timeline.item.add', [
            'fields' => [
                'ENTITY_ID'   => $entityId,
                'ENTITY_TYPE' => $entityType, // 'deal' или 'lead'
                'COMMENT'     => $message
            ]
        ]);
    }
}