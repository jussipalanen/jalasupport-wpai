<?php

declare(strict_types=1);

namespace JalaSupport\Services\AI;

use JalaSupport\Models\Ticket;
use WP_Error;

/**
 * An AI service that suggests ticket classifications and replies.
 *
 * Implementations return validated suggestions only; applying them is always
 * a separate, user-confirmed ticket update.
 */
interface AiProvider
{
    /**
     * Human-readable provider name, e.g. "Azure OpenAI".
     */
    public function name(): string;

    public function isConfigured(): bool;

    /**
     * Suggest category, priority, summary and a reply.
     */
    public function analyze(Ticket $ticket): TicketAnalysis|WP_Error;

    /**
     * Suggest a customer reply only.
     */
    public function suggestReply(Ticket $ticket): TicketAnalysis|WP_Error;

    /**
     * Non-sensitive configuration summary for the settings screen.
     * Never includes secrets.
     *
     * @return array<string, string>
     */
    public function describe(): array;
}
