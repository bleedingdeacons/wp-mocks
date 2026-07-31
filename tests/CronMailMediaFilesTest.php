<?php

declare(strict_types=1);

namespace BleedingDeacons\WpMocks\Tests;

use BleedingDeacons\WpMocks\TestCase;
use BleedingDeacons\WpMocks\WpState;

/**
 * The surface added for the second wave of plugin migrations: cron, mail,
 * media, the filesystem helpers, and the handful of post and URL functions
 * that were still being hand-rolled.
 */
final class CronMailMediaFilesTest extends TestCase
{
    // ── Cron ─────────────────────────────────────────────────────────

    /**
     * An activation routine's usual shape is "schedule unless already
     * scheduled", so both answers have to be reachable.
     */
    public function testAnUnscheduledHookHasNoNextRun(): void
    {
        self::assertFalse(wp_next_scheduled('trumpet_daily_sweep'));
    }

    public function testSchedulingAnEventMakesItFindable(): void
    {
        wp_schedule_event(1_800_000_000, 'daily', 'trumpet_daily_sweep');

        self::assertSame(1_800_000_000, wp_next_scheduled('trumpet_daily_sweep'));
    }

    public function testUnschedulingRemovesIt(): void
    {
        wp_schedule_event(1_800_000_000, 'daily', 'trumpet_daily_sweep');
        wp_unschedule_event(1_800_000_000, 'trumpet_daily_sweep');

        self::assertFalse(wp_next_scheduled('trumpet_daily_sweep'));
    }

    /** WordPress reports how many events it cleared; callers log that number. */
    public function testClearingReportsHowManyWentAndIsSafeWhenNothingWasThere(): void
    {
        wp_schedule_single_event(1_800_000_000, 'reach_purge_attempts');

        self::assertSame(1, wp_clear_scheduled_hook('reach_purge_attempts'));
        self::assertSame(0, wp_clear_scheduled_hook('reach_purge_attempts'));
    }

    // ── Mail ─────────────────────────────────────────────────────────

    public function testMailIsRecordedRatherThanSent(): void
    {
        wp_mail('a@b.test', 'Subject', 'Body', ['X-Test: 1']);

        self::assertCount(1, WpState::$mail);
        self::assertSame('a@b.test', WpState::$mail[0]['to']);
        self::assertSame('Subject', WpState::$mail[0]['subject']);
        self::assertSame(['X-Test: 1'], WpState::$mail[0]['headers']);
    }

    public function testASendFailureCanBeSimulated(): void
    {
        WpState::$mailResult = false;

        self::assertFalse(wp_mail('a@b.test', 'S', 'B'));
        // Still recorded: the caller's error branch usually asserts on both.
        self::assertCount(1, WpState::$mail);
    }

    /**
     * is_email() answers with the address, not true — code that assigns the
     * result rather than branching on it would notice the difference.
     */
    public function testIsEmailAnswersWithTheAddressOrFalse(): void
    {
        self::assertSame('a@b.test', is_email('a@b.test'));
        self::assertFalse(is_email('not-an-email'));
    }

    // ── Media ────────────────────────────────────────────────────────

    public function testAPostWithNoFeaturedImageAnswersZero(): void
    {
        self::assertSame(0, get_post_thumbnail_id(101));
        self::assertFalse(has_post_thumbnail(101));
    }

    public function testASeededThumbnailResolvesToItsSource(): void
    {
        WpState::$thumbnails[101] = 55;
        WpState::$attachments[55] = ['https://example.test/img.jpg', 800, 600, false];

        self::assertSame(55, get_post_thumbnail_id(101));
        self::assertTrue(has_post_thumbnail(101));
        self::assertSame(
            ['https://example.test/img.jpg', 800, 600, false],
            wp_get_attachment_image_src(55, 'full')
        );
        self::assertSame('https://example.test/img.jpg', wp_get_attachment_url(55));
    }

    /**
     * An unknown attachment is false, not a plausible tuple — render code
     * branches on exactly that.
     */
    public function testAnUnknownAttachmentIsFalse(): void
    {
        self::assertFalse(wp_get_attachment_image_src(999));
        self::assertFalse(wp_get_attachment_url(999));
    }

