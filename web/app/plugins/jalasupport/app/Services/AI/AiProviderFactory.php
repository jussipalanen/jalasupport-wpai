<?php

declare(strict_types=1);

namespace JalaSupport\Services\AI;

/**
 * Chooses the AI provider from the AI_PROVIDER setting.
 *
 * - "azure" or "gemini": that provider (even if it is not configured, so the
 *   UI reports "not configured" instead of silently using another service).
 * - empty: the first configured provider (Azure OpenAI, then Gemini).
 */
final class AiProviderFactory
{
    public static function fromEnvironment(): AiProvider
    {
        $choice = defined('AI_PROVIDER') ? (string) constant('AI_PROVIDER') : (string) getenv('AI_PROVIDER');

        return self::select(
            $choice,
            new AzureOpenAIService(AzureOpenAIConfig::fromEnvironment()),
            new GeminiService(GeminiConfig::fromEnvironment()),
        );
    }

    public static function select(string $choice, AiProvider $azure, AiProvider $gemini): AiProvider
    {
        return match (strtolower(trim($choice))) {
            'azure' => $azure,
            'gemini' => $gemini,
            default => match (true) {
                $azure->isConfigured() => $azure,
                $gemini->isConfigured() => $gemini,
                default => $azure,
            },
        };
    }
}
