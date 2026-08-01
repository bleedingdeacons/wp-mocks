<?php

declare(strict_types=1);

namespace BleedingDeacons\WpMocks\Exceptions;

use Error;

/**
 * Thrown by the stubbed wp_send_json_success()/wp_send_json_error(), which
 * also exit in production.
 *
 * The payload is carried on the exception so an AJAX handler's response can be
 * asserted on directly.
 *
 * Extends Error rather than Exception, deliberately. In production this
 * function does not return — it ends the request — so nothing downstream of
 * it runs. A stub throwing an Exception breaks that: a handler with its own
 * `catch (\Exception $e)` around the call swallows the stand-in and carries
 * on into its error path, and the test then asserts against a failure the
 * real code would never have reached. Error sits outside that catch, so the
 * unwind reaches the test as it reaches PHP's shutdown in production.
 *
 * `catch (\Throwable)` will still swallow it. Nothing can be done about that
 * from here, and code that broad would swallow a real fatal too.
 */
final class JsonResponseException extends Error
{
    public function __construct(
        public readonly bool $success,
        public readonly mixed $data = null,
        public readonly ?int $status = null
    ) {
        parent::__construct($success ? 'json_success' : 'json_error');
    }
}
