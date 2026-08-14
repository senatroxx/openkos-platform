<?php

namespace OpenKOS\Core\Data\Payment;

use DateTimeImmutable;
use InvalidArgumentException;
use OpenKOS\Core\Enums\PaymentStatus;

final readonly class PaymentWebhookResult
{
    /**
     * @param  array<string, bool|int|string|null>  $metadata
     */
    public function __construct(
        public string $eventReference,
        public string $providerReference,
        public PaymentStatus $status,
        public ?string $reference = null,
        public ?Money $amount = null,
        public ?DateTimeImmutable $occurredAt = null,
        public array $metadata = [],
    ) {
        if ($eventReference === '') {
            throw new InvalidArgumentException('Webhook event references cannot be empty.');
        }

        if ($providerReference === '') {
            throw new InvalidArgumentException('Provider payment references cannot be empty.');
        }

        if ($reference === '') {
            throw new InvalidArgumentException('Payment references cannot be empty.');
        }

        self::validateMetadata($metadata);
    }

    /**
     * @param  array<string, bool|int|string|null>  $metadata
     */
    private static function validateMetadata(array $metadata): void
    {
        foreach ($metadata as $key => $value) {
            if (! is_string($key) || (! is_bool($value) && ! is_int($value) && ! is_string($value) && $value !== null)) {
                throw new InvalidArgumentException('Payment metadata must contain only scalar values.');
            }
        }
    }
}
