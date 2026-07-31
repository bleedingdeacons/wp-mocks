<?php

declare(strict_types=1);

namespace BleedingDeacons\WpMocks;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;

/**
 * After-the-fact assertions about hooks a class registered.
 *
 * Unity, Integrity, Sentinel, Trusted and Trumpet each carried their own copy
 * of assertActionAdded()/assertFilterAdded() over WP_Mock::onActionAdded().
 * This is the one copy, rewritten over Brain Monkey's equivalents.
 *
 * These read Brain Monkey's hook store *after* the code has run, which is what
 * the existing tests do, so migrating them needs no restructuring. That is a
 * different style from Brain Monkey's own Actions\expectAdded(), which is set
 * up *before* the code runs and verified at teardown. Both work; use whichever
 * reads better.
 *
 * One thing only the expectation style can do is assert on the arguments a
 * hook fired with — Brain Monkey records that a hook ran, not what it was
 * handed. For that, use Actions\expectDone('hook')->once()->with($args)
 * before exercising the code.
 *
 * Provided as a trait as well as being mixed into {@see TestCase}, so a plugin
 * that already has its own base class can use it without reparenting.
 *
 * Like WordPress's own has_action(), Brain Monkey's Actions\has() answers with
 * the hook's *priority* when a callback is given — an int, and possibly 0 —
 * and false otherwise. So these assert on "not false" rather than "true".
 *
 * @method void assertNotFalse(mixed $condition, string $message = '')
 * @method void assertSame(mixed $expected, mixed $actual, string $message = '')
 * @method void assertGreaterThan(mixed $expected, mixed $actual, string $message = '')
 */
trait HookAssertions
{
    /**
     * Assert an action was registered, optionally with a specific callback.
     */
    protected function assertActionAdded(string $action, mixed $callback = false, string $message = ''): void
    {
        $this->assertNotFalse(
            Actions\has($action, $callback),
            $message !== '' ? $message : sprintf(
                'Failed asserting that action "%s" was added%s.',
                $action,
                $callback === false ? '' : ' with the expected callback'
            )
        );
    }

    /**
     * Assert a filter was registered, optionally with a specific callback.
     */
    protected function assertFilterAdded(string $filter, mixed $callback = false, string $message = ''): void
    {
        $this->assertNotFalse(
            Filters\has($filter, $callback),
            $message !== '' ? $message : sprintf(
                'Failed asserting that filter "%s" was added%s.',
                $filter,
                $callback === false ? '' : ' with the expected callback'
            )
        );
    }

    /**
     * Assert nothing was hooked to an action.
     */
    protected function assertActionNotAdded(string $action, mixed $callback = false, string $message = ''): void
    {
        $this->assertSame(
            false,
            Actions\has($action, $callback),
            $message !== '' ? $message : sprintf('Failed asserting that nothing was hooked to action "%s".', $action)
        );
    }

    /**
     * Assert nothing was hooked to a filter.
     */
    protected function assertFilterNotAdded(string $filter, mixed $callback = false, string $message = ''): void
    {
        $this->assertSame(
            false,
            Filters\has($filter, $callback),
            $message !== '' ? $message : sprintf('Failed asserting that nothing was hooked to filter "%s".', $filter)
        );
    }

    /**
     * Assert an action was fired at least once.
     *
     * To assert on the arguments it fired with, set the expectation up front
     * instead: Actions\expectDone('hook')->once()->with($args).
     */
    protected function assertActionFired(string $action, string $message = ''): void
    {
        $this->assertGreaterThan(
            0,
            Actions\did($action),
            $message !== '' ? $message : sprintf('Failed asserting that action "%s" was fired.', $action)
        );
    }

    /**
     * Assert an action fired an exact number of times.
     */
    protected function assertActionFiredTimes(string $action, int $times, string $message = ''): void
    {
        $this->assertSame(
            $times,
            Actions\did($action),
            $message !== '' ? $message : sprintf(
                'Failed asserting that action "%s" fired %d time(s).',
                $action,
                $times
            )
        );
    }

    /**
     * Assert a filter was applied at least once.
     */
    protected function assertFilterApplied(string $filter, string $message = ''): void
    {
        $this->assertGreaterThan(
            0,
            Filters\applied($filter),
            $message !== '' ? $message : sprintf('Failed asserting that filter "%s" was applied.', $filter)
        );
    }
}
