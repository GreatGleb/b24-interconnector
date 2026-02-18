<?php

namespace App\Responses;

use Psr\Http\Message\ResponseInterface as Response;
use App\Dto\InboundDealDto;

class DealResponder
{
    public function __construct(private ApiResponse $apiResponse) {}

    public function respond(Response $response, InboundDealDto $dto): Response
    {
        return $this->apiResponse::success($response, [
            'status'  => 'accepted',
            'deal_id' => $dto->dealId
        ]);
    }
}