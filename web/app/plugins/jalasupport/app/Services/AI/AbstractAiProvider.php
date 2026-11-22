<?php

declare(strict_types=1);

namespace JalaSupport\Services\AI;

use JalaSupport\Models\Ticket;
use JalaSupport\Support\Logger;
use WP_Error;

/**
 * Shared flow for HTTP-based AI providers: configuration check, response
 * validation and safe error mapping. Providers only implement the API call.
 *
 * Errors returned to clients never contain provider messages, response
 * bodies or credentials; logs contain only codes and statuses.
 */
abstract class AbstractAiProvider implements AiProvider
{
    public function __construct(
        protected AiResponseParser $parser = new AiResponseParser(),
    ) {}

    /**
     * Call the provider and return the raw JSON text of the answer.
     *
     * @param  list<string>  $fields
     */
    abstract protected function generate(Ticket $ticket, string $prompt, array $fields): string|WP_Error;

    public function analyze(Ticket $ticket): TicketAnalysis|WP_Error
    {
        return $this->complete($ticket, TicketPrompt::ANALYZE);
    }

    public function suggestReply(Ticket $ticket): TicketAnalysis|WP_Error
    {
        return $this->complete($ticket, TicketPrompt::SUGGEST_REPLY);
    }

    private function complete(Ticket $ticket, string $prompt): TicketAnalysis|WP_Error
    {
        if (! $this->isConfigured()) {
            return new WP_Error(
                'jalasupport_ai_unavailable',
                __('AI assistance is not configured.', 'jalasupport'),
                ['status' => 503],
            );
        }

        $fields = TicketPrompt::FIELDS[$prompt];
        $content = $this->generate($ticket, $prompt, $fields);

        if ($content instanceof WP_Error) {
            return $content;
        }

        $result = $this->parser->parse($content, $fields);

        if ($result instanceof WP_Error) {
            $data = $result->get_error_data();
            Logger::error('ai_invalid_response', ['ticket_id' => $ticket->id, 'reason' => is_array($data) ? ($data['reason'] ?? null) : null]);

            // Do not expose validation internals to the client.
            return new WP_Error($result->get_error_code(), $result->get_error_message(), ['status' => 502]);
        }

        return $result;
    }

    /**
     * Common HTTP response handling: transport errors, throttling and non-2xx
     * statuses. Returns the decoded JSON body.
     *
     * @param  array<string, mixed>|WP_Error  $response  Result of wp_remote_post().
     * @return array<string, mixed>|WP_Error
     */
    protected function decodeResponse(array|WP_Error $response, Ticket $ticket): array|WP_Error
    {
        if (is_wp_error($response)) {
            Logger::error('ai_request_failed', ['ticket_id' => $ticket->id, 'error' => $response->get_error_code()]);

            return $this->failure();
        }

        $status = (int) wp_remote_retrieve_response_code($response);

        // 429: provider quota or throttling. 503: provider temporarily overloaded.
        if ($status === 429 || $status === 503) {
            Logger::error('ai_busy', ['ticket_id' => $ticket->id, 'status' => $status]);

            return new WP_Error(
                'jalasupport_ai_busy',
                __('The AI service is busy. Please try again in a moment.', 'jalasupport'),
                ['status' => $status],
            );
        }

        if ($status < 200 || $status >= 300) {
            Logger::error('ai_http_error', ['ticket_id' => $ticket->id, 'status' => $status]);

            return $this->failure();
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        return is_array($body) ? $body : $this->invalidResponse($ticket, 'malformed_body');
    }

    protected function contentFiltered(): WP_Error
    {
        return new WP_Error(
            'jalasupport_ai_content_filtered',
            __('The AI service could not process this ticket.', 'jalasupport'),
            ['status' => 422],
        );
    }

    protected function invalidResponse(Ticket $ticket, string $reason): WP_Error
    {
        Logger::error('ai_invalid_response', ['ticket_id' => $ticket->id, 'reason' => $reason]);

        return new WP_Error(
            'jalasupport_ai_invalid_response',
            __('The AI service returned an invalid response. Please try again.', 'jalasupport'),
            ['status' => 502],
        );
    }

    protected function failure(): WP_Error
    {
        return new WP_Error(
            'jalasupport_ai_failed',
            __('The AI service is currently unavailable. Please try again later.', 'jalasupport'),
            ['status' => 502],
        );
    }
}
