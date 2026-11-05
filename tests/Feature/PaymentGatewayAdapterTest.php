<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Payments\Adapters\ManualBkashPaymentAdapter;
use App\Domain\Payments\Adapters\MockGatewayPaymentAdapter;
use App\Domain\Payments\Contracts\PaymentAdapterInterface;
use App\Domain\Payments\Contracts\PaymentGatewayAdapterInterface;
use App\Domain\Payments\DTOs\PaymentInitiationRequest;
use App\Domain\Payments\DTOs\PaymentRedirectResponse;
use App\Domain\Payments\DTOs\PaymentVerificationRequest;
use App\Domain\Payments\DTOs\WebhookResult;
use App\Domain\Payments\Exceptions\UnsupportedPaymentOperationException;
use App\Domain\Payments\Services\PaymentGatewayManager;
use App\Domain\Payments\Services\PaymentManager;
use Error;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Tests\TestCase;

class PaymentGatewayAdapterTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_gateway_adapter_interface_hierarchy(): void
    {
        $reflection = new \ReflectionClass(PaymentGatewayAdapterInterface::class);
        $this->assertTrue($reflection->isInterface());
        $this->assertTrue($reflection->implementsInterface(PaymentAdapterInterface::class));
    }

    public function test_dto_immutability_and_instantiation(): void
    {
        $initRequest = new PaymentInitiationRequest(
            invoiceId: 101,
            memberId: 5,
            amountBdt: 1250.00,
            customerName: 'Rahim Ahmed',
            customerEmail: 'rahim@example.com',
            customerPhone: '+8801700000000',
            callbackUrl: 'https://emk.test/payment/callback',
            cancelUrl: 'https://emk.test/payment/cancel',
            metadata: ['plan' => 'annual']
        );

        $this->assertSame(101, $initRequest->invoiceId);
        $this->assertSame(5, $initRequest->memberId);
        $this->assertSame(1250.00, $initRequest->amountBdt);
        $this->assertSame('Rahim Ahmed', $initRequest->customerName);
        $this->assertSame('rahim@example.com', $initRequest->customerEmail);
        $this->assertSame('+8801700000000', $initRequest->customerPhone);
        $this->assertSame('https://emk.test/payment/callback', $initRequest->callbackUrl);
        $this->assertSame('https://emk.test/payment/cancel', $initRequest->cancelUrl);
        $this->assertSame(['plan' => 'annual'], $initRequest->metadata);

        // Verify readonly immutability
        $this->expectException(Error::class);
        /** @phpstan-ignore-next-line */
        $initRequest->amountBdt = 999.00;
    }

    public function test_payment_redirect_response_immutability(): void
    {
        $redirectResponse = new PaymentRedirectResponse(
            redirectUrl: 'https://payment.mock/checkout?session=123',
            gatewaySessionId: 'MOCK-SESS-123',
            parameters: ['invoice' => 101]
        );

        $this->assertSame('https://payment.mock/checkout?session=123', $redirectResponse->redirectUrl);
        $this->assertSame('MOCK-SESS-123', $redirectResponse->gatewaySessionId);
        $this->assertSame(['invoice' => 101], $redirectResponse->parameters);

        $this->expectException(Error::class);
        /** @phpstan-ignore-next-line */
        $redirectResponse->gatewaySessionId = 'CHANGED';
    }

    public function test_webhook_result_dto_factories(): void
    {
        $success = WebhookResult::success(
            transactionId: 'TRX-12345',
            paymentReference: 'INV-101',
            amountBdt: 1500.00,
            rawPayload: ['foo' => 'bar'],
            status: 'paid'
        );

        $this->assertTrue($success->isHandled);
        $this->assertSame('TRX-12345', $success->transactionId);
        $this->assertSame('INV-101', $success->paymentReference);
        $this->assertSame(1500.00, $success->amountBdt);
        $this->assertSame('paid', $success->status);
        $this->assertNull($success->errorMessage);

        $failure = WebhookResult::failure(
            errorMessage: 'Signature verification failed',
            rawPayload: ['error' => 'invalid_sig']
        );

        $this->assertFalse($failure->isHandled);
        $this->assertSame('Signature verification failed', $failure->errorMessage);
        $this->assertSame('failed', $failure->status);
    }

    public function test_unsupported_payment_operation_exception(): void
    {
        $exception = new UnsupportedPaymentOperationException('Online payment not supported');
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertSame('Online payment not supported', $exception->getMessage());
    }

    public function test_mock_gateway_adapter_conformance_and_properties(): void
    {
        $adapter = new MockGatewayPaymentAdapter();

        $this->assertInstanceOf(PaymentAdapterInterface::class, $adapter);
        $this->assertInstanceOf(PaymentGatewayAdapterInterface::class, $adapter);
        $this->assertSame('mock', $adapter->getDriverName());
        $this->assertFalse($adapter->supportsManualVerification());
    }

    public function test_mock_gateway_adapter_initiate_payment(): void
    {
        $adapter = new MockGatewayPaymentAdapter([
            'endpoint' => 'https://sandbox.mock-pay.test/checkout',
        ]);

        $request = new PaymentInitiationRequest(
            invoiceId: 99,
            memberId: 12,
            amountBdt: 2000.00,
            customerName: 'Fatima Begum',
            customerEmail: 'fatima@example.com',
            customerPhone: '+8801811122233',
            callbackUrl: 'https://emk.test/payments/callback',
            cancelUrl: 'https://emk.test/payments/cancel'
        );

        $response = $adapter->initiatePayment($request);

        $this->assertInstanceOf(PaymentRedirectResponse::class, $response);
        $this->assertStringStartsWith('MOCK-SESS-', $response->gatewaySessionId);
        $this->assertStringContainsString('https://sandbox.mock-pay.test/checkout?session=', $response->redirectUrl);
        $this->assertStringContainsString('&invoice=99', $response->redirectUrl);
        $this->assertSame(2000.00, $response->parameters['amount_bdt']);
        $this->assertSame('Fatima Begum', $response->parameters['customer_name']);
    }

    public function test_mock_gateway_adapter_verify_payment(): void
    {
        $adapter = new MockGatewayPaymentAdapter();

        $verificationRequest = new PaymentVerificationRequest(
            leadId: 50,
            paymentReference: 'MOCKVERIFY1',
            amountBdt: 1000.0,
            reviewerUserId: 1,
            notes: 'Testing mock verification'
        );

        $result = $adapter->verifyPayment($verificationRequest);

        $this->assertTrue($result->isVerified);
        $this->assertTrue($result->isSuccessful);
        $this->assertSame('MOCKVERIFY1', $result->reference);
        $this->assertSame('mock', $result->evidencePayload['gateway']);
        $this->assertSame(1000.0, $result->evidencePayload['amount_received_bdt']);
    }

    public function test_mock_gateway_adapter_handle_webhook_with_valid_signature(): void
    {
        $secret = 'test-mock-secret';
        $adapter = new MockGatewayPaymentAdapter(['secret' => $secret]);

        $payload = [
            'transaction_id' => 'TRX-MOCK-998877',
            'payment_reference' => 'INV-2026-99',
            'amount_bdt' => 1500.00,
            'status' => 'paid',
        ];

        $rawBody = (string) json_encode($payload);
        $signature = hash_hmac('sha256', $rawBody, $secret);

        $request = Request::create(
            uri: '/payments/webhook/mock',
            method: 'POST',
            server: ['HTTP_X_MOCK_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
            content: $rawBody
        );

        $result = $adapter->handleWebhook($request);

        $this->assertTrue($result->isHandled);
        $this->assertSame('TRX-MOCK-998877', $result->transactionId);
        $this->assertSame('INV-2026-99', $result->paymentReference);
        $this->assertSame(1500.00, $result->amountBdt);
        $this->assertSame('paid', $result->status);
        $this->assertNull($result->errorMessage);
    }

    public function test_mock_gateway_adapter_handle_webhook_invalid_and_missing_signature(): void
    {
        $adapter = new MockGatewayPaymentAdapter(['secret' => 'correct-secret']);

        $payload = [
            'transaction_id' => 'TRX-MOCK-001',
            'amount_bdt' => 500.00,
            'status' => 'paid',
        ];

        // 1. Missing signature
        $requestNoSig = Request::create(
            uri: '/payments/webhook/mock',
            method: 'POST',
            content: (string) json_encode($payload)
        );
        $resultNoSig = $adapter->handleWebhook($requestNoSig);
        $this->assertFalse($resultNoSig->isHandled);
        $this->assertStringContainsString('Missing webhook digital signature', (string) $resultNoSig->errorMessage);

        // 2. Invalid signature
        $requestBadSig = Request::create(
            uri: '/payments/webhook/mock',
            method: 'POST',
            server: ['HTTP_X_MOCK_SIGNATURE' => 'invalid-hash-value'],
            content: (string) json_encode($payload)
        );
        $resultBadSig = $adapter->handleWebhook($requestBadSig);
        $this->assertFalse($resultBadSig->isHandled);
        $this->assertStringContainsString('Invalid webhook digital signature', (string) $resultBadSig->errorMessage);
    }

    public function test_manual_bkash_adapter_throws_unsupported_exception_on_online_methods(): void
    {
        $adapter = new ManualBkashPaymentAdapter();

        $this->assertSame('bkash_manual', $adapter->getDriverName());
        $this->assertTrue($adapter->supportsManualVerification());

        $initRequest = new PaymentInitiationRequest(
            invoiceId: 1,
            memberId: null,
            amountBdt: 1000.0,
            customerName: 'Test',
            customerEmail: 'test@example.com',
            customerPhone: '+8801700000000',
            callbackUrl: 'https://emk.test/cb',
            cancelUrl: 'https://emk.test/cancel'
        );

        $this->expectException(UnsupportedPaymentOperationException::class);
        $this->expectExceptionMessage('Driver [bkash_manual] does not support online payment initiation');
        $adapter->initiatePayment($initRequest);
    }

    public function test_manual_bkash_adapter_throws_unsupported_exception_on_webhook(): void
    {
        $adapter = new ManualBkashPaymentAdapter();
        $request = Request::create('/payments/webhook/bkash', 'POST');

        $this->expectException(UnsupportedPaymentOperationException::class);
        $this->expectExceptionMessage('Driver [bkash_manual] does not support automated webhook callbacks');
        $adapter->handleWebhook($request);
    }

    public function test_payment_manager_default_driver_resolution(): void
    {
        $manager = app(PaymentManager::class);

        $this->assertSame('bkash_manual', $manager->getDefaultDriver());
        $this->assertInstanceOf(ManualBkashPaymentAdapter::class, $manager->driver());
        $this->assertSame('bkash_manual', $manager->driver()->getDriverName());
    }

    public function test_payment_manager_and_gateway_manager_driver_resolution_and_swapping(): void
    {
        /** @var PaymentManager $paymentManager */
        $paymentManager = app(PaymentManager::class);
        /** @var PaymentGatewayManager $gatewayManager */
        $gatewayManager = app(PaymentGatewayManager::class);

        // Explicit driver calls
        $mockFromPayment = $paymentManager->driver('mock');
        $mockFromGateway = $gatewayManager->driver('mock');

        $this->assertInstanceOf(MockGatewayPaymentAdapter::class, $mockFromPayment);
        $this->assertInstanceOf(MockGatewayPaymentAdapter::class, $mockFromGateway);
        $this->assertSame('mock', $mockFromPayment->getDriverName());
        $this->assertSame('mock', $mockFromGateway->getDriverName());

        // Resolving future gateway drivers (sslcommerz and bkash_gateway)
        $sslDriver = $paymentManager->driver('sslcommerz');
        $this->assertInstanceOf(PaymentAdapterInterface::class, $sslDriver);

        $bkashGwDriver = $paymentManager->driver('bkash_gateway');
        $this->assertInstanceOf(PaymentAdapterInterface::class, $bkashGwDriver);

        // Dynamic runtime driver switching via config per DEC-10
        config(['payments.default_driver' => 'mock']);
        $newPaymentManager = new PaymentManager(app());
        $this->assertSame('mock', $newPaymentManager->getDefaultDriver());
        $this->assertInstanceOf(MockGatewayPaymentAdapter::class, $newPaymentManager->driver());
    }

    public function test_payment_manager_throws_invalid_argument_exception_for_unknown_driver(): void
    {
        $manager = app(PaymentManager::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Driver [unsupported_gateway] not supported');
        $manager->driver('unsupported_gateway');
    }

    public function test_payment_manager_prohibits_mock_driver_in_production_environment_without_explicit_flag(): void
    {
        $this->app['env'] = 'production';
        config(['payments.allow_mock_in_production' => false]);

        $manager = new PaymentManager($this->app);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Mock payment gateway driver is strictly prohibited in production.');
        $manager->createMockDriver();
    }
}
