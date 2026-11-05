<?php

declare(strict_types=1);

namespace App\Domain\Payments\DTOs;

readonly class PaymentInitiationRequest
{
    public function __construct(
        public int $invoiceId,
        public ?int $memberId,
        public float $amountBdt,
        public string $customerName,
        public string $customerEmail,
        public string $customerPhone,
        public string $callbackUrl,
        public string $cancelUrl,
        public array $metadata = []
    ) {}
}
