<?php

use Brain\Monkey\Functions;
use JalaSupport\Models\Ticket;
use JalaSupport\Services\TicketRepository;
use JalaSupport\Services\TicketTransfer;
use JalaSupport\Services\TicketValidator;

function transferTicket(int $id, string $title = 'Cannot log in'): Ticket
{
    return Ticket::fromPost(new WP_Post([
        'ID' => $id,
        'post_type' => 'ticket',
        'post_title' => $title,
        'post_content' => 'The reset link has expired.',
        'post_author' => 3,
        'post_date_gmt' => '2026-11-20 10:00:00',
        'post_modified_gmt' => '2026-11-21 10:00:00',
    ]), [
        'ticket_status' => 'in_progress',
        'ticket_priority' => 'high',
        'ticket_category' => 'account_issues',
        'customer_name' => 'Jane Doe',
        'customer_email' => 'jane@example.com',
        'ai_summary' => 'Expired reset link.',
        'ai_suggested_reply' => '',
    ]);
}

beforeEach(function () {
    Functions\when('sanitize_text_field')->alias(fn($v) => trim(preg_replace('/\s+/', ' ', strip_tags($v))));
    Functions\when('sanitize_textarea_field')->alias(fn($v) => trim(strip_tags($v)));
    Functions\when('sanitize_email')->alias(fn($v) => trim($v));
    Functions\when('is_email')->alias(fn($v) => filter_var($v, FILTER_VALIDATE_EMAIL) !== false);

    $this->repository = Mockery::mock(TicketRepository::class);
    $this->transfer = new TicketTransfer($this->repository, new TicketValidator());

    $this->document = fn(array $tickets, string $source = 'localhost'): array => [
        'format' => TicketTransfer::FORMAT,
        'version' => TicketTransfer::VERSION,
        'source' => $source,
        'tickets' => $tickets,
    ];
    $this->exported = fn(int ...$ids): array => $this->transfer->export(array_map(fn(int $id) => transferTicket($id), $ids), 'localhost');
});

it('exports ticket fields without site-specific IDs', function () {
    $document = ($this->exported)(4);

    expect($document)->toMatchArray(['format' => 'jalasupport-tickets', 'version' => 1, 'source' => 'localhost', 'count' => 1])
        ->and($document['tickets'][0])->toBe([
            'source_id' => 4,
            'title' => 'Cannot log in',
            'description' => 'The reset link has expired.',
            'status' => 'in_progress',
            'priority' => 'high',
            'category' => 'account_issues',
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'ai_summary' => 'Expired reset link.',
            'ai_suggested_reply' => '',
            'created_at' => '2026-11-20T10:00:00+00:00',
        ]);
});

it('imports an export with new IDs, the chosen author, origin key and original date', function () {
    $this->repository->shouldReceive('findIdByImportKey')->with('localhost#4')->andReturnNull();
    $this->repository->shouldReceive('create')
        ->once()
        ->withArgs(fn(array $data, int $author, array $options) => $data['title'] === 'Cannot log in'
            && $data['ticket_status'] === 'in_progress'
            && $data['customer_email'] === 'jane@example.com'
            && $author === 9
            && $options === ['import_key' => 'localhost#4', 'created_at' => '2026-11-20 10:00:00'])
        ->andReturn(transferTicket(51));

    $result = $this->transfer->import(($this->exported)(4), 9);

    expect($result['created'])->toBe([['source_id' => 4, 'id' => 51, 'title' => 'Cannot log in']])
        ->and($result['skipped'])->toBe([])
        ->and($result['invalid'])->toBe([]);
});

it('skips tickets imported before and duplicates within the file', function () {
    $this->repository->shouldReceive('findIdByImportKey')->with('localhost#4')->andReturn(51);
    $this->repository->shouldReceive('findIdByImportKey')->with('localhost#5')->andReturnNull();
    $this->repository->shouldReceive('create')->once()->andReturn(transferTicket(52));

    $document = ($this->exported)(4, 5, 5);
    $result = $this->transfer->import($document, 9);

    expect(array_column($result['created'], 'source_id'))->toBe([5])
        ->and(array_column($result['skipped'], 'source_id'))->toBe([4, 5]);
});

