<?php

declare(strict_types=1);

namespace JalaSupport\Services;

use JalaSupport\Models\TicketCategory;
use JalaSupport\Models\TicketMeta;
use JalaSupport\Models\TicketPriority;
use JalaSupport\Models\TicketStatus;
use WP_Error;

/**
 * Validates and sanitizes ticket input from any source (REST, forms).
 *
 * Returns sanitized data keyed by the internal field names:
 * title, description and the TicketMeta keys.
 */
final class TicketValidator
{
    public const MAX_TITLE = 200;
    public const MAX_NAME = 200;
    public const MAX_DESCRIPTION = 10000;
    public const MAX_AI_TEXT = 5000;

    /**
     * @param  array<string, mixed>  $input
     * @param  bool  $partial  When true (updates) only provided fields are validated.
     * @return array<string, string>|WP_Error
     */
    public function validate(array $input, bool $partial = false): array|WP_Error
    {
        $data = [];
        $errors = [];

        $required = $partial ? [] : ['title', 'description', 'customer_name', 'customer_email'];

        foreach ($required as $field) {
            if (! isset($input[$field]) || trim($this->scalar($input[$field])) === '') {
                $errors[$field] = __('This field is required.', 'jalasupport');
            }
        }

        $this->text($input, 'title', 'title', self::MAX_TITLE, false, $data, $errors);
        $this->text($input, 'description', 'description', self::MAX_DESCRIPTION, true, $data, $errors);
        $this->text($input, 'customer_name', TicketMeta::CUSTOMER_NAME, self::MAX_NAME, false, $data, $errors);
        $this->text($input, 'ai_summary', TicketMeta::AI_SUMMARY, self::MAX_AI_TEXT, true, $data, $errors);
        $this->text($input, 'ai_suggested_reply', TicketMeta::AI_SUGGESTED_REPLY, self::MAX_AI_TEXT, true, $data, $errors);

        if (array_key_exists('customer_email', $input) && ! isset($errors['customer_email'])) {
            $email = sanitize_email($this->scalar($input['customer_email']));

            if ($email === '' || ! is_email($email)) {
                $errors['customer_email'] = __('Enter a valid email address.', 'jalasupport');
            } else {
                $data[TicketMeta::CUSTOMER_EMAIL] = $email;
            }
        }

        $this->choice($input, 'status', TicketMeta::STATUS, TicketStatus::values(), $data, $errors);
        $this->choice($input, 'priority', TicketMeta::PRIORITY, TicketPriority::values(), $data, $errors);
        $this->choice($input, 'category', TicketMeta::CATEGORY, TicketCategory::values(), $data, $errors);

        if (! $partial) {
            $data[TicketMeta::STATUS] ??= TicketStatus::default()->value;
            $data[TicketMeta::PRIORITY] ??= TicketPriority::default()->value;
            $data[TicketMeta::CATEGORY] ??= TicketCategory::default()->value;
        }

        if ($errors !== []) {
            return new WP_Error(
                'jalasupport_validation_failed',
                __('The submitted ticket data is invalid.', 'jalasupport'),
                ['status' => 422, 'errors' => $errors],
            );
        }

        if ($partial && $data === []) {
            return new WP_Error(
                'jalasupport_no_changes',
                __('No valid fields were provided.', 'jalasupport'),
                ['status' => 400],
            );
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, string>  $data
     * @param  array<string, string>  $errors
     */
    private function text(array $input, string $field, string $target, int $max, bool $multiline, array &$data, array &$errors): void
    {
        if (! array_key_exists($field, $input) || isset($errors[$field])) {
            return;
        }

        if (! is_scalar($input[$field]) && $input[$field] !== null) {
            $errors[$field] = __('Invalid value.', 'jalasupport');

            return;
        }

        $value = $multiline
            ? sanitize_textarea_field($this->scalar($input[$field]))
            : sanitize_text_field($this->scalar($input[$field]));

        if (mb_strlen($value) > $max) {
            /* translators: %d: maximum number of characters */
            $errors[$field] = sprintf(__('Must be %d characters or fewer.', 'jalasupport'), $max);

            return;
        }

        $data[$target] = $value;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<string>  $allowed
     * @param  array<string, string>  $data
     * @param  array<string, string>  $errors
     */
    private function choice(array $input, string $field, string $target, array $allowed, array &$data, array &$errors): void
    {
        if (! array_key_exists($field, $input)) {
            return;
        }

        $value = $this->scalar($input[$field]);

        if (! in_array($value, $allowed, true)) {
            /* translators: %s: comma separated list of allowed values */
            $errors[$field] = sprintf(__('Must be one of: %s.', 'jalasupport'), implode(', ', $allowed));

            return;
        }

        $data[$target] = $value;
    }

    private function scalar(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
