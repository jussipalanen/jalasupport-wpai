<?php

declare(strict_types=1);

namespace JalaSupport;

use JalaSupport\PostTypes\TicketPostType;
use JalaSupport\Support\Installer;

/**
 * Plugin bootstrap: wires WordPress hooks to the application components.
 */
final class Plugin
{
    public function boot(): void
    {
        add_action('init', [Installer::class, 'maybeUpgrade'], 5);
        add_action('init', [new TicketPostType(), 'register']);
    }
}
