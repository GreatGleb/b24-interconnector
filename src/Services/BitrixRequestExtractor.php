<?php
namespace App\Services;

use App\Dto\InboundDealDto;
use Psr\Http\Message\ServerRequestInterface as Request;

class BitrixRequestExtractor
{
    public function extract(Request $request): ?InboundDealDto
    {
        $params = $request->getQueryParams();

        // Вся логика поиска ID теперь в одном месте
        $dealId = $params['id'] ?? $params['data']['FIELDS']['ID'] ?? null;

        if (!$dealId) {
            return null;
        }

        return new InboundDealDto(
            dealId: (string)$dealId,
            rawPayload: $params
        );
    }
}