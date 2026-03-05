<?php

// bin/worker.php

require __DIR__ . '/../vendor/autoload.php';

ob_start();
$app = require __DIR__ . '/../public/index.php';
ob_end_clean();

use App\Models\DealQueue;
use App\Services\BitrixClient;
use Psr\Log\LoggerInterface;

if (!$app instanceof \Slim\App) {
    if (!isset($container)) {
        die("Ошибка: Не удалось получить контейнер из index.php. Проверь, что файл возвращает \$app или создаёт \$container.\n");
    }
} else {
    $container = $app->getContainer();
}

echo "Контейнер получен. Ищу сделки в статусе pending...\n";

// Если в index.php лежит $container = ...
if (isset($container)) {
    $logger = $container->get(LoggerInterface::class);
    $bitrix = $container->get(BitrixClient::class);

    $logger->info("--- Worker Cycle Started ---");

    try {
        echo "Запуск цикла... Ищу сделки в статусе pending\n";

        $total = DealQueue::count();
        $pendingCount = DealQueue::where('status', 'pending')->count();

        echo "Всего записей в таблице: $total\n";
        echo "Из них в статусе pending: $pendingCount\n";

        $deals = DealQueue::where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->limit(10)
            ->get();

        if ($deals->isEmpty()) {
            $logger->info("No deals in queue. Exiting.");
            echo ("No deals in queue. Exiting.");
            exit;
        }

        foreach ($deals as $deal) {
            try {
                echo "--- Начало обработки сделки #{$deal->external_id} ---\n";

                // 1. Атомарно помечаем, что взяли в работу
                $deal->update([
                    'status' => 'processing',
                    'attempts' => $deal->attempts + 1
                ]);
                echo "Статус изменен на processing...\n";

                $logger->info("Processing Deal #{$deal->external_id}");

                $response = $bitrix->call($_ENV['SOURCE_B24_WEBHOOK'], 'crm.deal.get', ['id' => $deal->external_id], 'GET');
                $sourceDeal = $response['result'] ?? null; // Достаем саму сделку

                if (!$sourceDeal) {
                    throw new \Exception("Данные сделки не найдены в ответе Битрикса");
                }

                // Берем сырой комментарий. Если его нет, ставим пустую строку.
                $rawComment = $sourceDeal['COMMENTS'] ?? '';

                // 2. Создаем в Виниполе
                $response = $bitrix->call($_ENV['RECEIVER_B24_WEBHOOK'], 'crm.deal.add', [
                    'fields' => [
                        'TITLE'       => ($sourceDeal['TITLE'] ?? 'Без названия') . ' (из Винилам)',
                        'OPPORTUNITY' => $sourceDeal['OPPORTUNITY'] ?? 0,
                        'CURRENCY_ID' => $sourceDeal['CURRENCY_ID'] ?? 'RUB',
                        // Если $rawComment это массив, превращаем его в строку, иначе Битрикс выдаст ошибку
                        'COMMENTS'    => is_array($rawComment) ? json_encode($rawComment, JSON_UNESCAPED_UNICODE) : $rawComment,
                        'CATEGORY_ID' => 19,
                        'STAGE_ID'    => 'C19:NEW', // Уточни префикс C, обычно для воронки 19 это C19:NEW
                        'ORIGIN_ID'   => $deal->external_id,
                    ]
                ]);

                $newDealId = $response['result'] ?? null;

                if ($newDealId) {
                    $msg = "✅ Заказ передан в Винипол. Создана сделка №{$newDealId}";
                    echo $msg . PHP_EOL;

                    try {
                        $bitrix->call($_ENV['SOURCE_B24_WEBHOOK'], 'crm.timeline.comment.add', [
                            'fields' => [
                                'ENTITY_ID'   => $deal->external_id,
                                'ENTITY_TYPE' => 'deal',
                                'COMMENT'     => $msg
                            ]
                        ]);
                    } catch (\Exception $e) {
                        $logger->warning("Could not add timeline comment: " . $e->getMessage());
                    }

                    // Двигаем стадию в Источнике
                    $bitrix->call($_ENV['SOURCE_B24_WEBHOOK'], 'crm.deal.update', [
                        'id' => $deal->external_id,
                        'fields' => [
                            'STAGE_ID' => 'C2:UC_MXQPC8'
                        ]
                    ]);

                    // 5. Финализируем
                    $deal->update(['status' => 'done']); // У тебя в коде было 'done', в базе обычно 'completed', проверь соответствие
                    $logger->info("Deal #{$deal->external_id} processed. New ID: {$newDealId}");
                    echo "Сделка #{$deal->external_id} успешно обработана!\n";
                } else {
                    $errorMsg = "Bitrix returned success but no ID was created.";
                    $logger->error("Failed to create deal in Receiver for #{$deal->external_id}: {$errorMsg}");
                    echo "Failed to create deal in Receiver for #{$deal->external_id}: {$errorMsg}";

                    $deal->update([
                        'status' => 'error',
                        'error_log' => $errorMsg
                    ]);
                }
            } catch (\Exception $e) {
                $logger->error("Failed to process deal #{$deal->external_id}: " . $e->getMessage());
                echo "Failed to process deal #{$deal->external_id}: " . $e->getMessage();

                $deal->update([
                    'status' => 'error',
                    'error_log' => $e->getMessage()
                ]);
            }
        }

    } catch (\Exception $e) {
        $logger->critical("Worker fatal error: " . $e->getMessage());
    }

    $logger->info("--- Worker Cycle Finished ---");

} else {
    echo 'doesnt exist container';
}