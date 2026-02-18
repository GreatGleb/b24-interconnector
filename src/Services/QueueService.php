<?php
namespace App\Services;

use App\Dto\InboundDealDto;
use App\Models\DealQueue;
use App\Exceptions\QueueException;
use Psr\Log\LoggerInterface;
use Throwable;

class QueueService implements QueueServiceInterface
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    /**
     * @throws QueueException
     */
    public function addToQueue(InboundDealDto $dto): bool
    {
        try {
            DealQueue::create([
                'external_id' => $dto->dealId,
                'source_type' => $dto->source,
                'status'      => 'pending',
                'payload'     => $dto->rawPayload, // Передаем массив, Cast сам сделает JSON
            ]);

            $this->logger->info("Deal {id} saved via Eloquent", ['id' => $dto->dealId]);
            return true;

        } catch (Throwable $e) {
            $this->logger->error("Eloquent error: " . $e->getMessage(), [
                'deal_id' => $dto->dealId
            ]);
            throw new QueueException("Database storage failed.");
        }
    }
}