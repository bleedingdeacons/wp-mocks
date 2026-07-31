<?php

declare(strict_types=1);

namespace BleedingDeacons\WpMocks\Tests;

use BleedingDeacons\WpMocks\HookAssertions;
use BleedingDeacons\WpMocks\TestCase;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversTrait;

#[CoversTrait(HookAssertions::class)]
final class HookAssertionsTest extends TestCase
{
    public function testAssertActionAddedPassesForARegisteredHook(): void
    {
        add_action('init', 'my_callback');

        $this->assertActionAdded('init');
        $this->assertActionAdded('init', 'my_callback');
    }

    public function testAssertActionAddedFailsWhenNothingWasHooked(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that action "init" was added.');

        $this->assertActionAdded('init');
    }

    public function testAssertActionAddedFailsForTheWrongCallback(): void
    {
        add_action('init', 'my_callback');

        $this->expectException(AssertionFailedError::class);

        $this->assertActionAdded('init', 'a_different_callback');
    }

    public function testAssertFilterAddedMatchesArrayCallables(): void
    {
        add_filter('the_content', [self::class, 'aStaticCallback']);

        $this->assertFilterAdded('the_content');
        $this->assertFilterAdded('the_content', [self::class, 'aStaticCallback']);
    }

    public function testAssertActionNotAdded(): void
    {
        $this->assertActionNotAdded('never_registered');

        add_action('registered', 'cb');

        $this->expectException(AssertionFailedError::class);
        $this->assertActionNotAdded('registered');
    }

    public function testAssertFilterNotAdded(): void
    {
        $this->assertFilterNotAdded('never_registered');
    }

    public function testAssertActionFiredAndCounted(): void
    {
        do_action('unity/loaded', 'container');
        do_action('unity/loaded', 'container');

        $this->assertActionFired('unity/loaded');
        $this->assertActionFiredTimes('unity/loaded', 2);
    }

    public function testAssertActionFiredFailsWhenItNeverRan(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that action "unity/loaded" was fired.');

        $this->assertActionFired('unity/loaded');
    }

    public function testAssertFilterApplied(): void
    {
        apply_filters('unity/members', []);

        $this->assertFilterApplied('unity/members');
    }

    /**
     * Hook state must not survive into the next test, or assertions about
     * "was this registered" become order-dependent.
     */
    public function testHookStateDoesNotLeakBetweenTests(): void
    {
        $this->assertActionNotAdded('init');
        $this->assertActionNotAdded('registered');
    }

    public static function aStaticCallback(string $content): string
    {
        return $content;
    }
}
