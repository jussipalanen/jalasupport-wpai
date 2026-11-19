<?php

declare(strict_types=1);

namespace JalaSupport\Services\AI;

use JalaSupport\Models\TicketCategory;
use JalaSupport\Models\TicketPriority;

/**
 * Validated AI suggestions for a ticket. Never persisted automatically.
 */
final readonly class TicketAnalysis
{
    public function __construct(
        public ?TicketCategory $category,
        public ?TicketPriority $priority,
        public string $summary,
        public string $suggestedReply,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'category' => $this->category?->value,
            'category_label' => $this->category?->label(),
            'priority' => $this->priority?->value,
            'priority_label' => $this->priority?->label(),
            'summary' => $this->summary,
            'suggested_reply' => $this->suggestedReply,
        ], static fn($value): bool => $value !== null && $value !== '');
    }
}
