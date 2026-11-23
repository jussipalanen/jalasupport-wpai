<?php

use JalaSupport\Services\AI\AiProvider;
use JalaSupport\Services\AI\AiProviderFactory;
use JalaSupport\Services\AI\DemoAiService;

function fakeProvider(bool $configured): AiProvider
{
    $provider = Mockery::mock(AiProvider::class);
    $provider->shouldReceive('isConfigured')->andReturn($configured);

    return $provider;
}

it('uses the explicitly selected provider, even when it is not configured', function () {
    $azure = fakeProvider(true);
    $gemini = fakeProvider(false);

    expect(AiProviderFactory::select('gemini', $azure, $gemini))->toBe($gemini)
        ->and(AiProviderFactory::select(' Azure ', $azure, $gemini))->toBe($azure);
});

it('falls back to the first configured provider when none is selected', function () {
    $azure = fakeProvider(false);
    $gemini = fakeProvider(true);

    expect(AiProviderFactory::select('', $azure, $gemini))->toBe($gemini)
        ->and(AiProviderFactory::select('', fakeProvider(true), $gemini))->not->toBe($gemini);
});

it('returns Azure (reported as not configured) when nothing is configured', function () {
    $azure = fakeProvider(false);

    expect(AiProviderFactory::select('unknown', $azure, fakeProvider(false)))->toBe($azure);
});

it('selects the demo provider, which needs no AI service', function () {
    $provider = AiProviderFactory::select('demo', fakeProvider(true), fakeProvider(true));

    expect($provider)->toBeInstanceOf(DemoAiService::class)
        ->and($provider->isConfigured())->toBeTrue();
});
