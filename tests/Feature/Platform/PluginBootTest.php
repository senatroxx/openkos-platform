<?php

use Illuminate\Support\Facades\Log;
use OpenKOS\Core\Contracts\PluginDiscovery;
use OpenKOS\Platform\Facades\OpenKOS;
use OpenKOS\Platform\Navigation\NavigationItem;
use OpenKOS\Platform\OpenKOSManager;
use OpenKOS\Platform\PlatformServiceProvider;
use OpenKOS\Platform\Plugin\Plugin;
use OpenKOS\Platform\Plugin\PluginLifecycleFailureRegistry;
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

class RegisterFailurePlugin extends Plugin
{
    public static int $bootCalls = 0;

    public function manifest(): PluginManifest
    {
        return new PluginManifest(id: 'test/register-failure', name: 'Register failure', version: '1.2.3');
    }

    public function register(OpenKOSManager $platform): void
    {
        throw new RuntimeException('register failed');
    }

    public function boot(OpenKOSManager $platform): void
    {
        self::$bootCalls++;
    }
}

class BootFailurePlugin extends Plugin
{
    public function manifest(): PluginManifest
    {
        return new PluginManifest(id: 'test/boot-failure', name: 'Boot failure', version: '2.3.4');
    }

    public function register(OpenKOSManager $platform): void {}

    public function boot(OpenKOSManager $platform): void
    {
        throw new RuntimeException('boot failed');
    }
}

class ConstructorFailurePlugin extends Plugin
{
    public function __construct()
    {
        throw new RuntimeException('constructor failed');
    }

    public function manifest(): PluginManifest
    {
        return new PluginManifest(id: 'test/constructor-failure', name: 'Constructor failure', version: '1.0.0');
    }

    public function register(OpenKOSManager $platform): void {}
}

class ManifestFailurePlugin extends Plugin
{
    public function manifest(): PluginManifest
    {
        throw new RuntimeException('manifest failed');
    }

    public function register(OpenKOSManager $platform): void {}
}

class DependentOnRegisterFailurePlugin extends Plugin
{
    public static array $calls = [];

    public function manifest(): PluginManifest
    {
        return new PluginManifest(
            id: 'test/register-dependant',
            name: 'Register dependant',
            version: '1.0.0',
            dependencies: ['test/register-failure'],
        );
    }

    public function register(OpenKOSManager $platform): void
    {
        self::$calls[] = 'register';
    }

    public function boot(OpenKOSManager $platform): void
    {
        self::$calls[] = 'boot';
    }
}

class DependentOnBootFailurePlugin extends Plugin
{
    public static array $calls = [];

    public function manifest(): PluginManifest
    {
        return new PluginManifest(
            id: 'test/boot-dependant',
            name: 'Boot dependant',
            version: '1.0.0',
            dependencies: ['test/boot-failure'],
        );
    }

    public function register(OpenKOSManager $platform): void
    {
        self::$calls[] = 'register';
    }

    public function boot(OpenKOSManager $platform): void
    {
        self::$calls[] = 'boot';
    }
}

class PluginAfterFailure extends Plugin
{
    public static array $calls = [];

    public function manifest(): PluginManifest
    {
        return new PluginManifest(id: 'test/after-failure', name: 'After failure', version: '3.4.5');
    }

    public function register(OpenKOSManager $platform): void
    {
        self::$calls[] = 'register';
    }

    public function boot(OpenKOSManager $platform): void
    {
        self::$calls[] = 'boot';
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

it('skips an invalid plugin class without aborting application boot', function () {
    config(['platform.plugins' => [stdClass::class]]);

    (new PlatformServiceProvider(app()))->boot();

    expect(app(PluginLifecycleFailureRegistry::class)->failures())->toMatchArray([
        [
            'id' => null,
            'version' => null,
            'entry_class' => stdClass::class,
            'phase' => 'resolve',
            'exception' => InvalidArgumentException::class,
        ],
    ]);
});

it('requires a discovery binding when discovery is enabled', function () {
    config(['platform.discovery.enabled' => true]);

    (new PlatformServiceProvider(app()))->boot();
})->throws(InvalidArgumentException::class, 'no PluginDiscovery implementation is bound');

it('contains register failures without booting that plugin or blocking later plugins', function () {
    RegisterFailurePlugin::$bootCalls = 0;
    PluginAfterFailure::$calls = [];
    config(['platform.plugins' => [RegisterFailurePlugin::class, PluginAfterFailure::class]]);

    (new PlatformServiceProvider(app()))->boot();

    expect(RegisterFailurePlugin::$bootCalls)->toBe(0)
        ->and(PluginAfterFailure::$calls)->toBe(['register', 'boot']);
});

it('suppresses dependants of a plugin that fails during register', function (): void {
    DependentOnRegisterFailurePlugin::$calls = [];
    config(['platform.plugins' => [RegisterFailurePlugin::class, DependentOnRegisterFailurePlugin::class]]);

    (new PlatformServiceProvider(app()))->boot();

    expect(DependentOnRegisterFailurePlugin::$calls)->toBe([]);
});

it('contains boot failures without blocking later plugins', function () {
    PluginAfterFailure::$calls = [];
    config(['platform.plugins' => [BootFailurePlugin::class, PluginAfterFailure::class]]);

    (new PlatformServiceProvider(app()))->boot();

    expect(PluginAfterFailure::$calls)->toBe(['register', 'boot']);
});

it('suppresses dependant boot after a dependency fails during boot', function (): void {
    DependentOnBootFailurePlugin::$calls = [];
    config(['platform.plugins' => [BootFailurePlugin::class, DependentOnBootFailurePlugin::class]]);

    (new PlatformServiceProvider(app()))->boot();

    expect(DependentOnBootFailurePlugin::$calls)->toBe(['register']);
});

it('isolates constructor and manifest failures from healthy plugins', function (): void {
    DiscoveredPlugin::$registerCalls = 0;
    config(['platform.plugins' => [ConstructorFailurePlugin::class, ManifestFailurePlugin::class, DiscoveredPlugin::class]]);

    (new PlatformServiceProvider(app()))->boot();

    expect(DiscoveredPlugin::$registerCalls)->toBe(1)
        ->and(app(PluginLifecycleFailureRegistry::class)->failures())->toHaveCount(2);
});

it('logs lifecycle failures with plugin identity without exception details', function () {
    Log::spy();
    config(['platform.plugins' => [RegisterFailurePlugin::class]]);

    (new PlatformServiceProvider(app()))->boot();

    Log::shouldHaveReceived('error')
        ->once()
        ->with('Plugin lifecycle failed.', Mockery::on(function (array $context): bool {
            return $context['plugin_id'] === 'test/register-failure'
                && $context['plugin_version'] === '1.2.3'
                && $context['phase'] === 'register'
                && $context['exception'] === RuntimeException::class
                && ! array_key_exists('message', $context);
        }));
});
