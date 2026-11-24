<?php

declare(strict_types=1);

namespace JalaSupport;

use JalaSupport\Console\TicketsCommand;
use JalaSupport\Fields\TicketFields;
use JalaSupport\Http\Controllers\TicketAiController;
use JalaSupport\Http\Controllers\TicketController;
use JalaSupport\PostTypes\TicketPostType;
use JalaSupport\Services\AI\AiProviderFactory;
use JalaSupport\Services\PublicStats;
use JalaSupport\Services\TicketRepository;
use JalaSupport\Services\TicketTransfer;
use JalaSupport\Services\TicketValidator;
use JalaSupport\Support\AiAccessPolicy;
use JalaSupport\Support\DemoMode;
use JalaSupport\Support\Installer;
use JalaSupport\Support\RateLimiter;
use WP_CLI;

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
        (new DemoMode())->register();
        (new PublicStats(new TicketRepository()))->register();

        if (defined('WP_CLI') && WP_CLI) {
            $tickets = new TicketRepository();
            WP_CLI::add_command('jalasupport tickets', new TicketsCommand(
                $tickets,
                new TicketTransfer($tickets, new TicketValidator()),
            ));
        }

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
