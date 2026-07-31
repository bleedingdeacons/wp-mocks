<?php

declare(strict_types=1);

namespace BleedingDeacons\WpMocks\Tests;

use BleedingDeacons\WpMocks\Doubles\FakeWpdb;
use BleedingDeacons\WpMocks\Doubles\FakeWpHttp;
use BleedingDeacons\WpMocks\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use WP_Error;

#[CoversClass(FakeWpdb::class)]
#[CoversClass(FakeWpHttp::class)]
final class DoublesTest extends TestCase
{
    private FakeWpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wpdb = new FakeWpdb();
        FakeWpHttp::reset();
    }

    public function testPrepareInterpolatesPlaceholdersInOrder(): void
    {
        $sql = $this->wpdb->prepare('SELECT * FROM t WHERE a = %s AND b = %d', 'x', 5);

        self::assertSame("SELECT * FROM t WHERE a = 'x' AND b = 5", $sql);
    }

    public function testPrepareFlattensASingleArrayArgument(): void
    {
        $sql = $this->wpdb->prepare('WHERE a = %s AND b = %s', ['one', 'two']);

        self::assertSame("WHERE a = 'one' AND b = 'two'", $sql);
    }

    public function testEveryQueryMethodRecordsItsStatement(): void
    {
        $this->wpdb->results = [['id' => 1]];
        $this->wpdb->row = ['id' => 2];
        $this->wpdb->col = [3];
        $this->wpdb->var = 4;

        self::assertSame([['id' => 1]], $this->wpdb->get_results('SELECT 1'));
        self::assertSame(['id' => 2], $this->wpdb->get_row('SELECT 2'));
        self::assertSame([3], $this->wpdb->get_col('SELECT 3'));
        self::assertSame(4, $this->wpdb->get_var('SELECT 4'));

        self::assertSame(['SELECT 1', 'SELECT 2', 'SELECT 3', 'SELECT 4'], $this->wpdb->queries);
        self::assertSame('SELECT 4', $this->wpdb->lastQuery());
    }

    public function testWritesAreRecordedWithTheirFormats(): void
    {
        $this->wpdb->insert('wp_things', ['name' => 'a'], ['%s']);
        $this->wpdb->update('wp_things', ['name' => 'b'], ['id' => 1], ['%s'], ['%d']);
        $this->wpdb->delete('wp_things', ['id' => 1], ['%d']);

        self::assertSame([['wp_things', ['name' => 'a'], ['%s']]], $this->wpdb->inserts);
        self::assertSame([['wp_things', ['name' => 'b'], ['id' => 1], ['%s'], ['%d']]], $this->wpdb->updates);
        self::assertSame([['wp_things', ['id' => 1], ['%d']]], $this->wpdb->deletes);
    }

    public function testAFailedWriteCanBeSimulated(): void
    {
        $this->wpdb->insertResult = false;

        self::assertFalse($this->wpdb->insert('wp_things', ['name' => 'a']));
    }

    public function testResetClearsRecordedAndQueuedState(): void
    {
        $this->wpdb->get_results('SELECT 1');
        $this->wpdb->insert('t', []);
        $this->wpdb->insertResult = false;

        $this->wpdb->reset();

        self::assertSame([], $this->wpdb->queries);
        self::assertSame([], $this->wpdb->inserts);
        self::assertSame(1, $this->wpdb->insertResult);
    }

    public function testHttpReturnsQueuedResponsesInOrder(): void
    {
        FakeWpHttp::pushResponse(200, 'first');
        FakeWpHttp::pushResponse(404, 'second');

        $a = wp_remote_get('https://api.test/a');
        $b = wp_remote_get('https://api.test/b');

        self::assertSame('first', wp_remote_retrieve_body($a));
        self::assertSame(200, wp_remote_retrieve_response_code($a));
        self::assertSame('second', wp_remote_retrieve_body($b));
        self::assertSame(404, wp_remote_retrieve_response_code($b));
    }

    public function testHttpRecordsWhatWasSent(): void
    {
        FakeWpHttp::pushResponse(200);

        wp_remote_post('https://api.test/send', ['body' => 'payload', 'headers' => ['X-Test' => '1']]);

        self::assertSame(1, FakeWpHttp::callCount());
        self::assertSame('https://api.test/send', FakeWpHttp::sentUrl(0));
        self::assertSame('payload', FakeWpHttp::sentArgs(0)['body']);
        self::assertSame('POST', FakeWpHttp::sentArgs(0)['method']);
    }

    /**
     * An unscripted call is nearly always a test bug, so it surfaces as a
     * WP_Error rather than silently returning something plausible.
     */
    public function testAnUnscriptedCallReturnsAWpError(): void
    {
        $response = wp_remote_get('https://api.test/unscripted');

        self::assertInstanceOf(WP_Error::class, $response);
        self::assertSame('fake_http_no_response', $response->get_error_code());
    }

    public function testAQueuedWpErrorSimulatesANetworkFailure(): void
    {
        FakeWpHttp::push(new WP_Error('http_request_failed', 'timed out'));

        $response = wp_remote_get('https://api.test/down');

        self::assertTrue(is_wp_error($response));
        self::assertSame('timed out', $response->get_error_message());
    }
}
