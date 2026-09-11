<?php

namespace OpenKOS\Platform;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use OpenKOS\Core\Contracts\PluginDiscovery;
use OpenKOS\Platform\Dashboard\DashboardRegistry;
use OpenKOS\Platform\Navigation\NavigationRegistry;
use OpenKOS\Platform\Notification\NotificationRegistry;
use OpenKOS\Platform\Payment\PaymentRegistry;
use OpenKOS\Platform\Permission\PermissionRegistry;
use OpenKOS\Platform\Plugin\Plugin;
use OpenKOS\Platform\Plugin\PluginLifecycleFailureRegistry;
use OpenKOS\Platform\Plugin\PluginLoader;
use OpenKOS\Platform\Plugin\PluginManifest;
use OpenKOS\Platform\Settings\SettingsManager;
use OpenKOS\Platform\Settings\SettingsRegistry;
use OpenKOS\Platform\Workspace\WorkspaceRegistry;
use ReflectionClass;
use Throwable;

class PlatformServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/platform.php', 'platform');

        $this->app->singleton(DashboardRegistry::class);
        $this->app->singleton(NavigationRegistry::class);
        $this->app->singleton(WorkspaceRegistry::class);
        $this->app->singleton(SettingsRegistry::class);
        $this->app->singleton(SettingsManager::class);
        $this->app->singleton(NotificationRegistry::class);
        $this->app->singleton(PaymentRegistry::class);
        $this->app->singleton(PermissionRegistry::class);
        $this->app->singleton(OpenKOSManager::class);
        $this->app->singleton(PluginLifecycleFailureRegistry::class);
    }

    public function boot(): void
    {
        $failureRegistry = $this->app->make(PluginLifecycleFailureRegistry::class);
        $failureRegistry->clear();

        $this->publishes([
            __DIR__.'/../../config/platform.php' => config_path('platform.php'),
        ], 'openkos-platform-config');

        $manager = $this->app->make(OpenKOSManager::class);

        $pluginClasses = config('platform.plugins', []);

        if (config('platform.discovery.enabled', false)) {
            if (! $this->app->bound(PluginDiscovery::class)) {
                throw new InvalidArgumentException(
                    'Composer plugin discovery is enabled, but no PluginDiscovery implementation is bound.',
                );
            }

            $pluginClasses = array_merge(
                $pluginClasses,
                $this->app->make(PluginDiscovery::class)->discover(),
            );
        }

        /** @var array<int, Plugin> $plugins */
        $plugins = [];
        foreach (array_values(array_unique($pluginClasses)) as $class) {
            try {
                $plugins[] = $this->resolvePlugin($class);
            } catch (Throwable $exception) {
                $this->recordPluginFailure($class, null, 'resolve', $exception);
            }
        }

        $prepared = (new PluginLoader)->prepareRecoverably($plugins);
        $plugins = $prepared['plugins'];
        $manifests = $prepared['manifests'];
        $failedIds = [];

        foreach ($prepared['failures'] as $failure) {
            $manifest = $failure['manifest'];
            if ($manifest !== null) {
                $failedIds[$manifest->id] = true;
            }

            $this->recordPluginFailure(
                get_class($failure['plugin']),
                $manifest,
                $failure['phase'],
                $failure['exception'],
            );
        }

        // Load resources immediately before register so failed dependencies
        // cannot execute dependant plugin code.
        foreach ($plugins as $plugin) {
            $manifest = $manifests[spl_object_id($plugin)];
            if ($this->hasFailedDependency($manifest, $failedIds)) {
                $this->skipPlugin($plugin, $manifest, 'dependency', $failedIds);

                continue;
            }

            try {
                $this->loadPluginResources($plugin);
            } catch (Throwable $exception) {
                $this->failPlugin($plugin, $manifest, 'resources', $exception, $failedIds);

                continue;
            }

            try {
                $plugin->register($manager);
            } catch (Throwable $exception) {
                $this->failPlugin($plugin, $manifest, 'register', $exception, $failedIds);
            }
        }

        foreach ($plugins as $plugin) {
            $manifest = $manifests[spl_object_id($plugin)];
            if (
                isset($failedIds[$manifest->id])
                || $this->hasFailedDependency($manifest, $failedIds)
            ) {
                if (! isset($failedIds[$manifest->id])) {
                    $this->skipPlugin($plugin, $manifest, 'dependency', $failedIds);
                }

                continue;
            }

            try {
                $plugin->boot($manager);
            } catch (Throwable $exception) {
                $this->failPlugin($plugin, $manifest, 'boot', $exception, $failedIds);
            }
        }

        foreach ($plugins as $plugin) {
            $manifest = $manifests[spl_object_id($plugin)];
            if (
                isset($failedIds[$manifest->id])
                || $this->hasFailedDependency($manifest, $failedIds)
            ) {
                continue;
            }

            try {
                $this->registerListeners($plugin);
            } catch (Throwable $exception) {
                $this->failPlugin($plugin, $manifest, 'listeners', $exception, $failedIds);
            }
        }
    }

    /** @param array<string, bool> $failedIds */
    private function hasFailedDependency(PluginManifest $manifest, array $failedIds): bool
    {
        foreach ($manifest->dependencies as $dependency) {
            if (isset($failedIds[$dependency])) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, bool> $failedIds */
    private function failPlugin(
        Plugin $plugin,
        PluginManifest $manifest,
        string $phase,
        Throwable $exception,
        array &$failedIds,
    ): void {
        $failedIds[$manifest->id] = true;
        $this->recordPluginFailure(get_class($plugin), $manifest, $phase, $exception);
    }

    /** @param array<string, bool> $failedIds */
    private function skipPlugin(
        Plugin $plugin,
        PluginManifest $manifest,
        string $phase,
        array &$failedIds,
    ): void {
        $failedIds[$manifest->id] = true;
        $this->recordPluginFailure(
            get_class($plugin),
            $manifest,
            $phase,
            new InvalidArgumentException('A plugin dependency failed.'),
        );
    }

    private function recordPluginFailure(
        string $entryClass,
        ?PluginManifest $manifest,
        string $phase,
        Throwable $exception,
    ): void {
        $this->app->make(PluginLifecycleFailureRegistry::class)->record(
            $entryClass,
            $manifest,
            $phase,
            $exception,
        );

        Log::error('Plugin lifecycle failed.', [
            'plugin_id' => $manifest?->id ?? $entryClass,
            'plugin_version' => $manifest?->version,
            'phase' => $phase,
            'exception' => get_class($exception),
        ]);
    }

    private function resolvePlugin(string $class): Plugin
    {
        if (! class_exists($class)) {
            throw new InvalidArgumentException("Plugin class [{$class}] does not exist.");
        }

        if (! is_a($class, Plugin::class, true)) {
            throw new InvalidArgumentException("Plugin class [{$class}] must extend ".Plugin::class.'.');
        }

        return $this->app->make($class);
    }

    /**
     * Convention-over-configuration: load `routes/web.php` and
     * `database/migrations/` from the plugin's own directory if present.
     */
    private function loadPluginResources(Plugin $plugin): void
    {
        $dir = dirname((new ReflectionClass($plugin))->getFileName());

        if (is_file($routes = $dir.'/routes/web.php')) {
            $this->loadRoutesFrom($routes);
        }

        if (is_dir($migrations = $dir.'/database/migrations')) {
            $this->loadMigrationsFrom($migrations);
        }
    }

    private function registerListeners(Plugin $plugin): void
    {
        foreach ($plugin->listens() as $event => $listeners) {
            foreach ((array) $listeners as $listener) {
                Event::listen($event, $listener);
            }
        }
    }
}
