<?php

namespace OpenKOS\Core\Data\Payment;

use InvalidArgumentException;

final readonly class PaymentStatusLookupRequest
{
    /**
     * @param  array<string, bool|int|string|null>  $metadata
     */
    public function __construct(
        public string $providerReference,
        public ?string $reference = null,
        public array $metadata = [],
    ) {
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
