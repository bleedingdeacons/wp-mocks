<?php

declare(strict_types=1);

namespace BleedingDeacons\WpMocks\Tests;

use BleedingDeacons\WpMocks\TestCase;
use BleedingDeacons\WpMocks\WpState;

final class AcfStubsTest extends TestCase
{
    public function testFieldsRoundTripPerPost(): void
    {
        update_field('phone', '01234', 10);
        update_field('phone', '56789', 11);

        self::assertSame('01234', get_field('phone', 10));
        self::assertSame('56789', get_field('phone', 11));
        self::assertNull(get_field('phone', 12));
    }

    /**
     * ACF treats a falsy post id as the options page, and so does the stub —
     * so an options-page field and a post 0 field are deliberately the same
     * slot, exactly as in ACF itself.
     */
    public function testFalseyPostIdAddressesTheOptionsPage(): void
    {
        update_field('site_name', 'Intergroup');

        self::assertSame('Intergroup', get_field('site_name'));
        self::assertSame('Intergroup', get_field('site_name', false));
        self::assertSame('Intergroup', get_field('site_name', 0));
    }

    public function testGetFieldsReturnsEveryFieldForAPost(): void
    {
        update_field('a', 1, 5);
        update_field('b', 2, 5);
        update_field('c', 3, 6);

        self::assertSame(['a' => 1, 'b' => 2], get_fields(5));
    }

    public function testGetFieldsIsFalseWhenNothingIsSeeded(): void
    {
        self::assertFalse(get_fields(99));
    }

    public function testDeleteFieldRemovesIt(): void
    {
        update_field('temp', 'x', 1);
        delete_field('temp', 1);

        self::assertNull(get_field('temp', 1));
    }

    public function testAcfGetFieldReturnsAStableGeneratedKey(): void
    {
        $first = acf_get_field('member_phone');
        $second = acf_get_field('member_phone');

        self::assertIsArray($first);
        self::assertSame($first, $second, 'the same name must always resolve to the same key');
        self::assertStringStartsWith('field_', $first['key']);
        self::assertSame('member_phone', $first['name']);
    }

    public function testFieldObjectCarriesTheValue(): void
    {
        update_field('title', 'Chair', 4);

        $object = get_field_object('title', 4);

        self::assertIsArray($object);
        self::assertSame('Chair', $object['value']);
        self::assertFalse(get_field_object('absent', 4));
    }

    public function testValidationErrorsAreRecorded(): void
    {
        acf_add_validation_error('field_x', 'Required');

        self::assertSame(
            [['input' => 'field_x', 'message' => 'Required']],
            WpState::$options['__acf_validation_errors']
        );
    }

    public function testLocalFieldGroupsAreRecorded(): void
    {
        acf_add_local_field_group(['key' => 'group_1', 'title' => 'Details']);

        self::assertCount(1, WpState::$options['__acf_field_groups']);
    }
}
