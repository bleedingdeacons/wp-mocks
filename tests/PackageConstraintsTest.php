<?php

declare(strict_types=1);

namespace BleedingDeacons\WpMocks\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Guards the one property this package exists to have.
 *
 * 10up/wp_mock declares "phpunit/phpunit": "^9.6" in its own require block. It
 * has no PHPUnit 10 branch, so every plugin in this suite that asked for
 * "^9.6|^10.0" while depending on wp_mock silently resolved to PHPUnit 9.6 —
 * for as long as nobody thought to check `composer show phpunit/phpunit`.
 *
 * Adding a phpunit constraint to this package's require block would recreate
 * that exact trap, one layer down and just as invisibly. This test is the
 * tripwire. Note it deliberately extends PHPUnit's TestCase rather than this
 * package's own, since it is about packaging, not WordPress.
 */
final class PackageConstraintsTest extends PHPUnitTestCase
{
    /** @return array<string, mixed> */
    private function composerJson(): array
    {
        $path = dirname(__DIR__) . '/composer.json';
        $raw = file_get_contents($path);

        self::assertIsString($raw, 'composer.json should be readable at ' . $path);

        $decoded = json_decode($raw, true);
        self::assertIsArray($decoded, 'composer.json should be valid JSON');

        return $decoded;
    }

    public function testPhpunitIsNotARuntimeRequirement(): void
    {
        $require = $this->composerJson()['require'] ?? [];

        self::assertArrayNotHasKey(
            'phpunit/phpunit',
            $require,
            'PHPUnit must stay out of require. Putting it there is what pinned the whole '
            . 'plugin suite to PHPUnit 9.6 via wp_mock; the consuming plugin supplies PHPUnit.'
        );
    }

    public function testNoPackageInRequirePullsInPhpunit(): void
    {
        $require = $this->composerJson()['require'] ?? [];

        self::assertSame(
            ['php', 'brain/monkey', 'mockery/mockery'],
            array_keys($require),
            'The require block is deliberately tiny. brain/monkey and mockery both declare '
            . 'no phpunit constraint; adding anything else here risks reintroducing one '
            . 'transitively.'
        );
    }

    public function testWpMockIsNotADependency(): void
    {
        $json = $this->composerJson();

        self::assertArrayNotHasKey('10up/wp_mock', $json['require'] ?? []);
        self::assertArrayNotHasKey('10up/wp_mock', $json['require-dev'] ?? []);
    }

    /**
     * The dev constraint is what CI's matrix runs against, so it has to keep
     * naming every major this package claims to support.
     */
    public function testDevPhpunitConstraintSpansTheSupportedMajors(): void
    {
        $constraint = $this->composerJson()['require-dev']['phpunit/phpunit'] ?? '';

        foreach (['^10.5', '^11.0', '^12.0'] as $major) {
            self::assertStringContainsString($major, $constraint);
        }
    }
}
