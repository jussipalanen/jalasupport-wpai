<?php

use Brain\Monkey\Functions;
use JalaSupport\Support\DemoMode;

final class DemoRedirect extends RuntimeException {}

beforeEach(function () {
    putenv('JALASUPPORT_DEMO');
    Functions\when('home_url')->justReturn('https://demo.test/');
    Functions\when('wp_doing_ajax')->justReturn(false);
    Functions\when('wp_safe_redirect')->alias(fn(string $url) => throw new DemoRedirect($url));
    unset($GLOBALS['pagenow']);
});

afterEach(function () {
    putenv('JALASUPPORT_DEMO');
    unset($GLOBALS['pagenow']);
});

it('is off unless JALASUPPORT_DEMO is set', function () {
    expect(DemoMode::enabled())->toBeFalse();

    putenv('JALASUPPORT_DEMO=true');
    expect(DemoMode::enabled())->toBeTrue();

    putenv('JALASUPPORT_DEMO=false');
    expect(DemoMode::enabled())->toBeFalse();
});

it('adds no hooks when demo mode is off', function () {
    (new DemoMode())->register();

    expect(has_action('admin_init'))->toBeFalse()
        ->and(has_filter('show_admin_bar'))->toBeFalse();
});

it('hooks wp-admin, the toolbar and the admin link when demo mode is on', function () {
    putenv('JALASUPPORT_DEMO=1');
    $demo = new DemoMode();
    $demo->register();

    expect(has_action('admin_init', [$demo, 'redirectFromAdmin']))->toBe(1)
        ->and(has_filter('show_admin_bar', [$demo, 'filterAdminAccess']))->not->toBeFalse()
        ->and(has_filter(DemoMode::ADMIN_ACCESS_FILTER, [$demo, 'filterAdminAccess']))->not->toBeFalse()
        ->and(has_filter('login_message', [$demo, 'loginMessage']))->not->toBeFalse()
        ->and(has_filter('login_redirect', [$demo, 'loginRedirect']))->not->toBeFalse();
});

it('sends non-administrators from wp-admin to the app', function () {
    Functions\when('current_user_can')->justReturn(false);

    expect(fn() => (new DemoMode())->redirectFromAdmin())
        ->toThrow(DemoRedirect::class, 'https://demo.test/');
});

it('lets administrators, admin-ajax and admin-post requests through', function (bool $admin, bool $ajax, string $page) {
    Functions\when('current_user_can')->justReturn($admin);
    Functions\when('wp_doing_ajax')->justReturn($ajax);
    $GLOBALS['pagenow'] = $page;

    (new DemoMode())->redirectFromAdmin();

    expect(true)->toBeTrue();
})->with([
    'administrator' => [true, false, 'index.php'],
    'admin-ajax.php' => [false, true, 'admin-ajax.php'],
    'admin-post.php' => [false, false, 'admin-post.php'],
]);

it('hides the toolbar and the admin link from non-administrators only', function () {
    $demo = new DemoMode();

    Functions\when('current_user_can')->justReturn(false);
    expect($demo->filterAdminAccess(true))->toBeFalse();

    Functions\when('current_user_can')->justReturn(true);
    expect($demo->filterAdminAccess(true))->toBeTrue()
        ->and($demo->filterAdminAccess(false))->toBeFalse();
});

it('shows the demo account on the login screen only when it is configured', function () {
    $demo = new DemoMode();

    expect($demo->loginMessage('<p>Hi</p>'))->toBe('<p>Hi</p>');

    define('JALASUPPORT_DEMO_LOGIN', 'demo');
    define('JALASUPPORT_DEMO_PASSWORD', 'demo<b>');

    expect($demo->loginMessage(null))
        ->toBe('<p class="message">Demo: sign in with username <strong>demo</strong> and password <strong>demo&lt;b&gt;</strong>.</p>');
});

it('sends non-administrators to the app after login, keeping requested app pages', function () {
    Functions\when('admin_url')->justReturn('https://demo.test/wp-admin/');
    Functions\when('user_can')->justReturn(false);
    $demo = new DemoMode();
    $user = new WP_User();

    expect($demo->loginRedirect('https://demo.test/wp-admin/profile.php', '', $user))->toBe('https://demo.test/')
        ->and($demo->loginRedirect('', '', $user))->toBe('https://demo.test/')
        ->and($demo->loginRedirect('https://demo.test/tickets/12/', '', $user))->toBe('https://demo.test/tickets/12/')
        ->and($demo->loginRedirect('https://demo.test/wp-admin/', '', null))->toBe('https://demo.test/wp-admin/');

    Functions\when('user_can')->justReturn(true);
    expect($demo->loginRedirect('https://demo.test/wp-admin/', '', $user))->toBe('https://demo.test/wp-admin/');
});
