<?php

namespace OpenKOS\Core\Data\Payment;

use InvalidArgumentException;

final readonly class PaymentRequest
{
    /**
     * @param  array<string, bool|int|string|null>  $metadata
     */
    public function __construct(
        public string $reference,
        public Money $amount,
        public ?string $description = null,
        public array $metadata = [],
    ) {
        if ($reference === '') {
            throw new InvalidArgumentException('Payment references cannot be empty.');
        }

        if ($amount->minorUnits === 0) {
            throw new InvalidArgumentException('Payment amounts must be greater than zero.');
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
