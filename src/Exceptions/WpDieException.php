<?php

declare(strict_types=1);

namespace BleedingDeacons\WpMocks\Exceptions;

use RuntimeException;

/**
 * Thrown by the stubbed wp_die(), which terminates the request in production.
 *
 * Throwing instead of exiting keeps the guard clauses that call it assertable:
 * a test expects this exception rather than losing the whole PHPUnit process.
 */
final class WpDieException extends RuntimeException
{
}
