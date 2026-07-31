<?php

declare(strict_types=1);

namespace BleedingDeacons\WpMocks\Tests;

use BleedingDeacons\WpMocks\TestCase;
use BleedingDeacons\WpMocks\WpState;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * The `rest` group exists so a controller's route callback can be called
 * directly — handed a request, asserted on the response — without a REST
 * server. These cover the parts three plugins had each reimplemented.
 */
final class RestStubsTest extends TestCase
{
    public function testAParamCanBeSeededAndRead(): void
    {
        $request = new WP_REST_Request(['id' => 42]);
        $request->set_param('slug', 'tuesday');

        self::assertSame(42, $request->get_param('id'));
        self::assertSame('tuesday', $request->get_param('slug'));
        self::assertNull($request->get_param('absent'));
        self::assertSame(['id' => 42, 'slug' => 'tuesday'], $request->get_params());
    }

    /**
     * The stub keeps one undifferentiated parameter set, so the three
     * source-specific accessors all answer with it. Consumers here read
     * whichever one their controller happens to call.
     */
    public function testTheSourceSpecificAccessorsAllSeeTheSameParams(): void
    {
        $request = new WP_REST_Request(['page' => 2]);

        self::assertSame(['page' => 2], $request->get_query_params());
        self::assertSame(['page' => 2], $request->get_body_params());
        self::assertSame(['page' => 2], $request->get_url_params());
    }

    /**
     * WordPress lower-cases header names on the way in, so a controller
     * reading 'Authorization' must find one set as 'authorization'.
     */
    public function testHeaderLookupIsCaseInsensitive(): void
    {
        $request = new WP_REST_Request();
        $request->set_header('Authorization', 'Bearer token');

        self::assertSame('Bearer token', $request->get_header('authorization'));
        self::assertSame('Bearer token', $request->get_header('AUTHORIZATION'));
        self::assertNull($request->get_header('x-absent'));
    }

    public function testAJsonBodyDecodesToParams(): void
    {
        $request = new WP_REST_Request();
        $request->set_body('{"name":"Tuesday","size":12}');

        self::assertSame(['name' => 'Tuesday', 'size' => 12], $request->get_json_params());
    }

    public function testAnUndecodableBodyIsNullRatherThanAnEmptyArray(): void
    {
        $request = new WP_REST_Request();
        $request->set_body('not json');

        self::assertNull($request->get_json_params());
    }

    public function testAResponseCarriesDataStatusAndHeaders(): void
    {
        $response = new WP_REST_Response(['ok' => true], 201);
        $response->header('X-Total', '3');

        self::assertSame(['ok' => true], $response->get_data());
        self::assertSame(201, $response->get_status());
        self::assertSame(['X-Total' => '3'], $response->get_headers());

        $response->set_status(202);
        self::assertSame(202, $response->get_status());
    }

    public function testRestEnsureResponseWrapsBareData(): void
    {
        $response = rest_ensure_response(['id' => 1]);

        self::assertInstanceOf(WP_REST_Response::class, $response);
        self::assertSame(['id' => 1], $response->get_data());
        self::assertSame(200, $response->get_status());
    }

    public function testRestEnsureResponsePassesThroughWhatIsAlreadyOne(): void
    {
        $original = new WP_REST_Response(null, 204);

        self::assertSame($original, rest_ensure_response($original));
    }

    /**
     * A controller returning WP_Error is how it signals a 4xx, so wrapping one
     * in a 200 response would hide the failure.
     */
    public function testRestEnsureResponsePassesThroughAWpError(): void
    {
        $error = new WP_Error('forbidden', 'Nope');

        self::assertSame($error, rest_ensure_response($error));
    }

    /**
     * register_rest_route() belongs to the `wordpress` group and records into
     * WpState; the method constants a controller names live here. Both halves
     * are needed to assert a route was declared as intended.
     */
    public function testRouteRegistrationRecordsTheDeclaredMethods(): void
    {
        register_rest_route('reach/v1', '/members', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => static fn (): string => 'ok',
        ]);

        self::assertCount(1, WpState::$restRoutes);
        self::assertSame('reach/v1', WpState::$restRoutes[0]['namespace']);
        self::assertSame('GET', WpState::$restRoutes[0]['args']['methods']);
    }
}
