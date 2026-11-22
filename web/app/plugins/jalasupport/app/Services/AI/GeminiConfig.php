<?php

declare(strict_types=1);

namespace JalaSupport\Services\AI;

/**
 * Google Gemini (Gemini Developer API / Google AI Studio) settings, read from
 * Bedrock config constants populated from environment variables.
 */
final readonly class GeminiConfig
{
    public const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

    /**
     * Google's alias for the current Flash-Lite model: fast, with the highest
     * free-tier limits, and enough for ticket triage and reply drafts. "latest"
     * aliases keep working when pinned versions are retired for new keys
     * (as gemini-2.5-flash was).
     */
    public const DEFAULT_MODEL = 'gemini-flash-lite-latest';

    /**
     * Tried once when the primary model times out, is overloaded (503) or is
     * out of quota (429). Each model has its own free-tier quota.
     */
    public const DEFAULT_FALLBACK_MODEL = 'gemini-flash-latest';

    /**
     * Seconds per attempt. Primary plus fallback stays below PHP's
     * max_execution_time (60 s).
     */
    public const DEFAULT_TIMEOUT = 20;

    public function __construct(
        public string $apiKey,
        public string $model = self::DEFAULT_MODEL,
        public int $timeout = self::DEFAULT_TIMEOUT,
        public string $fallbackModel = self::DEFAULT_FALLBACK_MODEL,
    ) {}

    public static function fromEnvironment(): self
    {
        $value = static fn(string $name, mixed $default = ''): mixed => defined($name) ? constant($name) : (getenv($name) ?: $default);

        return new self(
            apiKey: (string) $value('GEMINI_API_KEY'),
            model: (string) ($value('GEMINI_MODEL') ?: self::DEFAULT_MODEL),
            timeout: max(5, min(25, (int) $value('GEMINI_TIMEOUT', self::DEFAULT_TIMEOUT))),
            fallbackModel: (string) $value('GEMINI_FALLBACK_MODEL', self::DEFAULT_FALLBACK_MODEL),
        );
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && self::isValidModel($this->model);
    }

    /**
     * A fallback is used only when it is a valid, different model.
     * Set GEMINI_FALLBACK_MODEL to "none" to disable it.
     */
    public function hasFallback(): bool
    {
        return $this->fallbackModel !== $this->model && self::isValidModel($this->fallbackModel);
    }

    /**
     * Model names are interpolated into the URL path: allow only the
     * characters model IDs use.
     */
    public static function isValidModel(string $model): bool
    {
        return $model !== 'none' && preg_match('/^[a-z0-9][a-z0-9.\-]{1,62}$/', $model) === 1;
    }

    public function generateContentUrl(?string $model = null): string
    {
        return self::BASE_URL . '/models/' . rawurlencode($model ?? $this->model) . ':generateContent';
    }
}
