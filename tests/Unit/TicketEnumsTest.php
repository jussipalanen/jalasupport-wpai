<?php

use JalaSupport\Models\TicketCategory;
use JalaSupport\Models\TicketPriority;
use JalaSupport\Models\TicketStatus;

it('defines the MVP ticket statuses', function () {
    expect(TicketStatus::options())->toBe([
        'open' => 'Open',
        'in_progress' => 'In Progress',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ]);
});

it('defines the MVP ticket priorities', function () {
    expect(TicketPriority::values())->toBe(['low', 'normal', 'high', 'urgent']);
});

it('defines the MVP ticket categories', function () {
    expect(TicketCategory::values())->toBe([
        'technical_support', 'account_issues', 'billing', 'general_inquiry', 'other',
    ]);
});

it('resolves enum cases from values and labels', function (string $input, ?TicketCategory $expected) {
    expect(TicketCategory::fromInput($input))->toBe($expected);
})->with([
    ['billing', TicketCategory::Billing],
    ['Technical Support', TicketCategory::TechnicalSupport],
    ['  general inquiry ', TicketCategory::GeneralInquiry],
    ['refunds', null],
    ['', null],
]);
