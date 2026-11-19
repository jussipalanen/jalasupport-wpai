<?php

declare(strict_types=1);

namespace JalaSupport\Models;

/**
 * Ticket metadata keys (stored as post meta, managed through ACF).
 */
final class TicketMeta
{
    public const STATUS = 'ticket_status';
    public const PRIORITY = 'ticket_priority';
    public const CATEGORY = 'ticket_category';
    public const CUSTOMER_NAME = 'customer_name';
    public const CUSTOMER_EMAIL = 'customer_email';
    public const AI_SUMMARY = 'ai_summary';
    public const AI_SUGGESTED_REPLY = 'ai_suggested_reply';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [
            self::STATUS,
            self::PRIORITY,
            self::CATEGORY,
            self::CUSTOMER_NAME,
            self::CUSTOMER_EMAIL,
            self::AI_SUMMARY,
            self::AI_SUGGESTED_REPLY,
        ];
    }
}
