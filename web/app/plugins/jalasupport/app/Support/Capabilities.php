<?php

declare(strict_types=1);

namespace JalaSupport\Support;

/**
 * Capability and role definitions for ticket access.
 *
 * Tickets use their own capability type so that regular WordPress users
 * (subscribers, authors, editors) cannot read or modify tickets.
 */
final class Capabilities
{
    public const AGENT_ROLE = 'jalasupport_agent';

    /** Access to the support application (dashboard, ticket list). */
    public const ACCESS_APP = 'access_jalasupport';

    /** Manage application settings. */
    public const MANAGE_SETTINGS = 'manage_jalasupport';

    /**
     * Capability map passed to register_post_type().
     *
     * @return array<string, string>
     */
    public static function postTypeCapabilities(): array
    {
        return [
            'read' => 'read_tickets',
            'create_posts' => 'create_tickets',
        ];
    }

    /**
     * Primitive capabilities granted to support agents.
     *
     * @return list<string>
     */
    public static function agentCapabilities(): array
    {
        return [
            self::ACCESS_APP,
            'read_tickets',
            'read_private_tickets',
            'create_tickets',
            'edit_tickets',
            'edit_others_tickets',
            'edit_published_tickets',
            'edit_private_tickets',
            'publish_tickets',
        ];
    }

    /**
     * Primitive capabilities granted to administrators.
     *
     * @return list<string>
     */
    public static function administratorCapabilities(): array
    {
        return array_merge(self::agentCapabilities(), [
            self::MANAGE_SETTINGS,
            'delete_tickets',
            'delete_others_tickets',
            'delete_published_tickets',
            'delete_private_tickets',
        ]);
    }
}
