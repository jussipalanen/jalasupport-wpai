<?php

use JalaSupport\Models\Ticket;
use JalaSupport\Services\AI\DemoAiService;

function demoTicket(string $title, string $description, string $customer = 'Jane Doe'): Ticket
{
    return Ticket::fromPost(new WP_Post([
        'ID' => 7,
        'post_type' => 'ticket',
        'post_title' => $title,
        'post_content' => $description,
        'post_author' => 1,
        'post_date_gmt' => '2026-11-20 10:00:00',
        'post_modified_gmt' => '2026-11-20 10:00:00',
    ]), ['customer_name' => $customer, 'customer_email' => 'jane@example.com']);
}

it('classifies tickets with keyword rules', function (string $title, string $description, string $category, string $priority) {
    $analysis = (new DemoAiService())->analyze(demoTicket($title, $description));

    expect($analysis->category?->value)->toBe($category)
        ->and($analysis->priority?->value)->toBe($priority);
})->with([
    'billing' => ['Charged twice', 'My card was charged twice for the March invoice.', 'billing', 'high'],
    'account' => ['Locked out', 'I cannot log in since this morning.', 'account_issues', 'high'],
    'technical urgent' => ['Site outage', 'Uploads fail with an error for everyone, please fix ASAP.', 'technical_support', 'urgent'],
    'question' => ['Feature question', 'Just wondering if dark mode is on the roadmap.', 'general_inquiry', 'low'],
    'other' => ['Thanks', 'Great service, have a nice day.', 'other', 'normal'],
]);

it('labels the summary as a demo and uses the first sentence', function () {
    $analysis = (new DemoAiService())->analyze(demoTicket('Locked out', "I cannot log in.\nIt worked yesterday."));

    expect($analysis->summary)->toBe('[Demo] Jane Doe writes: I cannot log in.');
});

it('shortens long summaries and falls back to the title', function () {
    $long = (new DemoAiService())->analyze(demoTicket('Long', str_repeat('word ', 80)));
    $empty = (new DemoAiService())->analyze(demoTicket('Only a title', '', ''));

    expect(mb_strlen($long->summary))->toBeLessThan(200)
        ->and($long->summary)->toEndWith('…')
        ->and($empty->summary)->toBe('[Demo] The customer writes: Only a title');
});

it('suggests a reply addressed to the customer by first name', function () {
    $analysis = (new DemoAiService())->suggestReply(demoTicket('Refund', 'Please refund my payment.'));

    expect($analysis->category)->toBeNull()
        ->and($analysis->summary)->toBe('')
        ->and($analysis->suggestedReply)->toStartWith("Hi Jane,\n\n")
        ->and($analysis->suggestedReply)->toContain('billing');
});

it('greets generically when the customer name is missing', function () {
    $analysis = (new DemoAiService())->suggestReply(demoTicket('Hello', 'Hello there.', ''));

    expect($analysis->suggestedReply)->toStartWith("Hello,\n\n");
});

it('describes itself as a demo without secrets or external requests', function () {
    $service = new DemoAiService();

    expect($service->name())->toBe('Demo AI (sample answers)')
        ->and($service->isConfigured())->toBeTrue()
        ->and($service->describe())->toMatchArray(['External requests' => 'None']);
});
