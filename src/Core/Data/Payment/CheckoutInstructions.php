<?php

namespace OpenKOS\Core\Data\Payment;

use InvalidArgumentException;

final readonly class CheckoutInstructions
{
    /**
     * @param  array<int, CheckoutInstruction>  $entries
     */
    public function __construct(
        public ?string $url = null,
        public array $entries = [],
    ) {
        foreach ($entries as $entry) {
            if (! $entry instanceof CheckoutInstruction) {
                throw new InvalidArgumentException('Checkout instruction entries must be typed values.');
            }
        }
    }
}
