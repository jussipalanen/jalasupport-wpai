<?php

declare(strict_types=1);

namespace JalaSupport\Services;

use JalaSupport\Models\TicketCategory;
use JalaSupport\Models\TicketMeta;
use JalaSupport\Models\TicketPriority;
use JalaSupport\Models\TicketStatus;
use JalaSupport\PostTypes\TicketPostType;

/**
 * Aggregate ticket numbers for the public start page: totals per status,
 * priority and category, never individual tickets.
 *
 * Themes read them with apply_filters('jalasupport_public_stats', null),
 * which returns null when JALASUPPORT_PUBLIC_STATS is off. The numbers are
 * cached for a few minutes and refreshed when a ticket changes, so guests
 * and bots don't cause database queries on every request.
 */
final class PublicStats
{
    public const FILTER = 'jalasupport_public_stats';

    public const CACHE_KEY = 'jalasupport_public_stats';

    /** Five minutes. */
    public const CACHE_TTL = 300;

    /** Statuses that count as handled for the resolution rate. */
    private const HANDLED = [TicketStatus::Resolved, TicketStatus::Closed];

    public function __construct(private TicketRepository $tickets) {}

    /**
     * On unless JALASUPPORT_PUBLIC_STATS is set to a false value.
     */
    public static function enabled(): bool
    {
        if (defined('JALASUPPORT_PUBLIC_STATS')) {
            return (bool) constant('JALASUPPORT_PUBLIC_STATS');
        }

        $value = getenv('JALASUPPORT_PUBLIC_STATS');

        return $value === false || $value === '' || filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function register(): void
    {
        add_filter(self::FILTER, [$this, 'filter']);

        add_action('save_post_' . TicketPostType::NAME, [$this, 'flush']);
        add_action('deleted_post', [$this, 'flush']);
        add_action('trashed_post', [$this, 'flush']);
        add_action('untrashed_post', [$this, 'flush']);

        foreach (['added_post_meta', 'updated_post_meta', 'deleted_post_meta'] as $hook) {
            add_action($hook, [$this, 'flushOnMeta'], 10, 3);
        }
    }

    /**
     * @return array{total: int, resolution_rate: int, statuses: list<array{key: string, label: string, count: int, share: int}>, priorities: list<array{key: string, label: string, count: int, share: int}>, categories: list<array{key: string, label: string, count: int, share: int}>}|null
     */
    public function filter(mixed $stats = null): ?array
    {
        return self::enabled() ? $this->stats() : null;
    }

    /**
     * @return array{total: int, resolution_rate: int, statuses: list<array{key: string, label: string, count: int, share: int}>, priorities: list<array{key: string, label: string, count: int, share: int}>, categories: list<array{key: string, label: string, count: int, share: int}>}
     */
    public function stats(): array
    {
        $cached = get_transient(self::CACHE_KEY);

        if (is_array($cached) && isset($cached['total'], $cached['statuses'])) {
            /** @var array{total: int, resolution_rate: int, statuses: list<array{key: string, label: string, count: int, share: int}>, priorities: list<array{key: string, label: string, count: int, share: int}>, categories: list<array{key: string, label: string, count: int, share: int}>} $cached */
            return $cached;
        }

        $statuses = $this->tickets->countByMeta(TicketMeta::STATUS, TicketStatus::values());
        $total = array_sum($statuses);
        $handled = array_sum(array_map(static fn(TicketStatus $status): int => $statuses[$status->value] ?? 0, self::HANDLED));

        $stats = [
            'total' => $total,
            'resolution_rate' => $this->share($handled, $total),
            'statuses' => $this->rows($statuses, TicketStatus::options(), $total),
            'priorities' => $this->rows(
                $this->tickets->countByMeta(TicketMeta::PRIORITY, TicketPriority::values()),
                TicketPriority::options(),
                $total,
            ),
            'categories' => $this->rows(
                $this->tickets->countByMeta(TicketMeta::CATEGORY, TicketCategory::values()),
                TicketCategory::options(),
                $total,
            ),
        ];

        set_transient(self::CACHE_KEY, $stats, self::CACHE_TTL);

        return $stats;
    }

    public function flush(): void
    {
        delete_transient(self::CACHE_KEY);
    }

    public function flushOnMeta(mixed $metaId, mixed $postId, mixed $metaKey): void
    {
        if (in_array($metaKey, [TicketMeta::STATUS, TicketMeta::PRIORITY, TicketMeta::CATEGORY], true)) {
            $this->flush();
        }
    }

    /**
     * @param  array<string, int>  $counts
     * @param  array<string, string>  $labels
     * @return list<array{key: string, label: string, count: int, share: int}>
     */
    private function rows(array $counts, array $labels, int $total): array
    {
        $rows = [];

        foreach ($labels as $key => $label) {
            $count = $counts[$key] ?? 0;
            $rows[] = ['key' => $key, 'label' => $label, 'count' => $count, 'share' => $this->share($count, $total)];
        }

        return $rows;
    }

    /** Whole percent of $part in $total (0 when there is nothing to count). */
    private function share(int $part, int $total): int
    {
        return $total > 0 ? (int) round($part / $total * 100) : 0;
    }
}
