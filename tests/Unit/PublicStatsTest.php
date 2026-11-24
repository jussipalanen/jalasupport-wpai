<?php

use Brain\Monkey\Functions;
use JalaSupport\Models\TicketMeta;
use JalaSupport\Services\PublicStats;
use JalaSupport\Services\TicketRepository;

beforeEach(function () {
    putenv('JALASUPPORT_PUBLIC_STATS');
    $this->repository = Mockery::mock(TicketRepository::class);
    $this->stats = new PublicStats($this->repository);

    $this->cache = [];
    Functions\when('get_transient')->alias(fn(string $key) => $this->cache[$key] ?? false);
    Functions\when('set_transient')->alias(function (string $key, mixed $value, int $ttl) {
        $this->cache[$key] = $value;

        return true;
    });
    Functions\when('delete_transient')->alias(function (string $key) {
        unset($this->cache[$key]);

        return true;
    });

    $this->countsFor = function (array $statuses, array $priorities = [], array $categories = []): void {
        $this->repository->shouldReceive('countByMeta')
            ->andReturnUsing(fn(string $key) => match ($key) {
                TicketMeta::STATUS => $statuses,
                TicketMeta::PRIORITY => $priorities,
                TicketMeta::CATEGORY => $categories,
            });
    };
});

afterEach(function () {
    putenv('JALASUPPORT_PUBLIC_STATS');
});

it('is on unless JALASUPPORT_PUBLIC_STATS is false', function () {
    expect(PublicStats::enabled())->toBeTrue();

    putenv('JALASUPPORT_PUBLIC_STATS=false');
    expect(PublicStats::enabled())->toBeFalse();

    putenv('JALASUPPORT_PUBLIC_STATS=true');
    expect(PublicStats::enabled())->toBeTrue();
});

it('returns null from the filter when switched off', function () {
    putenv('JALASUPPORT_PUBLIC_STATS=0');
    $this->repository->shouldNotReceive('countByMeta');

    expect($this->stats->filter())->toBeNull();
});

it('registers the filter and the cache invalidation hooks', function () {
    $this->stats->register();

    expect(has_filter(PublicStats::FILTER, [$this->stats, 'filter']))->not->toBeFalse()
        ->and(has_action('save_post_ticket', [$this->stats, 'flush']))->not->toBeFalse()
        ->and(has_action('deleted_post', [$this->stats, 'flush']))->not->toBeFalse()
        ->and(has_action('updated_post_meta', [$this->stats, 'flushOnMeta']))->not->toBeFalse();
});

it('counts totals, shares and the resolution rate in a fixed order', function () {
    ($this->countsFor)(
        ['open' => 3, 'in_progress' => 2, 'resolved' => 4, 'closed' => 1],
        ['low' => 1, 'normal' => 6, 'high' => 2, 'urgent' => 1],
        ['technical_support' => 5, 'billing' => 2, 'account_issues' => 3],
    );

    $stats = $this->stats->stats();

    expect($stats['total'])->toBe(10)
        ->and($stats['resolution_rate'])->toBe(50)
        ->and(array_column($stats['statuses'], 'key'))->toBe(['open', 'in_progress', 'resolved', 'closed'])
        ->and($stats['statuses'][0])->toBe(['key' => 'open', 'label' => 'Open', 'count' => 3, 'share' => 30])
        ->and(array_column($stats['priorities'], 'count'))->toBe([1, 6, 2, 1])
        ->and(array_column($stats['categories'], 'key'))->toContain('general_inquiry', 'other');

    // Categories without tickets are listed with zero.
    $other = array_values(array_filter($stats['categories'], fn(array $row) => $row['key'] === 'other'))[0];
    expect($other['count'])->toBe(0)->and($other['share'])->toBe(0);
});

it('handles an empty queue without dividing by zero', function () {
    ($this->countsFor)(['open' => 0, 'in_progress' => 0, 'resolved' => 0, 'closed' => 0]);

    $stats = $this->stats->stats();

    expect($stats['total'])->toBe(0)
        ->and($stats['resolution_rate'])->toBe(0)
        ->and(array_column($stats['statuses'], 'share'))->toBe([0, 0, 0, 0]);
});

it('serves cached numbers until a ticket field changes', function () {
    ($this->countsFor)(['open' => 1, 'in_progress' => 0, 'resolved' => 0, 'closed' => 0]);

    $this->stats->stats();
    $this->stats->stats();
    $this->repository->shouldHaveReceived('countByMeta')->times(3);

    // Unrelated meta keeps the cache, a ticket status change clears it.
    $this->stats->flushOnMeta(1, 7, 'customer_name');
    expect($this->cache)->toHaveKey(PublicStats::CACHE_KEY);

    $this->stats->flushOnMeta(1, 7, TicketMeta::STATUS);
    expect($this->cache)->not->toHaveKey(PublicStats::CACHE_KEY);
});
