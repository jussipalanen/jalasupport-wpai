<?php

use Brain\Monkey\Functions;
use JalaSupport\Services\TicketValidator;

beforeEach(function () {
    Functions\when('sanitize_text_field')->alias(fn($v) => trim(preg_replace('/\s+/', ' ', strip_tags($v))));
    Functions\when('sanitize_textarea_field')->alias(fn($v) => trim(strip_tags($v)));
    Functions\when('sanitize_email')->alias(fn($v) => trim($v));
    Functions\when('is_email')->alias(fn($v) => filter_var($v, FILTER_VALIDATE_EMAIL) !== false);

    $this->validator = new TicketValidator();
    $this->valid = [
        'title' => 'Cannot log in',
        'description' => "I reset my password\nbut still cannot log in.",
        'customer_name' => 'Jane Doe',
        'customer_email' => 'jane@example.com',
    ];
});

it('accepts a valid ticket and applies defaults', function () {
    $data = $this->validator->validate($this->valid);

    expect($data)->toMatchArray([
        'title' => 'Cannot log in',
        'customer_name' => 'Jane Doe',
        'customer_email' => 'jane@example.com',
        'ticket_status' => 'open',
        'ticket_priority' => 'normal',
        'ticket_category' => 'general_inquiry',
    ])->and($data['description'])->toContain("\n");
});

it('requires the core fields on create', function () {
    $result = $this->validator->validate([]);

    expect($result)->toBeInstanceOf(WP_Error::class)
        ->and($result->get_error_data()['status'])->toBe(422)
        ->and(array_keys($result->get_error_data()['errors']))
        ->toEqualCanonicalizing(['title', 'description', 'customer_name', 'customer_email']);
});

it('rejects whitespace-only required fields', function () {
    $result = $this->validator->validate(['title' => '   '] + $this->valid);

    expect($result)->toBeInstanceOf(WP_Error::class)
        ->and($result->get_error_data()['errors'])->toHaveKey('title');
});

it('rejects invalid emails', function () {
    $result = $this->validator->validate(['customer_email' => 'nope'] + $this->valid);

    expect($result->get_error_data()['errors'])->toHaveKey('customer_email');
});

it('rejects unknown status, priority and category values', function () {
    $result = $this->validator->validate([
        'status' => 'deleted',
        'priority' => 'Urgent',
        'category' => 'refunds',
    ] + $this->valid);

    expect(array_keys($result->get_error_data()['errors']))
        ->toEqualCanonicalizing(['status', 'priority', 'category']);
});

it('enforces maximum lengths', function () {
    $result = $this->validator->validate(['title' => str_repeat('a', TicketValidator::MAX_TITLE + 1)] + $this->valid);

    expect($result->get_error_data()['errors'])->toHaveKey('title');
});

it('rejects non-scalar values', function () {
    $result = $this->validator->validate(['title' => ['injected']] + $this->valid);

    expect($result)->toBeInstanceOf(WP_Error::class);
});

it('strips markup from text fields', function () {
    $data = $this->validator->validate(['title' => '<script>alert(1)</script>Help'] + $this->valid);

    expect($data['title'])->not->toContain('<script>');
});

it('validates only provided fields on partial updates', function () {
    expect($this->validator->validate(['status' => 'resolved'], partial: true))
        ->toBe(['ticket_status' => 'resolved']);
});

it('rejects partial updates without any known field', function () {
    $result = $this->validator->validate(['unknown' => 'x'], partial: true);

    expect($result)->toBeInstanceOf(WP_Error::class)
        ->and($result->get_error_code())->toBe('jalasupport_no_changes');
});

it('ignores fields that are not part of the ticket schema', function () {
    $data = $this->validator->validate(['author_id' => 1, 'post_status' => 'trash'] + $this->valid);

    expect($data)->not->toHaveKey('author_id')->not->toHaveKey('post_status');
});
