<?php

use Brain\Monkey\Functions;
use JalaSupport\Models\TicketCategory;
use JalaSupport\Models\TicketPriority;
use JalaSupport\Services\AI\AiResponseParser;

const ALL_FIELDS = ['category', 'priority', 'summary', 'suggested_reply'];

beforeEach(function () {
    Functions\when('sanitize_textarea_field')->alias(fn($v) => trim(strip_tags($v)));
    $this->parser = new AiResponseParser();
});

it('parses a valid structured response', function () {
    $result = $this->parser->parse(json_encode([
        'category' => 'account_issues',
        'priority' => 'high',
        'summary' => 'Customer cannot log in after a password reset.',
        'suggested_reply' => 'Thank you for contacting support...',
    ]), ALL_FIELDS);

    expect($result->category)->toBe(TicketCategory::AccountIssues)
        ->and($result->priority)->toBe(TicketPriority::High)
        ->and($result->toArray())->toHaveKeys(['category', 'category_label', 'priority', 'summary', 'suggested_reply']);
});

it('accepts human-readable labels', function () {
    $result = $this->parser->parse(json_encode([
        'category' => 'Technical Support',
        'priority' => 'Urgent',
        'summary' => 'x',
        'suggested_reply' => 'y',
    ]), ALL_FIELDS);

    expect($result->category)->toBe(TicketCategory::TechnicalSupport)
        ->and($result->priority)->toBe(TicketPriority::Urgent);
});

it('rejects malformed or invalid output', function (string $content, string $reason) {
    $result = $this->parser->parse($content, ALL_FIELDS);

    expect($result)->toBeInstanceOf(WP_Error::class)
        ->and($result->get_error_data())->toMatchArray(['status' => 502, 'reason' => $reason]);
})->with([
    'not json' => ['Sure! Here is the analysis:', 'malformed_json'],
    'json scalar' => ['"text"', 'malformed_json'],
    'unknown category' => [json_encode(['category' => 'refund', 'priority' => 'low', 'summary' => 'x', 'suggested_reply' => 'y']), 'category'],
    'unknown priority' => [json_encode(['category' => 'billing', 'priority' => 'critical', 'summary' => 'x', 'suggested_reply' => 'y']), 'priority'],
    'missing summary' => [json_encode(['category' => 'billing', 'priority' => 'low', 'suggested_reply' => 'y']), 'summary'],
    'empty reply' => [json_encode(['category' => 'billing', 'priority' => 'low', 'summary' => 'x', 'suggested_reply' => '  ']), 'suggested_reply'],
    'non-string summary' => [json_encode(['category' => 'billing', 'priority' => 'low', 'summary' => ['x'], 'suggested_reply' => 'y']), 'summary'],
    'too long' => [json_encode(['category' => 'billing', 'priority' => 'low', 'summary' => str_repeat('a', 5001), 'suggested_reply' => 'y']), 'summary'],
]);

it('strips markup from generated text', function () {
    $result = $this->parser->parse(json_encode(['suggested_reply' => '<img src=x onerror=alert(1)>Hello']), ['suggested_reply']);

    expect($result->suggestedReply)->toBe('Hello');
});

it('only requires the requested fields', function () {
    $result = $this->parser->parse(json_encode(['suggested_reply' => 'Hello']), ['suggested_reply']);

    expect($result->toArray())->toBe(['suggested_reply' => 'Hello']);
});
