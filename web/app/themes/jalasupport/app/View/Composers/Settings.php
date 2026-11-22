<?php

namespace App\View\Composers;

use JalaSupport\Services\AI\AiProviderFactory;
use JalaSupport\Services\AI\GeminiService;
use Roots\Acorn\View\Composer;

/**
 * Read-only application settings. Configuration comes from environment
 * variables; secrets are never passed to the view.
 */
class Settings extends Composer
{
    /**
     * @var array<int, string>
     */
    protected static $views = [
        'app.settings',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function with(): array
    {
        return [
            'ai' => $this->ai(),
            'environment' => $this->environment(),
        ];
    }

    /**
     * The active AI provider and its non-sensitive settings.
     *
     * @return array{provider: string, configured: bool, details: array<string, string>, freeTierNotice: bool}|null
     */
    protected function ai(): ?array
    {
        if (! class_exists(AiProviderFactory::class)) {
            return null;
        }

        $provider = AiProviderFactory::fromEnvironment();

        return [
            'provider' => $provider->name(),
            'configured' => $provider->isConfigured(),
            'details' => array_map(static fn(string $value): string => $value !== '' ? $value : '—', $provider->describe()),
            'freeTierNotice' => $provider instanceof GeminiService,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function environment(): array
    {
        return [
            __('Environment', 'jalasupport') => (string) (defined('WP_ENV') ? WP_ENV : wp_get_environment_type()),
            __('WordPress', 'jalasupport') => get_bloginfo('version'),
            __('PHP', 'jalasupport') => PHP_VERSION,
            __('Plugin version', 'jalasupport') => defined('JALASUPPORT_VERSION') ? JALASUPPORT_VERSION : '—',
            __('REST API', 'jalasupport') => rest_url('jalasupport/v1/'),
        ];
    }
}
