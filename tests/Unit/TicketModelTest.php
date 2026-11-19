<?php

use JalaSupport\Models\Ticket;
use JalaSupport\Models\TicketPriority;
use JalaSupport\Models\TicketStatus;

it('hydrates from a post and serializes for the API', function () {
    $post = new WP_Post([
        'ID' => 12,
        'post_author' => '3',
        'post_title' => 'Billing question',
        'post_content' => 'Why was I charged twice?',
        'post_type' => 'ticket',
        'post_date_gmt' => '2026-11-01 10:00:00',
        'post_modified_gmt' => '2026-11-02 11:30:00',
    ]);

    $ticket = Ticket::fromPost($post, [
        'ticket_status' => 'in_progress',
        'ticket_priority' => 'high',
        'ticket_category' => 'billing',
        'customer_name' => 'Jane',
        'customer_email' => 'jane@example.com',
    ]);

    expect($ticket->status)->toBe(TicketStatus::InProgress)
        ->and($ticket->toArray())->toMatchArray([
            'id' => 12,
            'status' => 'in_progress',
            'status_label' => 'In Progress',
            'priority' => 'high',
            'category' => 'billing',
            'customer' => ['name' => 'Jane', 'email' => 'jane@example.com'],
            'ai' => ['summary' => '', 'suggested_reply' => ''],
            'author_id' => 3,
            'created_at' => '2026-11-01T10:00:00+00:00',
            'updated_at' => '2026-11-02T11:30:00+00:00',
        ]);
});

it('falls back to defaults for missing or corrupt metadata', function () {
    $ticket = Ticket::fromPost(new WP_Post(['ID' => 1]), ['ticket_priority' => ['array']]);

    expect($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->priority)->toBe(TicketPriority::Normal)
        ->and($ticket->toArray()['created_at'])->toBeNull();
});
