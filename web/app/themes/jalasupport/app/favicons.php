<?php

/**
 * Favicons and device icons (presentation only).
 *
 * - favicon.ico (16/32/48) and an SVG icon for browser tabs
 * - apple-touch-icon.png (opaque, 180 px) for iOS/iPadOS home screens
 * - site.webmanifest with 192/512 px and maskable icons for Android
 * - theme-color for mobile browser bars
 *
 * Static icon files live in the web root (web/), so they are also found by
 * browsers and devices that request them directly (e.g. /favicon.ico).
 *
 * Sources: resources/images/favicon.svg (round; tab icon, favicon.ico,
 * icon-192/512) and resources/images/icon-touch.svg (square and opaque;
 * apple-touch-icon and the maskable Android icon). Re-export the PNG/ICO
 * files from these when the design changes.
 */

namespace App;

use Illuminate\Support\Facades\Vite;

function favicon_tags(): void
{
    $tags = [
        sprintf('<link rel="icon" href="%s" sizes="32x32">', esc_url(home_url('/favicon.ico'))),
        sprintf('<link rel="icon" href="%s" type="image/svg+xml">', esc_url(Vite::asset('resources/images/favicon.svg'))),
        sprintf('<link rel="apple-touch-icon" href="%s">', esc_url(home_url('/apple-touch-icon.png'))),
        sprintf('<link rel="manifest" href="%s">', esc_url(home_url('/site.webmanifest'))),
        '<meta name="theme-color" content="#0f172a">',
    ];

    echo implode("\n", $tags) . "\n";
}

add_action('wp_head', __NAMESPACE__ . '\\favicon_tags', 2);
add_action('login_head', __NAMESPACE__ . '\\favicon_tags');
add_action('admin_head', __NAMESPACE__ . '\\favicon_tags');
