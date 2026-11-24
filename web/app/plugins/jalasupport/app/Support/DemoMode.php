<?php

declare(strict_types=1);

namespace JalaSupport\Support;

use WP_User;

/**
 * Restrictions for public demos (JALASUPPORT_DEMO=true).
 *
 * Demo visitors use the support app only: wp-admin, the toolbar and the
 * admin link are reserved for administrators. Requests that the app itself
 * needs (admin-ajax.php, admin-post.php, the REST API) keep working.
 */
final class DemoMode
{
    /** Filter the theme uses to decide whether to show the wp-admin link. */
    public const ADMIN_ACCESS_FILTER = 'jalasupport_admin_access';

    public static function enabled(): bool
    {
        if (defined('JALASUPPORT_DEMO')) {
            return (bool) constant('JALASUPPORT_DEMO');
        }

        return filter_var(getenv('JALASUPPORT_DEMO'), FILTER_VALIDATE_BOOLEAN);
    }

    public function register(): void
    {
        if (! self::enabled()) {
            return;
        }

        add_action('admin_init', [$this, 'redirectFromAdmin'], 1);
        add_filter('show_admin_bar', [$this, 'filterAdminAccess']);
        add_filter(self::ADMIN_ACCESS_FILTER, [$this, 'filterAdminAccess']);
        add_filter('login_message', [$this, 'loginMessage']);
        add_filter('login_redirect', [$this, 'loginRedirect'], 10, 3);
    }

    /**
     * After login, non-administrators go to the app instead of wp-admin.
     * A requested app page (e.g. /tickets/12/) is kept.
     */
    public function loginRedirect(mixed $redirect, mixed $requested, mixed $user): string
    {
        $redirect = is_string($redirect) ? $redirect : '';

        if (! $user instanceof WP_User || user_can($user, 'manage_options')) {
            return $redirect;
        }

        return $redirect === '' || str_starts_with($redirect, admin_url()) ? home_url('/') : $redirect;
    }

    /**
     * Shows the public demo account on the login screen when one is
     * configured (JALASUPPORT_DEMO_LOGIN and JALASUPPORT_DEMO_PASSWORD).
     */
    public function loginMessage(mixed $message): string
    {
        $message = is_string($message) ? $message : '';
        $login = defined('JALASUPPORT_DEMO_LOGIN') ? (string) constant('JALASUPPORT_DEMO_LOGIN') : '';
        $password = defined('JALASUPPORT_DEMO_PASSWORD') ? (string) constant('JALASUPPORT_DEMO_PASSWORD') : '';

        if ($login === '' || $password === '') {
            return $message;
        }

        return $message . sprintf(
            '<p class="message">%s</p>',
            sprintf(
                /* translators: 1: demo username, 2: demo password */
                esc_html__('Demo: sign in with username %1$s and password %2$s.', 'jalasupport'),
                '<strong>' . esc_html($login) . '</strong>',
                '<strong>' . esc_html($password) . '</strong>',
            ),
        );
    }

    /**
     * Sends everyone except administrators from wp-admin back to the app.
     */
    public function redirectFromAdmin(): void
    {
        if ($this->allowsAdmin() || wp_doing_ajax() || $this->isAdminPost()) {
            return;
        }

        wp_safe_redirect(home_url('/'));
        exit;
    }

    public function filterAdminAccess(mixed $allowed): bool
    {
        return (bool) $allowed && $this->allowsAdmin();
    }

    private function allowsAdmin(): bool
    {
        return current_user_can('manage_options');
    }

    private function isAdminPost(): bool
    {
        return ($GLOBALS['pagenow'] ?? '') === 'admin-post.php';
    }
}
