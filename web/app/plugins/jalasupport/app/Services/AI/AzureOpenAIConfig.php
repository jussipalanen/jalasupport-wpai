<?php

declare(strict_types=1);

namespace JalaSupport\Services\AI;

/**
 * Azure OpenAI connection settings, read from Bedrock config constants
 * (populated from environment variables in config/application.php).
 */
final readonly class AzureOpenAIConfig
{
    public function __construct(
        public string $endpoint,
        public string $deployment,
        public string $apiVersion = '2024-10-21',
        public string $apiKey = '',
        public bool $useManagedIdentity = false,
        public int $timeout = 30,
    ) {}

    public static function fromEnvironment(): self
    {
        $value = static fn(string $name, mixed $default = ''): mixed => defined($name) ? constant($name) : (getenv($name) ?: $default);

        return new self(
            endpoint: rtrim((string) $value('AZURE_OPENAI_ENDPOINT'), '/'),
            deployment: (string) $value('AZURE_OPENAI_DEPLOYMENT'),
            apiVersion: (string) $value('AZURE_OPENAI_API_VERSION', '2024-10-21'),
            apiKey: (string) $value('AZURE_OPENAI_API_KEY'),
            useManagedIdentity: filter_var($value('AZURE_OPENAI_USE_MANAGED_IDENTITY', false), FILTER_VALIDATE_BOOLEAN),
            timeout: max(5, min(120, (int) $value('AZURE_OPENAI_TIMEOUT', 30))),
        );
    }

    public function isConfigured(): bool
    {
        return str_starts_with($this->endpoint, 'https://')
            && $this->deployment !== ''
            && ($this->apiKey !== '' || $this->useManagedIdentity);
    }

    public function chatCompletionsUrl(): string
    {
        return sprintf(
            '%s/openai/deployments/%s/chat/completions?api-version=%s',
            $this->endpoint,
            rawurlencode($this->deployment),
            rawurlencode($this->apiVersion),
        );
    }
}
