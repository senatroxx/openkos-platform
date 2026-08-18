<?php

namespace OpenKOS\Core\Data\Payment;

use InvalidArgumentException;
use OpenKOS\Core\Enums\PaymentStatus;

final readonly class PaymentWebhookResult extends PaymentProviderResult
{
    public function __construct(
        string $eventReference,
        string $providerReference,
        PaymentStatus $status,
        ?string $reference = null,
        ?Money $amount = null,
        ?\DateTimeImmutable $occurredAt = null,
        array $metadata = [],
    ) {
        if ($eventReference === '') {
            throw new InvalidArgumentException('Webhook event references cannot be empty.');
        }

        parent::__construct(
            providerReference: $providerReference,
            status: $status,
            reference: $reference,
            amount: $amount,
            occurredAt: $occurredAt,
            metadata: $metadata,
            eventReference: $eventReference,
        );
    }
}
