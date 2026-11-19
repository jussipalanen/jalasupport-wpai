<?php

declare(strict_types=1);

namespace JalaSupport\Models;

enum TicketPriority: string
{
    use HasOptions;

    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Low => __('Low', 'jalasupport'),
            self::Normal => __('Normal', 'jalasupport'),
            self::High => __('High', 'jalasupport'),
            self::Urgent => __('Urgent', 'jalasupport'),
        };
    }

    public static function default(): self
    {
        return self::Normal;
    }
}
