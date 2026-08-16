<?php

namespace OpenKOS\Core\Contracts;

use OpenKOS\Core\Data\Payment\PaymentCreationResult;
use OpenKOS\Core\Data\Payment\PaymentRequest;
use OpenKOS\Core\Data\Payment\PaymentWebhookRequest;
use OpenKOS\Core\Data\Payment\PaymentWebhookResult;
use OpenKOS\Core\Exceptions\PaymentWebhookPayloadException;
use OpenKOS\Core\Exceptions\PaymentWebhookVerificationException;

interface PaymentGateway
{
    public function key(): string;

    public function displayName(): string;

    /**
     * Create a provider-side payment attempt for the positive minor-unit amount
     * and stable OpenKOS reference in the request.
     */
    public function createPayment(PaymentRequest $request): PaymentCreationResult;

    /**
     * Verify and normalize a raw provider callback.
     *
     * @throws PaymentWebhookVerificationException when the callback is not trusted
     * @throws PaymentWebhookPayloadException when a trusted callback is malformed
     */
    public function handleCallback(PaymentWebhookRequest $request): PaymentWebhookResult;

    /**
     * Describe the configuration fields this gateway needs.
     */
    public function configurationSchema(): array;
}
