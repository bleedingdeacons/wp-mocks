<?php

declare(strict_types=1);

namespace BleedingDeacons\WpMocks\Exceptions;

use Error;

/**
 * Thrown by the stubbed wp_die(), which terminates the request in production.
 *
 * Throwing instead of exiting keeps the guard clauses that call it assertable:
 * a test expects this exception rather than losing the whole PHPUnit process.
 *
 * It carries wp_die()'s arguments for the same reason JsonResponseException
 * carries its payload — the interesting thing about a guard clause is usually
 * the status it refuses with, not merely that it refused.
 *
 * WordPress accepts the response code in two positions, and so does
 * {@see $status}: as the second argument (`wp_die($message, 403)`) or under
 * 'response' in the third (`wp_die($message, $title, ['response' => 403])`).
 * Where neither is given it is null, which stays distinguishable from a real 0.
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
final class WpDieException extends Error
{
    /**
     * @param array<string, mixed> $args
     */
    public function __construct(
        string $message = '',
        public readonly mixed $title = '',
        public readonly array $args = [],
        public readonly ?int $status = null
    ) {
        parent::__construct($message);
    }

    /**
     * Build one from wp_die()'s raw arguments, resolving the status from
     * whichever position it arrived in.
     */
    public static function fromArguments(mixed $message, mixed $title = '', mixed $args = []): self
    {
        $status = null;

        if (is_int($title)) {
            // wp_die($message, 403)
            $status = $title;
        } elseif (is_array($args) && isset($args['response'])) {
            // wp_die($message, $title, ['response' => 403])
            $status = (int) $args['response'];
        } elseif (is_int($args)) {
            $status = $args;
        }

        return new self(
            is_string($message) ? $message : 'wp_die',
            $title,
            is_array($args) ? $args : [],
            $status
        );
    }
}
