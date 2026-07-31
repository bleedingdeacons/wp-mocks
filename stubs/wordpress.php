<?php

declare(strict_types=1);

/**
 * WordPress stand-ins, backed by {@see BleedingDeacons\WpMocks\WpState}.
 *
 * These are real functions rather than per-test expectations. Plugin classes
 * call WordPress directly from inside long methods, so the practical way to
 * test them is to make the functions exist and back them with a store the
 * tests control.
 *
 * Every definition is guarded with function_exists(), so a plugin may define
 * its own variant beforehand and win. Brain Monkey can also override any of
 * them per-test via Patchwork — but only if Patchwork was required before this
 * file, which is what bootstrap.php exists to guarantee.
 *
 * Escaping and translation helpers pass their input straight through: what is
 * under test is what the code produced, not whether WordPress escapes
 * correctly, which is WordPress's own concern.
 *
 * The terminating functions (wp_die, wp_send_json_*) throw instead of exiting,
 * so the guard clauses that call them stay assertable.
 */

use BleedingDeacons\WpMocks\Doubles\FakeWpHttp;
use BleedingDeacons\WpMocks\Exceptions\JsonResponseException;
use BleedingDeacons\WpMocks\Exceptions\WpDieException;
use BleedingDeacons\WpMocks\WpState;

// ── Constants ────────────────────────────────────────────────────────
//
// WordPress core constants that plugin code references directly, so an
// unqualified use inside a namespaced class does not die on "Undefined
// constant". The $wpdb output formats matter most: a repository calling
// get_results($sql, ARRAY_A) fatals without them, and every custom-table
// plugin in this suite does exactly that.
//
// Not defined here: ABSPATH, WP_DEBUG, WP_PLUGIN_DIR and the plugin's own
// constants. Those describe a particular installation, so a plugin's own
// bootstrap decides them — several in this suite point ABSPATH at a real temp
// directory precisely so filesystem paths can be exercised.

foreach ([
    'OBJECT' => 'OBJECT',
    'OBJECT_K' => 'OBJECT_K',
    'ARRAY_A' => 'ARRAY_A',
    'ARRAY_N' => 'ARRAY_N',
    'MINUTE_IN_SECONDS' => 60,
    'HOUR_IN_SECONDS' => 3600,
    'DAY_IN_SECONDS' => 86400,
    'WEEK_IN_SECONDS' => 604800,
    'MONTH_IN_SECONDS' => 2592000,
    'YEAR_IN_SECONDS' => 31536000,
] as $wpMocksConstant => $wpMocksValue) {
    if (!defined($wpMocksConstant)) {
        define($wpMocksConstant, $wpMocksValue);
    }
}
unset($wpMocksConstant, $wpMocksValue);

// ── Classes ──────────────────────────────────────────────────────────

if (!class_exists('WP_Post')) {
    class WP_Post
    {
        public int $ID = 0;
        public string $post_title = '';
        public string $post_type = 'post';
        public string $post_status = 'publish';
        public string $post_name = '';
        public string $post_content = '';
        public string $post_excerpt = '';
        public int $post_parent = 0;
        public int $post_author = 1;
        public string $post_date = '';
        public string $post_modified_gmt = '';

        /** @param array<string, mixed>|object $data */
        public function __construct(array|object $data = [])
        {
            foreach ((array) $data as $k => $v) {
                $this->$k = $v;
            }
        }
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        public function __construct(
            private string $code = '',
            private string $message = '',
            private mixed $data = null
        ) {
        }

        public function get_error_code(): string
        {
            return $this->code;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }

        public function get_error_data(): mixed
        {
            return $this->data;
        }

        /** @return array<int, string> */
        public function get_error_codes(): array
        {
            return $this->code === '' ? [] : [$this->code];
        }

        public function has_errors(): bool
        {
            return $this->code !== '';
        }
    }
}

if (!class_exists('WP_Query')) {
    /**
     * Enough WP_Query for two jobs: code that constructs one and reads
     * ->posts, and list-table hooks that receive one and reshape it through
     * get()/set(). Query vars stay readable afterwards, so a test can assert
     * on the meta_query or orderby a hook installed.
     */
    class WP_Query
    {
        /** @var array<int, object> */
        public array $posts = [];
        public int $found_posts = 0;
        /** @var array<string, mixed> */
        public array $query_vars = [];

        /** What is_main_query()/is_search() report; set by tests. */
        public bool $isMainQuery = true;
        public bool $isSearch = false;

        /** @param array<string, mixed> $args */
        public function __construct(array $args = [])
        {
            $this->query_vars = $args;
            $this->posts = WpState::$queryPosts;
            $this->found_posts = count($this->posts);
            WpState::$options['__last_wp_query_args'] = $args;
        }

        public function get(string $key, mixed $default = ''): mixed
        {
            return $this->query_vars[$key] ?? $default;
        }

        public function set(string $key, mixed $value): void
        {
            $this->query_vars[$key] = $value;
        }

        public function is_main_query(): bool
        {
            return $this->isMainQuery;
        }

        public function is_search(): bool
        {
            return $this->isSearch;
        }

        public function have_posts(): bool
        {
            return $this->posts !== [];
        }
    }
}

if (!class_exists('WP_Screen')) {
    class WP_Screen
    {
        public string $id = '';
        public string $base = '';
        public string $post_type = '';

        /** @param array<string, mixed> $data */
        public function __construct(array $data = [])
        {
            foreach ($data as $k => $v) {
                $this->$k = $v;
            }
        }
    }
}

