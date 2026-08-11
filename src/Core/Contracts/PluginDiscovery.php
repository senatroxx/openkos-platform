<?php

namespace OpenKOS\Core\Contracts;

use OpenKOS\Platform\Plugin\Plugin;

/**
 * Host-provided seam for discovering external plugin classes.
 */
interface PluginDiscovery
{
    /**
     * @return array<class-string<Plugin>>
     */
    public function discover(): array;
}
