<?php
/**
 * Plugin Name: Dr Purg Jr. Ad and Verification Helpers
 * Description: Handles ads.txt, canonical redirects, manifests, and lightweight verification output.
 */

if (!defined('ABSPATH')) {
    exit;
}

function kepoli_mu_env(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value === false || $value === '' ? $default : trim((string) $value);
}

function kepoli_mu_env_bool(string $key, bool $default = false): bool
{
    $value = strtolower(kepoli_mu_env($key, $default ? '1' : '0'));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function kepoli_mu_site_profile(): array
{
    $profile = get_option('kepoli_site_profile');
    return is_array($profile) ? $profile : [];
}

function kepoli_mu_profile_value(array $path, $default = '')
{
    $value = kepoli_mu_site_profile();
    foreach ($path as $key) {
        if (!is_array($value) || !array_key_exists($key, $value)) {
            return $default;
        }

        $value = $value[$key];
    }

    return $value;
}

function kepoli_mu_site_name(): string
{
    $name = trim((string) kepoli_mu_profile_value(['brand', 'name'], get_bloginfo('name')));
    return $name !== '' ? $name : 'Dr Purg Jr.';
}

function kepoli_mu_brand_description(): string
{
    $description = trim((string) kepoli_mu_profile_value(['brand', 'description'], get_bloginfo('description')));
    return $description !== '' ? $description : 'Shocking health facts, body-signal explainers, and practical wellness context.';
}

function kepoli_mu_public_locale(): string
{
    $locale = trim((string) kepoli_mu_profile_value(['locales', 'public'], get_bloginfo('language') ?: 'en_US'));
    return $locale !== '' ? str_replace('_', '-', $locale) : 'en-US';
}

function kepoli_mu_asset_basename(string $key, string $fallback): string
{
    $asset = sanitize_file_name((string) kepoli_mu_profile_value(['assets', $key], ''));
    return $asset !== '' ? pathinfo($asset, PATHINFO_FILENAME) : $fallback;
}

function kepoli_mu_asset_uri(string $key, string $fallback, string $fallback_extension = 'svg'): string
{
    $basename = kepoli_mu_asset_basename($key, $fallback);
    $dir = get_template_directory();
    $uri = get_template_directory_uri();

    foreach (['webp', 'jpg', 'jpeg', 'png', 'svg'] as $extension) {
        $path = "/assets/img/{$basename}.{$extension}";
        if (file_exists($dir . $path)) {
            return $uri . $path;
        }
    }

    return $uri . "/assets/img/{$basename}.{$fallback_extension}";
}

function kepoli_mu_public_contact_email(): string
{
    $email = sanitize_email((string) kepoli_mu_profile_value(['brand', 'site_email'], kepoli_mu_env('SITE_EMAIL')));
    $site_url = kepoli_mu_env('SITE_URL', home_url('/'));
    $host = wp_parse_url($site_url, PHP_URL_HOST);
    $host = is_string($host) ? preg_replace('/^www\./', '', strtolower($host)) : '';

    if ($email === '' || str_contains(strtolower($email), '@' . 'kepoli' . '.com')) {
        return $host !== '' ? 'contact@' . $host : sanitize_email((string) get_option('admin_email'));
    }

    return $email;
}

function kepoli_mu_redirect_hosts(string $canonical_host): array
{
    $hosts = [
        'www.' . $canonical_host,
        'api.' . $canonical_host,
        'recipe.' . $canonical_host,
    ];

    $configured_hosts = array_filter(array_map(
        'trim',
        explode(',', kepoli_mu_env('CANONICAL_REDIRECT_HOSTS'))
    ));

    return array_values(array_unique(array_map('strtolower', array_merge($hosts, $configured_hosts))));
}

add_action('template_redirect', static function (): void {
    $site_url = kepoli_mu_env('SITE_URL', home_url('/'));
    $canonical_host = strtolower((string) parse_url($site_url, PHP_URL_HOST));
    if ($canonical_host === '') {
        return;
    }

    $current_host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $current_host = preg_replace('/:\d+$/', '', $current_host) ?: '';
    if ($current_host === '' || $current_host === $canonical_host) {
        return;
    }

    if (!in_array($current_host, kepoli_mu_redirect_hosts($canonical_host), true)) {
        return;
    }

    $scheme = (string) parse_url($site_url, PHP_URL_SCHEME);
    $scheme = $scheme !== '' ? $scheme : 'https';
    $request_uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $request_uri = str_starts_with($request_uri, '/') ? $request_uri : '/';

    wp_redirect($scheme . '://' . $canonical_host . $request_uri, 301, kepoli_mu_site_name());
    exit;
}, 0);

add_action('template_redirect', static function (): void {
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if ($path !== '/sw.js') {
        return;
    }

    if (!kepoli_mu_env_bool('MONETAG_ENABLE', false)) {
        return;
    }

    $script = '';
    $encoded_script = kepoli_mu_env('MONETAG_SW_JS_BASE64');
    if ($encoded_script !== '') {
        $decoded_script = base64_decode($encoded_script, true);
        if (is_string($decoded_script) && trim($decoded_script) !== '') {
            $script = $decoded_script;
        }
    }

    if ($script === '') {
        $bundled_script_path = '/content/monetag/sw.js';
        if (is_readable($bundled_script_path)) {
            $bundled_script = file_get_contents($bundled_script_path);
            if (is_string($bundled_script) && trim($bundled_script) !== '') {
                $script = $bundled_script;
            }
        }
    }

    status_header(200);
    header('Content-Type: application/javascript; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    header('Service-Worker-Allowed: /');

    echo $script !== '' ? $script : "/* Monetag service worker placeholder. Set MONETAG_SW_JS_BASE64 after the dashboard provides sw.js. */\n";
    exit;
}, 0);

add_action('template_redirect', static function (): void {
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if ($path !== '/ads.txt') {
        return;
    }

    $ezoic_ads_txt_url = kepoli_mu_env('EZOIC_ADSTXT_REDIRECT_URL');
    $ezoic_account_id = kepoli_mu_env('EZOIC_ADSTXT_ACCOUNT_ID');
    if ($ezoic_ads_txt_url === '' && $ezoic_account_id !== '') {
        $site_url = kepoli_mu_env('SITE_URL', home_url('/'));
        $site_host = strtolower((string) parse_url($site_url, PHP_URL_HOST));
        if ($site_host !== '') {
            $ezoic_ads_txt_url = 'https://srv.adstxtmanager.com/' . rawurlencode($ezoic_account_id) . '/' . rawurlencode($site_host);
        }
    }

    if ($ezoic_ads_txt_url !== '') {
        wp_redirect(esc_url_raw($ezoic_ads_txt_url), 301, kepoli_mu_site_name());
        exit;
    }

    $publisher_id = kepoli_mu_env('ADSENSE_PUB_ID');
    status_header(200);
    header('Content-Type: text/plain; charset=utf-8');

    if ($publisher_id !== '') {
        $publisher_id = str_starts_with($publisher_id, 'pub-') ? $publisher_id : 'pub-' . $publisher_id;
        echo 'google.com, ' . esc_html($publisher_id) . ", DIRECT, f08c47fec0942fa0\n";
    } else {
        echo "# ads.txt will be populated after the advertising partner provides publisher records.\n";
    }

    exit;
});

add_action('template_redirect', static function (): void {
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if ($path !== '/.well-known/security.txt') {
        return;
    }

    $site_url = trailingslashit(kepoli_mu_env('SITE_URL', home_url('/')));
    $contact_email = kepoli_mu_public_contact_email();
    $contact_page = trailingslashit(home_url('/contact/'));
    $expires = gmdate('Y-m-d\T00:00:00\Z', strtotime('+12 months'));

    status_header(200);
    header('Content-Type: text/plain; charset=utf-8');

    echo 'Contact: mailto:' . esc_html($contact_email) . "\n";
    echo 'Contact: ' . esc_url_raw($contact_page) . "\n";
    echo 'Canonical: ' . esc_url_raw($site_url . '.well-known/security.txt') . "\n";
    echo 'Preferred-Languages: ' . esc_html(strtolower(substr(kepoli_mu_public_locale(), 0, 2))) . ", en\n";
    echo 'Expires: ' . esc_html($expires) . "\n";

    exit;
});

add_action('template_redirect', static function (): void {
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if ($path !== '/site.webmanifest') {
        return;
    }

    status_header(200);
    header('Content-Type: application/manifest+json; charset=utf-8');

    $site_name = kepoli_mu_site_name();
    $icon_uri = kepoli_mu_asset_uri('icon', 'dr-purg-jr-icon');
    $icon_extension = strtolower(pathinfo((string) wp_parse_url($icon_uri, PHP_URL_PATH), PATHINFO_EXTENSION));
    $icon_type = match ($icon_extension) {
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        default => 'image/svg+xml',
    };
    $manifest = [
        'name' => $site_name,
        'short_name' => $site_name,
        'description' => kepoli_mu_brand_description(),
        'lang' => kepoli_mu_public_locale(),
        'start_url' => home_url('/'),
        'scope' => home_url('/'),
        'display' => 'standalone',
        'background_color' => '#fbf7ef',
        'theme_color' => '#252416',
        'icons' => [
            [
                'src' => $icon_uri,
                'sizes' => 'any',
                'type' => $icon_type,
                'purpose' => 'any',
            ],
        ],
    ];

    echo wp_json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
});

add_filter('robots_txt', static function (string $output, bool $public): string {
    if (!$public) {
        return $output;
    }

    if (!str_contains($output, 'facebookexternalhit')) {
        $output .= "\nUser-agent: facebookexternalhit\nAllow: /\n";
    }

    if (!str_contains($output, 'Facebot')) {
        $output .= "\nUser-agent: Facebot\nAllow: /\n";
    }

    if (!str_contains($output, 'Sitemap:')) {
        $output .= "\nSitemap: " . home_url('/wp-sitemap.xml') . "\n";
    }

    return $output;
}, 10, 2);

add_filter('xmlrpc_enabled', '__return_false');

add_filter('wp_headers', static function (array $headers): array {
    unset($headers['X-Pingback']);
    return $headers;
});

add_filter('wp_sitemaps_add_provider', static function ($provider, string $name) {
    if ($name === 'users') {
        return false;
    }

    return $provider;
}, 10, 2);

add_filter('rest_endpoints', static function (array $endpoints): array {
    if (is_user_logged_in()) {
        return $endpoints;
    }

    unset(
        $endpoints['/wp/v2/users'],
        $endpoints['/wp/v2/users/(?P<id>[\\d]+)']
    );

    return $endpoints;
});
