<?php

// bin/worker.php

require __DIR__ . '/../vendor/autoload.php';

ob_start();
$app = require __DIR__ . '/../public/index.php';
ob_end_clean();

use App\Models\DealQueue;
use App\Services\BitrixClient;
use Psr\Log\LoggerInterface;
use Carbon\Carbon;

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

        DealQueue::where('status', 'processing')
            ->where('updated_at', '<', Carbon::now()->subHour())
            ->update(['status' => 'pending']);

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
                echo "--- Начало обработки сделки #{$deal->external_id} - {$deal->source_type} ---\n";

                // 1. Атомарно помечаем, что взяли в работу
                $deal->update([
                    'status' => 'processing',
                    'attempts' => $deal->attempts + 1
                ]);
                echo "Статус изменен на processing...\n";

                $logger->info("Processing Deal #{$deal->external_id}");

                $payload = $deal->payload;

                if ($deal->source_type === 'receiver') {
                    // Список ключей, которые НЕ нужно отправлять в update (технические поля)
                    $excludeKeys = [
                        'ID', 'id', 'ORIGIN_ID', 'origin_id',
                        'user_id', 'token', 'event', 'ts'
                    ];

                    // Фильтруем payload: оставляем только полезные данные
                    $filteredFieldsToUpdate = array_filter($payload, function($value, $key) use ($excludeKeys) {
                        // Убираем технические ключи и пустые значения
                        return !in_array(strtoupper($key), array_map('strtoupper', $excludeKeys))
                            && $value !== null
                            && $value !== '';
                    }, ARRAY_FILTER_USE_BOTH);

                    // Карта соответствия (URL параметры -> коды Винилам)
                    $mapping = [
                        'accepted_at'     => 'UF_CRM_1773749705779',
                        'vinipol_manager' => 'UF_CRM_1773749746541',
                        'fail_reason'     => 'UF_CRM_1773749784601',
                        'target_stage'    => 'STAGE_ID', // target_stage превращаем в системный STAGE_ID
                    ];

                    $fieldsToUpdate = [];

                    foreach ($filteredFieldsToUpdate as $key => $value) {
                        // Приводим ключ к верхнему регистру для системных полей (comments -> COMMENTS)
                        $upperKey = strtoupper($key);

                        if (isset($mapping[$key])) {
                            // Если это наше спецполе из таблицы — мапим его на UF_код или системный ID
                            $fieldsToUpdate[$mapping[$key]] = $value;
                        } else {
                            // Если это любое другое поле (например, COMMENTS), просто оставляем в UPPERCASE
                            $fieldsToUpdate[$upperKey] = $value;
                        }
                    }

                    // Если после фильтрации что-то осталось — пушим в Винилам
                    if (!empty($fieldsToUpdate)) {
                        $bitrix->call($_ENV['SOURCE_B24_WEBHOOK'], 'crm.deal.update', [
                            'id' => $deal->external_id,
                            'fields' => $fieldsToUpdate
                        ]);

                        echo "✅ Сделка #{$deal->external_id} обновлена полями: " . implode(', ', array_keys($fieldsToUpdate)) . "\n";
                    }

                    $deal->update(['status' => 'done']);
                } else {
                    $response = $bitrix->call($_ENV['SOURCE_B24_WEBHOOK'], 'crm.deal.get', ['id' => $deal->external_id], 'GET');
                    $sourceDeal = $response['result'] ?? null; // Достаем саму сделку

                    if (!$sourceDeal) {
                        throw new \Exception("Данные сделки не найдены в ответе Битрикса");
                    }

                    $segmentId = null;
                    if (($payload['segment'] ?? '') === 'конечник') {
                        $segmentId = '1321';
                    } elseif (($payload['segment'] ?? '') === 'дизайнер') {
                        $segmentId = '1323';
                    }

                    $contact_info = $payload['contact_info'] ?? '';
                    $article = $payload['article'] ?? '';
                    $square_meters = $payload['square_meters'] ?? '';
                    $manager_name = $payload['manager_name'] ?? '';
                    $transfer_date = $payload['transfer_date'] ?? '';

                    $rawComment = $sourceDeal['COMMENTS'] ?? '';
                    // Если $rawComment это массив, превращаем его в строку, иначе Битрикс выдаст ошибку
                    $rawComment = is_array($rawComment) ? json_encode($rawComment, JSON_UNESCAPED_UNICODE) : $rawComment;
                    $comments = $payload['comments'] ?? $rawComment;

                    $requestFields = [
                        'TITLE' => ($sourceDeal['TITLE'] ?? 'Без названия') . ' (из Винилам)',
                        'OPPORTUNITY' => $sourceDeal['OPPORTUNITY'] ?? 0,
                        'CURRENCY_ID' => $sourceDeal['CURRENCY_ID'] ?? 'RUB',
                        'COMMENTS' => $comments,
                        'CATEGORY_ID' => 25,
                        'STAGE_ID' => 'C25:NEW', // Начальная стадия для 25-й воронки
                        'ORIGIN_ID' => $deal->external_id,

                        'UF_CRM_1773568798364' => $segmentId,
                        'UF_CRM_1773568832661' => $contact_info,
                        'UF_CRM_1773568858481' => $article,
                        'UF_CRM_1773568886847' => $square_meters,
                        'UF_CRM_1773569441'    => $manager_name,
                        'UF_CRM_1773568973415' => $transfer_date,
                    ];

                    // Создаем в Виниполе
                    $response = $bitrix->call($_ENV['RECEIVER_B24_WEBHOOK'], 'crm.deal.add', [
                        'fields' => $requestFields
                    ]);

                    $newDealId = $response['result'] ?? null;

                    if ($newDealId) {
                        $msg = "✅ Заказ передан в Винипол. Создана сделка №{$newDealId}";
                        echo $msg . PHP_EOL;

                        try {
                            $bitrix->call($_ENV['SOURCE_B24_WEBHOOK'], 'crm.timeline.comment.add', [
                                'fields' => [
                                    'ENTITY_ID' => $deal->external_id,
                                    'ENTITY_TYPE' => 'deal',
                                    'COMMENT' => $msg
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

                        // Финализируем
                        $deal->update(['status' => 'done']);
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