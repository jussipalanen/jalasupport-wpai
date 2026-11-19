<?php

declare(strict_types=1);

namespace JalaSupport\PostTypes;

use JalaSupport\Support\Capabilities;

/**
 * The private "ticket" custom post type.
 *
 * Tickets are not public: they have no front-end permalinks, are excluded
 * from search and are not exposed through the core wp/v2 REST routes. REST
 * access is provided by the jalasupport/v1 namespace, which enforces
 * explicit permission checks and controls which fields are returned.
 */
final class TicketPostType
{
    public const NAME = 'ticket';

    public function register(): void
    {
        if (post_type_exists(self::NAME)) {
            return;
        }

        register_post_type(self::NAME, $this->args());
    }

    /**
     * @return array<string, mixed>
     */
    public function args(): array
    {
        return [
            'labels' => [
                'name' => __('Tickets', 'jalasupport'),
                'singular_name' => __('Ticket', 'jalasupport'),
                'add_new_item' => __('Add New Ticket', 'jalasupport'),
                'edit_item' => __('Edit Ticket', 'jalasupport'),
                'new_item' => __('New Ticket', 'jalasupport'),
                'view_item' => __('View Ticket', 'jalasupport'),
                'search_items' => __('Search Tickets', 'jalasupport'),
                'not_found' => __('No tickets found.', 'jalasupport'),
                'not_found_in_trash' => __('No tickets found in Trash.', 'jalasupport'),
                'all_items' => __('All Tickets', 'jalasupport'),
                'menu_name' => __('Tickets', 'jalasupport'),
            ],
            'description' => __('Customer support tickets.', 'jalasupport'),
            'public' => false,
            'publicly_queryable' => false,
            'exclude_from_search' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_nav_menus' => false,
            'show_in_admin_bar' => false,
            'show_in_rest' => false,
            'has_archive' => false,
            'rewrite' => false,
            'query_var' => false,
            'menu_position' => 25,
            'menu_icon' => 'dashicons-tickets-alt',
            'supports' => ['title', 'editor', 'author', 'revisions'],
            'capability_type' => ['ticket', 'tickets'],
            'capabilities' => Capabilities::postTypeCapabilities(),
            'map_meta_cap' => true,
            'delete_with_user' => false,
        ];
    }
}
