<?php

declare(strict_types=1);

namespace JalaSupport\Support;

/**
 * Fixed-window rate limiter backed by transients (object cache aware).
 */
final class RateLimiter
{
    public function __construct(
        private int $maxAttempts,
        private int $windowSeconds,
    ) {}

    /**
     * Records an attempt. Returns false when the limit has been reached.
     */
    public function attempt(string $key): bool
    {
        $transient = 'jalasupport_rl_' . md5($key);
        $state = get_transient($transient);
        $now = time();

        if (! is_array($state) || ($state['reset'] ?? 0) <= $now) {
            $state = ['count' => 0, 'reset' => $now + $this->windowSeconds];
        }

        if ($state['count'] >= $this->maxAttempts) {
            return false;
        }

        $state['count']++;
        set_transient($transient, $state, max(1, $state['reset'] - $now));

        return true;
    }
}
