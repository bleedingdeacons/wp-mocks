<?php

declare(strict_types=1);

namespace BleedingDeacons\WpMocks;

use RuntimeException;

/**
 * Loads Patchwork and the stub groups, in the one order that works.
 *
 * Patchwork rewrites functions as their defining file is included, so it has
 * to be loaded *before* any file that defines something Brain Monkey might
 * later want to override. It ships no Composer autoload entry of its own —
 * brain/monkey lists it only under autoload-dev — so nothing loads it for you.
 * Without this, Brain Monkey's first attempt to override a stub in
 * stubs/wordpress.php dies with Patchwork\Exceptions\DefinedTooEarly.
 *
 * That single ordering constraint is the whole reason this class exists rather
 * than a plugin simply requiring the stub files directly.
 */
final class Bootstrap
{
    public const GROUPS = ['wordpress', 'acf', 'sentinel'];

    /** @var array<int, string> */
    private static array $loaded = [];

    /**
     * Load Patchwork and the named stub groups.
     *
     * @param array<int, string> $groups Defaults to every group. Pass a subset
     *                                   to leave an API undefined — a plugin
     *                                   covering its own ACF-unavailable branch
     *                                   should omit 'acf'.
     */
    public static function load(array $groups = self::GROUPS): void
    {
        self::loadPatchwork();

        foreach ($groups as $group) {
            self::loadStubGroup($group);
        }
    }

    /**
     * Require Patchwork, unless something already has.
     *
     * Safe to call more than once, and safe to call after a plugin has loaded
     * it by hand — which some may prefer to do at the very top of their own
     * bootstrap, before anything else at all.
     */
    public static function loadPatchwork(): void
    {
        if (function_exists('Patchwork\redefine')) {
            return;
        }

        foreach (self::patchworkCandidates() as $candidate) {
            if (is_file($candidate)) {
                require_once $candidate;

                return;
            }
        }

        throw new RuntimeException(
            'wp-mocks: could not locate antecedent/patchwork. It is a hard dependency of '
            . 'brain/monkey, so `composer install` should have provided it. Looked in: '
            . implode(', ', self::patchworkCandidates())
        );
    }

    /** Whether a stub group has been loaded in this process. */
    public static function isLoaded(string $group): bool
    {
        return in_array($group, self::$loaded, true);
    }

    private static function loadStubGroup(string $group): void
    {
        if (self::isLoaded($group)) {
            return;
        }

        if (!in_array($group, self::GROUPS, true)) {
            throw new RuntimeException(sprintf(
                'wp-mocks: unknown stub group "%s". Known groups: %s.',
                $group,
                implode(', ', self::GROUPS)
            ));
        }

        require_once dirname(__DIR__) . '/stubs/' . $group . '.php';

        self::$loaded[] = $group;
    }

    /**
     * Where Patchwork might be, depending on whether this package is the root
     * project or installed into a plugin's vendor/ directory.
     *
     * @return array<int, string>
     */
    private static function patchworkCandidates(): array
    {
        $packageRoot = dirname(__DIR__);

        return [
            // Installed as a dependency: vendor/bleedingdeacons/wp-mocks/src
            dirname($packageRoot, 2) . '/antecedent/patchwork/Patchwork.php',
            // This package as the root project, running its own suite.
            $packageRoot . '/vendor/antecedent/patchwork/Patchwork.php',
        ];
    }
}
