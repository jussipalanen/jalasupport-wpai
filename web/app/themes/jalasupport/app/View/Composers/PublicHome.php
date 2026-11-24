<?php

namespace App\View\Composers;

use Roots\Acorn\View\Composer;

use function App\app_url;

/**
 * Data for the public start page. The ticket totals come from the
 * JalaSupport plugin (jalasupport_public_stats filter); without the plugin,
 * or with JALASUPPORT_PUBLIC_STATS off, the page shows no numbers.
 */
class PublicHome extends Composer
{
    /**
     * @var array<int, string>
     */
    protected static $views = [
        'public.home',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function with(): array
    {
        $stats = $this->stats();

        return [
            'stats' => $stats,
            'loginUrl' => wp_login_url(app_url()),
            'statusTones' => [
                // Validated for the dark surface: lightness band, chroma and
                // colour-blind separation (in-progress/resolved rely on the
                // gaps, labels and legend as secondary encoding).
                'open' => ['fill' => 'bg-sky-600', 'ink' => 'text-slate-950'],
                'in_progress' => ['fill' => 'bg-amber-600', 'ink' => 'text-slate-950'],
                'resolved' => ['fill' => 'bg-emerald-600', 'ink' => 'text-slate-950'],
                'closed' => ['fill' => 'bg-violet-600', 'ink' => 'text-white'],
            ],
            'categories' => $stats ? $this->sortedByCount($stats['categories']) : [],
        ];
    }

    /**
     * @return array{total: int, resolution_rate: int, statuses: list<array{key: string, label: string, count: int, share: int}>, priorities: list<array{key: string, label: string, count: int, share: int}>, categories: list<array{key: string, label: string, count: int, share: int}>}|null
     */
    protected function stats(): ?array
    {
        $stats = apply_filters('jalasupport_public_stats', null);

        if (! is_array($stats) || ! isset($stats['total'], $stats['statuses'], $stats['priorities'], $stats['categories'])) {
            return null;
        }

        return $stats;
    }

    /**
     * Categories have no natural order: largest first.
     *
     * @param  list<array{key: string, label: string, count: int, share: int}>  $rows
     * @return list<array{key: string, label: string, count: int, share: int}>
     */
    protected function sortedByCount(array $rows): array
    {
        usort($rows, fn(array $a, array $b): int => $b['count'] <=> $a['count']);

        return $rows;
    }
}
