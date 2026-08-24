<?php

use OpenKOS\Core\Contracts\PaymentGateway;
use OpenKOS\Core\Contracts\PaymentGatewayCurrencySupport;
use OpenKOS\Core\Data\Payment\CheckoutInstructions;
use OpenKOS\Core\Data\Payment\PaymentCreationResult;
use OpenKOS\Core\Data\Payment\PaymentRequest;
use OpenKOS\Core\Data\Payment\PaymentWebhookRequest;
use OpenKOS\Core\Data\Payment\PaymentWebhookResult;
use OpenKOS\Core\Enums\PaymentStatus;

it('allows gateways to opt into currency support without changing the base contract', function () {
    $gateway = new class implements PaymentGateway, PaymentGatewayCurrencySupport
    {
        public function key(): string
        {
            return 'currency-aware';
        }

        public function displayName(): string
        {
            return 'Currency-aware gateway';
        }

        public function createPayment(PaymentRequest $request): PaymentCreationResult
        {
            return new PaymentCreationResult(
                providerReference: 'provider-reference',
                status: PaymentStatus::Pending,
                amount: $request->amount,
                instructions: new CheckoutInstructions,
            );
        }

        public function handleCallback(PaymentWebhookRequest $request): PaymentWebhookResult
        {
            return new PaymentWebhookResult(
                eventReference: 'event-reference',
                providerReference: 'provider-reference',
                status: PaymentStatus::Pending,
            );
        }

        public function configurationSchema(): array
        {
            return [];
        }

        public function supportsCurrency(string $currency): bool
        {
            return in_array(strtoupper($currency), $this->supportedCurrencies(), true);
        }

        public function supportedCurrencies(): array
        {
            return ['IDR', 'USD'];
        }
    };

    expect($gateway)->toBeInstanceOf(PaymentGateway::class)
        ->and($gateway)->toBeInstanceOf(PaymentGatewayCurrencySupport::class)
        ->and($gateway->supportsCurrency('usd'))->toBeTrue()
        ->and($gateway->supportsCurrency('EUR'))->toBeFalse()
        ->and($gateway->supportedCurrencies())->toBe(['IDR', 'USD']);
});
