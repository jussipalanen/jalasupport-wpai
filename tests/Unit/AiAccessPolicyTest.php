<?php

use Brain\Monkey\Functions;
use JalaSupport\Support\AiAccessPolicy;

beforeEach(function () {
    Functions\when('wp_parse_url')->alias(fn($url) => parse_url($url));
    Functions\when('rest_get_authenticated_app_password')->justReturn(null);
    $this->policy = new AiAccessPolicy('https://support.example.com');
});

it('allows same-origin browser requests', function (array $headers) {
    expect($this->policy->check(new WP_REST_Request([], [], $headers)))->toBeTrue();
})->with([
    'no browser headers' => [[]],
    'same origin' => [['origin' => 'https://support.example.com', 'sec_fetch_site' => 'same-origin']],
    'explicit default port' => [['origin' => 'https://SUPPORT.example.com:443']],
    'typed into the address bar' => [['sec_fetch_site' => 'none']],
]);

it('refuses cross-site requests', function (array $headers) {
    $result = $this->policy->check(new WP_REST_Request([], [], $headers));

    expect($result)->toBeInstanceOf(WP_Error::class)
        ->and($result->get_error_code())->toBe('jalasupport_ai_forbidden')
        ->and($result->get_error_data()['status'])->toBe(403);
})->with([
    'other origin' => [['origin' => 'https://evil.example']],
    'look-alike host' => [['origin' => 'https://support.example.com.evil.example']],
    'http downgrade' => [['origin' => 'http://support.example.com']],
    'other port' => [['origin' => 'https://support.example.com:8443']],
    'browser says cross-site' => [['sec_fetch_site' => 'cross-site']],
    'browser says same-site' => [['sec_fetch_site' => 'same-site']],
]);

it('refuses application-password API clients by default', function () {
    Functions\when('rest_get_authenticated_app_password')->justReturn('2d7c7f0e-uuid');

    $result = $this->policy->check(new WP_REST_Request());

    expect($result->get_error_code())->toBe('jalasupport_ai_forbidden');
});

it('can allow application-password API clients explicitly', function () {
    Functions\when('rest_get_authenticated_app_password')->justReturn('2d7c7f0e-uuid');

    expect((new AiAccessPolicy('https://support.example.com', allowApiClients: true))->check(new WP_REST_Request()))->toBeTrue();
});

it('normalizes origins', function () {
    expect(AiAccessPolicy::origin('HTTP://Localhost:8080/wp/'))->toBe('http://localhost:8080')
        ->and(AiAccessPolicy::origin('https://a.example:443/x'))->toBe('https://a.example')
        ->and(AiAccessPolicy::origin('not a url'))->toBe('');
});
