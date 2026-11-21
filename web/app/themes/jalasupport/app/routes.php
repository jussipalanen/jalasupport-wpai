<?php

/**
 * Front-end application routes.
 *
 * The theme maps pretty URLs to Blade views. It renders the application
 * shell only: data is loaded from the jalasupport/v1 REST API, which
 * enforces authorization. Access to the views is still gated here so that
 * guests never see the application UI.
 */

namespace App;

const ROUTES_VERSION = '1';

/**
 * Route name => Blade view (relative to resources/views).
 *
 * @return array<string, string>
 */
function app_views(): array
{
    return [
        'dashboard' => 'app/dashboard',
        'tickets' => 'app/tickets/index',
        'ticket-create' => 'app/tickets/create',
        'ticket' => 'app/tickets/show',
        'settings' => 'app/settings',
    ];
}

/**
 * The current application route, or null for non-application requests.
 */
function current_route(): ?string
{
    $view = (string) get_query_var('jalasupport_view');

    if ($view !== '' && array_key_exists($view, app_views())) {
        return $view;
    }

    return is_front_page() ? 'dashboard' : null;
}

function app_url(string $path = ''): string
{
    return home_url('/' . ltrim($path, '/'));
}

add_action('init', function () {
    add_rewrite_rule('^tickets/?$', 'index.php?jalasupport_view=tickets', 'top');
    add_rewrite_rule('^tickets/new/?$', 'index.php?jalasupport_view=ticket-create', 'top');
    add_rewrite_rule('^tickets/([0-9]+)/?$', 'index.php?jalasupport_view=ticket&jalasupport_ticket=$matches[1]', 'top');
    add_rewrite_rule('^settings/?$', 'index.php?jalasupport_view=settings', 'top');

    // The application routes need pretty permalinks; new installs default to
    // plain links, so switch them on once.
    if (get_option('permalink_structure') === '') {
        update_option('permalink_structure', '/%postname%/');
        delete_option('jalasupport_theme_routes');
    }

    if (get_option('jalasupport_theme_routes') !== ROUTES_VERSION) {
        flush_rewrite_rules(false);
        update_option('jalasupport_theme_routes', ROUTES_VERSION, false);
    }
});

add_action('after_switch_theme', function () {
    delete_option('jalasupport_theme_routes');
});

add_filter('query_vars', function (array $vars) {
    $vars[] = 'jalasupport_view';
    $vars[] = 'jalasupport_ticket';

    return $vars;
});

/**
 * Require login and application access for every application route.
 */
add_action('template_redirect', function () {
    if (current_route() === null) {
        return;
    }

    if (! is_user_logged_in()) {
        auth_redirect();
    }

    nocache_headers();
});

/**
 * Hide the admin bar inside the application UI.
 */
add_filter('show_admin_bar', fn($show) => current_route() === null ? $show : false);

/**
 * Serve the Blade view for the current route. Runs before Sage's own
 * template_include filter (priority 100), which renders the Blade file.
 */
add_filter('template_include', function (string $template) {
    $route = current_route();

    if ($route === null) {
        return $template;
    }

    $view = can_access_app() ? app_views()[$route] : 'app/forbidden';

    if ($route === 'settings' && ! current_user_can('manage_jalasupport')) {
        $view = 'app/forbidden';
    }

    if ($view === 'app/forbidden') {
        status_header(403);
    }

    return get_theme_file_path("resources/views/{$view}.blade.php");
}, 50);

/**
 * Document title for application routes.
 */
add_filter('document_title_parts', function (array $parts) {
    $titles = [
        'dashboard' => __('Dashboard', 'jalasupport'),
        'tickets' => __('Tickets', 'jalasupport'),
        'ticket-create' => __('New ticket', 'jalasupport'),
        'ticket' => __('Ticket', 'jalasupport'),
        'settings' => __('Settings', 'jalasupport'),
    ];

    $route = current_route();

    if ($route !== null) {
        $parts['title'] = $titles[$route];
    }

    return $parts;
});

function can_access_app(): bool
{
    return is_user_logged_in() && current_user_can('access_jalasupport');
}
