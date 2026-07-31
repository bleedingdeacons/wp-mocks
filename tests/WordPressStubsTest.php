<?php

declare(strict_types=1);

namespace BleedingDeacons\WpMocks\Tests;

use BleedingDeacons\WpMocks\Exceptions\JsonResponseException;
use BleedingDeacons\WpMocks\Exceptions\WpDieException;
use BleedingDeacons\WpMocks\TestCase;
use BleedingDeacons\WpMocks\WpState;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(WpState::class)]
final class WordPressStubsTest extends TestCase
{
    public function testOptionsRoundTrip(): void
    {
        self::assertFalse(get_option('absent'));
        self::assertSame('dflt', get_option('absent', 'dflt'));

        update_option('name', ['a' => 1]);
        self::assertSame(['a' => 1], get_option('name'));

        // A null value must still read back as set, not as the default.
        update_option('nullable', null);
        self::assertNull(get_option('nullable', 'dflt'));

        delete_option('name');
        self::assertFalse(get_option('name'));
    }

    public function testAddOptionDoesNotOverwrite(): void
    {
        self::assertTrue(add_option('once', 'first'));
        self::assertFalse(add_option('once', 'second'));
        self::assertSame('first', get_option('once'));
    }

    public function testTransientsAreSeparateFromOptions(): void
    {
        set_transient('key', 'value', 60);

        self::assertSame('value', get_transient('key'));
        self::assertFalse(get_option('key'));

        delete_transient('key');
        self::assertFalse(get_transient('key'));
    }

    public function testObjectCacheReportsWhetherItFound(): void
    {
        self::assertFalse(wp_cache_get('missing', 'grp', false, $found));
        self::assertFalse($found);

        wp_cache_set('key', 'cached', 'grp');
        self::assertSame('cached', wp_cache_get('key', 'grp', false, $found));
        self::assertTrue($found);

        wp_cache_flush();
        self::assertFalse(wp_cache_get('key', 'grp'));
    }

    public function testAddPostSeedsTypeAndStatusTogether(): void
    {
        WpState::addPost(7, ['post_title' => 'Hello', 'post_type' => 'meeting', 'post_status' => 'draft']);

        self::assertSame('Hello', get_the_title(7));
        self::assertSame('meeting', get_post_type(7));
        self::assertSame('draft', get_post_status(7));
        self::assertSame(7, get_post(7)?->ID);
    }

    public function testPostLookupsAcceptAnObjectOrAnId(): void
    {
        $post = WpState::addPost(3, ['post_type' => 'group']);

        self::assertSame('group', get_post_type($post));
        self::assertSame('group', get_post_type(3));
        self::assertSame(3, get_post($post)?->ID);
    }

    public function testMissingPostIsNull(): void
    {
        self::assertNull(get_post(999));
        self::assertFalse(get_post_type(999));
        self::assertFalse(get_post_status(999));
    }

    public function testPostMetaSingleAndArrayForms(): void
    {
        update_post_meta(1, 'colour', 'blue');

        self::assertSame('blue', get_post_meta(1, 'colour', true));
        self::assertSame(['blue'], get_post_meta(1, 'colour'));
        self::assertSame('', get_post_meta(1, 'absent', true));
        self::assertSame([], get_post_meta(1, 'absent'));
        self::assertSame(['colour' => 'blue'], get_post_meta(1));

        delete_post_meta(1, 'colour');
        self::assertSame('', get_post_meta(1, 'colour', true));
    }

    /**
     * WordPress hands every value back wrapped in an array, even single ones,
     * and calling code indexes [0] straight off the result.
     */
    public function testGetPostCustomAlwaysWrapsValuesInArrays(): void
    {
        update_post_meta(2, 'single', 'one');
        update_post_meta(2, 'many', ['a', 'b']);

        self::assertSame(['single' => ['one'], 'many' => ['a', 'b']], get_post_custom(2));
    }

    public function testInsertPostAllocatesAnIdAndRecordsTheCall(): void
    {
        $id = wp_insert_post(['post_title' => 'New', 'post_type' => 'group']);

        self::assertSame(100, $id);
        self::assertSame('New', get_the_title($id));
        self::assertSame('group', get_post_type($id));
        self::assertCount(1, WpState::$insertedPosts);

        self::assertSame(101, wp_insert_post(['post_title' => 'Another']), 'ids should not repeat');
    }

