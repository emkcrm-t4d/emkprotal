<?php

declare(strict_types=1);

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Adapters\ManualBkashPaymentAdapter;
use App\Domain\Payments\Contracts\PaymentAdapterInterface;
use Illuminate\Support\Manager;

/**
 * @method PaymentAdapterInterface driver(?string $driver = null)
 */
class PaymentManager extends Manager
{
    /**
     * Get the default payment driver name from configuration.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('payments.default_driver', 'bkash_manual');
    }

    /**
     * Create an instance of the manual bKash payment driver.
     */
    public function createBkashManualDriver(): PaymentAdapterInterface
    {
        return new ManualBkashPaymentAdapter(
            config: (array) $this->config->get('payments.drivers.bkash_manual', [])
        );
    }
}
