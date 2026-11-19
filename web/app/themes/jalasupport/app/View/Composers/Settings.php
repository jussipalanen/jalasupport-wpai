<?php

namespace App\View\Composers;

use JalaSupport\Services\AI\AzureOpenAIConfig;
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
     * @return array{configured: bool, endpoint: string, deployment: string, apiVersion: string, auth: string}|null
     */
    public function ai(): ?array
    {
        if (! class_exists(AzureOpenAIConfig::class)) {
            return null;
        }

        $config = AzureOpenAIConfig::fromEnvironment();
        $host = (string) wp_parse_url($config->endpoint, PHP_URL_HOST);

        return [
            'configured' => $config->isConfigured(),
            'endpoint' => $host,
            'deployment' => $config->deployment,
            'apiVersion' => $config->apiVersion,
            'auth' => $config->useManagedIdentity
                ? __('Managed identity (Microsoft Entra ID)', 'jalasupport')
                : ($config->apiKey !== '' ? __('API key', 'jalasupport') : __('Not configured', 'jalasupport')),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function environment(): array
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
