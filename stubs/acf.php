<?php

declare(strict_types=1);

/**
 * Advanced Custom Fields stand-ins, backed by
 * {@see BleedingDeacons\WpMocks\WpState::$fields}.
 *
 * ACF is a hard runtime dependency of several plugins in this suite and by far
 * the most-stubbed API in their tests — get_field() alone accounts for more
 * call sites than any WordPress core function. Backing it with state rather
 * than per-test expectations is what lets a test seed a field once and read it
 * back through whatever code path is under test.
 *
 * Field values are keyed "postId|selector". A post id of false/0 means the
 * options page, matching ACF's own convention.
 *
 * Note that a plugin testing the *ACF-unavailable* branch — code guarded by
 * function_exists('acf_get_field') — must not load this file, or must run that
 * test in a separate process. Once a function is defined it stays defined for
 * the lifetime of the PHP process; that is true of Brain Monkey and WP_Mock
 * equally, and is not something this package can paper over.
 */

use BleedingDeacons\WpMocks\WpState;

if (!function_exists('get_field')) {
    function get_field(string $selector, mixed $postId = false, bool $format = true): mixed
    {
        return WpState::$fields[((int) $postId) . '|' . $selector] ?? null;
    }
}

if (!function_exists('update_field')) {
    function update_field(string $selector, mixed $value, mixed $postId = false): bool
    {
        WpState::$fields[((int) $postId) . '|' . $selector] = $value;

        return true;
    }
}

if (!function_exists('delete_field')) {
    function delete_field(string $selector, mixed $postId = false): bool
    {
        unset(WpState::$fields[((int) $postId) . '|' . $selector]);

        return true;
    }
}

if (!function_exists('get_fields')) {
    /**
     * Every field seeded for a post, keyed by selector.
     *
     * @return array<string, mixed>|false
     */
    function get_fields(mixed $postId = false, bool $format = true): array|false
    {
        $prefix = ((int) $postId) . '|';
        $out = [];

        foreach (WpState::$fields as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $out[substr($key, strlen($prefix))] = $value;
            }
        }

        return $out === [] ? false : $out;
    }
}

if (!function_exists('get_field_object')) {
    /** @return array<string, mixed>|false */
    function get_field_object(string $selector, mixed $postId = false, bool $format = true): array|false
    {
        $key = ((int) $postId) . '|' . $selector;

        if (!array_key_exists($key, WpState::$fields)) {
            return false;
        }

        return [
            'key' => 'field_' . md5($selector),
            'name' => $selector,
            'value' => WpState::$fields[$key],
        ];
    }
}

if (!function_exists('acf_get_field')) {
    /**
     * ACF addresses fields by an opaque generated key rather than by name, so
     * the resolvers in this suite map name → key once and cache it. A stable
     * hash of the name stands in for the generated key.
     *
     * @return array<string, mixed>|false
     */
    function acf_get_field(string $selector): array|false
    {
        return ['key' => 'field_' . md5($selector), 'name' => $selector];
    }
}

if (!function_exists('acf_maybe_get_field')) {
    /** @return array<string, mixed>|false */
    function acf_maybe_get_field(string $selector, mixed $postId = false, bool $strict = true): array|false
    {
        return acf_get_field($selector);
    }
}

if (!function_exists('acf_add_local_field_group')) {
    /** @param array<string, mixed> $group */
    function acf_add_local_field_group(array $group): bool
    {
        WpState::$options['__acf_field_groups'][] = $group;

        return true;
    }
}

if (!function_exists('acf_add_validation_error')) {
    function acf_add_validation_error(string $input, string $message = ''): void
    {
        WpState::$options['__acf_validation_errors'][] = ['input' => $input, 'message' => $message];
    }
}

if (!function_exists('acf_save_post')) {
    /**
     * Writes the supplied values through update_field(), which is what a
     * caller passing $values is really after. With no values it is a no-op:
     * the real one saves $_POST['acf'], and a test driving a save through the
     * superglobal wants a real ACF, not a stand-in.
     *
     * @param array<string, mixed>|null $values
     */
    function acf_save_post(mixed $postId = 0, ?array $values = null): bool
    {
        foreach ($values ?? [] as $selector => $value) {
            update_field($selector, $value, $postId);
        }

        return true;
    }
}
