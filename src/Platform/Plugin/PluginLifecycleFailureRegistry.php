<?php

namespace OpenKOS\Platform\Plugin;

use Throwable;

final class PluginLifecycleFailureRegistry
{
    /** @var array<int, array{id: string|null, version: string|null, entry_class: string, phase: string, exception: string}> */
    private array $failures = [];

    public function clear(): void
    {
        $this->failures = [];
    }

    public function record(string $entryClass, ?PluginManifest $manifest, string $phase, Throwable $exception): void
    {
        $this->failures[] = [
            'id' => $manifest?->id,
            'version' => $manifest?->version,
            'entry_class' => $entryClass,
            'phase' => $phase,
            'exception' => $exception::class,
        ];
    }

    /** @return array<int, array{id: string|null, version: string|null, entry_class: string, phase: string, exception: string}> */
    public function failures(): array
    {
        return $this->failures;
    }

    /** @return array{id: string|null, version: string|null, entry_class: string, phase: string, exception: string}|null */
    public function forPlugin(string $id, ?string $entryClass = null): ?array
    {
        foreach ($this->failures as $failure) {
            if ($failure['id'] === $id || ($entryClass !== null && strcasecmp($failure['entry_class'], $entryClass) === 0)) {
                return $failure;
            }
        }

        return null;
    }
}
