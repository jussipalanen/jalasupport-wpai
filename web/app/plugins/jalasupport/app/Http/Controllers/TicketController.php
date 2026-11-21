<?php

declare(strict_types=1);

namespace JalaSupport\Http\Controllers;

use JalaSupport\Models\Ticket;
use JalaSupport\Models\TicketCategory;
use JalaSupport\Models\TicketPriority;
use JalaSupport\Models\TicketStatus;
use JalaSupport\Services\TicketRepository;
use JalaSupport\Services\TicketValidator;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * CRUD endpoints for tickets: /jalasupport/v1/tickets.
 */
final class TicketController extends Controller
{
    public function __construct(TicketRepository $tickets, private TicketValidator $validator)
    {
        parent::__construct($tickets);
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/tickets', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'index'],
                'permission_callback' => [$this, 'canList'],
                'args' => $this->collectionArguments(),
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'store'],
                'permission_callback' => [$this, 'canCreate'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/tickets/stats', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'stats'],
            'permission_callback' => [$this, 'canList'],
        ]);

        register_rest_route(self::NAMESPACE, '/tickets/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'show'],
                'permission_callback' => [$this, 'canView'],
                'args' => $this->idArgument(),
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update'],
                'permission_callback' => [$this, 'canUpdate'],
                'args' => $this->idArgument(),
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'destroy'],
                'permission_callback' => [$this, 'canDelete'],
                'args' => $this->idArgument(),
            ],
        ]);
    }

    public function canList(): bool|WP_Error
    {
        return $this->authorize('read_tickets');
    }

    public function canCreate(): bool|WP_Error
    {
        return $this->authorize('create_tickets');
    }

    public function canView(WP_REST_Request $request): bool|WP_Error
    {
        return $this->authorizeTicket($request, 'read_post');
    }

    public function canUpdate(WP_REST_Request $request): bool|WP_Error
    {
        return $this->authorizeTicket($request, 'edit_post');
    }

    public function canDelete(WP_REST_Request $request): bool|WP_Error
    {
        return $this->authorizeTicket($request, 'delete_post');
    }

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $result = $this->tickets->paginate([
            'search' => (string) $request->get_param('search'),
            'status' => (string) $request->get_param('status'),
            'priority' => (string) $request->get_param('priority'),
            'category' => (string) $request->get_param('category'),
            'orderby' => (string) $request->get_param('orderby'),
            'order' => (string) $request->get_param('order'),
            'page' => (int) $request->get_param('page'),
            'per_page' => (int) $request->get_param('per_page'),
        ]);

        $response = new WP_REST_Response([
            'data' => array_map(static fn(Ticket $ticket): array => $ticket->toArray(), $result['items']),
            'meta' => [
                'total' => $result['total'],
                'pages' => $result['pages'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
            ],
        ]);

        $response->header('X-WP-Total', (string) $result['total']);
        $response->header('X-WP-TotalPages', (string) $result['pages']);

        return $response;
    }

    public function stats(): WP_REST_Response
    {
        return $this->respond($this->tickets->countByStatus());
    }

    public function store(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $data = $this->validator->validate($this->input($request));

        if ($data instanceof WP_Error) {
            return $data;
        }

        $ticket = $this->tickets->create($data, get_current_user_id());

        return $ticket instanceof WP_Error ? $ticket : $this->respond($ticket->toArray(), 201);
    }

    public function show(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $ticket = $this->findOrFail($request);

        return $ticket instanceof WP_Error ? $ticket : $this->respond($ticket->toArray());
    }

    public function update(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $ticket = $this->findOrFail($request);

        if ($ticket instanceof WP_Error) {
            return $ticket;
        }

        $data = $this->validator->validate($this->input($request), partial: true);

        if ($data instanceof WP_Error) {
            return $data;
        }

        $updated = $this->tickets->update($ticket, $data);

        return $updated instanceof WP_Error ? $updated : $this->respond($updated->toArray());
    }

    public function destroy(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $ticket = $this->findOrFail($request);

        if ($ticket instanceof WP_Error) {
            return $ticket;
        }

        if (! $this->tickets->delete($ticket)) {
            return new WP_Error('jalasupport_delete_failed', __('The ticket could not be deleted.', 'jalasupport'), ['status' => 500]);
        }

        return $this->respond(['id' => $ticket->id, 'deleted' => true]);
    }

    /**
     * Body parameters only (JSON or form), never query string or route values.
     *
     * @return array<string, mixed>
     */
    private function input(WP_REST_Request $request): array
    {
        // get_json_params() returns null when the request has no JSON body.
        $body = (array) $request->get_json_params();

        return $body !== [] ? $body : (array) $request->get_body_params();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function collectionArguments(): array
    {
        $enum = static fn(array $values): array => [
            'type' => 'string',
            'enum' => array_merge([''], $values),
            'default' => '',
        ];

        return [
            'search' => ['type' => 'string', 'default' => '', 'maxLength' => 200, 'sanitize_callback' => 'sanitize_text_field'],
            'status' => $enum(TicketStatus::values()),
            'priority' => $enum(TicketPriority::values()),
            'category' => $enum(TicketCategory::values()),
            'orderby' => ['type' => 'string', 'enum' => array_keys(TicketRepository::SORTABLE), 'default' => 'created_at'],
            'order' => ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'desc'],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => TicketRepository::MAX_PER_PAGE, 'default' => TicketRepository::DEFAULT_PER_PAGE],
        ];
    }
}
