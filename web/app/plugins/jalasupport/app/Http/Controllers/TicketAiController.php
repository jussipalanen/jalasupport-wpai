<?php

declare(strict_types=1);

namespace JalaSupport\Http\Controllers;

use JalaSupport\Services\AI\AiProvider;
use JalaSupport\Services\AI\TicketAnalysis;
use JalaSupport\Services\TicketRepository;
use JalaSupport\Support\RateLimiter;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * AI assistance endpoints. They only return suggestions; applying a
 * suggestion is a separate, explicit ticket update by the user.
 */
final class TicketAiController extends Controller
{
    public function __construct(
        TicketRepository $tickets,
        private AiProvider $ai,
        private RateLimiter $limiter,
    ) {
        parent::__construct($tickets);
    }

    public function registerRoutes(): void
    {
        foreach (['analyze' => 'analyze', 'suggest-reply' => 'suggestReply'] as $route => $method) {
            register_rest_route(self::NAMESPACE, '/tickets/(?P<id>\d+)/ai/' . $route, [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, $method],
                'permission_callback' => [$this, 'canUseAi'],
                'args' => $this->idArgument(),
            ]);
        }
    }

    /**
     * AI is available to users who can edit the ticket.
     */
    public function canUseAi(WP_REST_Request $request): bool|WP_Error
    {
        return $this->authorizeTicket($request, 'edit_post');
    }

    public function analyze(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return $this->run($request, fn($ticket) => $this->ai->analyze($ticket));
    }

    public function suggestReply(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return $this->run($request, fn($ticket) => $this->ai->suggestReply($ticket));
    }

    /**
     * @param  callable(\JalaSupport\Models\Ticket): (TicketAnalysis|WP_Error)  $callback
     */
    private function run(WP_REST_Request $request, callable $callback): WP_REST_Response|WP_Error
    {
        $ticket = $this->findOrFail($request);

        if ($ticket instanceof WP_Error) {
            return $ticket;
        }

        if (! $this->ai->isConfigured()) {
            return new WP_Error('jalasupport_ai_unavailable', __('AI assistance is not configured.', 'jalasupport'), ['status' => 503]);
        }

        if (! $this->limiter->attempt('ai:' . get_current_user_id())) {
            return new WP_Error(
                'jalasupport_ai_rate_limited',
                __('Too many AI requests. Please wait a few minutes and try again.', 'jalasupport'),
                ['status' => 429],
            );
        }

        $analysis = $callback($ticket);

        if ($analysis instanceof WP_Error) {
            return $analysis;
        }

        return $this->respond([
            'ticket_id' => $ticket->id,
            'suggestions' => $analysis->toArray(),
            'applied' => false,
        ]);
    }
}
