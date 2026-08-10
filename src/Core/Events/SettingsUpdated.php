<?php

namespace OpenKOS\Core\Events;

final readonly class SettingsUpdated
{
    public function __construct(
        public string $group,
        public array $keys,
        public ?int $actorId = null,
    ) {}
}
