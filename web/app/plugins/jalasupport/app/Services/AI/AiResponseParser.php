<?php

declare(strict_types=1);

namespace JalaSupport\Services\AI;

use JalaSupport\Models\TicketCategory;
use JalaSupport\Models\TicketPriority;
use JalaSupport\Services\TicketValidator;
use WP_Error;

/**
 * Parses and validates the model's JSON output. AI output is untrusted:
 * every field is type-checked, mapped to known values and sanitized.
 */
final class AiResponseParser
{
    /**
     * @param  list<string>  $fields  Fields that must be present: category, priority, summary, suggested_reply.
     */
    public function parse(string $content, array $fields): TicketAnalysis|WP_Error
    {
        $json = json_decode($content, true);

        if (! is_array($json)) {
            return $this->invalid('malformed_json');
        }

        $category = null;
        $priority = null;
        $summary = '';
        $reply = '';

        if (in_array('category', $fields, true)) {
            $category = is_string($json['category'] ?? null) ? TicketCategory::fromInput($json['category']) : null;

            if ($category === null) {
                return $this->invalid('category');
            }
        }

        if (in_array('priority', $fields, true)) {
            $priority = is_string($json['priority'] ?? null) ? TicketPriority::fromInput($json['priority']) : null;

            if ($priority === null) {
                return $this->invalid('priority');
            }
        }

        if (in_array('summary', $fields, true)) {
            $summary = $this->text($json['summary'] ?? null);

            if ($summary === null) {
                return $this->invalid('summary');
            }
        }

        if (in_array('suggested_reply', $fields, true)) {
            $reply = $this->text($json['suggested_reply'] ?? null);

            if ($reply === null) {
                return $this->invalid('suggested_reply');
            }
        }

        return new TicketAnalysis($category, $priority, $summary, $reply);
    }

    private function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = sanitize_textarea_field($value);

        if ($value === '' || mb_strlen($value) > TicketValidator::MAX_AI_TEXT) {
            return null;
        }

        return $value;
    }

    private function invalid(string $reason): WP_Error
    {
        return new WP_Error(
            'jalasupport_ai_invalid_response',
            __('The AI service returned an invalid response. Please try again.', 'jalasupport'),
            ['status' => 502, 'reason' => $reason],
        );
    }
}
