<?php

declare(strict_types=1);

namespace JalaSupport\Models;

use WP_Post;

/**
 * Immutable ticket read model built from a ticket post and its metadata.
 */
final readonly class Ticket
{
    public function __construct(
        public int $id,
        public string $title,
        public string $description,
        public TicketStatus $status,
        public TicketPriority $priority,
        public TicketCategory $category,
        public string $customerName,
        public string $customerEmail,
        public string $aiSummary,
        public string $aiSuggestedReply,
        public int $authorId,
        public string $createdAt,
        public string $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $meta  Meta values keyed by TicketMeta constants.
     */
    public static function fromPost(WP_Post $post, array $meta): self
    {
        $string = static fn(string $key): string => is_scalar($meta[$key] ?? null) ? (string) $meta[$key] : '';

        return new self(
            id: (int) $post->ID,
            title: (string) $post->post_title,
            description: (string) $post->post_content,
            status: TicketStatus::tryFrom($string(TicketMeta::STATUS)) ?? TicketStatus::default(),
            priority: TicketPriority::tryFrom($string(TicketMeta::PRIORITY)) ?? TicketPriority::default(),
            category: TicketCategory::tryFrom($string(TicketMeta::CATEGORY)) ?? TicketCategory::default(),
            customerName: $string(TicketMeta::CUSTOMER_NAME),
            customerEmail: $string(TicketMeta::CUSTOMER_EMAIL),
            aiSummary: $string(TicketMeta::AI_SUMMARY),
            aiSuggestedReply: $string(TicketMeta::AI_SUGGESTED_REPLY),
            authorId: (int) $post->post_author,
            createdAt: (string) $post->post_date_gmt,
            updatedAt: (string) $post->post_modified_gmt,
        );
    }

    /**
     * Public API representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'priority' => $this->priority->value,
            'priority_label' => $this->priority->label(),
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'customer' => [
                'name' => $this->customerName,
                'email' => $this->customerEmail,
            ],
            'ai' => [
                'summary' => $this->aiSummary,
                'suggested_reply' => $this->aiSuggestedReply,
            ],
            'author_id' => $this->authorId,
            'created_at' => self::toIso8601($this->createdAt),
            'updated_at' => self::toIso8601($this->updatedAt),
        ];
    }

    private static function toIso8601(string $gmt): ?string
    {
        if ($gmt === '' || str_starts_with($gmt, '0000')) {
            return null;
        }

        $date = date_create_immutable($gmt, new \DateTimeZone('UTC'));

        return $date ? $date->format(DATE_ATOM) : null;
    }
}