if (!class_exists('WP_User')) {
    class WP_User
    {
        public int $ID = 1;
        public string $user_login = 'tester';
        public string $user_email = 'tester@example.test';
        public string $display_name = 'Tester';
        /** @var array<int, string> */
        public array $roles;
        /** @var array<string, bool> */
        public array $caps = [];

        /** @param array<int, string> $roles */
        public function __construct(array $roles = [], int $id = 1)
        {
            $this->roles = $roles;
            $this->ID = $id;
        }

        public function exists(): bool
        {
            return $this->ID > 0;
        }

        public function has_cap(string $cap): bool
        {
            return !in_array($cap, WpState::$deniedCaps, true) && WpState::$userCan;
        }
    }
}

if (!class_exists('WP_Http_Cookie')) {
    class WP_Http_Cookie
    {
        public string $name = '';
        public string $value = '';
        public string $domain = '';
        public string $path = '/';

        /** @param array<string, mixed> $data */
        public function __construct(array $data = [])
        {
            foreach ($data as $k => $v) {
                $this->$k = $v;
            }
        }
    }
}

// ── Escaping and translation ─────────────────────────────────────────

foreach (
    [
        'esc_html', 'esc_attr', 'esc_url', 'esc_url_raw', 'esc_textarea',
        'esc_js', 'wp_kses_post', 'sanitize_email', 'wp_slash',
    ] as $fn
) {
    if (!function_exists($fn)) {
        eval("function {$fn}(\$text = '') { return (string) \$text; }");
    }
}

