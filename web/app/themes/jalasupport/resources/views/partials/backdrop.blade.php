{{--
    Decorative space backdrop with a light synthwave touch: deep-space
    gradient, nebula glows, starfield, striped retro sun on a glowing horizon
    and a perspective grid. Pure SVG + CSS: no image requests.

    Variants:
    - "full"   (default) fixed, full-screen background, e.g. the login screen.
    - "banner" header band behind the page title in the application;
    shows the middle of the scene (nebula, stars, top of the sun and horizon).

    Hidden from assistive technology; animations respect
    prefers-reduced-motion (see resources/css/backdrop.css).
--}}
@php
    $variant = ($variant ?? 'full') === 'banner' ? 'banner' : 'full';
    $viewBox = $variant === 'banner' ? '0 360 1440 360' : '0 0 1440 900';
    $aspect = $variant === 'banner' ? 'xMidYMid slice' : 'xMidYMax slice';

    // Deterministic starfield (simple LCG, does not touch PHP's global RNG).
    $seed = 20261121;
    $next = function () use (&$seed): float {
        $seed = ($seed * 1103515245 + 12345) % 2147483648;

        return $seed / 2147483648;
    };

    $stars = [];
    for ($i = 0; $i < 150; $i++) {
        $stars[] = [
            'x' => round($next() * 1440, 1),
            'y' => round($next() * 690, 1),
            'r' => round(0.5 + $next() * 1.1, 2),
            'o' => round(0.25 + $next() * 0.65, 2),
            'twinkle' => $i % 11 === 0,
        ];
    }

    // Perspective grid: horizon at y=700, vanishing point at x=720.
    $horizontals = [];
    for ($k = 1; $k <= 9; $k++) {
        $horizontals[] = round(700 + 200 * (($k / 9) ** 2), 1);
    }

    $verticals = [];
    for ($xb = -2160; $xb <= 3600; $xb += 180) {
        $verticals[] = ['x1' => round(720 + ($xb - 720) * 0.06, 1), 'x2' => $xb];
    }
@endphp