    // ── Filesystem ───────────────────────────────────────────────────

    public function testFileNamesAreSanitised(): void
    {
        self::assertSame('my-report-2026.csv', sanitize_file_name('my report 2026.csv'));
        self::assertSame('a-b.xlsx', sanitize_file_name('a/b.xlsx'));
    }

    /** The suffix walks until the name is free, as WordPress's does. */
    public function testAUniqueFilenameAvoidsWhatIsAlreadyThere(): void
    {
        $dir = get_temp_dir() . 'wp-mocks-unique-' . getmypid();
        @mkdir($dir, 0777, true);

        try {
            self::assertSame('report.csv', wp_unique_filename($dir, 'report.csv'));

            touch($dir . '/report.csv');
            self::assertSame('report-1.csv', wp_unique_filename($dir, 'report.csv'));

            touch($dir . '/report-1.csv');
            self::assertSame('report-2.csv', wp_unique_filename($dir, 'report.csv'));
        } finally {
            array_map('unlink', glob($dir . '/*') ?: []);
            @rmdir($dir);
        }
    }

    public function testTheUploadDirectoryReportsNoError(): void
    {
        $upload = wp_upload_dir();

        self::assertFalse($upload['error']);
        self::assertSame($upload['basedir'], $upload['path']);
    }

    // ── Posts and URLs ───────────────────────────────────────────────

    public function testAPageIsFoundByItsSlug(): void
    {
        WpState::addPost(12, ['post_name' => 'lookup', 'post_type' => 'page']);

        self::assertSame(12, get_page_by_path('lookup')?->ID);
        self::assertSame(12, get_page_by_path('/lookup/')?->ID);
        self::assertNull(get_page_by_path('nowhere'));
    }

    /** Round-trips get_permalink()'s own output; anything else is 0. */
    public function testAPermalinkResolvesBackToItsPostId(): void
    {
        self::assertSame(42, url_to_postid(get_permalink(42)));
        self::assertSame(0, url_to_postid('https://example.test/unrecognised/'));
    }

    public function testPublishingAndTrashingMoveTheStatus(): void
    {
        WpState::addPost(7, ['post_status' => 'draft']);

        wp_publish_post(7);
        self::assertSame('publish', get_post_status(7));

        self::assertNotFalse(wp_trash_post(7));
        self::assertSame('trash', get_post_status(7));
    }

    public function testTrashingSomethingThatIsNotThereIsFalse(): void
    {
        self::assertFalse(wp_trash_post(999));
    }

    /**
     * The point of calling wp_validate_redirect() is the open-redirect guard,
     * so a stub that waved everything through would make it untestable.
     */
    public function testAnOffsiteRedirectFallsBack(): void
    {
        self::assertSame(
            '/safe',
            wp_validate_redirect('https://evil.test/steal', '/safe')
        );
    }

    public function testASameSiteOrRelativeRedirectSurvives(): void
    {
        self::assertSame('/dashboard', wp_validate_redirect('/dashboard', '/safe'));
        self::assertSame(home_url() . '/x', wp_validate_redirect(home_url() . '/x', '/safe'));
    }

    public function testSaltsAreFixedPerSchemeSoDerivedValuesReproduce(): void
    {
        self::assertSame(wp_salt('auth'), wp_salt('auth'));
        self::assertNotSame(wp_salt('auth'), wp_salt('nonce'));
    }

    public function testAShortcodeCanBeRemovedAgain(): void
    {
        add_shortcode('trumpet', static fn (): string => '');
        self::assertTrue(shortcode_exists('trumpet'));

        remove_shortcode('trumpet');
        self::assertFalse(shortcode_exists('trumpet'));
    }

    public function testSingularIsOffUntilAskedFor(): void
    {
        self::assertFalse(is_singular('announcement'));

        WpState::$isSingular = true;
        self::assertTrue(is_singular('announcement'));
    }

    public function testAcfSavePostWritesTheValuesItIsGiven(): void
    {
        acf_save_post(101, ['title' => 'Tuesday', 'hide' => true]);

        self::assertSame('Tuesday', get_field('title', 101));
        self::assertTrue(get_field('hide', 101));
    }
}
