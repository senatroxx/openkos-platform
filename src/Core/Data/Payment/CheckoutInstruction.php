<?php

namespace OpenKOS\Core\Data\Payment;

use InvalidArgumentException;

final readonly class CheckoutInstruction
{
    public function __construct(
        public string $key,
        public string $value,
        public ?string $label = null,
    ) {
        if ($key === '') {
            throw new InvalidArgumentException('Checkout instruction keys cannot be empty.');
        }
    }
}
