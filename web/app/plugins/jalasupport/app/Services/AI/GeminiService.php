<?php

declare(strict_types=1);

namespace JalaSupport\Services\AI;

use JalaSupport\Models\Ticket;
use JalaSupport\Support\Logger;
use WP_Error;

/**
 * Google Gemini provider (Gemini Developer API, keys from Google AI Studio).
 *
 * Uses generateContent with a system instruction, the ticket as untrusted
 * JSON data and structured JSON output (responseJsonSchema). The API key is
 * sent in the x-goog-api-key header, never in the URL.
 *
 * Note: on Google's free tier, submitted content may be used to improve
 * Google's products. Use test data, or a paid key for real customer tickets.
 */
class GeminiService extends AbstractAiProvider
{
    /**
     * Finish / block reasons that mean the content was refused for policy
     * reasons rather than a technical failure.
     */
    private const BLOCKED_REASONS = ['SAFETY', 'PROHIBITED_CONTENT', 'BLOCKLIST', 'SPII', 'RECITATION', 'IMAGE_SAFETY', 'OTHER'];

    public function __construct(
        private GeminiConfig $config,
        AiResponseParser $parser = new AiResponseParser(),
    ) {
        parent::__construct($parser);
    }

    public function name(): string
    {
        return 'Google Gemini';
    }

    public function isConfigured(): bool
    {
        return $this->config->isConfigured();
    }

    public function describe(): array
    {
        return [
            __('API', 'jalasupport') => 'generativelanguage.googleapis.com',
            __('Model', 'jalasupport') => $this->config->model,
            __('Fallback model', 'jalasupport') => $this->config->hasFallback() ? $this->config->fallbackModel : __('None', 'jalasupport'),
            __('Authentication', 'jalasupport') => $this->config->apiKey !== ''
                ? __('API key (Google AI Studio)', 'jalasupport')
                : __('Not configured', 'jalasupport'),
        ];
    }

    protected function generate(Ticket $ticket, string $prompt, array $fields): string|WP_Error
    {
        $payload = (string) wp_json_encode($this->requestBody($ticket, $prompt, $fields));
        $response = $this->post($this->config->model, $payload);

        // The free tier is best-effort: retry once with the fallback model when
        // the primary model times out, is overloaded or is out of quota.
        $status = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response);

        if (in_array($status, [0, 429, 503], true) && $this->config->hasFallback()) {
            Logger::error('ai_model_fallback', ['ticket_id' => $ticket->id, 'status' => $status]);
            $response = $this->post($this->config->fallbackModel, $payload);
        }

        $body = $this->decodeResponse($response, $ticket);

        if ($body instanceof WP_Error) {
            return $body;
        }

        // The whole prompt was blocked before generation.
        if (isset($body['promptFeedback']['blockReason'])) {
            return $this->contentFiltered();
        }

        $candidate = $body['candidates'][0] ?? null;

        if (! is_array($candidate)) {
            return $this->invalidResponse($ticket, 'missing_candidate');
        }

        $finishReason = (string) ($candidate['finishReason'] ?? '');

        if (in_array($finishReason, self::BLOCKED_REASONS, true)) {
            return $this->contentFiltered();
        }

        if ($finishReason === 'MAX_TOKENS') {
            return $this->invalidResponse($ticket, 'max_tokens');
        }

        // Join the text parts, skipping any "thought" parts of thinking models.
        $text = '';

        foreach ((array) ($candidate['content']['parts'] ?? []) as $part) {
            if (is_array($part) && empty($part['thought']) && is_string($part['text'] ?? null)) {
                $text .= $part['text'];
            }
        }

        return $text !== '' ? $text : $this->invalidResponse($ticket, 'missing_content');
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    private function post(string $model, string $payload): array|WP_Error
    {
        return wp_remote_post($this->config->generateContentUrl($model), [
            'timeout' => $this->config->timeout,
            'headers' => [
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $this->config->apiKey,
            ],
            'body' => $payload,
            'data_format' => 'body',
        ]);
    }

    /**
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    public function requestBody(Ticket $ticket, string $prompt, array $fields): array
    {
        $body = [
            'systemInstruction' => [
                'parts' => [['text' => TicketPrompt::system($prompt)]],
            ],
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => TicketPrompt::userMessage($ticket)]]],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                // Headroom for models that reserve part of the output for thinking.
                'maxOutputTokens' => 4096,
                'responseMimeType' => 'application/json',
                'responseJsonSchema' => TicketPrompt::schema($fields),
            ],
        ];

        /** Allows adjusting model parameters (not the prompt or ticket data) per deployment. */
        return (array) apply_filters('jalasupport_ai_request_body', $body, $prompt, 'gemini');
    }
}
