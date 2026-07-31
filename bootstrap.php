<?php

declare(strict_types=1);

/**
 * Convenience entry point: load Patchwork and every stub group.
 *
 * A plugin's tests/bootstrap.php normally needs only:
 *
 *     require __DIR__ . '/../vendor/autoload.php';
 *     require __DIR__ . '/../vendor/bleedingdeacons/wp-mocks/bootstrap.php';
 *
 * Order matters in one direction only: anything that defines WordPress
 * functions itself must come *after* this file, or Brain Monkey will not be
 * able to override those definitions. Requiring Composer's autoloader first is
 * fine — it defines no WordPress functions.
 *
 * To leave a group out — say a plugin that tests its own ACF-unavailable
 * branch — call the loader directly instead of requiring this file:
 *
 *     \BleedingDeacons\WpMocks\Bootstrap::load(['wordpress', 'sentinel']);
 */

use BleedingDeacons\WpMocks\Bootstrap;

Bootstrap::load();
