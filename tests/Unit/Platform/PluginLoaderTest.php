<?php

use OpenKOS\Platform\OpenKOSManager;
use OpenKOS\Platform\Plugin\Plugin;
use OpenKOS\Platform\Plugin\PluginLoader;
use OpenKOS\Platform\Plugin\PluginManifest;

function loaderPlugin(string $id, array $dependencies = [], string $coreVersion = '*'): Plugin
{
    return new class($id, $dependencies, $coreVersion) extends Plugin
    {
        public function __construct(
            private string $id,
            private array $dependencies,
            private string $coreVersion,
        ) {}

        public function manifest(): PluginManifest
        {
            return new PluginManifest(
                id: $this->id,
                name: $this->id,
                version: '1.0.0',
                coreVersion: $this->coreVersion,
                dependencies: $this->dependencies,
            );
        }

        public function register(OpenKOSManager $platform): void {}
    };
}

function loaderIds(array $plugins): array
{
    return array_map(fn (Plugin $p) => $p->manifest()->id, $plugins);
}

describe('legacy compatibility metadata', function () {
    it('does not gate loading on the deprecated coreVersion field', function () {
        $prepared = (new PluginLoader)->prepare([loaderPlugin('a', coreVersion: '^9.0')], '0.1.0');

        expect(loaderIds($prepared))->toBe(['a']);
    });

    it('retains the Composer constraint helper for legacy callers', function () {
        $loader = new PluginLoader;

        expect($loader->satisfies('0.1.0', '^0.1'))->toBeTrue()
            ->and($loader->satisfies('0.2.0', '^0.1'))->toBeFalse();
    });
});

describe('dependency resolution', function () {
    it('orders plugins after their dependencies', function () {
        $ordered = (new PluginLoader)->prepare([
            loaderPlugin('app', ['core']),
            loaderPlugin('core'),
        ], '0.1.0');

        expect(loaderIds($ordered))->toBe(['core', 'app']);
    });

    it('throws on a missing dependency', function () {
        (new PluginLoader)->prepare([loaderPlugin('app', ['missing'])], '0.1.0');
    })->throws(InvalidArgumentException::class, 'Plugin [app] depends on missing plugin [missing].');

    it('throws on a circular dependency', function () {
        (new PluginLoader)->prepare([
            loaderPlugin('a', ['b']),
            loaderPlugin('b', ['a']),
        ], '0.1.0');
    })->throws(InvalidArgumentException::class, 'Circular plugin dependency involving [a].');

    it('throws on a duplicate id', function () {
        (new PluginLoader)->prepare([loaderPlugin('a'), loaderPlugin('a')], '0.1.0');
    })->throws(InvalidArgumentException::class, 'Duplicate plugin id [a].');

    it('keeps unrelated plugins loadable when a dependency is invalid', function (): void {
        $result = (new PluginLoader)->prepareRecoverably([
            loaderPlugin('app', ['broken']),
            loaderPlugin('broken', ['missing']),
            loaderPlugin('healthy'),
        ], '0.1.0');

        expect(loaderIds($result['plugins']))->toBe(['healthy'])
            ->and($result['failures'])->toHaveCount(2);
    });
});
