<?php

use Composer\InstalledVersions;

return [
    'version' => InstalledVersions::getPrettyVersion('openkos/platform') ?: '0.2.0',
    'plugins' => [],
    'discovery' => [
        // Host applications must explicitly opt into external plugin discovery.
        'enabled' => false,
    ],
];