if (!function_exists('__')) {
    function __(string $text = '', string $domain = ''): string
    {
        return $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__(string $text = '', string $domain = ''): string
    {
        return $text;
    }
}

if (!function_exists('esc_attr__')) {
    function esc_attr__(string $text = '', string $domain = ''): string
    {
        return $text;
    }
}

if (!function_exists('_x')) {
    function _x(string $text = '', string $context = '', string $domain = ''): string
    {
        return $text;
    }
}

if (!function_exists('_e')) {
    function _e(string $text = '', string $domain = ''): void
    {
        echo $text;
    }
}

if (!function_exists('esc_html_e')) {
    function esc_html_e(string $text = '', string $domain = ''): void
    {
        echo $text;
    }
}

if (!function_exists('esc_attr_e')) {
    function esc_attr_e(string $text = '', string $domain = ''): void
    {
        echo $text;
    }
}

if (!function_exists('_n')) {
    function _n(string $single, string $plural, int $number, string $domain = ''): string
    {
        return $number === 1 ? $single : $plural;
    }
}

// ── Sanitising ───────────────────────────────────────────────────────

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(mixed $str = ''): string
    {
        return trim(strip_tags((string) $str));
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field(mixed $str = ''): string
    {
        return trim(strip_tags((string) $str));
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key(string $key = ''): string
    {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key)) ?? '';
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title(string $title = ''): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($title)) ?? '', '-');
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash(mixed $value): mixed
    {
        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!function_exists('absint')) {
    function absint(mixed $n): int
    {
        return abs((int) $n);
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit(string $value): string
    {
        return rtrim($value, '/\\') . '/';
    }
}

if (!function_exists('untrailingslashit')) {
    function untrailingslashit(string $value): string
    {
        return rtrim($value, '/\\');
    }
}

// ── Errors ───────────────────────────────────────────────────────────

if (!function_exists('is_wp_error')) {
    function is_wp_error(mixed $thing): bool
    {
        return $thing instanceof \WP_Error;
    }
}

// ── Options and transients ───────────────────────────────────────────

if (!function_exists('get_option')) {
    function get_option(string $name, mixed $default = false): mixed
    {
        return array_key_exists($name, WpState::$options) ? WpState::$options[$name] : $default;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $name, mixed $value, mixed $autoload = null): bool
    {
        WpState::$options[$name] = $value;

        return true;
    }
}

if (!function_exists('add_option')) {
    function add_option(string $name, mixed $value = '', string $deprecated = '', mixed $autoload = null): bool
    {
        if (array_key_exists($name, WpState::$options)) {
            return false;
        }

        WpState::$options[$name] = $value;

        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option(string $name): bool
    {
        unset(WpState::$options[$name]);

        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient(string $name): mixed
    {
        return array_key_exists($name, WpState::$transients) ? WpState::$transients[$name] : false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient(string $name, mixed $value, int $expiration = 0): bool
    {
        WpState::$transients[$name] = $value;

        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient(string $name): bool
    {
        unset(WpState::$transients[$name]);

        return true;
    }
}

// ── Object cache ─────────────────────────────────────────────────────

if (!function_exists('wp_cache_get')) {
    function wp_cache_get(string $key, string $group = '', bool $force = false, mixed &$found = null): mixed
    {
        $found = array_key_exists($group . ':' . $key, WpState::$cache);

        return $found ? WpState::$cache[$group . ':' . $key] : false;
    }
}

if (!function_exists('wp_cache_set')) {
    function wp_cache_set(string $key, mixed $data, string $group = '', int $expire = 0): bool
    {
        WpState::$cache[$group . ':' . $key] = $data;

        return true;
    }
}

if (!function_exists('wp_cache_delete')) {
    function wp_cache_delete(string $key, string $group = ''): bool
    {
        unset(WpState::$cache[$group . ':' . $key]);

        return true;
    }
}

if (!function_exists('wp_cache_flush')) {
    function wp_cache_flush(): bool
    {
        WpState::$cache = [];

        return true;
    }
}

// ── Posts and meta ───────────────────────────────────────────────────

if (!function_exists('get_post')) {
    function get_post(mixed $id = null): ?object
    {
        $id = is_object($id) ? (int) ($id->ID ?? 0) : (int) $id;

        return WpState::$posts[$id] ?? null;
    }
}

if (!function_exists('get_post_type')) {
    function get_post_type(mixed $post = null): string|false
    {
        $id = is_object($post) ? (int) ($post->ID ?? 0) : (int) $post;

        return WpState::$postTypes[$id] ?? false;
    }
}

if (!function_exists('get_post_status')) {
    function get_post_status(mixed $post = null): string|false
    {
        $id = is_object($post) ? (int) ($post->ID ?? 0) : (int) $post;

        return WpState::$postStatuses[$id] ?? false;
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta(int $postId, string $key = '', bool $single = false): mixed
    {
        $meta = WpState::$postMeta[$postId] ?? [];

        if ($key === '') {
            return $meta;
        }

        $value = $meta[$key] ?? ($single ? '' : []);

        return $single ? $value : (is_array($value) ? $value : [$value]);
    }
}

if (!function_exists('get_post_custom')) {
    /**
     * WordPress returns every meta value as an array, even single ones, so
     * that is what this normalises to — code under test frequently indexes
     * [0] straight off the result.
     *
     * @return array<string, array<int, mixed>>
     */
    function get_post_custom(int $postId = 0): array
    {
        $out = [];

        foreach (WpState::$postMeta[$postId] ?? [] as $key => $value) {
            $out[$key] = is_array($value) ? array_values($value) : [$value];
        }

        return $out;
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta(int $postId, string $key, mixed $value, mixed $prev = ''): bool
    {
        WpState::$postMeta[$postId][$key] = $value;

        return true;
    }
}

if (!function_exists('add_post_meta')) {
    function add_post_meta(int $postId, string $key, mixed $value, bool $unique = false): bool
    {
        WpState::$postMeta[$postId][$key] = $value;

        return true;
    }
}

if (!function_exists('delete_post_meta')) {
    function delete_post_meta(int $postId, string $key, mixed $value = ''): bool
    {
        unset(WpState::$postMeta[$postId][$key]);

        return true;
    }
}

if (!function_exists('wp_insert_post')) {
    /**
     * Return type is int|\WP_Error, matching WordPress: with $wpError true it
     * hands back a WP_Error on failure. Patchwork keeps a function's declared
     * signature when Brain Monkey redefines it, so a narrower `int` here would
     * make `Functions\expect('wp_insert_post')->andReturn($wpError)` a
     * TypeError — and simulating a failed insert is exactly what a test needs
     * to do.
     *
     * @param array<string, mixed> $post
     */
    function wp_insert_post(array $post = [], bool $wpError = false): int|\WP_Error
    {
        WpState::$insertedPosts[] = $post;

        $id = (int) ($post['ID'] ?? 0);
        if ($id === 0) {
            $id = WpState::$nextPostId++;
        }

        WpState::addPost($id, $post);

        return $id;
    }
}

if (!function_exists('wp_update_post')) {
    /**
     * int|\WP_Error for the same reason as wp_insert_post() above.
     *
     * @param array<string, mixed> $post
     */
    function wp_update_post(array $post = [], bool $wpError = false): int|\WP_Error
    {
        WpState::$updatedPosts[] = $post;

        $id = (int) ($post['ID'] ?? 0);
        if ($id !== 0 && isset(WpState::$posts[$id])) {
            foreach ($post as $key => $value) {
                WpState::$posts[$id]->$key = $value;
            }
        }

        return $id;
    }
}

if (!function_exists('wp_delete_post')) {
    function wp_delete_post(int $postId = 0, bool $forceDelete = false): mixed
    {
        WpState::$deletedPosts[] = $postId;

        $post = WpState::$posts[$postId] ?? false;
        unset(WpState::$posts[$postId], WpState::$postTypes[$postId], WpState::$postStatuses[$postId]);

        return $post;
    }
}

if (!function_exists('wp_is_post_revision')) {
    function wp_is_post_revision(mixed $post): int|false
    {
        return false;
    }
}

if (!function_exists('wp_is_post_autosave')) {
    function wp_is_post_autosave(mixed $post): int|false
    {
        return false;
    }
}

if (!function_exists('get_posts')) {
    /**
     * @param array<string, mixed> $args
     * @return array<int, object>
     */
    function get_posts(array $args = []): array
    {
        WpState::$options['__last_get_posts_args'] = $args;

        return WpState::$queryPosts;
    }
}

if (!function_exists('get_permalink')) {
    /**
     * string|false, matching WordPress: it returns false for a post that does
     * not exist. Declaring plain `string` made that case unreachable in a
     * test, because Patchwork keeps the declared return type.
     */
    function get_permalink(mixed $post = 0): string|false
    {
        return 'https://example.test/?p=' . (is_object($post) ? ($post->ID ?? 0) : (int) $post);
    }
}

if (!function_exists('get_the_title')) {
    function get_the_title(mixed $post = 0): string
    {
        $id = is_object($post) ? (int) ($post->ID ?? 0) : (int) $post;

        return (string) (WpState::$posts[$id]->post_title ?? '');
    }
}

if (!function_exists('get_the_ID')) {
    function get_the_ID(): int|false
    {
        return WpState::$options['__current_post_id'] ?? false;
    }
}

if (!function_exists('get_edit_post_link')) {
    function get_edit_post_link(mixed $id = 0, string $context = 'display'): ?string
    {
        return 'https://example.test/wp-admin/post.php?post=' . (int) $id . '&action=edit';
    }
}

if (!function_exists('register_post_type')) {
    /** @param array<string, mixed> $args */
    function register_post_type(string $postType, array $args = []): object
    {
        WpState::$postTypesRegistered[] = ['name' => $postType, 'args' => $args];

        return (object) ['name' => $postType];
    }
}

if (!function_exists('get_page_by_path')) {
    /**
     * Matches on post_name, which is where WordPress ends up for a top-level
     * page. Nested paths are not modelled — a test wanting hierarchy wants a
     * real query.
     */
    function get_page_by_path(string $path, mixed $output = null, mixed $postType = 'page'): ?object
    {
        $slug = trim($path, '/');

        foreach (WpState::$posts as $post) {
            if (($post->post_name ?? '') === $slug) {
                return $post;
            }
        }

        return null;
    }
}

if (!function_exists('url_to_postid')) {
    /**
     * Real WordPress parses the permalink structure; here the id is read back
     * out of the ?p= form get_permalink() produces, and anything else is 0 —
     * which is what WordPress answers for an unrecognised URL.
     */
    function url_to_postid(string $url): int
    {
        return preg_match('/[?&]p=(\d+)/', $url, $m) === 1 ? (int) $m[1] : 0;
    }
}

if (!function_exists('wp_publish_post')) {
    function wp_publish_post(mixed $post): void
    {
        $id = is_object($post) ? (int) ($post->ID ?? 0) : (int) $post;

        WpState::$postStatuses[$id] = 'publish';

        if (isset(WpState::$posts[$id])) {
            WpState::$posts[$id]->post_status = 'publish';
        }
    }
}

if (!function_exists('wp_trash_post')) {
    /**
     * Returns the trashed post, or false when there is nothing at that id —
     * the same shape WordPress uses to signal failure.
     */
    function wp_trash_post(int $postId = 0): object|false
    {
        if (!isset(WpState::$posts[$postId])) {
            return false;
        }

        WpState::$postStatuses[$postId] = 'trash';
        WpState::$posts[$postId]->post_status = 'trash';

        return WpState::$posts[$postId];
    }
}

if (!function_exists('clean_post_cache')) {
    function clean_post_cache(mixed $post): void
    {
    }
}

if (!function_exists('get_the_time')) {
    /**
     * string|false, as WordPress declares it — false when the post cannot be
     * resolved. Callers passing the result into a string parameter under
     * strict_types would get a TypeError on the false branch, which is the
     * point: it is a real branch.
     */
    function get_the_time(string $format = '', mixed $post = null): string|false
    {
        $timestamp = (int) strtotime(WpState::$now);

        return $format === '' ? (string) $timestamp : date($format, $timestamp);
    }
}

if (!function_exists('get_post_timestamp')) {
    function get_post_timestamp(mixed $post = null, string $field = 'date'): int|false
    {
        return (int) strtotime(WpState::$now);
    }
}

// ── Media ────────────────────────────────────────────────────────────

if (!function_exists('get_post_thumbnail_id')) {
    /**
     * WordPress returns 0 (or false) when a post has no featured image, and
     * code branches on that, so an unseeded post answers 0 rather than a
     * plausible id.
     */
    function get_post_thumbnail_id(mixed $post = null): int
    {
        $id = is_object($post) ? (int) ($post->ID ?? 0) : (int) $post;

        return WpState::$thumbnails[$id] ?? 0;
    }
}

if (!function_exists('has_post_thumbnail')) {
    function has_post_thumbnail(mixed $post = null): bool
    {
        return get_post_thumbnail_id($post) > 0;
    }
}

if (!function_exists('wp_get_attachment_image_src')) {
    /**
     * The [url, width, height, is_intermediate] tuple, or false for an unknown
     * attachment — again a real branch, so unseeded ids do not invent one.
     *
     * @return array{0: string, 1: int, 2: int, 3: bool}|false
     */
    function wp_get_attachment_image_src(int $attachmentId, mixed $size = 'thumbnail', bool $icon = false): array|false
    {
        return WpState::$attachments[$attachmentId] ?? false;
    }
}

if (!function_exists('wp_get_attachment_url')) {
    function wp_get_attachment_url(int $attachmentId): string|false
    {
        return WpState::$attachments[$attachmentId][0] ?? false;
    }
}

// ── Taxonomies ───────────────────────────────────────────────────────

if (!function_exists('wp_get_post_terms')) {
    /**
     * @param array<string, mixed> $args
     * @return array<int, mixed>
     */
    function wp_get_post_terms(int $postId = 0, string $taxonomy = 'post_tag', array $args = []): array
    {
        return WpState::$postTerms[$postId][$taxonomy] ?? [];
    }
}

if (!function_exists('wp_get_object_terms')) {
    /**
     * @param array<int, int>|int         $objectIds
     * @param array<int, string>|string   $taxonomies
     * @param array<string, mixed>        $args
     * @return array<int, mixed>
     */
    function wp_get_object_terms(array|int $objectIds, array|string $taxonomies, array $args = []): array
    {
        $id = is_array($objectIds) ? (int) ($objectIds[0] ?? 0) : $objectIds;
        $tax = is_array($taxonomies) ? (string) ($taxonomies[0] ?? '') : $taxonomies;

        return WpState::$postTerms[$id][$tax] ?? [];
    }
}

if (!function_exists('wp_set_object_terms')) {
    /**
     * @param array<int, mixed>|string $terms
     * @return array<int, mixed>
     */
    function wp_set_object_terms(int $objectId, array|string $terms, string $taxonomy, bool $append = false): array
    {
        WpState::$postTerms[$objectId][$taxonomy] = (array) $terms;

        return (array) $terms;
    }
}

// ── Users and capabilities ───────────────────────────────────────────

if (!function_exists('current_user_can')) {
    function current_user_can(string $cap = '', mixed ...$args): bool
    {
        if (in_array($cap, WpState::$deniedCaps, true)) {
            return false;
        }

        return WpState::$userCan;
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id(): int
    {
        return WpState::$currentUserId;
    }
}

if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user(): \WP_User
    {
        return new \WP_User(WpState::$currentUserRoles, WpState::$currentUserId);
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool
    {
        return WpState::$currentUserId > 0;
    }
}

if (!function_exists('get_role')) {
    function get_role(string $role): ?object
    {
        return in_array($role, WpState::$currentUserRoles, true)
            ? (object) ['name' => $role, 'capabilities' => []]
            : null;
    }
}

// ── Request context ──────────────────────────────────────────────────

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        return WpState::$isAdmin;
    }
}

if (!function_exists('is_ssl')) {
    function is_ssl(): bool
    {
        return WpState::$isSsl;
    }
}

if (!function_exists('is_multisite')) {
    function is_multisite(): bool
    {
        return false;
    }
}

if (!function_exists('is_singular')) {
    function is_singular(mixed $postTypes = ''): bool
    {
        return WpState::$isSingular;
    }
}

if (!function_exists('wp_doing_ajax')) {
    function wp_doing_ajax(): bool
    {
        return WpState::$doingAjax;
    }
}

if (!function_exists('get_current_screen')) {
    function get_current_screen(): ?object
    {
        return WpState::$screen;
    }
}

if (!function_exists('nocache_headers')) {
    function nocache_headers(): void
    {
    }
}

// ── Terminating functions ────────────────────────────────────────────

if (!function_exists('wp_die')) {
    function wp_die(mixed $message = '', mixed $title = '', mixed $args = []): void
    {
        throw WpDieException::fromArguments($message, $title, $args);
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success(mixed $data = null, ?int $status = null): void
    {
        throw new JsonResponseException(true, $data, $status);
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error(mixed $data = null, ?int $status = null): void
    {
        throw new JsonResponseException(false, $data, $status);
    }
}

if (!function_exists('wp_safe_redirect')) {
    function wp_safe_redirect(string $location, int $status = 302): bool
    {
        WpState::$redirects[] = $location;

        return true;
    }
}

if (!function_exists('wp_redirect')) {
    function wp_redirect(string $location, int $status = 302): bool
    {
        WpState::$redirects[] = $location;

        return true;
    }
}

if (!function_exists('wp_validate_redirect')) {
    /**
     * WordPress rejects off-site locations, falling back to the second
     * argument. Same rule here against home_url()'s host, because the point of
     * calling it is the open-redirect guard, and a stub that waved everything
     * through would make that guard untestable.
     */
    function wp_validate_redirect(string $location, string $fallback = ''): string
    {
        $host = parse_url($location, PHP_URL_HOST);

        if ($host === null || $host === false) {
            // Relative — same-site by definition.
            return $location;
        }

        return $host === parse_url(home_url(), PHP_URL_HOST) ? $location : $fallback;
    }
}

if (!function_exists('wp_salt')) {
    /**
     * Fixed per scheme, so anything derived from it — a signature, a token —
     * is reproducible across a run without being a real secret.
     */
    function wp_salt(string $scheme = 'auth'): string
    {
        return 'wp-mocks-salt-' . $scheme;
    }
}

// ── Nonces ───────────────────────────────────────────────────────────

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce(string $action = ''): string
    {
        return 'nonce-' . $action;
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce(string $nonce, string $action = ''): int|false
    {
        return $nonce === 'nonce-' . $action ? 1 : false;
    }
}

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field(
        string $action = '',
        string $name = '_wpnonce',
        bool $referer = true,
        bool $echo = true
    ): string {
        $html = '<input type="hidden" name="' . $name . '" value="nonce-' . $action . '" />';
        if ($echo) {
            echo $html;
        }

        return $html;
    }
}

if (!function_exists('check_admin_referer')) {
    function check_admin_referer(string $action = '', string $name = '_wpnonce'): bool
    {
        return true;
    }
}

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer(string $action = '', mixed $name = false, bool $die = true): bool
    {
        return true;
    }
}

// ── URLs and paths ───────────────────────────────────────────────────

if (!function_exists('admin_url')) {
    function admin_url(string $path = '', string $scheme = 'admin'): string
    {
        return 'https://example.test/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('home_url')) {
    function home_url(string $path = ''): string
    {
        return 'https://example.test/' . ltrim($path, '/');
    }
}

if (!function_exists('site_url')) {
    function site_url(string $path = ''): string
    {
        return 'https://example.test/' . ltrim($path, '/');
    }
}

if (!function_exists('rest_url')) {
    function rest_url(string $path = ''): string
    {
        return 'https://example.test/wp-json/' . ltrim($path, '/');
    }
}

if (!function_exists('plugins_url')) {
    function plugins_url(string $path = '', string $plugin = ''): string
    {
        return 'https://example.test/wp-content/plugins/' . WpState::$pluginSlug . '/' . ltrim($path, '/');
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url(string $file = ''): string
    {
        return 'https://example.test/wp-content/plugins/' . WpState::$pluginSlug . '/';
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path(string $file = ''): string
    {
        return dirname($file) . '/';
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename(string $file = ''): string
    {
        return WpState::$pluginSlug . '/' . basename($file);
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg(mixed ...$args): string
    {
        $base = 'https://example.test/wp-admin/admin.php';

        if (is_array($args[0] ?? null)) {
            $params = $args[0];
            $url = $args[1] ?? $base;
        } else {
            $params = [$args[0] => $args[1] ?? ''];
            $url = $args[2] ?? $base;
        }

        return $url . (str_contains((string) $url, '?') ? '&' : '?') . http_build_query($params);
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p(string $target): bool
    {
        return is_dir($target) || mkdir($target, 0777, true);
    }
}

// ── Hooks ────────────────────────────────────────────────────────────
//
// Deliberately almost empty. Brain Monkey owns the entire hook layer —
// add_action, add_filter, do_action, apply_filters, has_action, has_filter,
// remove_action, remove_filter, did_action, current_filter, doing_action,
// doing_filter and the _ref_array/_deprecated variants — and routes every call
// into the container that backs Actions\expectAdded(), Actions\expectDone(),
// Filters\expectApplied(), Actions\has() and Actions\did().
//
// Those definitions are loaded lazily by Monkey\setUp(), and every one of them
// is function_exists()-guarded. This file runs at bootstrap, long before the
// first setUp(), so anything defined here permanently shadows Brain Monkey's
// version and silently breaks its hook assertions: the expectation is never
// satisfied because the call never reaches the container. Do not add hook
// functions here.
//
// The plugin-lifecycle registrars below are not part of that set, so they are
// stubbed normally.

if (!function_exists('register_activation_hook')) {
    function register_activation_hook(string $file, mixed $callback): void
    {
        WpState::$activationHooks[] = $callback;
    }
}

if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook(string $file, mixed $callback): void
    {
        WpState::$deactivationHooks[] = $callback;
    }
}

if (!function_exists('register_rest_route')) {
    /** @param array<string, mixed> $args */
    function register_rest_route(string $namespace, string $route, array $args = [], bool $override = false): bool
    {
        WpState::$restRoutes[] = ['namespace' => $namespace, 'route' => $route, 'args' => $args];

        return true;
    }
}

if (!function_exists('flush_rewrite_rules')) {
    function flush_rewrite_rules(bool $hard = true): void
    {
    }
}

// ── Admin menus, assets and shortcodes ───────────────────────────────

if (!function_exists('add_menu_page')) {
    function add_menu_page(
        string $pageTitle = '',
        string $menuTitle = '',
        string $capability = '',
        string $slug = '',
        mixed $callback = null,
        string $icon = '',
        mixed $position = null
    ): string {
        WpState::$menus[] = ['type' => 'menu', 'slug' => $slug, 'title' => $menuTitle, 'cap' => $capability];

        return 'toplevel_page_' . $slug;
    }
}

if (!function_exists('add_submenu_page')) {
    function add_submenu_page(
        string $parent = '',
        string $pageTitle = '',
        string $menuTitle = '',
        string $capability = '',
        string $slug = '',
        mixed $callback = null,
        mixed $position = null
    ): string {
        WpState::$menus[] = [
            'type' => 'submenu', 'parent' => $parent, 'slug' => $slug,
            'title' => $menuTitle, 'cap' => $capability,
        ];

        return $parent . '_page_' . $slug;
    }
}

if (!function_exists('add_options_page')) {
    function add_options_page(
        string $pageTitle = '',
        string $menuTitle = '',
        string $capability = '',
        string $slug = '',
        mixed $callback = null
    ): string {
        WpState::$menus[] = [
            'type' => 'submenu', 'parent' => 'options-general.php', 'slug' => $slug,
            'title' => $menuTitle, 'cap' => $capability,
        ];

        return 'settings_page_' . $slug;
    }
}

if (!function_exists('remove_submenu_page')) {
    function remove_submenu_page(string $parent = '', string $slug = ''): mixed
    {
        WpState::$removedSubmenus[] = [$parent, $slug];

        return false;
    }
}

if (!function_exists('wp_add_dashboard_widget')) {
    function wp_add_dashboard_widget(string $id = '', string $name = '', mixed $callback = null): void
    {
        WpState::$widgets[$id] = ['name' => $name, 'callback' => $callback];
    }
}

foreach (
    [
        'wp_enqueue_script', 'wp_enqueue_style',
        'wp_register_script', 'wp_register_style',
        'wp_add_inline_style', 'wp_add_inline_script',
    ] as $fn
) {
    if (!function_exists($fn)) {
        eval(
            "function {$fn}(\$handle = '', ...\$rest) {"
            . " \\BleedingDeacons\\WpMocks\\WpState::\$enqueued[] = ['fn' => '{$fn}', 'handle' => \$handle];"
            . " return true; }"
        );
    }
}

if (!function_exists('wp_localize_script')) {
    /** @param array<string, mixed> $data */
    function wp_localize_script(string $handle = '', string $objectName = '', array $data = []): bool
    {
        WpState::$localized[$objectName] = $data;

        return true;
    }
}

if (!function_exists('add_shortcode')) {
    function add_shortcode(string $tag = '', mixed $callback = null): void
    {
        WpState::$shortcodes[$tag] = $callback;
    }
}

if (!function_exists('shortcode_exists')) {
    function shortcode_exists(string $tag = ''): bool
    {
        return isset(WpState::$shortcodes[$tag]);
    }
}

if (!function_exists('remove_shortcode')) {
    function remove_shortcode(string $tag = ''): void
    {
        unset(WpState::$shortcodes[$tag]);
    }
}

if (!function_exists('shortcode_atts')) {
    /**
     * @param array<string, mixed> $pairs
     * @return array<string, mixed>
     */
    function shortcode_atts(array $pairs, mixed $atts, string $shortcode = ''): array
    {
        $atts = (array) $atts;
        $out = [];
        foreach ($pairs as $name => $default) {
            $out[$name] = array_key_exists($name, $atts) ? $atts[$name] : $default;
        }

        return $out;
    }
}

if (!function_exists('do_shortcode')) {
    function do_shortcode(string $content = ''): string
    {
        return $content;
    }
}

// ── HTTP API ─────────────────────────────────────────────────────────

if (!function_exists('wp_remote_request')) {
    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>|\WP_Error
     */
    function wp_remote_request(string $url, array $args = []): array|\WP_Error
    {
        return FakeWpHttp::dispatch($url, $args);
    }
}

if (!function_exists('wp_remote_get')) {
    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>|\WP_Error
     */
    function wp_remote_get(string $url, array $args = []): array|\WP_Error
    {
        return FakeWpHttp::dispatch($url, $args + ['method' => 'GET']);
    }
}

if (!function_exists('wp_remote_post')) {
    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>|\WP_Error
     */
    function wp_remote_post(string $url, array $args = []): array|\WP_Error
    {
        return FakeWpHttp::dispatch($url, $args + ['method' => 'POST']);
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body(mixed $response): string
    {
        return is_array($response) ? (string) ($response['body'] ?? '') : '';
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code(mixed $response): int|string
    {
        return is_array($response) ? (int) ($response['response']['code'] ?? 0) : '';
    }
}

if (!function_exists('wp_remote_retrieve_response_message')) {
    function wp_remote_retrieve_response_message(mixed $response): string
    {
        return is_array($response) ? (string) ($response['response']['message'] ?? '') : '';
    }
}

if (!function_exists('wp_remote_retrieve_headers')) {
    /** @return array<string, mixed>|object */
    function wp_remote_retrieve_headers(mixed $response): array|object
    {
        return is_array($response) ? ($response['headers'] ?? []) : [];
    }
}

if (!function_exists('wp_remote_retrieve_cookies')) {
    /**
     * A transport that keeps a session alive replays these on the next
     * request, so the cookie jar matters as much as the body.
     *
     * @return array<int, \WP_Http_Cookie>
     */
    function wp_remote_retrieve_cookies(mixed $response): array
    {
        return is_array($response) ? ($response['cookies'] ?? []) : [];
    }
}

if (!function_exists('wp_remote_retrieve_cookie')) {
    function wp_remote_retrieve_cookie(mixed $response, string $name): \WP_Http_Cookie|string
    {
        foreach (wp_remote_retrieve_cookies($response) as $cookie) {
            if ($cookie->name === $name) {
                return $cookie;
            }
        }

        // WordPress returns an empty string, not null, when the cookie is absent.
        return '';
    }
}

if (!function_exists('wp_remote_retrieve_cookie_value')) {
    function wp_remote_retrieve_cookie_value(mixed $response, string $name): string
    {
        $cookie = wp_remote_retrieve_cookie($response, $name);

        return $cookie instanceof \WP_Http_Cookie ? $cookie->value : '';
    }
}

// ── Mail ─────────────────────────────────────────────────────────────

if (!function_exists('wp_mail')) {
    /**
     * Records the message rather than sending it. Set WpState::$mailResult to
     * false to exercise a caller's send-failure branch.
     *
     * @param string|array<int, string> $to
     * @param string|array<int, string> $headers
     * @param array<int, string>        $attachments
     */
    function wp_mail(
        string|array $to,
        string $subject,
        string $message,
        string|array $headers = '',
        array $attachments = []
    ): bool {
        WpState::$mail[] = compact('to', 'subject', 'message', 'headers', 'attachments');

        return WpState::$mailResult;
    }
}

if (!function_exists('is_email')) {
    /**
     * WordPress returns the address on success and false on failure, not a
     * bool — code does `if (!is_email($x))`, which works either way, but a
     * caller assigning the result would see the difference.
     */
    function is_email(string $email): string|false
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) === false ? false : $email;
    }
}

// ── Cron ─────────────────────────────────────────────────────────────

if (!function_exists('wp_next_scheduled')) {
    /**
     * @param array<int, mixed> $args
     */
    function wp_next_scheduled(string $hook, array $args = []): int|false
    {
        return WpState::$cron[$hook] ?? false;
    }
}

if (!function_exists('wp_schedule_event')) {
    /**
     * @param array<int, mixed> $args
     */
    function wp_schedule_event(int $timestamp, string $recurrence, string $hook, array $args = []): bool
    {
        WpState::$cron[$hook] = $timestamp;

        return true;
    }
}

if (!function_exists('wp_schedule_single_event')) {
    /**
     * @param array<int, mixed> $args
     */
    function wp_schedule_single_event(int $timestamp, string $hook, array $args = []): bool
    {
        WpState::$cron[$hook] = $timestamp;

        return true;
    }
}

if (!function_exists('wp_unschedule_event')) {
    /**
     * @param array<int, mixed> $args
     */
    function wp_unschedule_event(int $timestamp, string $hook, array $args = []): bool
    {
        unset(WpState::$cron[$hook]);

        return true;
    }
}

if (!function_exists('wp_clear_scheduled_hook')) {
    /**
     * Returns the number of events cleared, as WordPress does.
     *
     * @param array<int, mixed> $args
     */
    function wp_clear_scheduled_hook(string $hook, array $args = []): int
    {
        $cleared = isset(WpState::$cron[$hook]) ? 1 : 0;
        unset(WpState::$cron[$hook]);

        return $cleared;
    }
}

// ── Filesystem ───────────────────────────────────────────────────────

if (!function_exists('get_temp_dir')) {
    function get_temp_dir(): string
    {
        return rtrim(sys_get_temp_dir(), '/\\') . '/';
    }
}

if (!function_exists('sanitize_file_name')) {
    function sanitize_file_name(string $filename): string
    {
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '-', $filename) ?? '';

        return trim(preg_replace('/-+/', '-', $filename) ?? '', '-');
    }
}

if (!function_exists('wp_unique_filename')) {
    /**
     * Real WordPress increments a suffix until the name is free on disk; so
     * does this, against the directory it is handed.
     */
    function wp_unique_filename(string $dir, string $filename, mixed $callback = null): string
    {
        $filename = sanitize_file_name($filename);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $base = $extension === '' ? $filename : substr($filename, 0, -(strlen($extension) + 1));
        $suffix = $extension === '' ? '' : '.' . $extension;

        $candidate = $base . $suffix;
        $n = 1;
        while (file_exists(rtrim($dir, '/\\') . '/' . $candidate)) {
            $candidate = $base . '-' . $n++ . $suffix;
        }

        return $candidate;
    }
}

if (!function_exists('wp_upload_dir')) {
    /**
     * @return array{path: string, url: string, subdir: string, basedir: string, baseurl: string, error: false}
     */
    function wp_upload_dir(?string $time = null, bool $createDir = true, bool $refreshCache = false): array
    {
        $base = rtrim(sys_get_temp_dir(), '/\\') . '/wp-mocks-uploads';

        return [
            'path' => $base,
            'url' => 'https://example.test/wp-content/uploads',
            'subdir' => '',
            'basedir' => $base,
            'baseurl' => 'https://example.test/wp-content/uploads',
            'error' => false,
        ];
    }
}

// ── Formatting and time ──────────────────────────────────────────────

if (!function_exists('number_format_i18n')) {
    function number_format_i18n(float $number, int $decimals = 0): string
    {
        return number_format($number, $decimals);
    }
}

if (!function_exists('size_format')) {
    function size_format(mixed $bytes, int $decimals = 0): string
    {
        return number_format((float) $bytes, $decimals) . ' B';
    }
}

if (!function_exists('human_time_diff')) {
    function human_time_diff(int $from, int $to = 0): string
    {
        return '5 mins';
    }
}

if (!function_exists('current_time')) {
    function current_time(string $type = 'timestamp', mixed $gmt = 0): mixed
    {
        return $type === 'timestamp' ? strtotime(WpState::$now) : WpState::$now;
    }
}

if (!function_exists('wp_date')) {
    function wp_date(string $format, ?int $timestamp = null, mixed $timezone = null): string
    {
        return date($format, $timestamp ?? (int) strtotime(WpState::$now));
    }
}

if (!function_exists('wp_timezone')) {
    function wp_timezone(): DateTimeZone
    {
        return new DateTimeZone('UTC');
    }
}

if (!function_exists('wpautop')) {
    function wpautop(string $text = '', bool $br = true): string
    {
        return '<p>' . $text . '</p>';
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $data, int $options = 0, int $depth = 512): string|false
    {
        return json_encode($data, $options, $depth);
    }
}

if (!function_exists('wp_kses')) {
    /** @param array<string, mixed>|string $allowed */
    function wp_kses(string $text, array|string $allowed = [], array $protocols = []): string
    {
        return $text;
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $text, bool $break = false): string
    {
        return strip_tags($text);
    }
}

if (!function_exists('wp_trim_words')) {
    function wp_trim_words(string $text, int $num = 55, ?string $more = null): string
    {
        return $text;
    }
}

if (!function_exists('wp_unique_id')) {
    function wp_unique_id(string $prefix = ''): string
    {
        static $id = 0;

        return $prefix . (string) ++$id;
    }
}

if (!function_exists('wp_generate_password')) {
    function wp_generate_password(int $length = 12, bool $specialChars = true, bool $extraSpecial = false): string
    {
        return str_repeat('x', $length);
    }
}

if (!function_exists('maybe_unserialize')) {
    function maybe_unserialize(mixed $data): mixed
    {
        if (!is_string($data)) {
            return $data;
        }
        $out = @unserialize($data);

        return $out === false && $data !== serialize(false) ? $data : $out;
    }
}

if (!function_exists('is_serialized')) {
    function is_serialized(mixed $data, bool $strict = true): bool
    {
        return is_string($data) && @unserialize($data) !== false;
    }
}

if (!function_exists('wp_parse_args')) {
    /**
     * @param array<string, mixed> $defaults
     * @return array<string, mixed>
     */
    function wp_parse_args(mixed $args, array $defaults = []): array
    {
        return array_merge($defaults, (array) $args);
    }
}

if (!function_exists('esc_sql')) {
    function esc_sql(mixed $data): mixed
    {
        return is_string($data) ? addslashes($data) : $data;
    }
}

// ── Form helpers ─────────────────────────────────────────────────────

if (!function_exists('checked')) {
    function checked(mixed $checked, mixed $current = true, bool $echo = true): string
    {
        $html = (string) $checked === (string) $current ? ' checked="checked"' : '';
        if ($echo) {
            echo $html;
        }

        return $html;
    }
}

if (!function_exists('selected')) {
    function selected(mixed $selected, mixed $current = true, bool $echo = true): string
    {
        $html = (string) $selected === (string) $current ? ' selected="selected"' : '';
        if ($echo) {
            echo $html;
        }

        return $html;
    }
}

if (!function_exists('disabled')) {
    function disabled(mixed $disabled, mixed $current = true, bool $echo = true): string
    {
        $html = (string) $disabled === (string) $current ? ' disabled="disabled"' : '';
        if ($echo) {
            echo $html;
        }

        return $html;
    }
}

// ── Plugin introspection ─────────────────────────────────────────────

if (!function_exists('is_plugin_active')) {
    function is_plugin_active(string $plugin): bool
    {
        return in_array($plugin, WpState::$options['__active_plugins'] ?? [], true);
    }
}

if (!function_exists('get_plugin_data')) {
    /** @return array<string, string> */
    function get_plugin_data(string $file, bool $markup = true, bool $translate = true): array
    {
        return [
            'Name' => 'Test Plugin',
            'Version' => '1.0.0',
            'PluginURI' => '',
            'Description' => '',
            'Author' => '',
            'TextDomain' => WpState::$pluginSlug,
        ];
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo(string $show = '', string $filter = 'raw'): string
    {
        return match ($show) {
            'name' => 'Test Site',
            'version' => '6.5',
            'admin_email' => 'admin@example.test',
            'charset' => 'UTF-8',
            default => '',
        };
    }
}
