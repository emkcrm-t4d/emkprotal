<?php

declare(strict_types=1);

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Adapters\ManualBkashPaymentAdapter;
use App\Domain\Payments\Adapters\MockGatewayPaymentAdapter;
use App\Domain\Payments\Contracts\PaymentAdapterInterface;
use Illuminate\Support\Manager;
use InvalidArgumentException;

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

    /**
     * Create an instance of the mock payment driver.
     */
    public function createMockDriver(): PaymentAdapterInterface
    {
        return new MockGatewayPaymentAdapter(
            config: (array) $this->config->get('payments.drivers.mock', [])
        );
    }

    /**
     * Create an instance of the SSLCommerz gateway driver.
     */
    public function createSslcommerzDriver(): PaymentAdapterInterface
    {
        $config = (array) $this->config->get('payments.drivers.sslcommerz', []);

        if (class_exists(\App\Domain\Payments\Adapters\SslCommerzPaymentAdapter::class)) {
            /** @var class-string<PaymentAdapterInterface> $class */
            $class = \App\Domain\Payments\Adapters\SslCommerzPaymentAdapter::class;
            return new $class($config);
        }

        return new MockGatewayPaymentAdapter(array_merge($config, ['gateway' => 'sslcommerz']));
    }

    /**
     * Create an instance of the direct bKash Gateway driver.
     */
    public function createBkashGatewayDriver(): PaymentAdapterInterface
    {
        $config = (array) $this->config->get('payments.drivers.bkash_gateway', []);

        if (class_exists(\App\Domain\Payments\Adapters\BkashGatewayPaymentAdapter::class)) {
            /** @var class-string<PaymentAdapterInterface> $class */
            $class = \App\Domain\Payments\Adapters\BkashGatewayPaymentAdapter::class;
            return new $class($config);
        }

        return new MockGatewayPaymentAdapter(array_merge($config, ['gateway' => 'bkash_gateway']));
    }
}
