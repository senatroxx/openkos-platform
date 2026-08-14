<?php

namespace OpenKOS\Core\Data\Payment;

use InvalidArgumentException;

final readonly class PaymentWebhookRequest
{
    /**
     * @param  array<string, string|array<int, string>>  $headers
     */
    public function __construct(
        public string $rawBody,
        public array $headers = [],
    ) {
        foreach ($headers as $name => $value) {
            if (! is_string($name) || (! is_string($value) && ! self::isStringList($value))) {
                throw new InvalidArgumentException('Webhook headers must contain strings or string lists.');
            }
        }
    }

    private static function isStringList(mixed $value): bool
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (! is_string($item)) {
                return false;
            }
        }

        return true;
    }
}
