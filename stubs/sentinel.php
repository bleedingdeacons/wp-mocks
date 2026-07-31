<?php

declare(strict_types=1);

/**
 * Stand-in for the shared logger mu-plugin that Sentinel deploys.
 *
 * Unity's HasLogger trait (and its copies in the other plugins) caches a
 * channel typed against \Sentinel_Log_Channel and resolves it through
 * wp_log(). The trait is written to no-op when wp_log() is absent, so two
 * things have to be testable: the path where logging happens, and the path
 * where it degrades.
 *
 * This file provides the *present* case. Every call is recorded on the channel
 * and mirrored into {@see BleedingDeacons\WpMocks\WpState::$logs}, so a test
 * can assert the trait forwarded at the right level without reaching for a
 * mock. For the *absent* case, simply do not load this file — the trait's
 * function_exists() guard then takes the other branch.
 */

use BleedingDeacons\WpMocks\WpState;

if (!class_exists('Sentinel_Log_Channel')) {
    class Sentinel_Log_Channel
    {
        /** @var array<int, array{level: string, message: string, context: array<string, mixed>}> */
        public array $calls = [];

        public function __construct(public readonly string $channel = 'test')
        {
        }

        /** @param array<string, mixed> $context */
        public function emergency(string $message, array $context = []): void
        {
            $this->record('emergency', $message, $context);
        }

        /** @param array<string, mixed> $context */
        public function alert(string $message, array $context = []): void
        {
            $this->record('alert', $message, $context);
        }

        /** @param array<string, mixed> $context */
        public function critical(string $message, array $context = []): void
        {
            $this->record('critical', $message, $context);
        }

        /** @param array<string, mixed> $context */
        public function error(string $message, array $context = []): void
        {
            $this->record('error', $message, $context);
        }

        /** @param array<string, mixed> $context */
        public function warning(string $message, array $context = []): void
        {
            $this->record('warning', $message, $context);
        }

        /** @param array<string, mixed> $context */
        public function notice(string $message, array $context = []): void
        {
            $this->record('notice', $message, $context);
        }

        /** @param array<string, mixed> $context */
        public function info(string $message, array $context = []): void
        {
            $this->record('info', $message, $context);
        }

        /** @param array<string, mixed> $context */
        public function debug(string $message, array $context = []): void
        {
            $this->record('debug', $message, $context);
        }

        /** The levels this channel was called at, in order. @return array<int, string> */
        public function levels(): array
        {
            return array_column($this->calls, 'level');
        }

        /** @param array<string, mixed> $context */
        private function record(string $level, string $message, array $context): void
        {
            $this->calls[] = ['level' => $level, 'message' => $message, 'context' => $context];
            WpState::$logs[] = [$this->channel, $level, $message, $context];
        }
    }
}

if (!function_exists('wp_log')) {
    /**
     * Channels are memoised so two calls for the same name return the same
     * object — HasLogger caches the channel it is given, and a test asserting
     * on ->calls needs to be looking at the same instance the code used.
     *
     * The return type is nullable even though this stub never returns null.
     * Patchwork keeps the original signature when Brain Monkey redefines a
     * function, so a non-nullable type here would make
     * `Functions\when('wp_log')->justReturn(null)` a TypeError — and that is
     * exactly how a caller simulates the logger being unavailable. HasLogger
     * already types its own accessor as ?Sentinel_Log_Channel, so nullable is
     * the more faithful signature in any case.
     */
    function wp_log(string $channel = 'default'): ?\Sentinel_Log_Channel
    {
        /** @var array<string, \Sentinel_Log_Channel> $channels */
        static $channels = [];

        return $channels[$channel] ??= new \Sentinel_Log_Channel($channel);
    }
}
