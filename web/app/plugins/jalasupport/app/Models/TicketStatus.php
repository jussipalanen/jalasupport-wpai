<?php

declare(strict_types=1);

namespace JalaSupport\Models;

enum TicketStatus: string
{
    use HasOptions;

    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open', 'jalasupport'),
            self::InProgress => __('In Progress', 'jalasupport'),
            self::Resolved => __('Resolved', 'jalasupport'),
            self::Closed => __('Closed', 'jalasupport'),
        };
    }

    public static function default(): self
    {
        return self::Open;
    }
}
