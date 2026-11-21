<?php

declare(strict_types=1);

namespace JalaSupport\Services;

use JalaSupport\Fields\TicketFields;
use JalaSupport\Models\Ticket;
use JalaSupport\Models\TicketMeta;
use JalaSupport\Models\TicketStatus;
use JalaSupport\PostTypes\TicketPostType;
use JalaSupport\Support\Logger;
use WP_Error;
use WP_Post;
use WP_Query;

/**
 * Persistence for tickets on top of WordPress posts and post meta.
 *
 * Expects already validated and sanitized data (see TicketValidator).
 * Authorization is the caller's responsibility.
 */
class TicketRepository
{
    public const MAX_PER_PAGE = 100;

    public const DEFAULT_PER_PAGE = 25;

    public const SORTABLE = ['created_at' => 'date', 'updated_at' => 'modified', 'title' => 'title'];

    public function find(int $id): ?Ticket
    {
        $post = get_post($id);

        if (! $post instanceof WP_Post || $post->post_type !== TicketPostType::NAME || in_array($post->post_status, ['trash', 'auto-draft'], true)) {
            return null;
        }

        return $this->hydrate($post);
    }

    /**
     * @param  array<string, string>  $data  Sanitized data from TicketValidator.
     */
    public function create(array $data, int $authorId): Ticket|WP_Error
    {
        $postId = wp_insert_post([
            'post_type' => TicketPostType::NAME,
            'post_status' => 'publish',
            'post_title' => $data['title'],
            'post_content' => $data['description'],
            'post_author' => $authorId,
        ], true);

        if ($postId instanceof WP_Error) {
            Logger::error('ticket_create_failed', ['error' => $postId->get_error_code()]);

            return $this->persistenceError();
        }

        $this->saveMeta($postId, $data);

        return $this->find($postId) ?? $this->persistenceError();
    }

    /**
     * @param  array<string, string>  $data  Sanitized (partial) data from TicketValidator.
     */
    public function update(Ticket $ticket, array $data): Ticket|WP_Error
    {
        $postData = array_filter([
            'post_title' => $data['title'] ?? null,
            'post_content' => $data['description'] ?? null,
        ], static fn($value): bool => $value !== null);

        if ($postData !== []) {
            $result = wp_update_post(['ID' => $ticket->id] + $postData, true);

            if ($result instanceof WP_Error) {
                Logger::error('ticket_update_failed', ['ticket_id' => $ticket->id, 'error' => $result->get_error_code()]);

                return $this->persistenceError();
            }
        }

        $this->saveMeta($ticket->id, $data);

        if ($postData === []) {
            // Meta-only changes: still bump the modified date for activity tracking.
            wp_update_post(['ID' => $ticket->id]);
        }

        return $this->find($ticket->id) ?? $this->persistenceError();
    }

    /**
     * Moves the ticket to the trash (recoverable from the admin).
     */
    public function delete(Ticket $ticket): bool
    {
        return wp_trash_post($ticket->id) instanceof WP_Post;
    }

    /**
     * @param  array{search?: string, status?: string, priority?: string, category?: string, orderby?: string, order?: string, page?: int, per_page?: int}  $filters
     * @return array{items: list<Ticket>, total: int, pages: int, page: int, per_page: int}
     */
    public function paginate(array $filters): array
    {
        $perPage = max(1, min(self::MAX_PER_PAGE, (int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE)));
        $page = max(1, (int) ($filters['page'] ?? 1));

        $metaQuery = [];

        foreach (['status' => TicketMeta::STATUS, 'priority' => TicketMeta::PRIORITY, 'category' => TicketMeta::CATEGORY] as $filter => $key) {
            if (! empty($filters[$filter])) {
                $metaQuery[] = ['key' => $key, 'value' => $filters[$filter], 'compare' => '='];
            }
        }

        $args = [
            'post_type' => TicketPostType::NAME,
            'post_status' => 'publish',
            'posts_per_page' => $perPage,
            'paged' => $page,
            'orderby' => self::SORTABLE[$filters['orderby'] ?? 'created_at'] ?? 'date',
            'order' => strtoupper($filters['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC',
            'ignore_sticky_posts' => true,
            'update_post_term_cache' => false,
        ];

        if ($metaQuery !== []) {
            $args['meta_query'] = ['relation' => 'AND', ...$metaQuery];
        }

        if (! empty($filters['search'])) {
            $args['s'] = $filters['search'];
        }

        $query = new WP_Query($args);
        $total = (int) $query->found_posts;

        // WP_Query only counts matches when the requested page has results.
        // For a page past the end, count separately so clients can recover.
        if ($query->posts === [] && $page > 1) {
            $total = (int) (new WP_Query([
                'posts_per_page' => 1,
                'paged' => 1,
                'fields' => 'ids',
                'update_post_meta_cache' => false,
            ] + $args))->found_posts;
        }

        return [
            'items' => array_values(array_map(fn(WP_Post $post): Ticket => $this->hydrate($post), $query->posts)),
            'total' => $total,
            'pages' => (int) ceil($total / $perPage),
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Ticket counts per status plus the total.
     *
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        $counts = ['total' => 0];

        foreach (TicketStatus::values() as $status) {
            $query = new WP_Query([
                'post_type' => TicketPostType::NAME,
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'meta_query' => [['key' => TicketMeta::STATUS, 'value' => $status]],
            ]);

            $counts[$status] = (int) $query->found_posts;
            $counts['total'] += $counts[$status];
        }

        return $counts;
    }

    private function hydrate(WP_Post $post): Ticket
    {
        // get_post_meta() is served from the meta cache primed by WP_Query.
        $meta = [];

        foreach (TicketMeta::keys() as $key) {
            $meta[$key] = get_post_meta($post->ID, $key, true);
        }

        return Ticket::fromPost($post, $meta);
    }

    /**
     * @param  array<string, string>  $data
     */
    private function saveMeta(int $postId, array $data): void
    {
        foreach (TicketMeta::keys() as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            // Prefer ACF so its field reference meta stays in sync with the admin UI.
            if (function_exists('update_field')) {
                update_field(TicketFields::fieldKey($key), $data[$key], $postId);
            } else {
                update_post_meta($postId, $key, $data[$key]);
            }
        }
    }

    private function persistenceError(): WP_Error
    {
        return new WP_Error(
            'jalasupport_persistence_failed',
            __('The ticket could not be saved.', 'jalasupport'),
            ['status' => 500],
        );
    }
}
