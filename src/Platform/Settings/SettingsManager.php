<?php

namespace OpenKOS\Platform\Settings;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Events\Dispatcher;
use InvalidArgumentException;
use OpenKOS\Core\Contracts\SettingsStore;
use OpenKOS\Core\Events\SettingsUpdated;

class SettingsManager
{
    public function __construct(
        private SettingsRegistry $registry,
        private SettingsStore $store,
        private Dispatcher $events,
        private ValidationFactory $validator,
    ) {}

    public function get(string $key): mixed
    {
        $definition = $this->findDefinition($key);

        return $this->store->get($key) ?? $definition->default;
    }

    public function set(string $key, mixed $value, ?Authenticatable $actor = null): void
    {
        $definition = $this->findDefinition($key);

        if ($definition->rules) {
            $safeKey = str_replace('.', '_', $key);
            $validator = $this->validator->make(
                [$safeKey => $value],
                [$safeKey => $definition->rules],
            );

            if ($validator->fails()) {
                throw new InvalidArgumentException($validator->errors()->first($key));
            }
        }

        $this->store->set($key, $value, $definition->type);

        $this->events->dispatch(new SettingsUpdated(
            group: $definition->page ?? 'general',
            keys: [$key],
            actorId: $actor?->getKey(),
        ));
    }

    public function all(?string $page = null): array
    {
        $definitions = $this->registry->definitions($page);
        $values = [];

        foreach ($definitions as $definition) {
            $values[$definition->key] = $this->get($definition->key);
        }

        return $values;
    }

    public function definitions(?string $page = null): array
    {
        return $this->registry->definitions($page);
    }

    private function findDefinition(string $key): SettingDefinition
    {
        $definitions = $this->registry->definitions();
        if (! isset($definitions[$key])) {
            throw new InvalidArgumentException("Setting [{$key}] is not registered.");
        }

        return $definitions[$key];
    }
}
