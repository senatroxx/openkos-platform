# OpenKOS Platform

The standalone Laravel platform and plugin SDK for OpenKOS.

Plugins extend `OpenKOS\Platform\Plugin\Plugin` and register through the
typed `OpenKOSManager` registries. Host applications can keep explicit plugin
registration in `config/platform.php` or provide a `PluginDiscovery`
implementation for external plugin packages.

The package contains no OpenKOS application models, events, persistence,
frontend assets, or Spatie dependencies. Settings persistence is supplied by
the host through `OpenKOS\Core\Contracts\SettingsStore`.

The package does not scan Composer metadata itself. Host applications own
Composer discovery and provide discovered plugin class names through
`OpenKOS\Core\Contracts\PluginDiscovery`.

## Installation

```shell
composer require openkos/platform
```

Publish the optional configuration with:

```shell
php artisan vendor:publish --tag=openkos-platform-config
```

## Payment gateways

Payment plugins implement `OpenKOS\Core\Contracts\PaymentGateway` with the
platform's typed payment DTOs. Amounts use integer minor units and an ISO 4217
currency code; metadata is limited to flat scalar values; gateway-specific
response shapes stay inside the plugin.

```php
use OpenKOS\Core\Contracts\PaymentGateway;
use OpenKOS\Core\Data\Payment\CheckoutInstruction;
use OpenKOS\Core\Data\Payment\CheckoutInstructions;
use OpenKOS\Core\Data\Payment\PaymentCreationResult;
use OpenKOS\Core\Data\Payment\PaymentRequest;
use OpenKOS\Core\Data\Payment\PaymentWebhookRequest;
use OpenKOS\Core\Data\Payment\PaymentWebhookResult;
use OpenKOS\Core\Enums\PaymentStatus;
use OpenKOS\Core\Exceptions\PaymentWebhookVerificationException;

final class ExampleGateway implements PaymentGateway
{
    public function createPayment(PaymentRequest $request): PaymentCreationResult
    {
        return new PaymentCreationResult(
            providerReference: $this->providerReference($request),
            status: PaymentStatus::Pending,
            amount: $request->amount,
            instructions: new CheckoutInstructions(
                url: 'https://provider.example/checkout',
                entries: [
                    new CheckoutInstruction('retail_code', '1234567890', 'Payment code'),
                ],
            ),
        );
    }

    public function handleCallback(PaymentWebhookRequest $request): PaymentWebhookResult
    {
        if (! $this->isValidSignature($request)) {
            throw new PaymentWebhookVerificationException('Webhook signature is invalid.');
        }

        return $this->normalizeCallback($request);
    }

    // key(), displayName(), configurationSchema(), and provider mapping omitted.
}
```

`PaymentStatus::Settled` means the provider has settled the gateway attempt;
the host application decides when that becomes a canonical OpenKOS payment.
The other normalized states are `Pending`, `Failed`, `Expired`, and `Canceled`.
Invalid webhook signatures throw before a normalized payment event is returned.
