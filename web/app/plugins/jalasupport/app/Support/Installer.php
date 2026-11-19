<?php

declare(strict_types=1);

namespace JalaSupport\Support;

use JalaSupport\PostTypes\TicketPostType;

/**
 * Installs roles and capabilities. Runs on activation and whenever the stored
 * schema version is older than the plugin version (e.g. after a deploy).
 */
final class Installer
{
    public const VERSION_OPTION = 'jalasupport_version';

    public static function activate(): void
    {
        self::install();
        (new TicketPostType())->register();
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }

    public static function maybeUpgrade(): void
    {
        if (get_option(self::VERSION_OPTION) !== JALASUPPORT_VERSION) {
            self::install();
        }
    }

    public static function install(): void
    {
        $agent = get_role(Capabilities::AGENT_ROLE) ?? add_role(
            Capabilities::AGENT_ROLE,
            __('Support Agent', 'jalasupport'),
            ['read' => true],
        );

        if ($agent !== null) {
            foreach (Capabilities::agentCapabilities() as $capability) {
                $agent->add_cap($capability);
            }
        }

        $administrator = get_role('administrator');

        if ($administrator !== null) {
            foreach (Capabilities::administratorCapabilities() as $capability) {
                $administrator->add_cap($capability);
            }
        }

        update_option(self::VERSION_OPTION, JALASUPPORT_VERSION, false);
    }
}
