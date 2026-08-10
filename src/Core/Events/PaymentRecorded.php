<?php

namespace OpenKOS\Core\Events;

final readonly class PaymentRecorded
{
    public function __construct(
        public int|string $paymentId,
        public ?int $actorId = null,
    ) {}
}