<div class="backdrop backdrop--{{ $variant }}" aria-hidden="true">
    <svg class="backdrop__svg" viewBox="{{ $viewBox }}" preserveAspectRatio="{{ $aspect }}" xmlns="http://www.w3.org/2000/svg" focusable="false">
        <defs>
            <linearGradient id="backdrop-space" x1="0" y1="0" x2="0" y2="900" gradientUnits="userSpaceOnUse">
                <stop offset="0" stop-color="#05060f" />
                <stop offset="0.45" stop-color="#0c0a23" />
                <stop offset="0.72" stop-color="#1a0f3d" />
                <stop offset="0.78" stop-color="#2b1150" />
                <stop offset="1" stop-color="#0a0616" />
            </linearGradient>

            <radialGradient id="backdrop-nebula-violet" cx="0.5" cy="0.5" r="0.5">
                <stop offset="0" stop-color="#7c3aed" stop-opacity="0.45" />
                <stop offset="1" stop-color="#7c3aed" stop-opacity="0" />
            </radialGradient>

            <radialGradient id="backdrop-nebula-magenta" cx="0.5" cy="0.5" r="0.5">
                <stop offset="0" stop-color="#db2777" stop-opacity="0.32" />
                <stop offset="1" stop-color="#db2777" stop-opacity="0" />
            </radialGradient>

            <radialGradient id="backdrop-nebula-cyan" cx="0.5" cy="0.5" r="0.5">
                <stop offset="0" stop-color="#06b6d4" stop-opacity="0.22" />
                <stop offset="1" stop-color="#06b6d4" stop-opacity="0" />
            </radialGradient>

            <linearGradient id="backdrop-sun" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#ffd27a" />
                <stop offset="0.55" stop-color="#ff7a9c" />
                <stop offset="1" stop-color="#c026d3" />
            </linearGradient>

            <radialGradient id="backdrop-sun-glow" cx="0.5" cy="0.5" r="0.5">
                <stop offset="0" stop-color="#f472b6" stop-opacity="0.45" />
                <stop offset="1" stop-color="#f472b6" stop-opacity="0" />
            </radialGradient>

            {{-- Retro sun stripes: gaps grow toward the horizon. --}}
            <mask id="backdrop-sun-stripes">
                <rect x="560" y="480" width="320" height="230" fill="#ffffff" />
                <rect x="560" y="606" width="320" height="4" fill="#000000" />
                <rect x="560" y="626" width="320" height="6" fill="#000000" />
                <rect x="560" y="646" width="320" height="8" fill="#000000" />
                <rect x="560" y="666" width="320" height="10" fill="#000000" />
                <rect x="560" y="686" width="320" height="14" fill="#000000" />
            </mask>

            <linearGradient id="backdrop-floor" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#1d0b3a" />
                <stop offset="1" stop-color="#07040f" />
            </linearGradient>

            <linearGradient id="backdrop-grid-fade" x1="0" y1="700" x2="0" y2="900" gradientUnits="userSpaceOnUse">
                <stop offset="0" stop-color="#ffffff" stop-opacity="0.15" />
                <stop offset="1" stop-color="#ffffff" stop-opacity="1" />
            </linearGradient>

            <mask id="backdrop-grid-mask">
                <rect x="0" y="700" width="1440" height="200" fill="url(#backdrop-grid-fade)" />
            </mask>

            <filter id="backdrop-blur" x="-50%" y="-50%" width="200%" height="200%">
                <feGaussianBlur stdDeviation="40" />
            </filter>
        </defs>

        {{-- Space --}}
        <rect width="1440" height="900" fill="url(#backdrop-space)" />

        {{-- Nebula glows --}}
        <g class="backdrop__nebula" filter="url(#backdrop-blur)">
            <ellipse cx="320" cy="260" rx="460" ry="300" fill="url(#backdrop-nebula-violet)" />
            <ellipse cx="1160" cy="330" rx="420" ry="260" fill="url(#backdrop-nebula-magenta)" />
            <ellipse cx="820" cy="130" rx="340" ry="180" fill="url(#backdrop-nebula-cyan)" />
        </g>

        {{-- Stars --}}
        <g fill="#ffffff">
            @foreach ($stars as $star)
                <circle @class(['backdrop__star' => $star['twinkle']]) cx="{{ $star['x'] }}" cy="{{ $star['y'] }}" r="{{ $star['r'] }}" opacity="{{ $star['o'] }}" />
            @endforeach
        </g>

        {{-- Retro sun on the horizon --}}
        <circle cx="720" cy="600" r="260" fill="url(#backdrop-sun-glow)" />
        <circle class="backdrop__sun" cx="720" cy="600" r="110" fill="url(#backdrop-sun)" mask="url(#backdrop-sun-stripes)" opacity="0.75" />

        {{-- Floor and perspective grid --}}
        <rect y="700" width="1440" height="200" fill="url(#backdrop-floor)" />
        <g mask="url(#backdrop-grid-mask)" stroke="#c084fc" stroke-width="1" opacity="0.45">
            @foreach ($verticals as $line)
                <line x1="{{ $line['x1'] }}" y1="700" x2="{{ $line['x2'] }}" y2="900" />
            @endforeach
            @foreach ($horizontals as $y)
                <line x1="0" y1="{{ $y }}" x2="1440" y2="{{ $y }}" />
            @endforeach
        </g>

        {{-- Horizon glow --}}
        <line x1="0" y1="700" x2="1440" y2="700" stroke="#f472b6" stroke-width="1.5" opacity="0.7" />
        <rect y="680" width="1440" height="40" fill="#f472b6" opacity="0.08" filter="url(#backdrop-blur)" />
    </svg>
</div>
