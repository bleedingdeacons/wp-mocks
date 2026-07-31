<?php

declare(strict_types=1);

/**
 * This package's own bootstrap — and, not incidentally, a worked example of
 * what a consuming plugin's tests/bootstrap.php should look like.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../bootstrap.php';

if (!defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir() . '/wp-mocks-test-wp/');
}
