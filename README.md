# OpenKOS Platform

The standalone Laravel platform and plugin SDK for OpenKOS.

Plugins extend `OpenKOS\Platform\Plugin\Plugin` and register through the
typed `OpenKOSManager` registries. Host applications can keep explicit plugin
registration in `config/platform.php` or provide a `PluginDiscovery`
implementation for external plugin packages.

The package contains no OpenKOS application models, events, persistence,
frontend assets, or Spatie dependencies. Settings persistence is supplied by
the host through `OpenKOS\Core\Contracts\SettingsStore`.

The package does not scan Composer metadata itself. Host applications own
Composer discovery and provide discovered plugin class names through
`OpenKOS\Core\Contracts\PluginDiscovery`.

## Installation

```shell
composer require openkos/platform
```

Publish the optional configuration with:

```shell
php artisan vendor:publish --tag=openkos-platform-config
```
