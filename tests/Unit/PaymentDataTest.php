<?php

use OpenKOS\Core\Data\Payment\CheckoutInstruction;
use OpenKOS\Core\Data\Payment\CheckoutInstructions;
use OpenKOS\Core\Data\Payment\Money;
use OpenKOS\Core\Data\Payment\PaymentCreationResult;
use OpenKOS\Core\Data\Payment\PaymentProviderResult;
use OpenKOS\Core\Data\Payment\PaymentRequest;
use OpenKOS\Core\Data\Payment\PaymentStatusLookupRequest;
use OpenKOS\Core\Data\Payment\PaymentWebhookRequest;
use OpenKOS\Core\Data\Payment\PaymentWebhookResult;
use OpenKOS\Core\Enums\PaymentStatus;
use OpenKOS\Core\Exceptions\PaymentWebhookPayloadException;
use OpenKOS\Core\Exceptions\PaymentWebhookVerificationException;

it('normalizes currency and permits zero-valued money', function () {
    $money = new Money(0, 'idr');

    expect($money->minorUnits)->toBe(0)
        ->and($money->currency)->toBe('IDR');
});

it('rejects invalid money currencies and negative amounts', function () {
    new Money(-1, 'IDR');
})->throws(InvalidArgumentException::class, 'cannot be negative');

it('rejects currencies that are not three ASCII letters', function () {
    new Money(100, 'EURO');
})->throws(InvalidArgumentException::class, 'three-letter ISO 4217');

it('requires payment requests to have a positive amount', function () {
    new PaymentRequest('attempt-1', new Money(0, 'IDR'));
})->throws(InvalidArgumentException::class, 'greater than zero');

it('represents redirect and structured checkout instructions', function () {
    $instructions = new CheckoutInstructions(
        url: 'https://pay.example.test/checkout',
        entries: [
            new CheckoutInstruction('virtual_account', '8800123456', 'Virtual account'),
            new CheckoutInstruction('qr_code', '0002010102', 'QRIS payload'),
        ],
    );

    expect($instructions->url)->toBe('https://pay.example.test/checkout')
        ->and($instructions->entries[0]->key)->toBe('virtual_account')
        ->and($instructions->entries[1]->value)->toBe('0002010102');
});

it('rejects nested payment metadata', function () {
    new PaymentRequest('attempt-1', new Money(100, 'IDR'), metadata: ['raw' => ['payload']]);
})->throws(InvalidArgumentException::class, 'scalar values');

it('models normalized creation and webhook results', function () {
    $amount = new Money(150_000, 'IDR');
    $created = new PaymentCreationResult(
        providerReference: 'provider-1',
        status: PaymentStatus::Pending,
        amount: $amount,
        instructions: new CheckoutInstructions,
        expiresAt: new DateTimeImmutable('2026-08-15T00:00:00+00:00'),
    );
    $webhook = new PaymentWebhookResult(
        eventReference: 'event-1',
        providerReference: 'provider-1',
        status: PaymentStatus::Settled,
        reference: 'attempt-1',
        amount: $amount,
    );

    expect($created->status)->toBe(PaymentStatus::Pending)
        ->and($created->amount)->toBe($amount)
        ->and($webhook->status)->toBe(PaymentStatus::Settled)
        ->and($webhook->reference)->toBe('attempt-1');
});

it('models a provider result without requiring a webhook event reference', function () {
    $result = new PaymentProviderResult(
        providerReference: 'provider-1',
        status: PaymentStatus::Pending,
        reference: 'attempt-1',
        amount: new Money(150_000, 'IDR'),
    );

    expect($result->eventReference)->toBeNull()
        ->and($result->status)->toBe(PaymentStatus::Pending);
});

it('models provider status lookup requests', function () {
    $request = new PaymentStatusLookupRequest(
        providerReference: 'provider-1',
        reference: 'attempt-1',
        metadata: ['invoice_id' => 123],
    );

    expect($request->providerReference)->toBe('provider-1')
        ->and($request->reference)->toBe('attempt-1')
        ->and($request->metadata)->toBe(['invoice_id' => 123]);
});

it('rejects empty provider status lookup references', function () {
    new PaymentStatusLookupRequest('');
})->throws(InvalidArgumentException::class, 'Provider payment references cannot be empty.');

it('keeps webhook input independent from the HTTP framework', function () {
    $request = new PaymentWebhookRequest(
        rawBody: '{"id":"event-1"}',
        headers: ['x-signature' => 'signature'],
    );

    expect($request->rawBody)->toContain('event-1')
        ->and($request->headers['x-signature'])->toBe('signature');
});

it('uses a dedicated exception for invalid webhook verification', function () {
    throw new PaymentWebhookVerificationException('Webhook signature is invalid.');
})->throws(PaymentWebhookVerificationException::class, 'signature is invalid');

it('uses a separate exception for authenticated malformed webhook payloads', function () {
    throw new PaymentWebhookPayloadException('Webhook payload is malformed.');
})->throws(PaymentWebhookPayloadException::class, 'payload is malformed');
