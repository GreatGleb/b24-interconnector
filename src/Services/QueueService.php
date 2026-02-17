<?php
namespace App\Services;

class QueueService {
    public function addToQueue($dealId, $source) {
        // Здесь будет PDO INSERT в deals_queue
        // Пока оставим заглушку, чтобы проверить роутинг
        return true;
    }
}