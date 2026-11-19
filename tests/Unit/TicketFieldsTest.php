<?php

use Brain\Monkey\Functions;
use JalaSupport\Fields\TicketFields;
use JalaSupport\Models\TicketMeta;

beforeEach(function () {
    Functions\when('sanitize_text_field')->alias(fn($v) => trim(strip_tags($v)));
    Functions\when('sanitize_textarea_field')->alias(fn($v) => trim(strip_tags($v)));
    Functions\when('sanitize_email')->alias(fn($v) => filter_var($v, FILTER_VALIDATE_EMAIL) ?: '');
});

it('defines an ACF field for every ticket meta key', function () {
    $names = array_column((new TicketFields())->fieldGroup()['fields'], 'name');

    expect($names)->toEqualCanonicalizing(TicketMeta::keys());
});

it('uses stable unique field keys', function () {
    $keys = array_column((new TicketFields())->fieldGroup()['fields'], 'key');

    expect($keys)->toHaveCount(count(array_unique($keys)))
        ->each->toStartWith('field_jalasupport_');
});

it('only shows the field group on tickets and not in REST', function () {
    $group = (new TicketFields())->fieldGroup();

    expect($group['location'][0][0])->toBe(['param' => 'post_type', 'operator' => '==', 'value' => 'ticket'])
        ->and($group['show_in_rest'])->toBe(0);
});

it('sanitizes select meta to known values or the default', function () {
    $sanitizers = (new TicketFields())->metaSanitizers();

    expect($sanitizers[TicketMeta::STATUS]('resolved'))->toBe('resolved')
        ->and($sanitizers[TicketMeta::STATUS]('hacked'))->toBe('open')
        ->and($sanitizers[TicketMeta::PRIORITY]('urgent'))->toBe('urgent')
        ->and($sanitizers[TicketMeta::PRIORITY](''))->toBe('normal')
        ->and($sanitizers[TicketMeta::CATEGORY]('<b>billing</b>'))->toBe('general_inquiry');
});

it('sanitizes text meta', function () {
    $sanitizers = (new TicketFields())->metaSanitizers();

    expect($sanitizers[TicketMeta::CUSTOMER_NAME]('<script>x</script> Jane '))->toBe('x Jane')
        ->and($sanitizers[TicketMeta::CUSTOMER_EMAIL]('not-an-email'))->toBe('');
});
