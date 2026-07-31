<?php

declare(strict_types=1);

namespace BleedingDeacons\WpMocks\Doubles;

/**
 * A recording stand-in for WordPress's global $wpdb.
 *
 * The custom-table repositories and admin screens across this suite build SQL
 * by hand and hand it to $wpdb, so the interesting behaviour is *what they ask
 * the database for* — which filters became WHERE clauses, whether ORDER BY was
 * whitelisted, whether a save became an INSERT or an UPDATE. This double
 * records every call and lets a test queue the rows to hand back, so those
 * questions can be asked directly rather than inferred.
 *
 * prepare() interpolates naively — enough for assertions about the shape of a
 * statement, without pretending to be WordPress's escaping.
 *
 * Assign an instance to $GLOBALS['wpdb'] in your bootstrap or setUp, and call
 * reset() between tests.
 */
final class FakeWpdb
{
    public string $prefix = 'wp_';
    public string $posts = 'wp_posts';
    public string $postmeta = 'wp_postmeta';
    public string $users = 'wp_users';
    public string $usermeta = 'wp_usermeta';
    public string $options = 'wp_options';
    public string $terms = 'wp_terms';
    public string $last_error = '';
    public int $insert_id = 1;

    /** @var array<int, string> Every statement passed to a query method, in order. */
    public array $queries = [];

    /** @var array<int, mixed> Rows returned by get_results(). */
    public array $results = [];

    /** Row returned by get_row(); null means "not found". */
    public mixed $row = null;

    /** @var array<int, mixed> Column returned by get_col(). */
    public array $col = [];

    /** Scalar returned by get_var(). */
    public mixed $var = '0';

    /** Return value handed back by insert(); false simulates a failure. */
    public mixed $insertResult = 1;

    /** Return value handed back by update(); false simulates a failure. */
    public mixed $updateResult = 1;

    /** Return value handed back by delete(); false simulates a failure. */
    public mixed $deleteResult = 1;

    /** Return value handed back by query(); false simulates a failure. */
    public mixed $queryResult = 1;

    /** @var array<int, array{0: string, 1: array<string, mixed>, 2: mixed}> Calls to insert(). */
    public array $inserts = [];

    /** @var array<int, array{0: string, 1: array<string, mixed>, 2: array<string, mixed>, 3: mixed, 4: mixed}> */
    public array $updates = [];

    /** @var array<int, array{0: string, 1: array<string, mixed>, 2: mixed}> Calls to delete(). */
    public array $deletes = [];

    public function prepare(string $query, mixed ...$args): string
    {
        // Flatten a single array argument, matching how callers spread values.
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }

        foreach ($args as $arg) {
            $replacement = is_int($arg) || is_float($arg) ? (string) $arg : "'" . (string) $arg . "'";
            $query = preg_replace('/%[sdfF]/', $replacement, $query, 1) ?? $query;
        }

        return $query;
    }

    /** @return array<int, mixed> */
    public function get_results(string $query, mixed $output = null): array
    {
        $this->queries[] = $query;

        return $this->results;
    }

    public function get_row(string $query, mixed $output = null, int $y = 0): mixed
    {
        $this->queries[] = $query;

        return $this->row;
    }

    /** @return array<int, mixed> */
    public function get_col(string $query, int $x = 0): array
    {
        $this->queries[] = $query;

        return $this->col;
    }

    public function get_var(string $query, int $x = 0, int $y = 0): mixed
    {
        $this->queries[] = $query;

        return $this->var;
    }

    public function query(string $query): mixed
    {
        $this->queries[] = $query;

        return $this->queryResult;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(string $table, array $data, mixed $formats = null): mixed
    {
        $this->inserts[] = [$table, $data, $formats];

        return $this->insertResult;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where
     */
    public function update(string $table, array $data, array $where, mixed $f = null, mixed $wf = null): mixed
    {
        $this->updates[] = [$table, $data, $where, $f, $wf];

        return $this->updateResult;
    }

    /**
     * @param array<string, mixed> $where
     */
    public function delete(string $table, array $where, mixed $formats = null): mixed
    {
        $this->deletes[] = [$table, $where, $formats];

        return $this->deleteResult;
    }

    public function get_charset_collate(): string
    {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }

    public function esc_like(string $text): string
    {
        return addcslashes($text, '_%\\');
    }

    /** The most recent statement, for terse assertions. */
    public function lastQuery(): string
    {
        return $this->queries === [] ? '' : (string) end($this->queries);
    }

    /** Forget everything recorded and queued. */
    public function reset(): void
    {
        $this->queries = [];
        $this->results = [];
        $this->row = null;
        $this->col = [];
        $this->var = '0';
        $this->inserts = [];
        $this->updates = [];
        $this->deletes = [];
        $this->insertResult = 1;
        $this->updateResult = 1;
        $this->deleteResult = 1;
        $this->queryResult = 1;
        $this->insert_id = 1;
        $this->last_error = '';
    }
}
