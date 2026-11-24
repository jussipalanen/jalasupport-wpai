<?php

/**
 * Search engine and AI discovery (presentation only).
 *
 * The public start page is the only page meant for search engines and AI
 * assistants: it gets a description, a canonical URL, Open Graph tags and
 * JSON-LD. Every other front-end page (the application, the login screen,
 * WordPress' default posts and archives) is marked noindex.
 *
 * Sitemap and llms.txt follow the WordPress setting Settings > Reading >
 * "Search engine visibility" (blog_public): while search engines are
 * discouraged, both stay off and every page is noindex.
 */

namespace App;

use App\Sitemaps\HomeProvider;

/**
 * One description for the meta tags, structured data and llms.txt.
 */
function public_description(): string
{
    return __('Support tickets in one queue, with AI that suggests a category, priority, summary and reply for agents to review before anything is saved.', 'jalasupport');
}

/**
 * @return list<string>
 */
function public_features(): array
{
    return [
        __('One ticket queue: search, filter and sort tickets by status, priority and category.', 'jalasupport'),
        __('AI suggestions: category, priority, summary and a reply draft. Nothing is applied without the agent.', 'jalasupport'),
        __('Private by design: tickets are visible only to signed-in agents. The start page shows totals only.', 'jalasupport'),
    ];
}

function is_site_indexable(): bool
{
    return (bool) get_option('blog_public');
}

/**
 * Only the public start page may be indexed.
 */
add_filter('wp_robots', function (array $robots) {
    if (! is_public_home()) {
        $robots['noindex'] = true;
    }

    return $robots;
});

/**
 * Meta, Open Graph and Twitter tags and JSON-LD for the public start page.
 */
add_action('wp_head', function () {
    if (! is_public_home()) {
        return;
    }

    $url = home_url('/');
    $name = get_bloginfo('name');
    // Plain text: printed through esc_attr() and JSON, not as HTML.
    $title = html_entity_decode(wp_get_document_title(), ENT_QUOTES, 'UTF-8');
    $description = public_description();
    $image = home_url('/icon-512.png');

    $meta = [
        ['name' => 'description', 'content' => $description],
        ['property' => 'og:type', 'content' => 'website'],
        ['property' => 'og:site_name', 'content' => $name],
        ['property' => 'og:title', 'content' => $title],
        ['property' => 'og:description', 'content' => $description],
        ['property' => 'og:url', 'content' => $url],
        ['property' => 'og:locale', 'content' => get_locale()],
        ['property' => 'og:image', 'content' => $image],
        ['property' => 'og:image:width', 'content' => '512'],
        ['property' => 'og:image:height', 'content' => '512'],
        ['property' => 'og:image:alt', 'content' => $name],
        ['name' => 'twitter:card', 'content' => 'summary'],
    ];

    printf('<link rel="canonical" href="%s">' . "\n", esc_url($url));

    foreach ($meta as $tag) {
        $key = isset($tag['name']) ? 'name' : 'property';
        printf('<meta %s="%s" content="%s">' . "\n", $key, esc_attr($tag[$key]), esc_attr($tag['content']));
    }

    $language = str_replace('_', '-', get_locale());
    $data = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'WebSite',
                '@id' => $url . '#website',
                'url' => $url,
                'name' => $name,
                'description' => $description,
                'inLanguage' => $language,
            ],
            [
                '@type' => 'WebPage',
                '@id' => $url . '#webpage',
                'url' => $url,
                'name' => $title,
                'description' => $description,
                'inLanguage' => $language,
                'isPartOf' => ['@id' => $url . '#website'],
                'about' => ['@id' => $url . '#app'],
            ],
            [
                '@type' => 'SoftwareApplication',
                '@id' => $url . '#app',
                'name' => $name,
                'url' => $url,
                'description' => $description,
                'applicationCategory' => 'BusinessApplication',
                'operatingSystem' => 'Web browser',
                'image' => $image,
                'featureList' => public_features(),
            ],
        ],
    ];

    // JSON_HEX_TAG keeps "</script>" in any text from closing the tag early.
    printf(
        '<script type="application/ld+json">%s</script>' . "\n",
        wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG),
    );
}, 3);

/**
 * Sitemap: the start page only. Core would also list users (their login
 * slugs), taxonomies and WordPress' default posts and pages.
 */
add_filter('wp_sitemaps_add_provider', function ($provider, string $name) {
    return in_array($name, ['posts', 'taxonomies', 'users'], true) ? false : $provider;
}, 10, 2);

add_action('init', function () {
    wp_register_sitemap_provider('home', new HomeProvider());
});

/**
 * /llms.txt: a short Markdown summary for AI assistants (https://llmstxt.org).
 * Served only while search engines are allowed; otherwise a normal 404.
 */
add_action('parse_request', function (\WP $wp) {
    if ($wp->request !== 'llms.txt' || ! is_site_indexable()) {
        return;
    }

    $lines = [
        '# ' . get_bloginfo('name'),
        '',
        '> ' . public_description(),
        '',
        __('JalaSupport AI is a customer support and ticket management application built on WordPress. Support agents sign in to work through a shared ticket queue; AI assists them, but every change is confirmed by an agent.', 'jalasupport'),
        '',
        '## ' . __('Features', 'jalasupport'),
        '',
        ...array_map(fn(string $feature) => '- ' . $feature, public_features()),
        '',
        '## ' . __('Pages', 'jalasupport'),
        '',
        sprintf('- [%s](%s): %s', __('Start page', 'jalasupport'), home_url('/'), __('what the application does and the current ticket totals.', 'jalasupport')),
        sprintf('- [%s](%s): %s', __('Sign in', 'jalasupport'), wp_login_url(), __('for support agents. Accounts are created by an administrator.', 'jalasupport')),
    ];

    status_header(200);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    echo implode("\n", $lines) . "\n";
    exit;
});
