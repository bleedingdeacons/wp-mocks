<?php

declare(strict_types=1);

/**
 * Stand-ins for the WP REST API request/response objects.
 *
 * Kept out of the `wordpress` group deliberately. A plugin with no REST
 * surface has no use for them, and several here register routes without ever
 * constructing a WP_REST_Response — `register_rest_route()` lives in the
 * `wordpress` group and records into {@see WpState::$restRoutes}, which is all
 * a route-registration test needs.
 *
 * What is here is the shape a controller's route callback is *called with* and
 * *returns*: enough of WP_REST_Request to drive a callback directly in a unit
 * test, and enough of WP_REST_Response to assert on data, status and headers
 * afterwards. Integrity, Beacon and Reach each hand-rolled a slightly
 * different subset of exactly this; the union of the three is what follows.
 *
 * WP_REST_Server carries only its method constants, because that is all any
 * consumer here reads off it — controllers name `WP_REST_Server::READABLE` and
 * friends when declaring routes.
 *
 * Assumes the `wordpress` group is also loaded: rest_ensure_response() passes a
 * WP_Error straight through, which is where that class comes from.
 */

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        /**
         * @param array<string, mixed> $params
         * @param array<string, string> $headers
         */
        public function __construct(
            private array $params = [],
            private string $route = '',
            private array $headers = [],
            private string $body = ''
        ) {
        }

        public function get_param(string $key): mixed
        {
            return $this->params[$key] ?? null;
        }

        public function set_param(string $key, mixed $value): void
        {
            $this->params[$key] = $value;
        }

        /** @return array<string, mixed> */
        public function get_params(): array
        {
            return $this->params;
        }

        /**
         * WordPress partitions parameters by where they arrived from — query
         * string, body, URL path — and merges them for get_param(). This stub
         * keeps one undifferentiated set, so these three answer with all of
         * them. That is what every hand-rolled copy in this suite did, and no
         * consumer here distinguishes the sources; a test that needs to tell
         * them apart wants a real request, not a stand-in.
         *
         * @return array<string, mixed>
         */
        public function get_query_params(): array
        {
            return $this->params;
        }

        /** @return array<string, mixed> */
        public function get_body_params(): array
        {
            return $this->params;
        }

        /** @return array<string, mixed> */
        public function get_url_params(): array
        {
            return $this->params;
        }

        public function get_route(): string
        {
            return $this->route;
        }

        /**
         * Real WP_REST_Request returns null for an absent header and an array
         * of values for a present one; get_header() flattens that to a string.
         */
        public function get_header(string $key): ?string
        {
            return $this->headers[strtolower($key)] ?? null;
        }

        public function set_header(string $key, string $value): void
        {
            $this->headers[strtolower($key)] = $value;
        }

        /** @return array<string, string> */
        public function get_headers(): array
        {
            return $this->headers;
        }

        public function get_body(): string
        {
            return $this->body;
        }

        public function set_body(string $body): void
        {
            $this->body = $body;
        }

        /**
         * WordPress decodes a JSON body into the params; here it is decoded on
         * demand so a test can set either one.
         *
         * @return array<string, mixed>|null
         */
        public function get_json_params(): ?array
        {
            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode($this->body, true);

            return is_array($decoded) ? $decoded : null;
        }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response
    {
        /** @var array<string, string> */
        private array $headers = [];

        public function __construct(private mixed $data = null, private int $status = 200)
        {
        }

        public function get_data(): mixed
        {
            return $this->data;
        }

        public function set_data(mixed $data): void
        {
            $this->data = $data;
        }

        public function get_status(): int
        {
            return $this->status;
        }

        public function set_status(int $status): void
        {
            $this->status = $status;
        }

        public function header(string $key, string $value): void
        {
            $this->headers[$key] = $value;
        }

        /** @return array<string, string> */
        public function get_headers(): array
        {
            return $this->headers;
        }
    }
}

if (!class_exists('WP_REST_Server')) {
    class WP_REST_Server
    {
        public const READABLE = 'GET';
        public const CREATABLE = 'POST';
        public const EDITABLE = 'POST, PUT, PATCH';
        public const DELETABLE = 'DELETE';
        public const ALLMETHODS = 'GET, POST, PUT, PATCH, DELETE';
    }
}

if (!function_exists('rest_ensure_response')) {
    /**
     * Passes a WP_REST_Response or WP_Error through untouched and wraps
     * anything else, exactly as WordPress does.
     */
    function rest_ensure_response(mixed $response): WP_REST_Response|WP_Error
    {
        if ($response instanceof WP_REST_Response || $response instanceof WP_Error) {
            return $response;
        }

        return new WP_REST_Response($response, 200);
    }
}
