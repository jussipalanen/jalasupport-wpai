<?php

/**
 * Theme filters.
 */

namespace App;

/**
 * Body classes for the application shell.
 */
add_filter('body_class', function (array $classes) {
    $route = current_route();

    if ($route !== null) {
        $classes[] = 'jalasupport-app';
        $classes[] = 'route-' . sanitize_html_class($route);
    }

    return $classes;
});
