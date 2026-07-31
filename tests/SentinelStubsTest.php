<?php

declare(strict_types=1);

namespace BleedingDeacons\WpMocks\Tests;

use BleedingDeacons\WpMocks\TestCase;
use BleedingDeacons\WpMocks\WpState;

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
        wp_log('mirror-test')->warning('careful', ['id' => 3]);

        self::assertContains(['mirror-test', 'warning', 'careful', ['id' => 3]], WpState::$logs);
    }
}
