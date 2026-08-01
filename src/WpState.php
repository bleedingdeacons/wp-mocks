<?php

declare(strict_types=1);

namespace BleedingDeacons\WpMocks;

/**
 * The single mutable store the WordPress stubs read and write.
 *
 * The stubs in stubs/*.php are real functions rather than per-test
 * expectations, and this is where their data lives. A test seeds what a
 * scenario needs — an option, an ACF field, a post — and then asserts on what
 * the class under test did with it.
 *
 * The reason for state rather than stacked expectations is not stylistic.
 * Both WP_Mock and Brain Monkey resolve the *first* matching expectation, so a
 * catch-all `get_option` registered in a base TestCase silently shadows any
 * narrower stub a test adds afterwards. Amber and Sentinel each hit that and
 * each independently invented a store to route reads through; this is that
 * store, factored out.
 *
 * Everything is static because the stubs are plain global functions with
 * nowhere to hold an instance. {@see reset()} clears it, and
 * {@see TestCase::setUp()} calls that for you.
 */
final class WpState
{
    /** @var array<string, mixed> Option name => value. */
    public static array $options = [];

    /** @var array<string, mixed> Transient name => value. */
    public static array $transients = [];

    /** @var array<string, mixed> "postId|field" => value, read by get_field(). */
    public static array $fields = [];

    /** @var array<int, array<string, mixed>> Post id => meta key => value. */
    public static array $postMeta = [];

    /** @var array<int, object> Post id => post object. */
    public static array $posts = [];

    /** @var array<int, string> Post id => post type. */
    public static array $postTypes = [];

    /** @var array<int, string> Post id => post status. */
    public static array $postStatuses = [];

    /** @var array<int, array<string, mixed>> Post id => term lists, keyed by taxonomy. */
    public static array $postTerms = [];

    /** Whether current_user_can() grants anything. */
    public static bool $userCan = true;

    /** @var array<int, string> Capabilities explicitly denied even when $userCan is true. */
    public static array $deniedCaps = [];

    /** The id reported by get_current_user_id(). */
    public static int $currentUserId = 1;

    /** @var array<int, string> Roles the current user holds, reported by wp_get_current_user(). */
    public static array $currentUserRoles = ['administrator'];

    /**
     * @var array<int, mixed> Callbacks passed to register_activation_hook().
     *
     * Note there is no general hook store here: Brain Monkey owns add_action(),
     * add_filter(), do_action() and apply_filters(), and answers questions
     * about them through Actions\has(), Actions\did() and Filters\has().
     * {@see HookAssertions} wraps those.
     */
    public static array $activationHooks = [];

    /** @var array<int, mixed> Callbacks passed to register_deactivation_hook(). */
    public static array $deactivationHooks = [];

    /** @var array<string, mixed> Shortcodes registered via add_shortcode(). */
    public static array $shortcodes = [];

    /** @var array<int, array<string, mixed>> Scripts/styles enqueued, for assertions. */
    public static array $enqueued = [];

    /** @var array<string, array<string, mixed>> Data passed to wp_localize_script(), keyed by object name. */
    public static array $localized = [];

    /** @var array<string, array<string, mixed>> Dashboard widgets registered. */
    public static array $widgets = [];

    /** @var array<int, array<string, mixed>> Admin menu pages registered. */
    public static array $menus = [];

    /** @var array<int, array<int, string>> Submenu pages removed via remove_submenu_page(). */
    public static array $removedSubmenus = [];

    /** @var array<int, array<string, mixed>> Post types registered via register_post_type(). */
    public static array $postTypesRegistered = [];

    /** @var array<int, array<string, mixed>> Routes registered via register_rest_route(). */
    public static array $restRoutes = [];

    /** @var array<int, array<string, mixed>> Calls to wp_update_post(). */
    public static array $updatedPosts = [];

    /** @var array<int, array<string, mixed>> Calls to wp_insert_post(). */
    public static array $insertedPosts = [];

    /** @var array<int, int> Post ids passed to wp_delete_post(). */
    public static array $deletedPosts = [];

    /** The id wp_insert_post() hands back; incremented on each call. */
    public static int $nextPostId = 100;

    /** @var array<int, string> Redirect targets passed to wp_safe_redirect()/wp_redirect(). */
    public static array $redirects = [];

    /** The current admin screen returned by get_current_screen(). */
    public static ?object $screen = null;

    /** @var array<int, object> Rows get_posts()/WP_Query should report as found. */
    public static array $queryPosts = [];

    /** @var array<string, mixed> The object cache behind wp_cache_get()/set(). */
    public static array $cache = [];

    /** Whether the request is an AJAX request. */
    public static bool $doingAjax = false;

    /** Whether is_admin() reports an admin request. */
    public static bool $isAdmin = true;

