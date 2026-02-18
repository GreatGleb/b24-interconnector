<?php
namespace App\Services;

use App\Dto\InboundDealDto;

interface QueueServiceInterface
{
    /**
     * Добавляет сделку в очередь на обработку
     */
    public function addToQueue(InboundDealDto $dto): bool;
}