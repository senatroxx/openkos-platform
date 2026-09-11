<?php

namespace OpenKOS\Platform\Plugin;

use Composer\Semver\Semver;
use InvalidArgumentException;
use Throwable;

/**
 * Validates plugin manifests against their declared dependencies, then
 * returns them ordered so every plugin loads after its dependencies.
 */
class PluginLoader
{
    /**
     * @param  array<int, Plugin>  $plugins
     * @return array<int, Plugin>
     *
     * @throws InvalidArgumentException on duplicate id, missing dependency,
     *                                  or cycle
     */
    public function prepare(array $plugins, ?string $legacyCoreVersion = null): array
    {
        $byId = [];

        foreach ($plugins as $plugin) {
            $id = $plugin->manifest()->id;

            if (isset($byId[$id])) {
                throw new InvalidArgumentException("Duplicate plugin id [{$id}].");
            }

            $byId[$id] = $plugin;
        }

        foreach ($byId as $id => $plugin) {
            $manifest = $plugin->manifest();

            foreach ($manifest->dependencies as $dependency) {
                if (! isset($byId[$dependency])) {
                    throw new InvalidArgumentException(
                        "Plugin [{$id}] depends on missing plugin [{$dependency}].",
                    );
                }
            }
        }

        return $this->sortByDependencies($byId);
    }

