<?php
namespace App\Services;

use App\Dto\InboundDealDto;
use Psr\Http\Message\ServerRequestInterface as Request;

class BitrixRequestExtractor
{
    public function extract(Request $request, string $sourceType = 'source'): ?InboundDealDto
    {
        $params = $request->getQueryParams();
        $body = $request->getParsedBody();

        // Вся логика поиска ID теперь в одном месте
        $dealId = $params['ORIGIN_ID']
            ?? $params['ID']
            ?? $params['id']
            ?? $body['data']['FIELDS']['ID']
            ?? $body['id']
            ?? null;

        $payload = array_merge((array)$params, (array)$body);

        if (!$dealId) {
            return null;
        }

        return new InboundDealDto(
            dealId: (string)$dealId,
            rawPayload: $payload,
            source: $sourceType
        );
    }
}