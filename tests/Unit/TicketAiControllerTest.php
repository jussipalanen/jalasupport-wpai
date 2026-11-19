<?php

use Brain\Monkey\Functions;
use JalaSupport\Http\Controllers\TicketAiController;
use JalaSupport\Models\Ticket;
use JalaSupport\Services\AI\AzureOpenAIService;
use JalaSupport\Services\AI\TicketAnalysis;
use JalaSupport\Services\TicketRepository;
use JalaSupport\Support\RateLimiter;

beforeEach(function () {
    $this->ticket = Ticket::fromPost(new WP_Post(['ID' => 3, 'post_type' => 'ticket']), []);
    $this->repository = Mockery::mock(TicketRepository::class);
    $this->repository->shouldReceive('find')->with(3)->andReturn($this->ticket);
    $this->ai = Mockery::mock(AzureOpenAIService::class);
    $this->controller = new TicketAiController($this->repository, $this->ai, new RateLimiter(2, 600));
    $this->request = new WP_REST_Request(['id' => 3]);

    $this->transients = [];
    Functions\when('get_transient')->alias(fn($k) => $this->transients[$k] ?? false);
    Functions\when('set_transient')->alias(function ($k, $v) {
        $this->transients[$k] = $v;

        return true;
    });
    Functions\when('get_current_user_id')->justReturn(4);
});

it('requires edit access to the ticket', function () {
    Functions\when('is_user_logged_in')->justReturn(true);
    Functions\when('current_user_can')->justReturn(false);

    expect($this->controller->canUseAi($this->request)->get_error_data()['status'])->toBe(403);
});

it('returns suggestions without applying them', function () {
    $this->ai->shouldReceive('isConfigured')->andReturnTrue();
    $this->ai->shouldReceive('analyze')->once()->andReturn(new TicketAnalysis(null, null, 'Summary', 'Reply'));
    $this->repository->shouldNotReceive('update');

    $data = $this->controller->analyze($this->request)->get_data()['data'];

    expect($data)->toBe([
        'ticket_id' => 3,
        'suggestions' => ['summary' => 'Summary', 'suggested_reply' => 'Reply'],
        'applied' => false,
    ]);
});

it('returns 503 when AI is not configured', function () {
    $this->ai->shouldReceive('isConfigured')->andReturnFalse();
    $this->ai->shouldNotReceive('analyze');

    expect($this->controller->analyze($this->request)->get_error_data()['status'])->toBe(503);
});

it('rate limits AI requests per user', function () {
    $this->ai->shouldReceive('isConfigured')->andReturnTrue();
    $this->ai->shouldReceive('suggestReply')->twice()->andReturn(new TicketAnalysis(null, null, '', 'Reply'));

    $this->controller->suggestReply($this->request);
    $this->controller->suggestReply($this->request);
    $third = $this->controller->suggestReply($this->request);

    expect($third->get_error_code())->toBe('jalasupport_ai_rate_limited')
        ->and($third->get_error_data()['status'])->toBe(429);
});
