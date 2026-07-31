# wp-mocks

Shared WordPress test doubles for The Bleeding Deacons plugin suite.

A state-backed stub layer over [Brain Monkey](https://github.com/Brain-WP/BrainMonkey),
plus fakes for `$wpdb` and the WP HTTP API, so seventeen plugins stop
hand-rolling seventeen copies of the same thing.

## Why this exists

Two problems, one package.

**1. wp_mock pins the suite to PHPUnit 9.** `10up/wp_mock` 1.1.1 declares
`"phpunit/phpunit": "^9.6"` in its own `require` block, and has no PHPUnit 10
branch. Every plugin here that asked for `"^9.6|^10.0"` while depending on
wp_mock silently resolved to **PHPUnit 9.6.35** — invisibly, until somebody ran
`composer show phpunit/phpunit`. Brain Monkey declares no PHPUnit constraint at
all, and neither does this package. Its own suite runs green on **10.5, 11 and
12**.

That property is load-bearing, so it is tested: `tests/PackageConstraintsTest.php`
fails if `phpunit/phpunit` ever appears in `require`.

**2. Everyone rebuilt the same doubles.** `FakeWpHttp` existed three times
(Beacon and Tamar differed only by namespace), `FakeWpdb` twice,
`assertActionAdded()` five times.

## Why state, not expectations

Both WP_Mock and Brain Monkey resolve the **first matching expectation**. A
catch-all `get_option` in a base TestCase therefore silently shadows any
narrower stub a test adds afterwards. Amber and Sentinel each hit this and each
independently invented a store to route reads through. `WpState` is that store,
factored out.

So the stubs here are *real functions* backed by one mutable store. A test
seeds what a scenario needs and asserts on what the code did with it:

```php
use BleedingDeacons\WpMocks\TestCase;
use BleedingDeacons\WpMocks\WpState;

final class MemberSyncTest extends TestCase
{
    public function testItReadsTheConfiguredGroup(): void
    {
        update_option('unity_settings', ['group' => 42]);
        WpState::addPost(42, ['post_type' => 'group', 'post_title' => 'Tuesday']);
        update_field('phone', '01234', 42);

        self::assertSame('Tuesday', (new MemberSync())->groupName());
    }
}
```

Brain Monkey still works for everything else, and can override any stub
per-test:

```php
Functions\when('get_option')->justReturn('patched');
Functions\expect('wp_mail')->once()->with('a@b.test', 'Subject', 'Body');
```

## Installation

```bash
composer require --dev bleedingdeacons/wp-mocks
```

Then in `tests/bootstrap.php`:

```php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/bleedingdeacons/wp-mocks/bootstrap.php';

\BleedingDeacons\WpMocks\WpState::$pluginSlug = 'unity';
```

and extend the base TestCase:

```php
use BleedingDeacons\WpMocks\TestCase;
```

### Ordering matters, in one direction

Anything that defines WordPress functions of its own must come **after** the
package bootstrap.

Patchwork rewrites functions as their defining file is included, so it must
load before anything patchable. It ships **no Composer autoload entry** —
brain/monkey lists it only under `autoload-dev` — and Brain Monkey itself only
requires it inside `Monkey\setUp()`, by which time a plugin's stubs are long
defined. The result is `Patchwork\Exceptions\DefinedTooEarly` the first time a
test tries to override a stub. `Bootstrap::loadPatchwork()` exists purely to
get this right, and `tests/BrainMonkeyInteropTest.php` keeps it honest.

### Leaving a group out

```php
\BleedingDeacons\WpMocks\Bootstrap::load(['wordpress', 'sentinel']);
```

A plugin covering its own *ACF-unavailable* branch — code guarded by
`function_exists('acf_get_field')` — must omit `acf`, or run those tests in a
separate process. Once a function is defined it stays defined for the life of
the process; that is equally true of WP_Mock and Brain Monkey, and no package
can paper over it.

## What is in the box

| Stub group | Covers |
|---|---|
| `wordpress` | options, transients, object cache, posts, meta, media, taxonomies, users and caps, nonces, URLs, cron, mail, the filesystem helpers, admin menus, assets, shortcodes, the HTTP API (including the cookie jar), formatting, escaping, i18n, and the core constants (`ARRAY_A` and the other `$wpdb` output formats, `*_IN_SECONDS`) |
| `rest` | `WP_REST_Request`, `WP_REST_Response`, `WP_REST_Server`'s method constants, `rest_ensure_response`. Assumes `wordpress` is loaded too — `rest_ensure_response()` passes a `WP_Error` straight through. `register_rest_route()` itself is in `wordpress`, recording into `WpState::$restRoutes`, so a plugin only asserting that a route was *declared* does not need this group |
| `acf` | `get_field`, `update_field`, `get_fields`, `get_field_object`, `acf_get_field`, `acf_add_local_field_group`, `acf_add_validation_error` |
| `sentinel` | `wp_log()` and `Sentinel_Log_Channel`, memoised per channel so `HasLogger`'s cached channel is the one a test asserts on |

| Class | Purpose |
|---|---|
| `WpState` | The store every stub reads and writes. `reset()` between tests (the base TestCase does it) |
| `TestCase` | Brain Monkey lifecycle + `MockeryPHPUnitIntegration` + `WpState::reset()` |
| `HookAssertions` | `assertActionAdded()` and friends, over Brain Monkey's hook store. Also usable as a standalone trait |
| `Doubles\FakeWpdb` | Recording `$wpdb`: queues rows, records every statement, insert/update/delete with their formats |
| `Doubles\FakeWpHttp` | Scriptable WP HTTP backend: queue responses, assert on what was sent |
| `Exceptions\WpDieException`, `Exceptions\JsonResponseException` | Thrown by the terminating functions instead of exiting, so guard clauses stay assertable |

Constants that describe a *particular installation* — `ABSPATH`, `WP_DEBUG`,
`WP_PLUGIN_DIR`, and a plugin's own — are deliberately not defined here. Several
plugins in this suite point `ABSPATH` at a real temp directory so filesystem
paths can be exercised, so that decision belongs to their bootstrap.

### Brain Monkey owns the hooks

`add_action`, `add_filter`, `do_action`, `apply_filters`, `has_action`,
`remove_action`, `did_action` and the rest are **deliberately not stubbed
here**. Brain Monkey defines them, routing each call into the container behind
`Actions\expectAdded()`, `Actions\expectDone()`, `Filters\expectApplied()`,
`Actions\has()` and `Actions\did()`.

Its definitions are loaded lazily by `Monkey\setUp()` and are all
`function_exists()`-guarded, so anything this package defined at bootstrap
would permanently shadow them — and Brain Monkey's hook expectations would then
never be satisfied, because the calls never reach its container. That failure
is silent, which is why `WordPressStubsTest` asserts `add_action` still comes
from Brain Monkey.

## Migrating from wp_mock

| wp_mock | Brain Monkey |
|---|---|
| `WP_Mock::userFunction('f')->andReturn($x)` | `Functions\expect('f')->andReturn($x)` |
| `WP_Mock::passthruFunction('f')` | `Functions\when('f')->returnArg()` |
| `WP_Mock::echoFunction('f')` | `Functions\when('f')->echoArg()` |
| `WP_Mock::expectAction('a', $arg)` | `Actions\expectDone('a')->once()->with($arg)` |
| `WP_Mock::expectActionAdded('a', $cb)` | `Actions\expectAdded('a')->once()->with($cb)` |
| `WP_Mock::expectFilterAdded('f', $cb)` | `Filters\expectAdded('f')->once()->with($cb)` |
| `WP_Mock::onFilter('f')->with($x)->reply($y)` | `Filters\expectApplied('f')->with($x)->andReturn($y)` |
| `WP_Mock::onActionAdded('a')->react(...)` | `$this->assertActionAdded('a', $cb)` |
| `WP_Mock::setUp()` / `tearDown()` | `Monkey\setUp()` / `Monkey\tearDown()` (the base TestCase does it) |

`Functions\expect()` enforces no default call count — Mockery's default is
zero-or-more — so it is a direct swap for `userFunction()`, and `->once()`,
`->never()`, `->with()` and `->andReturnUsing()` all behave the same.

Better still, where a test only stubbed `get_option`/`get_post`/`get_field` to
hold a value, delete the stub and seed `WpState` instead.

### One stub per function per test

This is the one migration trap that fails *silently*. `WP_Mock::userFunction()`
registered a separate stub per argument set, so stacking calls dispatched on
the arguments. Brain Monkey keeps one stub per function per test, and the first
one registered answers every call — whatever its `->with()` says:

```php
Functions\expect('get_field')->with('title', 42)->andReturn('A title');
Functions\expect('get_field')->with('date', 42)->andReturn('2026-07-01');

get_field('title', 42);  // 'A title'
get_field('date', 42);   // 'A title'  ← not the date
```

Nothing errors. The second value simply never appears, and the failure surfaces
somewhere downstream — a `DateTime::createFromFormat(): must be of type string,
array given`, three assertions later.

Use one expectation that dispatches on the argument:

```php
Functions\expect('get_field')->andReturnUsing(
    static fn (string $field, int $postId): mixed => match ($field) {
        'title' => 'A title',
        'date'  => '2026-07-01',
        default => null,
    }
);
```

The same applies to a base class or helper that stubs a function every test
needs: a per-test override cannot be layered on top of it. Give the helper an
`$overrides` parameter and merge, rather than adding a second expectation.

### Signatures survive the override

Patchwork keeps a function's declared signature when Brain Monkey redefines it,
so a stub cannot return something its own declaration forbids. The stubs here
are typed as WordPress types them — `wp_insert_post()` returns `int|WP_Error`,
`get_permalink()` returns `string|false`, `wp_is_post_autosave()` returns
`int|false` — but the flip side is that a test simulating a failure has to use
the real shape. `->andReturn(new stdClass())` in place of a `WP_Error`, or a
bare `true` for "yes, this is an autosave", is a `TypeError` now.

## Development

```bash
composer install
composer test
composer stan
```

CI runs the suite against PHPUnit 10.5, 11 and 12 on PHP 8.1 and 8.3. The
matrix is the point: it is what stops this package quietly acquiring a PHPUnit
ceiling of its own.
