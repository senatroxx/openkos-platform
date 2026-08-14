<?php

namespace OpenKOS\Core\Data\Payment;

use InvalidArgumentException;

final readonly class Money
{
    public readonly int $minorUnits;

    public readonly string $currency;

    public function __construct(int $minorUnits, string $currency)
    {
        $currency = strtoupper($currency);

        if ($minorUnits < 0) {
            throw new InvalidArgumentException('Money minor units cannot be negative.');
        }

        if (! preg_match('/\A[A-Z]{3}\z/D', $currency)) {
            throw new InvalidArgumentException('Currency must be a three-letter ISO 4217 code.');
        }

        $this->minorUnits = $minorUnits;
        $this->currency = $currency;
    }
}
