<?php

use JalaSupport\PostTypes\TicketPostType;
use JalaSupport\Support\Capabilities;

it('registers the ticket post type as non-public', function () {
    $args = (new TicketPostType())->args();

    expect($args['public'])->toBeFalse()
        ->and($args['publicly_queryable'])->toBeFalse()
        ->and($args['exclude_from_search'])->toBeTrue()
        ->and($args['has_archive'])->toBeFalse()
        ->and($args['rewrite'])->toBeFalse();
});

it('does not expose tickets through the core wp/v2 REST routes', function () {
    expect((new TicketPostType())->args()['show_in_rest'])->toBeFalse();
});

it('uses dedicated ticket capabilities instead of the post capabilities', function () {
    $args = (new TicketPostType())->args();

    expect($args['capability_type'])->toBe(['ticket', 'tickets'])
        ->and($args['map_meta_cap'])->toBeTrue()
        ->and($args['capabilities']['read'])->toBe('read_tickets');
});

it('only grants delete and settings capabilities to administrators', function () {
    $agent = Capabilities::agentCapabilities();
    $admin = Capabilities::administratorCapabilities();

    expect($agent)->not->toContain('delete_others_tickets')
        ->not->toContain(Capabilities::MANAGE_SETTINGS)
        ->and($admin)->toContain('delete_others_tickets')
        ->toContain(Capabilities::MANAGE_SETTINGS)
        ->and(array_diff($agent, $admin))->toBeEmpty();
});
