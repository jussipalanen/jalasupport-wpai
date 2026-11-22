<?php

declare(strict_types=1);

namespace JalaSupport\Services\AI;

use JalaSupport\Models\Ticket;
use JalaSupport\Models\TicketCategory;
use JalaSupport\Models\TicketPriority;

/**
 * Provider-independent prompt material: system instructions, the ticket as
 * untrusted JSON data, and the JSON schema of the expected answer.
 */
final class TicketPrompt
{
    public const ANALYZE = 'ticket-analysis';
    public const SUGGEST_REPLY = 'suggest-reply';

    /**
     * Required answer fields per prompt.
     *
     * @var array<string, list<string>>
     */
    public const FIELDS = [
        self::ANALYZE => ['category', 'priority', 'summary', 'suggested_reply'],
        self::SUGGEST_REPLY => ['suggested_reply'],
    ];

    /**
     * Version-controlled system instructions (resources/prompts/*.txt).
     */
    public static function system(string $prompt): string
    {
        $path = dirname(__DIR__, 3) . '/resources/prompts/' . basename($prompt) . '.txt';

        return trim((string) file_get_contents($path));
    }

    /**
     * The ticket as a JSON document, so its content cannot break out of its
     * role as data. The customer email address is intentionally not sent.
     */
    public static function userMessage(Ticket $ticket): string
    {
        $ticketData = [
            'title' => $ticket->title,
            'description' => $ticket->description,
            'customer_name' => $ticket->customerName,
            'current_category' => $ticket->category->value,
            'current_priority' => $ticket->priority->value,
        ];

        return "Ticket data (JSON):\n" . wp_json_encode($ticketData, JSON_UNESCAPED_UNICODE);
    }

    /**
     * JSON schema for the answer.
     *
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    public static function schema(array $fields): array
    {
        $properties = [
            'category' => ['type' => 'string', 'enum' => TicketCategory::values()],
            'priority' => ['type' => 'string', 'enum' => TicketPriority::values()],
            'summary' => ['type' => 'string'],
            'suggested_reply' => ['type' => 'string'],
        ];

        return [
            'type' => 'object',
            'properties' => array_intersect_key($properties, array_flip($fields)),
            'required' => $fields,
            'additionalProperties' => false,
        ];
    }
}
