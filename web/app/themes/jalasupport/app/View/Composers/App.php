<?php

namespace App\View\Composers;

use JalaSupport\Models\TicketCategory;
use JalaSupport\Models\TicketPriority;
use JalaSupport\Models\TicketStatus;
use Roots\Acorn\View\Composer;

use function App\app_url;
use function App\current_route;

/**
 * Shared data for the application shell and all views.
 */
class App extends Composer
{
    /**
     * Views that render the application shell or pages (not small components).
     *
     * @var array<int, string>
     */
    protected static $views = [
        'layouts.*',
        'sections.*',
        'app.*',
        'index',
        '404',
    ];

    /**
     * Data passed to the views. Values are evaluated here so templates
     * receive plain arrays and scalars.
     *
     * @return array<string, mixed>
     */
    protected function with(): array
    {
        return [
            'siteName' => $this->siteName(),
            'route' => current_route(),
            'navigation' => $this->navigation(),
            'currentUser' => $this->currentUser(),
            'ticketOptions' => $this->ticketOptions(),
            'pluginActive' => $this->pluginActive(),
            'clientConfig' => $this->clientConfig(),
        ];
    }

    protected function siteName(): string
    {
        return get_bloginfo('name', 'display');
    }

    /**
     * Sidebar navigation for the current user.
     *
     * @return array<int, array{label: string, url: string, icon: string, active: bool}>
     */
    protected function navigation(): array
    {
        $route = current_route();

        $items = [
            ['label' => __('Dashboard', 'jalasupport'), 'url' => app_url(), 'icon' => 'home', 'active' => $route === 'dashboard'],
            ['label' => __('Tickets', 'jalasupport'), 'url' => app_url('tickets/'), 'icon' => 'inbox', 'active' => in_array($route, ['tickets', 'ticket'], true)],
            ['label' => __('New ticket', 'jalasupport'), 'url' => app_url('tickets/new/'), 'icon' => 'plus', 'active' => $route === 'ticket-create'],
        ];

        if (current_user_can('manage_jalasupport')) {
            $items[] = ['label' => __('Settings', 'jalasupport'), 'url' => app_url('settings/'), 'icon' => 'cog', 'active' => $route === 'settings'];
        }

        return $items;
    }

    /**
     * @return array{name: string, email: string, avatar: string, logout: string, admin: ?string}|null
     */
    protected function currentUser(): ?array
    {
        $user = wp_get_current_user();

        if (! $user->exists()) {
            return null;
        }

        return [
            'name' => $user->display_name,
            'email' => $user->user_email,
            'avatar' => (string) get_avatar_url($user->ID, ['size' => 64]),
            'logout' => wp_logout_url(app_url()),
            // The plugin's demo mode hides wp-admin from non-administrators.
            'admin' => (current_user_can('edit_posts') || current_user_can('edit_tickets'))
                && apply_filters('jalasupport_admin_access', true) ? admin_url() : null,
        ];
    }

    /**
     * Ticket vocabulary from the JalaSupport plugin (empty when inactive).
     *
     * @return array{statuses: array<string, string>, priorities: array<string, string>, categories: array<string, string>}
     */
    protected function ticketOptions(): array
    {
        if (! class_exists(TicketStatus::class)) {
            return ['statuses' => [], 'priorities' => [], 'categories' => []];
        }

        return [
            'statuses' => TicketStatus::options(),
            'priorities' => TicketPriority::options(),
            'categories' => TicketCategory::options(),
        ];
    }

    protected function pluginActive(): bool
    {
        return class_exists(TicketStatus::class) && post_type_exists('ticket');
    }

    /**
     * Non-sensitive configuration for the Alpine components. The REST nonce
     * is a per-user CSRF token for cookie authentication, not a secret.
     *
     * @return array<string, mixed>
     */
    protected function clientConfig(): array
    {
        return [
            'restUrl' => esc_url_raw(rest_url('jalasupport/v1/')),
            'nonce' => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
            'appUrl' => esc_url_raw(app_url()),
            'canDelete' => current_user_can('delete_others_tickets'),
            'options' => $this->ticketOptions(),
        ];
    }
}
