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

    /**
     * An ordinary value is handed back unchanged. sanitize_url() is
     * WordPress's alias of esc_url_raw(), and code reaches for either.
     */
    public function testTheEscapingHelpersLeaveOrdinaryValuesAlone(): void
    {
        self::assertSame('https://a.test/x', esc_url_raw('https://a.test/x'));
        self::assertSame('https://a.test/x', sanitize_url('https://a.test/x'));
        self::assertSame('North', esc_html('North'));
        self::assertSame('North', esc_attr('North'));
    }

    /**
     * These used to pass their input through, on the reasoning that modelling
     * escaping would mean asserting against the stub rather than the code.
     *
     * It meant no escaping bug in a consuming plugin could fail a test, and
     * one duly shipped: Confur's HtmlHelper::createLink() built an <a> tag
     * from raw input, and the first regression tests written for it passed
     * against the unfixed code.
     */
    public function testEscHtmlAndEscAttrNeutraliseMarkup(): void
    {
        self::assertSame('&lt;b&gt;', esc_html('<b>'));
        self::assertSame('&quot; onfocus=&quot;x', esc_attr('" onfocus="x'));
        self::assertSame('&#039;', esc_attr("'"));
    }

    /**
     * Core passes double_encode = false, so an entity already in the string is
     * left alone rather than becoming &amp;amp;.
     */
    public function testEscHtmlDoesNotDoubleEncodeAnExistingEntity(): void
    {
        self::assertSame('a &amp; b', esc_html('a &amp; b'));
    }

    /**
     * esc_textarea() is the exception: textarea content is literal text, so
     * core double-encodes there.
     */
    public function testEscTextareaDoubleEncodes(): void
    {
        self::assertSame('a &amp;amp; b', esc_textarea('a &amp; b'));
    }

    public function testEscUrlRefusesADisallowedProtocol(): void
    {
        self::assertSame('', esc_url('javascript:alert(1)'));
        self::assertSame('', esc_url_raw('javascript:alert(1)'));
        self::assertSame('', esc_url('data:text/html;base64,PHN2Zz4='));
    }

    /**
     * Whitespace and control characters inside the scheme are how a refused
     * protocol gets smuggled past a check that only looks at the prefix.
     */
    public function testEscUrlRefusesASchemeSplitByControlCharacters(): void
    {
        self::assertSame('', esc_url("java\nscript:alert(1)"));
        self::assertSame('', esc_url("java\tscript:alert(1)"));
    }

    public function testEscUrlHonoursAnExplicitProtocolList(): void
    {
        self::assertSame('', esc_url('https://a.test/x', ['mailto']));
        self::assertSame('mailto:a@b.test', esc_url('mailto:a@b.test', ['mailto']));
    }

    /**
     * A URL with no scheme is left alone — core permits those, and refusing
     * them would break every relative link a consumer builds.
     */
    public function testEscUrlLeavesRelativeUrlsAlone(): void
    {
        self::assertSame('/meetings/?x=1', esc_url_raw('/meetings/?x=1'));
        self::assertSame('#frag', esc_url_raw('#frag'));
    }

    /**
     * The display form encodes the two characters core encodes; the raw form,
     * meant for storage and HTTP clients, does not.
     */
    public function testEscUrlEncodesForDisplayButEscUrlRawDoesNot(): void
    {
        self::assertSame('https://a.test/?a=1&#038;b=2', esc_url('https://a.test/?a=1&b=2'));
        self::assertSame('https://a.test/?a=1&b=2', esc_url_raw('https://a.test/?a=1&b=2'));
    }

    /**
     * Removing the tags but leaving the body behind would read as "escaped" to
     * a careless assertion, so the element goes with its contents.
     */
    public function testWpKsesPostRemovesScriptElementsEntirely(): void
    {
        self::assertSame('', wp_kses_post('<script>alert(1)</script>'));
        self::assertStringNotContainsString('alert(1)', wp_kses_post('<script>alert(1)</script>'));
    }

    public function testWpKsesPostRemovesEventHandlerAttributes(): void
    {
        self::assertStringNotContainsString('onclick', wp_kses_post('<a href="/x" onclick="alert(1)">y</a>'));
        self::assertStringNotContainsString('onerror', wp_kses_post('<img src="/x" onerror=alert(1)>'));
    }

    public function testWpKsesPostKeepsOrdinaryMarkup(): void
    {
        self::assertSame('<b>North</b>', wp_kses_post('<b>North</b>'));
        self::assertStringContainsString('href="/x"', wp_kses_post('<a href="/x">y</a>'));
    }

    public function testWpKsesPostDropsAJavascriptHref(): void
    {
        self::assertStringNotContainsString('javascript:', wp_kses_post('<a href="javascript:alert(1)">y</a>'));
    }

    /**
     * __() and esc_html__() are not interchangeable; a stub treating them as
     * such hides the one bug worth catching.
     */
    public function testTheTranslateAndEscapeHelpersEscape(): void
    {
        self::assertSame('&lt;b&gt;', esc_html__('<b>', 'd'));
        self::assertSame('&lt;b&gt;', esc_attr__('<b>', 'd'));
        self::assertSame('<b>', __('<b>', 'd'), '__() translates only');
    }

    public function testSanitizeEmailReturnsEmptyForSomethingThatIsNotAnAddress(): void
    {
        self::assertSame('a@b.test', sanitize_email(' a@b.test '));
        self::assertSame('', sanitize_email('not-an-email'));
    }

    /**
     * The one that bites: WordPress hands back integers here, so a stub
     * returning objects regardless would have the caller iterating posts
     * where it expects ids — and failing somewhere downstream.
     */
    public function testGetPostsCanAnswerWithIdsRatherThanObjects(): void
    {
        WpState::$queryPosts = [
            (object) ['ID' => 1, 'post_type' => 'answer'],
            (object) ['ID' => 2, 'post_type' => 'answer'],
        ];

        self::assertSame([1, 2], get_posts(['fields' => 'ids']));
        self::assertIsObject(get_posts()[0]);
    }

    public function testGetPostsFiltersByTypeStatusAndInclusion(): void
    {
        WpState::$queryPosts = [
            (object) ['ID' => 1, 'post_type' => 'answer', 'post_status' => 'publish'],
            (object) ['ID' => 2, 'post_type' => 'page', 'post_status' => 'publish'],
            (object) ['ID' => 3, 'post_type' => 'answer', 'post_status' => 'draft'],
        ];

        self::assertSame([1, 3], get_posts(['post_type' => 'answer', 'fields' => 'ids']));
        self::assertSame([1, 2], get_posts(['post_status' => 'publish', 'fields' => 'ids']));
        self::assertSame([2, 3], get_posts(['post__not_in' => [1], 'fields' => 'ids']));
        self::assertSame([3], get_posts(['post__in' => [3], 'fields' => 'ids']));
    }

    /** Whatever was asked for stays assertable, filtered or not. */
    public function testGetPostsRecordsTheArgumentsItWasCalledWith(): void
    {
        get_posts(['post_type' => 'answer', 'numberposts' => 5]);

        self::assertSame(
            ['post_type' => 'answer', 'numberposts' => 5],
            WpState::$options['__last_get_posts_args']
        );
    }

    /**
     * The stand-ins have to be uncatchable by the code they interrupt. In
     * production these functions end the request; a handler wrapping the call
     * in catch (\Exception) would otherwise swallow the stub and carry on
     * into an error path the real code never reaches, and the test would then
     * be asserting against a failure that cannot happen.
     */
    public function testTheTerminatingFunctionsEscapeACatchAllForExceptions(): void
    {
        $caught = null;

        try {
            try {
                wp_send_json_success(['ok' => true]);
            } catch (\Exception $e) {          // as a handler would write it
                self::fail('the stub must not be catchable as an Exception');
            }
        } catch (JsonResponseException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertTrue($caught->success);
    }

    public function testWpDieAlsoEscapesACatchAllForExceptions(): void
    {
        $caught = null;

        try {
            try {
                wp_die('Forbidden', 403);
            } catch (\Exception $e) {
                self::fail('the stub must not be catchable as an Exception');
            }
        } catch (WpDieException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertSame(403, $caught->status);
    }

    /**
     * A stub must not be stricter than what it stands in for. Core declares
     * both of these untyped and guards with is_scalar(), so a caller handing
     * one an array gets '' rather than a TypeError — and a test exercising a
     * hostile-input path then sees the branch the real code would take.
     */
    public function testSanitiseHelpersTolerateNonScalarsAsCoreDoes(): void
    {
        self::assertSame('', sanitize_key(['an', 'array']));
        self::assertSame('', sanitize_title(['an', 'array']));
        self::assertSame('fallback', sanitize_title(['an', 'array'], 'fallback'));
    }

    /**
     * Silently, too. Core returns '' before touching the value, so no "Array
     * to string conversion" warning is emitted — and a suite running with
     * failOnWarning would otherwise fail on a path production handles fine.
     */
    public function testSanitiseTextHelpersRejectNonScalarsWithoutWarning(): void
    {
        set_error_handler(static function (int $errno, string $message): bool {
            throw new \RuntimeException('unexpected warning: ' . $message);
        }, E_WARNING);

        try {
            self::assertSame('', sanitize_text_field(['an', 'array']));
            self::assertSame('', sanitize_textarea_field(['an', 'array']));
            self::assertSame('', sanitize_text_field(new \stdClass()));
        } finally {
            restore_error_handler();
        }
    }

    public function testSanitiseHelpersStillSanitiseScalars(): void
    {
        self::assertSame('my_key-2', sanitize_key('My_Key-2!!'));
        self::assertSame('a-title', sanitize_title('A Title!'));
        self::assertSame('fallback', sanitize_title('!!!', 'fallback'));
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
