<?php

namespace App\Middleware;

use App\Services\BitrixRequestExtractor;
class ReceiverValidationMiddleware extends BitrixValidationMiddleware
{
    public function __construct(BitrixRequestExtractor $extractor) {
        parent::__construct($extractor, 'receiver');
    }
}