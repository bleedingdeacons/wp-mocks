<?php

declare(strict_types=1);

namespace BleedingDeacons\WpMocks\Tests;

use BleedingDeacons\WpMocks\TestCase;
use BleedingDeacons\WpMocks\WpState;
use Brain\Monkey\Functions;

final class SentinelStubsTest extends TestCase
{
    /**
     * HasLogger caches the channel it is handed, so a test asserting on that
     * channel's calls has to be looking at the same object the code used.
     */
    public function testChannelsAreMemoisedByName(): void
    {
        self::assertSame(wp_log('unity'), wp_log('unity'));
        self::assertNotSame(wp_log('unity'), wp_log('amber'));
    }

    public function testEveryLevelIsRecordedInOrder(): void
    {
        $channel = wp_log('levels-test');
        $channel->calls = [];

        $channel->emergency('a');
        $channel->error('b');
        $channel->debug('c');

        self::assertSame(['emergency', 'error', 'debug'], $channel->levels());
    }

    public function testCallsAreMirroredIntoTheSharedState(): void
    {
        wp_log('mirror-test')?->warning('careful', ['id' => 3]);

        self::assertContains(['mirror-test', 'warning', 'careful', ['id' => 3]], WpState::$logs);
    }

    /**
     * Code guarded by function_exists('wp_log') cannot be exercised for the
     * "no logger" branch by removing the function — it is already defined. The
     * way to do it is to have wp_log() answer null, which only works if the
     * stub's return type is nullable: Patchwork keeps the original signature
     * when Brain Monkey redefines a function, so a non-nullable type would
     * turn this into a TypeError.
     */
    public function testWpLogCanBeMadeToReturnNullSoTheDegradedPathIsTestable(): void
    {
        Functions\when('wp_log')->justReturn(null);

        self::assertNull(wp_log('anything'));
    }
}