    public function testUpdatePostRecordsAndMutates(): void
    {
        WpState::addPost(5, ['post_title' => 'Before']);

        self::assertSame(5, wp_update_post(['ID' => 5, 'post_title' => 'After']));
        self::assertSame('After', get_the_title(5));
        self::assertSame([['ID' => 5, 'post_title' => 'After']], WpState::$updatedPosts);
    }

    public function testDeletePostRemovesItAndRecordsTheId(): void
    {
        WpState::addPost(9);

        wp_delete_post(9);

        self::assertNull(get_post(9));
        self::assertSame([9], WpState::$deletedPosts);
    }

    /**
     * The hook layer belongs to Brain Monkey, not to this package's stubs —
     * see the note in stubs/wordpress.php. Its behaviour is covered by
     * {@see HookAssertionsTest}. What is asserted here is only that this file
     * has not started shadowing it again, since doing so breaks Brain Monkey's
     * hook expectations silently.
     */
    public function testHookFunctionsComeFromBrainMonkey(): void
    {
        $reflected = new \ReflectionFunction('add_action');

        self::assertStringContainsString(
            'brain' . DIRECTORY_SEPARATOR . 'monkey',
            (string) $reflected->getFileName(),
            'add_action must be Brain Monkey\'s, or Actions\expectAdded() will never see the call'
        );
    }

    public function testPluginLifecycleHooksAreRecorded(): void
    {
        register_activation_hook(__FILE__, 'on_activate');
        register_deactivation_hook(__FILE__, 'on_deactivate');

        self::assertSame(['on_activate'], WpState::$activationHooks);
        self::assertSame(['on_deactivate'], WpState::$deactivationHooks);
    }

    public function testCapabilitiesCanBeDeniedIndividually(): void
    {
        self::assertTrue(current_user_can('manage_options'));

        WpState::$deniedCaps = ['manage_options'];

        self::assertFalse(current_user_can('manage_options'));
        self::assertTrue(current_user_can('read'), 'only the named cap should be denied');
    }

    public function testWpDieThrowsRatherThanExiting(): void
    {
        $this->expectException(WpDieException::class);
        $this->expectExceptionMessage('Nope');

        wp_die('Nope');
    }

    /**
     * The interesting thing about a guard clause is usually the status it
     * refuses with, and WordPress takes that in either of two positions.
     */
    public function testWpDieCarriesAStatusGivenAsTheSecondArgument(): void
    {
        try {
            wp_die('Forbidden', 403);
            self::fail('wp_die should have thrown');
        } catch (WpDieException $e) {
            self::assertSame('Forbidden', $e->getMessage());
            self::assertSame(403, $e->status);
        }
    }

    public function testWpDieCarriesAStatusGivenUnderResponse(): void
    {
        try {
            wp_die('Forbidden', 'Not allowed', ['response' => 401, 'back_link' => true]);
            self::fail('wp_die should have thrown');
        } catch (WpDieException $e) {
            self::assertSame(401, $e->status);
            self::assertSame('Not allowed', $e->title);
            self::assertTrue($e->args['back_link']);
        }
    }

    /** Null, not 0, so "no status given" stays distinguishable from a real 0. */
    public function testWpDieWithNoStatusReportsNullRatherThanZero(): void
    {
        try {
            wp_die('Nope');
            self::fail('wp_die should have thrown');
        } catch (WpDieException $e) {
            self::assertNull($e->status);
        }
    }

    public function testJsonResponsesCarryTheirPayload(): void
    {
        try {
            wp_send_json_error(['reason' => 'bad'], 400);
            self::fail('wp_send_json_error should have thrown');
        } catch (JsonResponseException $e) {
            self::assertFalse($e->success);
            self::assertSame(['reason' => 'bad'], $e->data);
            self::assertSame(400, $e->status);
        }
    }

    public function testRedirectsAreRecordedNotFollowed(): void
    {
        wp_safe_redirect('https://example.test/a');
        wp_redirect('https://example.test/b');

        self::assertSame(['https://example.test/a', 'https://example.test/b'], WpState::$redirects);
    }

    public function testNoncesRoundTrip(): void
    {
        $nonce = wp_create_nonce('save-thing');

        self::assertSame(1, wp_verify_nonce($nonce, 'save-thing'));
        self::assertFalse(wp_verify_nonce($nonce, 'a-different-action'));
    }

