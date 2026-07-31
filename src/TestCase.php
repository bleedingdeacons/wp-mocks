<?php

declare(strict_types=1);

namespace BleedingDeacons\WpMocks;

use Brain\Monkey;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base TestCase for plugin suites using this package.
 *
 * Drives Brain Monkey by hand rather than extending anything of its own, which
 * matches how the suite already drove WP_Mock and keeps plugins free to use
 * their own base class instead — everything here is also available as the
 * {@see HookAssertions} trait.
 *
 * MockeryPHPUnitIntegration is not optional. Brain Monkey's expectations are
 * Mockery expectations, and without the trait a test that only sets
 * expectations registers zero PHPUnit assertions — which every plugin in this
 * suite treats as a failure, since they all run with failOnRisky="true".
 */
abstract class TestCase extends PHPUnitTestCase
{
    use MockeryPHPUnitIntegration;
    use HookAssertions;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        WpState::reset();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }
}
