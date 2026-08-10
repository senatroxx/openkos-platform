<?php

namespace OpenKOS\Core\Contracts;

interface SettingsStore
{
    public function get(string $key): mixed;

    public function set(string $key, mixed $value, string $type): void;
}
