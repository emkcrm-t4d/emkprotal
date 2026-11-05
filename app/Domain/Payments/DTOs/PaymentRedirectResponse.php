<?php

declare(strict_types=1);

namespace App\Domain\Payments\DTOs;

readonly class PaymentRedirectResponse
{
    public function __construct(
        public string $redirectUrl,
        public string $gatewaySessionId,
        public array $parameters = []
    ) {}
}
