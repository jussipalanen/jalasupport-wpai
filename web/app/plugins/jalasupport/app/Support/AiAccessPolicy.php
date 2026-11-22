<?php

declare(strict_types=1);

namespace JalaSupport\Support;

use WP_Error;
use WP_REST_Request;

/**
 * Restricts the AI endpoints to interactive use from this site's own UI.
 *
 * - Requests authenticated with an application password (external API
 *   clients) are refused unless AI_ALLOW_API_CLIENTS is enabled. Browser
 *   requests use cookie authentication, which WordPress only accepts together
 *   with a valid REST nonce.
 * - Requests whose Origin header is another site, or that the browser marks
 *   as cross-site (Sec-Fetch-Site), are refused.
 *
 * This complements, not replaces, the capability checks and rate limits.
 */
final class AiAccessPolicy
{
    public function __construct(
        private string $siteOrigin,
        private bool $allowApiClients = false,
    ) {}

    public static function fromEnvironment(): self
    {
        $allow = defined('AI_ALLOW_API_CLIENTS') ? constant('AI_ALLOW_API_CLIENTS') : getenv('AI_ALLOW_API_CLIENTS');

        return new self(
            self::origin((string) home_url('/')),
            filter_var($allow, FILTER_VALIDATE_BOOLEAN),
        );
    }

    public function check(WP_REST_Request $request): true|WP_Error
    {
        if (! $this->allowApiClients
            && function_exists('rest_get_authenticated_app_password')
            && rest_get_authenticated_app_password() !== null
        ) {
            return $this->deny(__('AI assistance is only available in the JalaSupport application.', 'jalasupport'));
        }

        $origin = (string) $request->get_header('origin');

        if ($origin !== '' && self::origin($origin) !== $this->siteOrigin) {
            return $this->deny(__('Cross-site AI requests are not allowed.', 'jalasupport'));
        }

        $fetchSite = strtolower((string) $request->get_header('sec_fetch_site'));

        if ($fetchSite !== '' && ! in_array($fetchSite, ['same-origin', 'none'], true)) {
            return $this->deny(__('Cross-site AI requests are not allowed.', 'jalasupport'));
        }

        return true;
    }

    /**
     * Normalized "scheme://host[:port]" (default ports omitted).
     */
    public static function origin(string $url): string
    {
        $parts = wp_parse_url($url);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        $scheme = strtolower($parts['scheme']);
        $port = isset($parts['port']) && ! in_array([$scheme, (int) $parts['port']], [['http', 80], ['https', 443]], true)
            ? ':' . $parts['port']
            : '';

        return $scheme . '://' . strtolower($parts['host']) . $port;
    }

    private function deny(string $message): WP_Error
    {
        return new WP_Error('jalasupport_ai_forbidden', $message, ['status' => 403]);
    }
}
