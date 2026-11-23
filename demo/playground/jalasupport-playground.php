<?php

/**
 * Plugin Name: JalaSupport Playground support
 * Description: WordPress Playground runs PHP with the "cli" SAPI. Tell Acorn (the theme's framework) that page requests are web requests, so it renders the Blade views.
 *
 * Installed by demo/playground/blueprint.json only; not part of the app.
 */

if (! defined('WP_CLI')) {
    putenv('APP_RUNNING_IN_CONSOLE=false');
    $_ENV['APP_RUNNING_IN_CONSOLE'] = 'false';
    $_SERVER['APP_RUNNING_IN_CONSOLE'] = 'false';
}
