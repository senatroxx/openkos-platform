# OpenKOS Platform

The standalone Laravel platform and plugin SDK for OpenKOS.

Plugins extend `OpenKOS\Platform\Plugin\Plugin` and register through the
typed `OpenKOSManager` registries. Host applications keep explicit plugin
registration in `config/platform.php`.

The package contains no OpenKOS application models, events, persistence,
frontend assets, or Spatie dependencies. Settings persistence is supplied by
the host through `OpenKOS\Core\Contracts\SettingsStore`.

## Installation

```shell
composer require openkos/platform
```

Publish the optional configuration with:

```shell
php artisan vendor:publish --tag=openkos-platform-config
```