    /**
     * Validates and orders plugins while isolating invalid plugins and their
     * dependants. This is intended for application boot, where one broken
     * plugin must not make the management surface unreachable.
     *
     * @param  array<int, Plugin>  $plugins
     * @return array{
     *     plugins: array<int, Plugin>,
     *     manifests: array<int, PluginManifest>,
     *     failures: array<int, array{plugin: Plugin, manifest: PluginManifest|null, phase: string, exception: Throwable}>
     * }
     */
    public function prepareRecoverably(array $plugins, ?string $legacyCoreVersion = null): array
    {
        $records = [];
        $byId = [];
        $failures = [];

        foreach ($plugins as $plugin) {
            $objectId = spl_object_id($plugin);

            try {
                $manifest = $plugin->manifest();
            } catch (Throwable $exception) {
                $records[$objectId] = ['plugin' => $plugin, 'manifest' => null, 'failed' => true];
                $failures[$objectId] = [
                    'plugin' => $plugin,
                    'manifest' => null,
                    'phase' => 'manifest',
                    'exception' => $exception,
                ];

                continue;
            }

            $records[$objectId] = ['plugin' => $plugin, 'manifest' => $manifest, 'failed' => false];
            $byId[$manifest->id][] = $objectId;
        }

        $fail = function (int $objectId, string $phase, Throwable $exception) use (&$records, &$failures): void {
            if ($records[$objectId]['failed']) {
                return;
            }

            $records[$objectId]['failed'] = true;
            $failures[$objectId] = [
                'plugin' => $records[$objectId]['plugin'],
                'manifest' => $records[$objectId]['manifest'],
                'phase' => $phase,
                'exception' => $exception,
            ];
        };

        foreach ($byId as $id => $objectIds) {
            if (count($objectIds) > 1) {
                foreach ($objectIds as $objectId) {
                    $fail(
                        $objectId,
                        'validation',
                        new InvalidArgumentException("Duplicate plugin id [{$id}]."),
                    );
                }
            }
        }

        foreach ($records as $objectId => $record) {
            if ($record['failed']) {
                continue;
            }

            foreach ($manifest->dependencies as $dependency) {
                if (! isset($byId[$dependency])) {
                    $fail(
                        $objectId,
                        'dependency',
                        new InvalidArgumentException(
                            "Plugin [{$manifest->id}] depends on missing plugin [{$dependency}].",
                        ),
                    );

                    break;
                }
            }
        }

        do {
            $changed = false;

            foreach ($records as $objectId => $record) {
                if ($record['failed']) {
                    continue;
                }

                foreach ($record['manifest']->dependencies as $dependency) {
                    foreach ($byId[$dependency] as $dependencyObjectId) {
                        if (! $records[$dependencyObjectId]['failed']) {
                            continue;
                        }

                        $fail(
                            $objectId,
                            'dependency',
                            new InvalidArgumentException(
                                "Plugin [{$record['manifest']->id}] depends on unavailable plugin [{$dependency}].",
                            ),
                        );
                        $changed = true;
                        break 2;
                    }
                }
            }
        } while ($changed);

        $active = [];
        foreach ($byId as $id => $objectIds) {
            if (count($objectIds) === 1 && ! $records[$objectIds[0]]['failed']) {
                $active[$id] = $objectIds[0];
            }
        }

        $colors = [];
        $stack = [];
        $markCycle = function (string $id) use (&$markCycle, &$colors, &$stack, &$active, &$records, $fail): void {
            $colors[$id] = 1;
            $stack[] = $id;
            $manifest = $records[$active[$id]]['manifest'];

            foreach ($manifest->dependencies as $dependency) {
                if (! isset($active[$dependency])) {
                    continue;
                }

                if (($colors[$dependency] ?? 0) === 0) {
                    $markCycle($dependency);
                } elseif (($colors[$dependency] ?? 0) === 1) {
                    $start = array_search($dependency, $stack, true);
                    foreach (array_slice($stack, $start === false ? 0 : $start) as $cycleId) {
                        $fail(
                            $active[$cycleId],
                            'dependency',
                            new InvalidArgumentException('Circular plugin dependency detected.'),
                        );
                    }
                }
            }

            array_pop($stack);
            $colors[$id] = 2;
        };

        foreach (array_keys($active) as $id) {
            if (($colors[$id] ?? 0) === 0) {
                $markCycle($id);
            }
        }

        do {
            $changed = false;

            foreach ($records as $objectId => $record) {
                if ($record['failed']) {
                    continue;
                }

                foreach ($record['manifest']->dependencies as $dependency) {
                    if (! isset($byId[$dependency]) || $records[$byId[$dependency][0]]['failed']) {
                        $fail(
                            $objectId,
                            'dependency',
                            new InvalidArgumentException(
                                "Plugin [{$record['manifest']->id}] depends on unavailable plugin [{$dependency}].",
                            ),
                        );
                        $changed = true;
                        break;
                    }
                }
            }
        } while ($changed);

        $ordered = [];
        $remaining = $active;
        foreach ($remaining as $id => $objectId) {
            if ($records[$objectId]['failed']) {
                unset($remaining[$id]);
            }
        }

        while ($remaining !== []) {
            $progress = false;

            foreach ($remaining as $id => $objectId) {
                $dependenciesReady = true;
                foreach ($records[$objectId]['manifest']->dependencies as $dependency) {
                    if (isset($remaining[$dependency])) {
                        $dependenciesReady = false;
                        break;
                    }
                }

                if (! $dependenciesReady) {
                    continue;
                }

                $ordered[] = $objectId;
                unset($remaining[$id]);
                $progress = true;
            }

            if (! $progress) {
                foreach ($remaining as $objectId) {
                    $fail($objectId, 'dependency', new InvalidArgumentException('Circular plugin dependency detected.'));
                }
                break;
            }
        }

        return [
            'plugins' => array_map(fn (int $objectId): Plugin => $records[$objectId]['plugin'], $ordered),
            'manifests' => array_combine(
                $ordered,
                array_map(fn (int $objectId): PluginManifest => $records[$objectId]['manifest'], $ordered),
            ) ?: [],
            'failures' => array_values($failures),
        ];
    }

    /** @deprecated Composer owns plugin platform compatibility. */
    public function satisfies(string $version, string $constraint): bool
    {
        return $constraint === '' || Semver::satisfies($version, $constraint);
    }

    /**
     * @param  array<string, Plugin>  $byId
     * @return array<int, Plugin>
     */
    private function sortByDependencies(array $byId): array
    {
        $sorted = [];
        $visiting = [];

        $visit = function (string $id) use (&$visit, &$sorted, &$visiting, $byId): void {
            if (isset($sorted[$id])) {
                return;
            }

            if (isset($visiting[$id])) {
                throw new InvalidArgumentException("Circular plugin dependency involving [{$id}].");
            }

            $visiting[$id] = true;

            foreach ($byId[$id]->manifest()->dependencies as $dependency) {
                $visit($dependency);
            }

            unset($visiting[$id]);
            $sorted[$id] = $byId[$id];
        };

        foreach (array_keys($byId) as $id) {
            $visit($id);
        }

        return array_values($sorted);
    }
}
