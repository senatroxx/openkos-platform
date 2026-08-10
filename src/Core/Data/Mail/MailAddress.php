<?php

namespace OpenKOS\Core\Data\Mail;

use InvalidArgumentException;

final readonly class MailAddress
{
    public function __construct(
        public string $address,
        public ?string $name = null,
    ) {
        if (! filter_var($address, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid mail address [{$address}].");
        }
    }
}
