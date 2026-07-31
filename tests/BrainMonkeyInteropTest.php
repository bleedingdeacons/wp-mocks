<?php

declare(strict_types=1);

namespace BleedingDeacons\WpMocks\Tests;

use BleedingDeacons\WpMocks\TestCase;
use BleedingDeacons\WpMocks\WpState;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\Depends;

/**
 * The load-bearing assumption of this whole package: that Brain Monkey can
 * override functions this package has already defined.
 *
 * Nothing else here works if it cannot. Patchwork refuses to redefine a
 * function whose defining file was included before Patchwork itself, and
 * because Patchwork has no Composer autoload entry, the naive bootstrap gets
 * exactly that failure. {@see \BleedingDeacons\WpMocks\Bootstrap} exists to
 * prevent it, and these tests are what keep it honest — including across
 * PHPUnit majors, which is why CI runs them on 10.5, 11 and 12.
 */
final class BrainMonkeyInteropTest extends TestCase
{
    public function testStubsAreBackedByTheSharedState(): void
    {
        update_option('foo', 'bar');

        self::assertSame('bar', get_option('foo'));
        self::assertSame('fallback', get_option('missing', 'fallback'));
    }

    public function testBrainMonkeyCanOverrideAnAlreadyDefinedStub(): void
    {
        Functions\when('get_option')->justReturn('patched');

        self::assertSame('patched', get_option('anything'));
    }

    #[Depends('testBrainMonkeyCanOverrideAnAlreadyDefinedStub')]
    public function testAnOverrideDoesNotLeakIntoTheNextTest(): void
    {
        update_option('foo', 'baz');

        self::assertSame('baz', get_option('foo'), 'the previous test\'s override should have been reverted');
    }

    public function testExpectationsWorkOnFunctionsNoStubDefines(): void
    {
        Functions\expect('some_plugin_specific_function')->once()->with('x')->andReturn('y');

        self::assertSame('y', some_plugin_specific_function('x'));
    }

    public function testAnAliasCanDelegateBackToTheSharedState(): void
    {
        Functions\when('get_transient')->alias(
            static fn (string $key): mixed => WpState::$options['t_' . $key] ?? false
        );
        WpState::$options['t_cache'] = 'hit';

        self::assertSame('hit', get_transient('cache'));
    }

    public function testReturnArgReplacesWpMockPassthruFunction(): void
    {
        Functions\when('esc_html')->returnArg();

        self::assertSame('<b>', esc_html('<b>'));
    }

    public function testActionExpectations(): void
    {
        Actions\expectAdded('init')->once()->with('some_callback', 10, 1);
        add_action('init', 'some_callback');

        Actions\expectDone('my/action')->once()->with('payload');
        do_action('my/action', 'payload');
    }

    public function testFilterExpectations(): void
    {
        Filters\expectApplied('my/filter')->once()->andReturn('filtered');

        self::assertSame('filtered', apply_filters('my/filter', 'original'));
    }

    /**
     * This test deliberately makes no assertion of its own — only a Brain
     * Monkey expectation, which is a Mockery expectation underneath.
     *
     * Mockery's count is folded into PHPUnit's via MockeryPHPUnitIntegration,
     * and only in assertPostConditions() after the body has run, so it cannot
     * be asserted on from in here. Instead the guard is structural: this
     * package runs with failOnRisky="true", so if that trait ever came off
     * the base TestCase, this test would be reported as risky — "did not
     * perform any assertions" — and fail the build.
     */
    public function testAnExpectationOnlyTestIsNotReportedAsRisky(): void
    {
        Functions\expect('wp_send_json_success')->once()->with(['ok' => true]);

        wp_send_json_success(['ok' => true]);
    }

    public function testTheBaseTestCaseCarriesTheMockeryIntegration(): void
    {
        self::assertContains(
            \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration::class,
            class_uses(TestCase::class) ?: [],
            'Without this trait every expectation-only test in the suite becomes risky.'
        );
    }
}
