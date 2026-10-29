<?php

declare(strict_types=1);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    if (5 !== $argc) {
        fwrite(STDERR, "Usage: merge-asset-manifests.php AUTOLOAD SOURCE PRODUCTION OUTPUT\n");
        exit(64);
    }
    require $argv[1];
    \WordleHitToday\Publishing\ProductionAssetManifestMerger::mergeFiles($argv[2], $argv[3], $argv[4]);
}
