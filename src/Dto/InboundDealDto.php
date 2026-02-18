<?php
namespace App\Dto;

class InboundDealDto
{
    public function __construct(
        public string $dealId,
        public array $rawPayload,
        public string $source = 'source'
    ) {}
}