    public function testUrlHelpersUseTheConfiguredPluginSlug(): void
    {
        $original = WpState::$pluginSlug;
        WpState::$pluginSlug = 'unity';

        try {
            self::assertSame('https://example.test/wp-content/plugins/unity/css/a.css', plugins_url('css/a.css'));
            self::assertSame('https://example.test/wp-content/plugins/unity/', plugin_dir_url(__FILE__));
        } finally {
            WpState::$pluginSlug = $original;
        }
    }

    /**
     * $pluginSlug is set once in a plugin's bootstrap, so unlike everything
     * else in the store it must survive reset().
     */
    public function testResetKeepsThePluginSlug(): void
    {
        $original = WpState::$pluginSlug;
        WpState::$pluginSlug = 'tamar';
        WpState::$options['x'] = 1;

        WpState::reset();

        self::assertSame('tamar', WpState::$pluginSlug);
        self::assertSame([], WpState::$options);

        WpState::$pluginSlug = $original;
    }

    public function testIsWpErrorRecognisesTheStubClass(): void
    {
        self::assertTrue(is_wp_error(new \WP_Error('code', 'message')));
        self::assertFalse(is_wp_error('a string'));
        self::assertFalse(is_wp_error(null));
    }

    public function testTimeIsFixedSoOutputIsDeterministic(): void
    {
        WpState::$now = '2026-01-02 03:04:05';

        self::assertSame('2026-01-02', wp_date('Y-m-d'));
        self::assertSame(strtotime('2026-01-02 03:04:05'), current_time('timestamp'));
        self::assertSame('2026-01-02 03:04:05', current_time('mysql'));
    }

    public function testShortcodeAttsAppliesDefaults(): void
    {
        $atts = shortcode_atts(['a' => 1, 'b' => 2], ['b' => 9, 'c' => 3]);

        self::assertSame(['a' => 1, 'b' => 9], $atts, 'unknown attributes are dropped');
    }

    /**
     * The $wpdb output formats are the ones that bite: a repository calling
     * get_results($sql, ARRAY_A) from inside a namespace fatals on "Undefined
     * constant" without them, and every custom-table plugin in this suite does
     * exactly that.
     */
    public function testWordPressCoreConstantsAreDefined(): void
    {
        self::assertSame('ARRAY_A', ARRAY_A);
        self::assertSame('ARRAY_N', ARRAY_N);
        self::assertSame('OBJECT', OBJECT);
        self::assertSame('OBJECT_K', OBJECT_K);

        self::assertSame(60, MINUTE_IN_SECONDS);
        self::assertSame(3600, HOUR_IN_SECONDS);
        self::assertSame(86400, DAY_IN_SECONDS);
        self::assertSame(604800, WEEK_IN_SECONDS);
        self::assertSame(2592000, MONTH_IN_SECONDS);
        self::assertSame(31536000, YEAR_IN_SECONDS);
    }

    /**
     * Signatures a test has to be able to widen.
     *
     * Patchwork keeps a function's declared return type when Brain Monkey
     * redefines it, so a stub narrower than WordPress's own makes a real
     * scenario untestable: wp_insert_post()/wp_update_post() hand back a
     * WP_Error on failure, and get_permalink() returns false for a post that
     * does not exist.
     */
    public function testFailureShapedReturnsAreExpressible(): void
    {
        $error = new \WP_Error('db_insert_error', 'could not insert');

        \Brain\Monkey\Functions\when('wp_insert_post')->justReturn($error);
        \Brain\Monkey\Functions\when('wp_update_post')->justReturn($error);
        \Brain\Monkey\Functions\when('get_permalink')->justReturn(false);

        self::assertSame($error, wp_insert_post(['post_title' => 'x']));
        self::assertSame($error, wp_update_post(['ID' => 1]));
        self::assertFalse(get_permalink(1));
    }

    /**
     * Constants describing a particular installation are deliberately left to
     * the consuming plugin's bootstrap — several in this suite point ABSPATH
     * at a real temp directory so filesystem paths can be exercised.
     */
    public function testInstallationConstantsAreLeftToTheConsumer(): void
    {
        self::assertFalse(defined('WP_DEBUG'), 'WP_DEBUG is the plugin bootstrap to decide');
        self::assertFalse(defined('WP_PLUGIN_DIR'), 'WP_PLUGIN_DIR is the plugin bootstrap to decide');
    }
}
