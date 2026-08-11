<?php

use OpenKOS\Core\Contracts\PluginDiscovery;
use OpenKOS\Platform\Facades\OpenKOS;
use OpenKOS\Platform\Navigation\NavigationItem;
use OpenKOS\Platform\OpenKOSManager;
use OpenKOS\Platform\PlatformServiceProvider;
use OpenKOS\Platform\Plugin\Plugin;
use OpenKOS\Platform\Plugin\PluginManifest;

class OrderProbePluginA extends Plugin
{
    public static array $calls = [];

    public function manifest(): PluginManifest
    {
        return new PluginManifest(id: 'test/a', name: 'A', version: '1.0.0');
    }

    public function register(OpenKOSManager $platform): void
    {
        static::$calls[] = 'register:a';
    }

    public function boot(OpenKOSManager $platform): void
    {
        static::$calls[] = 'boot:a';
    }
}

class OrderProbePluginB extends Plugin
{
    public function manifest(): PluginManifest
    {
        return new PluginManifest(id: 'test/b', name: 'B', version: '1.0.0');
    }

    public function register(OpenKOSManager $platform): void
    {
        OrderProbePluginA::$calls[] = 'register:b';
    }

    public function boot(OpenKOSManager $platform): void
    {
        OrderProbePluginA::$calls[] = 'boot:b';
    }
}

class FixturePlugin extends Plugin
{
    public function manifest(): PluginManifest
    {
        return new PluginManifest(id: 'test/fixture', name: 'Fixture', version: '1.0.0');
    }

    public function register(OpenKOSManager $platform): void
    {
        $platform->navigation()->registerItem(
            new NavigationItem('Fixture'),
        );
    }
}

class DiscoveredPlugin extends Plugin
{
    public static int $registerCalls = 0;

    public function manifest(): PluginManifest
    {
        return new PluginManifest(id: 'test/discovered', name: 'Discovered', version: '1.0.0');
    }

    public function register(OpenKOSManager $platform): void
    {
        self::$registerCalls++;
    }
}

class FixturePluginDiscovery implements PluginDiscovery
{
    public function discover(): array
    {
        return [DiscoveredPlugin::class];
    }
}

it('applies a plugins registrations across every registry on boot', function () {
    config(['platform.plugins' => [FixturePlugin::class]]);
    (new PlatformServiceProvider(app()))->boot();

    $navTitles = array_map(fn ($item) => $item->title, OpenKOS::navigation()->items('main'));

    expect($navTitles)->toContain('Fixture');
});

it('runs every register() before any boot()', function () {
    OrderProbePluginA::$calls = [];
    config(['platform.plugins' => [OrderProbePluginA::class, OrderProbePluginB::class]]);

    (new PlatformServiceProvider(app()))->boot();

    expect(OrderProbePluginA::$calls)->toBe(['register:a', 'register:b', 'boot:a', 'boot:b']);
});

it('merges discovered plugins with explicit plugins and de-duplicates classes', function () {
    DiscoveredPlugin::$registerCalls = 0;
    config([
        'platform.plugins' => [DiscoveredPlugin::class],
        'platform.discovery.enabled' => true,
    ]);
    app()->singleton(PluginDiscovery::class, fn () => new FixturePluginDiscovery);

    (new PlatformServiceProvider(app()))->boot();

    expect(DiscoveredPlugin::$registerCalls)->toBe(1);
});

it('rejects an invalid plugin class before the lifecycle starts', function () {
    config(['platform.plugins' => [stdClass::class]]);

    (new PlatformServiceProvider(app()))->boot();
})->throws(InvalidArgumentException::class, 'must extend OpenKOS\\Platform\\Plugin\\Plugin');

it('requires a discovery binding when discovery is enabled', function () {
    config(['platform.discovery.enabled' => true]);

    (new PlatformServiceProvider(app()))->boot();
})->throws(InvalidArgumentException::class, 'no PluginDiscovery implementation is bound');
