<?php

declare(strict_types=1);

namespace JalaSupport\Support;

/**
 * Minimal diagnostic logger.
 *
 * Only log identifiers, codes and statuses. Never log ticket content,
 * customer data, prompts, API responses or credentials.
 */
final class Logger
{
    /**
     * @param  array<string, scalar|null>  $context
     */
    public static function error(string $event, array $context = []): void
    {
        error_log('[jalasupport] ' . $event . ($context === [] ? '' : ' ' . wp_json_encode($context)));
    }
}
