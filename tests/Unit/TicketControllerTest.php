<?php

use Brain\Monkey\Functions;
use JalaSupport\Http\Controllers\TicketController;
use JalaSupport\Models\Ticket;
use JalaSupport\Services\TicketRepository;
use JalaSupport\Services\TicketValidator;

function makeTicket(int $id = 7): Ticket
{
    return Ticket::fromPost(new WP_Post(['ID' => $id, 'post_type' => 'ticket', 'post_title' => 'Help']), []);
}

beforeEach(function () {
    $this->repository = Mockery::mock(TicketRepository::class);
    $this->controller = new TicketController($this->repository, new TicketValidator());

    $this->loginAs = function (array $capabilities): void {
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('current_user_can')->alias(fn(string $cap) => in_array($cap, $capabilities, true));
    };
});

describe('collection permissions', function () {
    it('requires authentication', function () {
        Functions\when('is_user_logged_in')->justReturn(false);

        expect($this->controller->canList()->get_error_data()['status'])->toBe(401)
            ->and($this->controller->canCreate()->get_error_data()['status'])->toBe(401);
    });

    it('forbids users without ticket capabilities', function () {
        ($this->loginAs)(['read']);

        expect($this->controller->canList()->get_error_data()['status'])->toBe(403)
            ->and($this->controller->canCreate()->get_error_data()['status'])->toBe(403);
    });

    it('allows support agents', function () {
        ($this->loginAs)(['read_tickets', 'create_tickets']);

        expect($this->controller->canList())->toBeTrue()
            ->and($this->controller->canCreate())->toBeTrue();
    });
});

describe('single ticket permissions', function () {
    it('requires authentication', function () {
        Functions\when('is_user_logged_in')->justReturn(false);

        expect($this->controller->canView(new WP_REST_Request(['id' => 7]))->get_error_data()['status'])->toBe(401);
    });

    it('does not reveal whether a ticket exists to unauthorized users', function () {
        ($this->loginAs)(['read']);
        $this->repository->shouldReceive('find')->with(999)->andReturnNull();

        expect($this->controller->canView(new WP_REST_Request(['id' => 999]))->get_error_data()['status'])->toBe(403);
    });

    it('lets authorized users through so a missing ticket returns 404', function () {
        ($this->loginAs)(['read_tickets']);
        $this->repository->shouldReceive('find')->with(999)->andReturnNull();

        expect($this->controller->canView(new WP_REST_Request(['id' => 999])))->toBeTrue()
            ->and($this->controller->show(new WP_REST_Request(['id' => 999]))->get_error_data()['status'])->toBe(404);
    });

    it('checks the meta capability for the specific ticket', function () {
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\expect('current_user_can')->once()->with('edit_post', 7)->andReturn(false);
        $this->repository->shouldReceive('find')->with(7)->andReturn(makeTicket());

        expect($this->controller->canUpdate(new WP_REST_Request(['id' => 7]))->get_error_data()['status'])->toBe(403);
    });

    it('forbids deletion for users without delete capability', function () {
        ($this->loginAs)(['read_tickets', 'read_post', 'edit_post']);
        $this->repository->shouldReceive('find')->with(7)->andReturn(makeTicket());

        expect($this->controller->canDelete(new WP_REST_Request(['id' => 7]))->get_error_data()['status'])->toBe(403);
    });
});

describe('handlers', function () {
    beforeEach(function () {
        Functions\when('sanitize_text_field')->alias(fn($v) => trim(strip_tags($v)));
        Functions\when('sanitize_textarea_field')->alias(fn($v) => trim(strip_tags($v)));
        Functions\when('sanitize_email')->returnArg();
        Functions\when('is_email')->alias(fn($v) => filter_var($v, FILTER_VALIDATE_EMAIL) !== false);
    });

    it('returns 422 for invalid input without touching the database', function () {
        $this->repository->shouldNotReceive('create');

        $response = $this->controller->store(new WP_REST_Request([], ['title' => 'Only a title']));

        expect($response)->toBeInstanceOf(WP_Error::class)
            ->and($response->get_error_data()['status'])->toBe(422);
    });

    it('creates tickets as the current user and returns 201', function () {
        Functions\when('get_current_user_id')->justReturn(5);
        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn(array $data) => $data['title'] === 'Help' && $data['ticket_status'] === 'open'), 5)
            ->andReturn(makeTicket());

        $response = $this->controller->store(new WP_REST_Request([], [
            'title' => 'Help',
            'description' => 'Details',
            'customer_name' => 'Jane',
            'customer_email' => 'jane@example.com',
        ]));

        expect($response->get_status())->toBe(201)
            ->and($response->get_data()['data']['id'])->toBe(7);
    });

    it('applies partial updates', function () {
        $ticket = makeTicket();
        $this->repository->shouldReceive('find')->with(7)->andReturn($ticket);
        $this->repository->shouldReceive('update')->once()->with($ticket, ['ticket_status' => 'resolved'])->andReturn($ticket);

        $response = $this->controller->update(new WP_REST_Request(['id' => 7], ['status' => 'resolved']));

        expect($response->get_status())->toBe(200);
    });

    it('returns pagination metadata and headers', function () {
        $this->repository->shouldReceive('paginate')->once()->andReturn([
            'items' => [makeTicket(1), makeTicket(2)],
            'total' => 42,
            'pages' => 3,
            'page' => 1,
            'per_page' => 20,
        ]);

        $response = $this->controller->index(new WP_REST_Request(['page' => 1, 'per_page' => 20]));

        expect($response->get_data()['data'])->toHaveCount(2)
            ->and($response->get_data()['meta']['total'])->toBe(42)
            ->and($response->headers['X-WP-TotalPages'])->toBe('3');
    });
});
