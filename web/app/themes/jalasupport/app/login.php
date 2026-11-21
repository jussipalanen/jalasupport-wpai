<?php

/**
 * WordPress login screen branding (presentation only).
 */

namespace App;

use Illuminate\Support\Facades\Vite;

use function Roots\view;

/**
 * Login screen styles (built by Vite).
 */
add_action('login_head', function () {
    echo Vite::withEntryPoints(['resources/css/login.css'])->toHtml();
});

/**
 * Space backdrop, rendered right after the opening <body> tag.
 */
add_action('login_header', function () {
    echo view('partials.backdrop')->render();
});

/**
 * Logo links to the application and shows the site name.
 */
add_filter('login_headerurl', fn() => home_url('/'));
add_filter('login_headertext', fn() => esc_html(get_bloginfo('name')));

/**
 * Keep the login card focused: hide the language switcher.
 */
add_filter('login_display_language_dropdown', '__return_false');
