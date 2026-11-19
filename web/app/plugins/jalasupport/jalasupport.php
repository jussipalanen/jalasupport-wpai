<?php

/**
 * Plugin Name:       JalaSupport
 * Description:       Ticket management, REST API and AI assistance for JalaSupport AI.
 * Version:           0.1.0
 * Requires at least: 6.6
 * Requires PHP:      8.3
 * Author:            JalaSupport
 * Text Domain:       jalasupport
 * License:           Proprietary
 */

declare(strict_types=1);

use JalaSupport\Plugin;
use JalaSupport\Support\Installer;

if (! defined('ABSPATH')) {
    exit;
}

define('JALASUPPORT_VERSION', '0.1.0');
define('JALASUPPORT_FILE', __FILE__);
define('JALASUPPORT_PATH', plugin_dir_path(__FILE__));

// Classes are autoloaded by the Bedrock root composer.json (JalaSupport\ namespace).
if (! class_exists(Plugin::class)) {
    add_action('admin_notices', static function (): void {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__('JalaSupport: classes could not be autoloaded. Run "composer install" in the project root.', 'jalasupport'),
        );
    });

    return;
}

register_activation_hook(__FILE__, [Installer::class, 'activate']);
register_deactivation_hook(__FILE__, [Installer::class, 'deactivate']);

(new Plugin())->boot();
