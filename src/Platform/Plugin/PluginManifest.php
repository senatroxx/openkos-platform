<?php

namespace OpenKOS\Platform\Plugin;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Metadata every plugin declares. Drives boot ordering (dependencies) and
 * discovery/UI (id, name, version).
 */
final readonly class PluginManifest implements Arrayable
{
    /**
     * @param  string  $id  unique, vendor-namespaced, e.g. 'openkos/whatsapp'
     * @param  string  $coreVersion  deprecated legacy compatibility metadata; Composer owns platform compatibility
     * @param  array<int, string>  $dependencies  ids of plugins that must load first
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $version,
        public string $description = '',
        public string $coreVersion = '*',
        public array $dependencies = [],
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'version' => $this->version,
            'description' => $this->description,
            'dependencies' => $this->dependencies,
        ];
    }
}
