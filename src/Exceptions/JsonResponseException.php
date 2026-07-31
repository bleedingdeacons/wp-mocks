<?php

declare(strict_types=1);

namespace BleedingDeacons\WpMocks\Exceptions;

use RuntimeException;

/**
 * Thrown by the stubbed wp_send_json_success()/wp_send_json_error(), which
 * also exit in production.
 *
 * The payload is carried on the exception so an AJAX handler's response can be
 * asserted on directly.
 */
final class JsonResponseException extends RuntimeException
{
    public function __construct(
        public readonly bool $success,
        public readonly mixed $data = null,
        public readonly ?int $status = null
    ) {
        parent::__construct($success ? 'json_success' : 'json_error');
    }
}
