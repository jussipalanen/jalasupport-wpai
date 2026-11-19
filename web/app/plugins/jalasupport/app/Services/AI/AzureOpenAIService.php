<?php

declare(strict_types=1);

namespace JalaSupport\Services\AI;

use JalaSupport\Models\Ticket;
use JalaSupport\Models\TicketCategory;
use JalaSupport\Models\TicketPriority;
use JalaSupport\Support\Logger;
use WP_Error;

/**
 * Azure OpenAI integration for ticket assistance.
 *
 * - Authenticates with an API key or the App Service managed identity.
 * - Sends ticket content as JSON data (never as instructions) and requests
 *   strict structured JSON output.
 * - Validates the response and maps failures to safe WP_Error responses.
 */
class AzureOpenAIService
{
    private const COGNITIVE_SERVICES_SCOPE = 'https://cognitiveservices.azure.com';

    /**
     * Managed identity token, cached for the current request only so that
     * bearer tokens are never written to the database.
     *
     * @var array{token: string, expires: int}|null
     */
    private ?array $token = null;

    public function __construct(
        private AzureOpenAIConfig $config,
        private AiResponseParser $parser = new AiResponseParser(),
    ) {}

    public function isConfigured(): bool
    {
        return $this->config->isConfigured();
    }

    /**
     * Suggest category, priority, summary and a reply.
     */
    public function analyze(Ticket $ticket): TicketAnalysis|WP_Error
    {
        return $this->complete(
            $ticket,
            'ticket-analysis',
            ['category', 'priority', 'summary', 'suggested_reply'],
        );
    }

    /**
     * Suggest a customer reply only.
     */
    public function suggestReply(Ticket $ticket): TicketAnalysis|WP_Error
    {
        return $this->complete($ticket, 'suggest-reply', ['suggested_reply']);
    }

    /**
     * @param  list<string>  $fields
     */
    private function complete(Ticket $ticket, string $prompt, array $fields): TicketAnalysis|WP_Error
    {
        if (! $this->isConfigured()) {
            return new WP_Error(
                'jalasupport_ai_unavailable',
                __('AI assistance is not configured.', 'jalasupport'),
                ['status' => 503],
            );
        }

        $headers = $this->authHeaders();

        if ($headers instanceof WP_Error) {
            return $headers;
        }

        $response = wp_remote_post($this->config->chatCompletionsUrl(), [
            'timeout' => $this->config->timeout,
            'headers' => $headers + ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($this->requestBody($ticket, $prompt, $fields)),
            'data_format' => 'body',
        ]);

        if (is_wp_error($response)) {
            Logger::error('ai_request_failed', ['ticket_id' => $ticket->id, 'error' => $response->get_error_code()]);

            return $this->failure();
        }

        $status = (int) wp_remote_retrieve_response_code($response);

        if ($status === 429) {
            Logger::error('ai_rate_limited', ['ticket_id' => $ticket->id]);

            return new WP_Error(
                'jalasupport_ai_busy',
                __('The AI service is busy. Please try again in a moment.', 'jalasupport'),
                ['status' => 429],
            );
        }

        if ($status < 200 || $status >= 300) {
            Logger::error('ai_http_error', ['ticket_id' => $ticket->id, 'status' => $status]);

            return $this->failure();
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        $choice = is_array($body) ? ($body['choices'][0] ?? null) : null;

        if (($choice['finish_reason'] ?? null) === 'content_filter') {
            return new WP_Error(
                'jalasupport_ai_content_filtered',
                __('The AI service could not process this ticket.', 'jalasupport'),
                ['status' => 422],
            );
        }

        $content = $choice['message']['content'] ?? null;

        if (! is_string($content)) {
            Logger::error('ai_invalid_response', ['ticket_id' => $ticket->id, 'reason' => 'missing_content']);

            return new WP_Error(
                'jalasupport_ai_invalid_response',
                __('The AI service returned an invalid response. Please try again.', 'jalasupport'),
                ['status' => 502],
            );
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
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    public function requestBody(Ticket $ticket, string $prompt, array $fields): array
    {
        $properties = [
            'category' => ['type' => 'string', 'enum' => TicketCategory::values()],
            'priority' => ['type' => 'string', 'enum' => TicketPriority::values()],
            'summary' => ['type' => 'string'],
            'suggested_reply' => ['type' => 'string'],
        ];

        // Ticket data is passed as a JSON document so it cannot break out of
        // its role. Customer email is intentionally not sent.
        $ticketData = [
            'title' => $ticket->title,
            'description' => $ticket->description,
            'customer_name' => $ticket->customerName,
            'current_category' => $ticket->category->value,
            'current_priority' => $ticket->priority->value,
        ];

        $body = [
            'messages' => [
                ['role' => 'system', 'content' => $this->prompt($prompt)],
                ['role' => 'user', 'content' => "Ticket data (JSON):\n" . wp_json_encode($ticketData, JSON_UNESCAPED_UNICODE)],
            ],
            'temperature' => 0.2,
            'max_tokens' => 1000,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => str_replace('-', '_', $prompt),
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'properties' => array_intersect_key($properties, array_flip($fields)),
                        'required' => $fields,
                        'additionalProperties' => false,
                    ],
                ],
            ],
        ];

        /** Allows adjusting model parameters (not the prompt or ticket data) per deployment. */
        return (array) apply_filters('jalasupport_ai_request_body', $body, $prompt);
    }

    /**
     * @return array<string, string>|WP_Error
     */
    private function authHeaders(): array|WP_Error
    {
        if (! $this->config->useManagedIdentity) {
            return ['api-key' => $this->config->apiKey];
        }

        $token = $this->managedIdentityToken();

        return $token instanceof WP_Error ? $token : ['Authorization' => 'Bearer ' . $token];
    }

    /**
     * Fetches an Entra ID token from the App Service managed identity endpoint.
     */
    private function managedIdentityToken(): string|WP_Error
    {
        if ($this->token !== null && $this->token['expires'] > time() + 60) {
            return $this->token['token'];
        }

        $endpoint = (string) getenv('IDENTITY_ENDPOINT');
        $secret = (string) getenv('IDENTITY_HEADER');

        if ($endpoint === '' || $secret === '') {
            Logger::error('ai_managed_identity_unavailable');

            return $this->failure();
        }

        $query = ['resource' => self::COGNITIVE_SERVICES_SCOPE, 'api-version' => '2019-08-01'];

        if ($clientId = getenv('AZURE_CLIENT_ID')) {
            $query['client_id'] = $clientId;
        }

        $response = wp_remote_get(add_query_arg($query, $endpoint), [
            'timeout' => 10,
            'headers' => ['X-IDENTITY-HEADER' => $secret],
        ]);

        $body = is_wp_error($response) ? null : json_decode((string) wp_remote_retrieve_body($response), true);

        if (! is_array($body) || ! is_string($body['access_token'] ?? null)) {
            Logger::error('ai_managed_identity_token_failed');

            return $this->failure();
        }

        $this->token = ['token' => $body['access_token'], 'expires' => (int) ($body['expires_on'] ?? 0)];

        return $body['access_token'];
    }

    private function prompt(string $name): string
    {
        $path = dirname(__DIR__, 3) . '/resources/prompts/' . basename($name) . '.txt';

        return trim((string) file_get_contents($path));
    }

    private function failure(): WP_Error
    {
        return new WP_Error(
            'jalasupport_ai_failed',
            __('The AI service is currently unavailable. Please try again later.', 'jalasupport'),
            ['status' => 502],
        );
    }
}
