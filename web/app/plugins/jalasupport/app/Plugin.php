<?php

declare(strict_types=1);

namespace JalaSupport;

use JalaSupport\Fields\TicketFields;
use JalaSupport\Http\Controllers\TicketAiController;
use JalaSupport\Http\Controllers\TicketController;
use JalaSupport\PostTypes\TicketPostType;
use JalaSupport\Services\AI\AiProviderFactory;
use JalaSupport\Services\TicketRepository;
use JalaSupport\Services\TicketValidator;
use JalaSupport\Support\AiAccessPolicy;
use JalaSupport\Support\Installer;
use JalaSupport\Support\RateLimiter;

/**
 * Plugin bootstrap: wires WordPress hooks to the application components.
 */
final class Plugin
{
    public function boot(): void
    {
        add_action('init', [Installer::class, 'maybeUpgrade'], 5);
        add_action('init', [new TicketPostType(), 'register']);
        (new TicketFields())->register();

        add_action('rest_api_init', static function (): void {
            $tickets = new TicketRepository();

            (new TicketController($tickets, new TicketValidator()))->registerRoutes();
            (new TicketAiController(
                $tickets,
                AiProviderFactory::fromEnvironment(),
                new RateLimiter(
                    (int) apply_filters('jalasupport_ai_rate_limit', 20),
                    (int) apply_filters('jalasupport_ai_rate_window', 10 * MINUTE_IN_SECONDS),
                ),
                new RateLimiter(
                    (int) apply_filters('jalasupport_ai_daily_limit', defined('AI_DAILY_LIMIT') ? (int) AI_DAILY_LIMIT : 200),
                    DAY_IN_SECONDS,
                ),
                AiAccessPolicy::fromEnvironment(),
            ))->registerRoutes();
        });
    }
}
