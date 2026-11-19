<?php

declare(strict_types=1);

namespace JalaSupport\Http\Controllers;

use JalaSupport\Models\Ticket;
use JalaSupport\Services\TicketRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Shared helpers for jalasupport/v1 controllers.
 *
 * Success responses are wrapped as {"data": ...}; errors use WP_Error so they
 * follow the standard WordPress REST error format {code, message, data}.
 */
abstract class Controller
{
    public const NAMESPACE = 'jalasupport/v1';

    public function __construct(protected TicketRepository $tickets) {}

    abstract public function registerRoutes(): void;

    /**
     * Permission check for routes that act on a single ticket.
     *
     * Unauthorized users get 401/403 whether or not the ticket exists, so
     * ticket IDs cannot be enumerated. Users who can read tickets get a 404
     * from the handler for missing tickets.
     */
    protected function authorizeTicket(WP_REST_Request $request, string $metaCapability): bool|WP_Error
    {
        if (! is_user_logged_in()) {
            return $this->unauthorized();
        }

        $ticket = $this->tickets->find((int) $request->get_param('id'));

        if ($ticket === null) {
            return current_user_can('read_tickets') ? true : $this->forbidden();
        }

        return current_user_can($metaCapability, $ticket->id) ? true : $this->forbidden();
    }

    protected function authorize(string $capability): bool|WP_Error
    {
        if (! is_user_logged_in()) {
            return $this->unauthorized();
        }

        return current_user_can($capability) ? true : $this->forbidden();
    }

    protected function findOrFail(WP_REST_Request $request): Ticket|WP_Error
    {
        return $this->tickets->find((int) $request->get_param('id')) ?? new WP_Error(
            'jalasupport_ticket_not_found',
            __('Ticket not found.', 'jalasupport'),
            ['status' => 404],
        );
    }

    protected function respond(mixed $data, int $status = 200): WP_REST_Response
    {
        return new WP_REST_Response(['data' => $data], $status);
    }

    protected function unauthorized(): WP_Error
    {
        return new WP_Error('jalasupport_unauthorized', __('Authentication is required.', 'jalasupport'), ['status' => 401]);
    }

    protected function forbidden(): WP_Error
    {
        return new WP_Error('jalasupport_forbidden', __('You are not allowed to do this.', 'jalasupport'), ['status' => 403]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function idArgument(): array
    {
        return [
            'id' => [
                'type' => 'integer',
                'minimum' => 1,
                'required' => true,
                'sanitize_callback' => 'absint',
            ],
        ];
    }
}