it('saves nothing on a dry run', function () {
    $this->repository->shouldReceive('findIdByImportKey')->andReturnNull();
    $this->repository->shouldNotReceive('create');

    $result = $this->transfer->import(($this->exported)(4, 5), 9, dryRun: true);

    expect(array_column($result['created'], 'source_id'))->toBe([4, 5])
        ->and(array_column($result['created'], 'id'))->toBe([0, 0]);
});

it('validates every ticket like app input and reports invalid ones', function () {
    $this->repository->shouldReceive('findIdByImportKey')->andReturnNull();
    $this->repository->shouldNotReceive('create');

    $tickets = ($this->exported)(1, 2, 3)['tickets'];
    $tickets[0]['customer_email'] = 'not-an-email';
    $tickets[1]['status'] = 'deleted';
    $tickets[2]['source_id'] = '3';
    $tickets[] = 'not a ticket';

    $result = $this->transfer->import(($this->document)($tickets), 9, dryRun: true);

    expect($result['created'])->toBe([])
        ->and(array_column($result['invalid'], 'index'))->toBe([0, 1, 2, 3])
        ->and($result['invalid'][0]['errors'])->toHaveKey('customer_email')
        ->and($result['invalid'][1]['errors'])->toHaveKey('status')
        ->and($result['invalid'][2]['errors'])->toHaveKey('source_id');
});

it('ignores unknown fields and strips markup from imported text', function () {
    $this->repository->shouldReceive('findIdByImportKey')->andReturnNull();
    $this->repository->shouldReceive('create')
        ->once()
        ->withArgs(fn(array $data) => $data['title'] === 'Hello' && ! array_key_exists('post_author', $data) && ! array_key_exists('id', $data))
        ->andReturn(transferTicket(60));

    $ticket = ($this->exported)(4)['tickets'][0];
    $ticket['title'] = '<b>Hello</b>';
    $ticket['post_author'] = 1;
    $ticket['id'] = 999;

    $this->transfer->import(($this->document)([$ticket]), 9);
});

it('uses the import time for missing, invalid or future creation dates', function (mixed $createdAt) {
    $this->repository->shouldReceive('findIdByImportKey')->andReturnNull();
    $this->repository->shouldReceive('create')
        ->once()
        ->withArgs(fn(array $data, int $author, array $options) => $options['created_at'] === null)
        ->andReturn(transferTicket(61));

    $ticket = ($this->exported)(4)['tickets'][0];
    $ticket['created_at'] = $createdAt;

    $this->transfer->import(($this->document)([$ticket]), 9);
})->with([null, '', 'not a date', '2999-01-01T00:00:00+00:00', 12345]);

it('rejects documents that are not a supported export', function (mixed $payload, string $code) {
    $this->repository->shouldNotReceive('create');

    expect($this->transfer->import($payload, 9)->get_error_code())->toBe($code);
})->with([
    'not an array' => ['tickets', 'jalasupport_import_format'],
    'wrong format' => [['format' => 'wxr'], 'jalasupport_import_format'],
    'newer version' => [['format' => 'jalasupport-tickets', 'version' => 2], 'jalasupport_import_version'],
    'no source' => [['format' => 'jalasupport-tickets', 'version' => 1, 'tickets' => []], 'jalasupport_import_source'],
    'tickets not a list' => [['format' => 'jalasupport-tickets', 'version' => 1, 'source' => 'x', 'tickets' => ['a' => []]], 'jalasupport_import_tickets'],
]);

it('limits the number of tickets per file', function () {
    $this->repository->shouldNotReceive('create');
    $tickets = array_fill(0, TicketTransfer::MAX_TICKETS + 1, []);

    expect($this->transfer->import(($this->document)($tickets), 9)->get_error_code())->toBe('jalasupport_import_size');
});

it('keys imported tickets by their source site', function () {
    $this->repository->shouldReceive('findIdByImportKey')->with('staging.example.com#4')->once()->andReturnNull();
    $this->repository->shouldReceive('create')->once()->andReturn(transferTicket(62));

    $this->transfer->import(($this->document)(($this->exported)(4)['tickets'], 'staging.example.com'), 9);
});
