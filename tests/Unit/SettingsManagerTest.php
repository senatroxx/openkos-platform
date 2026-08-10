<?php

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Events\Dispatcher;
use OpenKOS\Core\Contracts\SettingsStore;
use OpenKOS\Core\Events\SettingsUpdated;
use OpenKOS\Platform\Settings\SettingDefinition;
use OpenKOS\Platform\Settings\SettingsManager;
use OpenKOS\Platform\Settings\SettingsRegistry;
use Tests\TestCase;

uses(TestCase::class);

it('uses the host-provided settings store and emits a platform event', function () {
    $store = new class implements SettingsStore
    {
        public array $values = [];

        public function get(string $key): mixed
        {
            return $this->values[$key] ?? null;
        }

        public function set(string $key, mixed $value, string $type): void
        {
            $this->values[$key] = $value;
        }
    };
    $events = new Dispatcher;
    $captured = null;
    $events->listen(SettingsUpdated::class, function (SettingsUpdated $event) use (&$captured): void {
        $captured = $event;
    });
    $registry = new SettingsRegistry;
    $registry->registerSetting(new SettingDefinition('timezone', 'Timezone', page: 'general'));

    $manager = new SettingsManager(
        $registry,
        $store,
        $events,
        app(ValidationFactory::class),
    );

    $manager->set('timezone', 'Asia/Jakarta');

    expect($store->values)->toBe(['timezone' => 'Asia/Jakarta'])
        ->and($captured)->toBeInstanceOf(SettingsUpdated::class)
        ->and($captured->group)->toBe('general')
        ->and($captured->keys)->toBe(['timezone']);
});