    /** Whether is_ssl() reports a secure request. */
    public static bool $isSsl = true;

    /** Fixed "now", so time-dependent output is deterministic. */
    public static string $now = '2026-07-24 12:00:00';

    /**
     * Slug used by the URL helpers (plugins_url(), plugin_dir_url()).
     *
     * Set it in a plugin's bootstrap so those helpers return that plugin's
     * own path rather than a generic one.
     */
    public static string $pluginSlug = 'test-plugin';

    /** @var array<int, array<int, mixed>> Messages passed to wp_log(), as [channel, level, message, context]. */
    public static array $logs = [];

    /**
     * Scheduled cron events, keyed by hook, holding the next run timestamp.
     *
     * wp_next_scheduled() reads this and wp_schedule_event() writes it, so an
     * activation routine's "schedule unless already scheduled" branch can be
     * driven both ways.
     *
     * @var array<string, int>
     */
    public static array $cron = [];

    /** @var array<int, array{to: mixed, subject: string, message: string, headers: mixed, attachments: array<int, string>}> Everything passed to wp_mail(). */
    public static array $mail = [];

    /** Value wp_mail() hands back; false simulates a send failure. */
    public static bool $mailResult = true;

    /**
     * Featured-image ids keyed by post id, read by get_post_thumbnail_id().
     *
     * @var array<int, int>
     */
    public static array $thumbnails = [];

    /**
     * Attachment metadata keyed by attachment id, as
     * [url, width, height, isIntermediate] — the tuple
     * wp_get_attachment_image_src() returns.
     *
     * @var array<int, array{0: string, 1: int, 2: int, 3: bool}>
     */
    public static array $attachments = [];

    /** Whether is_singular() reports a single-post view. */
    public static bool $isSingular = false;

    public static function reset(): void
    {
        self::$options = [];
        self::$transients = [];
        self::$fields = [];
        self::$postMeta = [];
        self::$posts = [];
        self::$postTypes = [];
        self::$postStatuses = [];
        self::$postTerms = [];
        self::$userCan = true;
        self::$deniedCaps = [];
        self::$currentUserId = 1;
        self::$currentUserRoles = ['administrator'];
        self::$activationHooks = [];
        self::$deactivationHooks = [];
        self::$shortcodes = [];
        self::$enqueued = [];
        self::$localized = [];
        self::$widgets = [];
        self::$menus = [];
        self::$removedSubmenus = [];
        self::$postTypesRegistered = [];
        self::$restRoutes = [];
        self::$updatedPosts = [];
        self::$insertedPosts = [];
        self::$deletedPosts = [];
        self::$nextPostId = 100;
        self::$redirects = [];
        self::$screen = null;
        self::$queryPosts = [];
        self::$cache = [];
        self::$doingAjax = false;
        self::$isAdmin = true;
        self::$isSsl = true;
        self::$now = '2026-07-24 12:00:00';
        self::$logs = [];
        self::$cron = [];
        self::$mail = [];
        self::$mailResult = true;
        self::$thumbnails = [];
        self::$attachments = [];
        self::$isSingular = false;
        // $pluginSlug is deliberately NOT reset: a plugin sets it once in its
        // bootstrap, and clearing it between tests would undo that.
    }

    /**
     * Resolve whatever ACF was handed into a post id.
     *
     * ACF's $post_id is deliberately loose: an int, a WP_Post, an array with
     * an 'ID' key, false/null for the options page, or a string like "option"
     * or "user_3". Casting straight to int covers the first and raises
     * "Object of class WP_Post could not be converted to int" for the second,
     * which is a real call shape � plenty of code passes the post it already
     * has rather than digging the id back out of it.
     *
     * Non-numeric string ids (ACF's "option", "user_3", "term_5") share bucket
     * 0 with the options page. Nothing in this suite distinguishes them, and
     * separating them here would be inventing behaviour rather than standing
     * in for it.
     */
    public static function acfPostId(mixed $postId): int
    {
        if (is_object($postId)) {
            return (int) ($postId->ID ?? 0);
        }

        if (is_array($postId)) {
            return (int) ($postId['ID'] ?? 0);
        }

        return is_numeric($postId) ? (int) $postId : 0;
    }

    /**
     * Seed a post, its type and its status in one call.
     *
     * @param array<string, mixed> $properties
     */
    public static function addPost(int $id, array $properties = []): object
    {
        $post = (object) array_merge([
            'ID' => $id,
            'post_title' => 'Test Post ' . $id,
            'post_type' => 'post',
            'post_status' => 'publish',
            'post_name' => 'test-post-' . $id,
            'post_content' => '',
            'post_parent' => 0,
        ], $properties);

        self::$posts[$id] = $post;
        self::$postTypes[$id] = (string) $post->post_type;
        self::$postStatuses[$id] = (string) $post->post_status;

        return $post;
    }
}
