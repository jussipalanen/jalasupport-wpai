<?php

declare(strict_types=1);

namespace JalaSupport\Services\AI;

use JalaSupport\Models\Ticket;
use JalaSupport\Support\Logger;
use WP_Error;

/**
 * Azure OpenAI provider (chat completions with strict JSON schema output).
 *
 * Authenticates with an API key or the App Service managed identity.
 */
class AzureOpenAIService extends AbstractAiProvider
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
        AiResponseParser $parser = new AiResponseParser(),
    ) {
        parent::__construct($parser);
    }

    public function name(): string
    {
        return 'Azure OpenAI';
    }

    public function isConfigured(): bool
    {
        return $this->config->isConfigured();
    }

    public function describe(): array
    {
        return [
            __('Endpoint', 'jalasupport') => (string) wp_parse_url($this->config->endpoint, PHP_URL_HOST),
            __('Deployment', 'jalasupport') => $this->config->deployment,
            __('API version', 'jalasupport') => $this->config->apiVersion,
            __('Authentication', 'jalasupport') => $this->config->useManagedIdentity
                ? __('Managed identity (Microsoft Entra ID)', 'jalasupport')
                : ($this->config->apiKey !== '' ? __('API key', 'jalasupport') : __('Not configured', 'jalasupport')),
        ];
    }

    protected function generate(Ticket $ticket, string $prompt, array $fields): string|WP_Error
    {
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

        $body = $this->decodeResponse($response, $ticket);

        if ($body instanceof WP_Error) {
            return $body;
        }

        $choice = $body['choices'][0] ?? null;

        if (($choice['finish_reason'] ?? null) === 'content_filter') {
            return $this->contentFiltered();
        }

        $content = $choice['message']['content'] ?? null;

        return is_string($content) ? $content : $this->invalidResponse($ticket, 'missing_content');
    }

    /**
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    public function requestBody(Ticket $ticket, string $prompt, array $fields): array
    {
        $body = [
            'messages' => [
                ['role' => 'system', 'content' => TicketPrompt::system($prompt)],
                ['role' => 'user', 'content' => TicketPrompt::userMessage($ticket)],
            ],
            'temperature' => 0.2,
            'max_tokens' => 1000,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => str_replace('-', '_', $prompt),
                    'strict' => true,
                    'schema' => TicketPrompt::schema($fields),
                ],
            ],
        ];

        /** Allows adjusting model parameters (not the prompt or ticket data) per deployment. */
        return (array) apply_filters('jalasupport_ai_request_body', $body, $prompt, 'azure');
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
}
