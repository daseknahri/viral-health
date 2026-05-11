<?php
/**
 * Dr Purg Jr. theme functions.
 */

if (!defined('ABSPATH')) {
    exit;
}

function kepoli_env(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value === false || $value === '' ? $default : trim((string) $value);
}

function kepoli_site_host(): string
{
    $site_url = kepoli_env('SITE_URL', home_url('/'));
    $host = wp_parse_url($site_url, PHP_URL_HOST);
    return is_string($host) && $host !== '' ? strtolower($host) : strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
}

function kepoli_public_contact_email(): string
{
    $email = sanitize_email((string) kepoli_profile_value(['brand', 'site_email'], kepoli_env('SITE_EMAIL', 'contact@health.ibnbatoutaweb.com')));
    $host = preg_replace('/^www\./', '', kepoli_site_host());

    if ($email === '' || str_contains(strtolower($email), '@' . 'kepoli' . '.com')) {
        return $host !== '' ? 'contact@' . $host : 'contact@health.ibnbatoutaweb.com';
    }

    return $email;
}

function kepoli_env_bool(string $key, bool $default = false): bool
{
    $value = strtolower(kepoli_env($key, $default ? '1' : '0'));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function kepoli_env_int(string $key, int $default = 0, int $min = 0, int $max = 10080): int
{
    $raw = kepoli_env($key, (string) $default);
    if (!preg_match('/^-?\d+$/', $raw)) {
        return $default;
    }

    return max($min, min($max, (int) $raw));
}

function kepoli_default_site_profile(): array
{
    $public_locale = (string) get_option('WPLANG');
    if ($public_locale === '') {
        $public_locale = kepoli_env('WP_LOCALE', 'en_US');
    }
    if ($public_locale === '') {
        $public_locale = 'en_US';
    }

    $is_english = str_starts_with(strtolower($public_locale), 'en');
    $default_tagline = $is_english
        ? 'Shocking health facts, explained calmly for curious mobile readers.'
        : 'Fapte surprinzatoare despre sanatate, explicate calm pentru cititori curiosi.';

    return [
        'brand' => [
            'name' => get_bloginfo('name') ?: 'Dr Purg Jr.',
            'tagline' => get_bloginfo('description') ?: $default_tagline,
            'description' => get_bloginfo('description') ?: '',
            'site_email' => kepoli_env('SITE_EMAIL', get_option('admin_email') ?: 'contact@health.ibnbatoutaweb.com'),
        ],
        'locales' => [
            'public' => $public_locale,
            'admin' => 'en_US',
            'force_admin' => true,
        ],
        'writer' => [
            'name' => 'Dr Purg Jr Editorial Desk',
            'email' => kepoli_env('WRITER_EMAIL', 'editor@health.ibnbatoutaweb.com'),
            'gravatar_url' => '',
            'bio' => '',
        ],
        'assets' => [
            'wordmark' => 'dr-purg-jr-wordmark',
            'icon' => 'dr-purg-jr-icon',
            'social_cover' => 'dr-purg-jr-social-cover',
        ],
        'slugs' => [
            'home' => $is_english ? 'home' : 'acasa',
            'recipes' => $is_english ? 'recipes' : 'recipes',
            'guides' => $is_english ? 'guides' : 'guides',
            'about' => $is_english ? 'about-dr-purg-jr' : 'despre-dr-purg-jr',
            'author' => $is_english ? 'about-author' : 'about-author',
            'privacy' => $is_english ? 'privacy-policy' : 'politica-de-confidentialitate',
            'cookies' => $is_english ? 'cookie-policy' : 'politica-de-cookies',
            'advertising' => $is_english ? 'advertising-and-consent' : 'publicitate-si-consimtamant',
            'editorial' => $is_english ? 'editorial-policy' : 'politica-editoriala',
            'terms' => $is_english ? 'terms-and-conditions' : 'termeni-si-conditii',
            'disclaimer' => $is_english ? 'culinary-disclaimer' : 'disclaimer-culinar',
        ],
    ];
}

function kepoli_site_profile(): array
{
    static $profile = null;

    if ($profile !== null) {
        return $profile;
    }

    $stored = get_option('kepoli_site_profile');
    $profile = array_replace_recursive(kepoli_default_site_profile(), is_array($stored) ? $stored : []);
    $profile['locales']['admin'] = 'en_US';
    $profile['locales']['force_admin'] = true;

    return $profile;
}

function kepoli_profile_value(array $path, $default = '')
{
    $value = kepoli_site_profile();
    foreach ($path as $key) {
        if (!is_array($value) || !array_key_exists($key, $value)) {
            return $default;
        }

        $value = $value[$key];
    }

    return $value;
}

function kepoli_profile_slug(string $key, string $fallback): string
{
    $slug = sanitize_title((string) kepoli_profile_value(['slugs', $key], ''));
    return $slug !== '' ? $slug : $fallback;
}

function kepoli_public_locale(): string
{
    $locale = trim((string) kepoli_profile_value(['locales', 'public'], get_option('WPLANG') ?: 'en_US'));
    return $locale !== '' ? $locale : 'en_US';
}

function kepoli_admin_locale(): string
{
    $locale = trim((string) kepoli_profile_value(['locales', 'admin'], 'en_US'));
    return $locale !== '' ? $locale : 'en_US';
}

function kepoli_locale_to_language_tag(string $locale): string
{
    $locale = trim(str_replace('_', '-', $locale));
    if ($locale === '') {
        return 'en-US';
    }

    $parts = explode('-', $locale);
    if (count($parts) >= 2) {
        return strtolower($parts[0]) . '-' . strtoupper($parts[1]);
    }

    return strtolower($parts[0]);
}

function kepoli_public_language_code(): string
{
    return strtolower(substr(kepoli_language_tag(), 0, 2));
}

function kepoli_available_languages(): array
{
    return array_values(array_unique(array_filter([kepoli_public_language_code(), 'en'])));
}

function kepoli_force_admin_locale(string $locale): string
{
    $force_admin = (bool) kepoli_profile_value(['locales', 'force_admin'], true);
    if ($force_admin && is_admin()) {
        return kepoli_admin_locale();
    }

    return $locale;
}
add_filter('locale', 'kepoli_force_admin_locale', 20);
add_filter('determine_locale', 'kepoli_force_admin_locale', 20);

function kepoli_resolve_profile_page_template(string $template): string
{
    if (!is_page()) {
        return $template;
    }

    $page = get_queried_object();
    if (!$page instanceof WP_Post) {
        return $template;
    }

    $template_map = [
        kepoli_profile_slug('about', kepoli_is_english() ? 'about-dr-purg-jr' : 'despre-dr-purg-jr') => 'page-about-dr-purg-jr.php',
        kepoli_profile_slug('author', kepoli_is_english() ? 'about-author' : 'about-author') => 'page-about-author.php',
        kepoli_profile_slug('recipes', kepoli_is_english() ? 'recipes' : 'recipes') => 'page-recipes.php',
        kepoli_profile_slug('guides', kepoli_is_english() ? 'guides' : 'guides') => 'page-guides.php',
    ];

    $target = $template_map[(string) $page->post_name] ?? '';
    if ($target === '') {
        return $template;
    }

    $resolved = locate_template($target);
    return $resolved !== '' ? $resolved : $template;
}
add_filter('template_include', 'kepoli_resolve_profile_page_template', 20);

function kepoli_asset_uri(string $basename, string $fallback_extension = 'svg'): string
{
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

function kepoli_profile_asset(string $key, string $fallback): string
{
    $asset = sanitize_file_name((string) kepoli_profile_value(['assets', $key], ''));
    return $asset !== '' ? pathinfo($asset, PATHINFO_FILENAME) : $fallback;
}

function kepoli_wordmark_asset(): string
{
    return kepoli_profile_asset('wordmark', 'dr-purg-jr-wordmark');
}

function kepoli_icon_asset(): string
{
    return kepoli_profile_asset('icon', 'dr-purg-jr-icon');
}

function kepoli_social_cover_asset(): string
{
    return kepoli_profile_asset('social_cover', 'dr-purg-jr-social-cover');
}

function kepoli_asset_dimensions(string $basename): array
{
    $dimensions = [
        'hero-homepage' => [1536, 1024],
        'dr-purg-jr-social-cover' => [1536, 1024],
        'writer-photo' => [1024, 1024],
        'dr-purg-jr-wordmark' => [1311, 264],
        'dr-purg-jr-icon' => [512, 512],
    ];

    return $dimensions[$basename] ?? [];
}

function kepoli_dimension_attributes(array $item): string
{
    $width = isset($item['width']) ? (int) $item['width'] : 0;
    $height = isset($item['height']) ? (int) $item['height'] : 0;

    if ($width <= 0 || $height <= 0) {
        return '';
    }

    return sprintf(' width="%d" height="%d"', $width, $height);
}

function kepoli_asset_dimension_attributes(string $basename): string
{
    [$width, $height] = array_pad(kepoli_asset_dimensions($basename), 2, 0);
    return kepoli_dimension_attributes(['width' => $width, 'height' => $height]);
}

function kepoli_home_hero_sources(): array
{
    $base_dir = get_template_directory();
    $base_uri = get_template_directory_uri();
    $candidates = [
        ['file' => 'hero-homepage-640.jpg', 'width' => 640],
        ['file' => 'hero-homepage-960.jpg', 'width' => 960],
        ['file' => 'hero-homepage.jpg', 'width' => 1536],
    ];

    $sources = [];
    foreach ($candidates as $candidate) {
        $path = '/assets/img/' . $candidate['file'];
        if (!file_exists($base_dir . $path)) {
            continue;
        }

        $sources[] = [
            'url' => $base_uri . $path,
            'width' => $candidate['width'],
        ];
    }

    return $sources;
}

function kepoli_home_hero_srcset(): string
{
    $entries = [];
    foreach (kepoli_home_hero_sources() as $source) {
        $entries[] = esc_url($source['url']) . ' ' . (int) $source['width'] . 'w';
    }

    return implode(', ', $entries);
}

function kepoli_icon(string $name): string
{
    $icons = [
        'calendar' => '<path d="M7 3v3M17 3v3M4.5 9h15M6 5h12a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/><path d="M8 13h2M8 16h2M14 13h2M14 16h2"/>',
        'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5v5l3.2 2"/>',
        'user' => '<circle cx="12" cy="8.5" r="3.2"/><path d="M5.5 20a6.5 6.5 0 0 1 13 0"/>',
        'refresh' => '<path d="M19 7.5A8 8 0 0 0 5.6 6.2L4 8"/><path d="M4 4v4h4"/><path d="M5 16.5a8 8 0 0 0 13.4 1.3L20 16"/><path d="M20 20v-4h-4"/>',
        'facebook' => '<path d="M14 8h2V4.8A11 11 0 0 0 13.2 4C10.4 4 9 5.7 9 8.8V11H6v3.6h3V21h3.7v-6.4h3L16.2 11h-3.5V9.1c0-.8.3-1.1 1.3-1.1Z"/>',
        'whatsapp' => '<path d="M5.2 19 6 16.1A7.2 7.2 0 1 1 8.8 18Z"/><path d="M9.2 8.6c-.2-.5-.4-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.4-1 1-1 2.3 0 1.4 1 2.7 1.1 2.9.2.2 2 3.2 5 4.2 2.5.8 3 .4 3.5.4s1.7-.7 1.9-1.4.2-1.2.1-1.4c-.1-.1-.3-.2-.7-.4l-2-.9c-.3-.1-.6-.2-.8.2l-.6.8c-.2.3-.4.3-.7.1-1-.4-1.9-1-2.6-1.8-.6-.7-1-1.3-1.1-1.6-.1-.3 0-.5.2-.7l.5-.6c.2-.2.2-.4.3-.6.1-.2 0-.4 0-.6Z"/>',
        'email' => '<path d="M4.5 6.5h15v11h-15Z"/><path d="m5 7 7 6 7-6"/>',
        'link' => '<path d="M10.5 13.5a3 3 0 0 0 4.2 0l3-3a3 3 0 0 0-4.2-4.2l-1.1 1.1"/><path d="M13.5 10.5a3 3 0 0 0-4.2 0l-3 3a3 3 0 0 0 4.2 4.2l1.1-1.1"/>',
        'print' => '<path d="M7 8V4h10v4"/><path d="M7 17H5a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2h-2"/><path d="M7 14h10v7H7Z"/><path d="M17.5 12.5h.01"/>',
        'ingredients' => '<path d="M7 10h10l-1 10H8Z"/><path d="M9 10V8a3 3 0 0 1 6 0v2"/><path d="M9.5 14h5M9.5 17h4"/>',
        'steps' => '<path d="M8 6h12M8 12h12M8 18h12"/><path d="M4 6h.01M4 12h.01M4 18h.01"/>',
        'prep' => '<path d="M4 20h16"/><path d="M6 20V9a6 6 0 0 1 12 0v11"/><path d="M8 12h8"/><path d="M9 5.2A5.8 5.8 0 0 1 12 4a5.8 5.8 0 0 1 3 1.2"/>',
        'tips' => '<path d="M9 18h6"/><path d="M10 21h4"/><path d="M8.5 14.5a5.5 5.5 0 1 1 7 0c-.8.6-1.2 1.3-1.3 2.2H9.8c-.1-.9-.5-1.6-1.3-2.2Z"/>',
        'storage' => '<path d="M6 7h12v14H6Z"/><path d="M8 7V4h8v3"/><path d="M9 11h6M9 15h6"/>',
        'question' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.7 2.7 0 0 1 5.1 1.2c0 1.8-1.8 2.2-2.4 3.3"/><path d="M12 17h.01"/>',
        'arrow-right' => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
        'search' => '<circle cx="10.7" cy="10.7" r="5.7"/><path d="m15 15 4.2 4.2"/>',
    ];

    if (!isset($icons[$name])) {
        return '';
    }

    return '<svg class="kepoli-icon kepoli-icon--' . esc_attr($name) . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $icons[$name] . '</svg>';
}

function kepoli_is_english(): bool
{
    return str_starts_with(strtolower(kepoli_public_locale()), 'en');
}

function kepoli_language_tag(): string
{
    return kepoli_locale_to_language_tag(kepoli_public_locale());
}

function kepoli_og_locale(): string
{
    return str_replace('-', '_', kepoli_language_tag());
}

function kepoli_ui_text(string $ro, string $en): string
{
    return kepoli_is_english() ? $en : $ro;
}

function kepoli_site_name(): string
{
    $name = trim((string) kepoli_profile_value(['brand', 'name'], ''));
    return $name !== '' ? $name : (get_bloginfo('name') ?: 'Dr Purg Jr.');
}

function kepoli_find_page_by_candidates(array $slugs): ?WP_Post
{
    foreach ($slugs as $slug) {
        $page = get_page_by_path($slug, OBJECT, 'page');
        if ($page instanceof WP_Post) {
            return $page;
        }
    }

    return null;
}

function kepoli_static_page_url(array $slugs, string $fallback_slug): string
{
    $page = kepoli_find_page_by_candidates($slugs);
    return $page instanceof WP_Post ? get_permalink($page) : home_url('/' . trim($fallback_slug, '/') . '/');
}

function kepoli_recipes_page(): ?WP_Post
{
    static $page = false;

    if ($page instanceof WP_Post || $page === null) {
        return $page;
    }

    $page = kepoli_find_page_by_candidates(array_unique(array_filter([kepoli_profile_slug('recipes', ''), 'recipes', 'recipes'])));
    return $page instanceof WP_Post ? $page : null;
}

function kepoli_recipes_page_url(): string
{
    $page = kepoli_recipes_page();
    return $page ? get_permalink($page) : home_url('/' . kepoli_profile_slug('recipes', kepoli_is_english() ? 'recipes' : 'recipes') . '/');
}

function kepoli_guides_page(): ?WP_Post
{
    static $page = false;

    if ($page instanceof WP_Post || $page === null) {
        return $page;
    }

    $page = kepoli_find_page_by_candidates(array_unique(array_filter([kepoli_profile_slug('guides', ''), 'guides', 'articles', 'guides'])));
    return $page instanceof WP_Post ? $page : null;
}

function kepoli_guides_page_url(): string
{
    $page = kepoli_guides_page();
    return $page ? get_permalink($page) : home_url('/' . kepoli_profile_slug('guides', kepoli_is_english() ? 'guides' : 'guides') . '/');
}

function kepoli_editorial_category_slugs(): array
{
    return array_values(array_unique(array_filter([
        kepoli_profile_slug('guides', ''),
        'guides',
        'articles',
        'guides',
    ])));
}

function kepoli_is_editorial_category_slug(string $slug): bool
{
    return in_array($slug, kepoli_editorial_category_slugs(), true)
        || str_contains($slug, 'guide')
        || str_contains($slug, 'article');
}

function kepoli_author_page_url(): string
{
    $author_slug = kepoli_profile_slug('author', kepoli_is_english() ? 'about-author' : 'about-author');
    $page = kepoli_find_page_by_candidates(array_unique(array_filter([$author_slug, 'about-author', 'about-author'])));
    return $page ? get_permalink($page) : home_url('/' . $author_slug . '/');
}

function kepoli_about_page(): ?WP_Post
{
    static $about_page = false;

    if ($about_page instanceof WP_Post || $about_page === null) {
        return $about_page;
    }

    $about_slug = kepoli_profile_slug('about', kepoli_is_english() ? 'about-dr-purg-jr' : 'despre-dr-purg-jr');
    $page = kepoli_find_page_by_candidates(array_unique(array_filter([$about_slug, 'about-dr-purg-jr', 'despre-dr-purg-jr'])));
    if ($page instanceof WP_Post) {
        $about_page = $page;
        return $about_page;
    }

    $pages = get_pages([
        'post_status' => 'publish',
        'sort_column' => 'menu_order,post_title',
    ]);

    foreach ($pages as $candidate) {
        $slug = (string) $candidate->post_name;
        if ((str_starts_with($slug, 'despre-') || str_starts_with($slug, 'about-')) && !in_array($slug, ['about-author', 'about-author'], true)) {
            $about_page = $candidate;
            return $about_page;
        }
    }

    $about_page = null;
    return null;
}

function kepoli_about_page_url(): string
{
    $page = kepoli_about_page();
    return $page ? get_permalink($page) : home_url('/' . kepoli_profile_slug('about', kepoli_is_english() ? 'about-dr-purg-jr' : 'despre-dr-purg-jr') . '/');
}

function kepoli_contact_page_url(): string
{
    return kepoli_static_page_url(['contact'], 'contact');
}

function kepoli_editorial_policy_url(): string
{
    return kepoli_static_page_url(array_unique(array_filter([kepoli_profile_slug('editorial', ''), 'politica-editoriala', 'editorial-policy'])), kepoli_profile_slug('editorial', kepoli_is_english() ? 'editorial-policy' : 'politica-editoriala'));
}

function kepoli_privacy_policy_url(): string
{
    return kepoli_static_page_url(array_unique(array_filter([kepoli_profile_slug('privacy', ''), 'politica-de-confidentialitate', 'privacy-policy'])), kepoli_profile_slug('privacy', kepoli_is_english() ? 'privacy-policy' : 'politica-de-confidentialitate'));
}

function kepoli_advertising_page_url(): string
{
    return kepoli_static_page_url(array_unique(array_filter([kepoli_profile_slug('advertising', ''), 'publicitate-si-consimtamant', 'advertising-and-consent'])), kepoli_profile_slug('advertising', kepoli_is_english() ? 'advertising-and-consent' : 'publicitate-si-consimtamant'));
}

function kepoli_cookie_policy_url(): string
{
    return kepoli_static_page_url(array_unique(array_filter([kepoli_profile_slug('cookies', ''), 'politica-de-cookies', 'cookie-policy'])), kepoli_profile_slug('cookies', kepoli_is_english() ? 'cookie-policy' : 'politica-de-cookies'));
}

function kepoli_terms_page_url(): string
{
    return kepoli_static_page_url(array_unique(array_filter([kepoli_profile_slug('terms', ''), 'termeni-si-conditii', 'terms-and-conditions'])), kepoli_profile_slug('terms', kepoli_is_english() ? 'terms-and-conditions' : 'termeni-si-conditii'));
}

function kepoli_disclaimer_page_url(): string
{
    return kepoli_static_page_url(array_unique(array_filter([kepoli_profile_slug('disclaimer', ''), 'disclaimer-culinar', 'culinary-disclaimer'])), kepoli_profile_slug('disclaimer', kepoli_is_english() ? 'culinary-disclaimer' : 'disclaimer-culinar'));
}

function kepoli_writer_user(): ?WP_User
{
    static $writer = false;

    if ($writer instanceof WP_User || $writer === null) {
        return $writer;
    }

    $email = trim((string) kepoli_profile_value(['writer', 'email'], kepoli_env('WRITER_EMAIL', '')));
    if ($email !== '') {
        $user = get_user_by('email', $email);
        if ($user instanceof WP_User) {
            $writer = $user;
            return $writer;
        }
    }

    $users = get_users([
        'role__in' => ['administrator', 'editor', 'author'],
        'number' => 1,
        'orderby' => 'registered',
        'order' => 'ASC',
    ]);

    $writer = $users[0] ?? null;
    return $writer instanceof WP_User ? $writer : null;
}

function kepoli_writer_name(): string
{
    $profile_name = trim((string) kepoli_profile_value(['writer', 'name'], ''));
    if ($profile_name !== '') {
        return $profile_name;
    }

    $writer = kepoli_writer_user();
    if ($writer instanceof WP_User && trim((string) $writer->display_name) !== '') {
        return (string) $writer->display_name;
    }

    return kepoli_site_name() ?: kepoli_ui_text('Autor', 'Author');
}

function kepoli_post_author_name(int $post_id = 0): string
{
    $post_id = $post_id > 0 ? $post_id : get_the_ID();
    $author_id = $post_id > 0 ? (int) get_post_field('post_author', $post_id) : 0;
    if ($author_id > 0) {
        $name = trim((string) get_the_author_meta('display_name', $author_id));
        if ($name !== '') {
            return $name;
        }
    }

    return kepoli_writer_name();
}

function kepoli_writer_email(): string
{
    $email = trim((string) kepoli_profile_value(['writer', 'email'], kepoli_env('WRITER_EMAIL', '')));
    if ($email !== '') {
        return $email;
    }

    $writer = kepoli_writer_user();
    return $writer instanceof WP_User ? (string) $writer->user_email : '';
}

function kepoli_writer_gravatar_url(): string
{
    $profile_url = esc_url_raw((string) kepoli_profile_value(['writer', 'gravatar_url'], ''), ['https']);
    if ($profile_url !== '') {
        return $profile_url;
    }

    $email = strtolower(trim(kepoli_writer_email()));
    return $email !== '' ? 'https://gravatar.com/' . md5($email) : 'https://gravatar.com/';
}

function kepoli_writer_description(): string
{
    $profile_bio = trim((string) kepoli_profile_value(['writer', 'bio'], ''));
    if ($profile_bio !== '') {
        return $profile_bio;
    }

    $writer = kepoli_writer_user();
    $description = $writer instanceof WP_User ? trim((string) get_user_meta($writer->ID, 'description', true)) : '';

    if ($description !== '') {
        return $description;
    }

    return sprintf(kepoli_ui_text('Pregateste explicatii despre sanatate si ghiduri practice pentru %s.', 'Prepares careful health explainers and practical guides for %s.'), kepoli_site_name());
}

function kepoli_brand_description(): string
{
    $profile_description = trim((string) kepoli_profile_value(['brand', 'description'], ''));
    if ($profile_description !== '') {
        return $profile_description;
    }

    $description = trim((string) get_bloginfo('description'));
    if ($description !== '') {
        return $description;
    }

    if (kepoli_is_english()) {
        return sprintf('%s publishes careful health facts, body-signal explainers, and everyday wellness context.', kepoli_site_name());
    }

    return sprintf('%s publica explicatii despre sanatate, obiceiuri zilnice si semnale ale corpului.', kepoli_site_name());
}

function kepoli_current_page_slug(): string
{
    if (!is_page()) {
        return '';
    }

    $page_id = get_queried_object_id();
    if (!$page_id) {
        return '';
    }

    return (string) get_post_field('post_name', $page_id);
}

function kepoli_current_description(): string
{
    $description = get_bloginfo('description');
    if (is_singular()) {
        $meta = get_post_meta(get_the_ID(), '_kepoli_meta_description', true);
        $description = $meta ?: wp_strip_all_tags(get_the_excerpt());
    } elseif (is_category()) {
        $description = category_description() ?: single_cat_title('', false);
    } elseif (is_front_page()) {
        $description = kepoli_brand_description();
    } elseif (is_page()) {
        $page_descriptions = [
            kepoli_profile_slug('about', kepoli_is_english() ? 'about-dr-purg-jr' : 'despre-dr-purg-jr') => kepoli_is_english()
                ? sprintf('Learn what %s publishes, who it is for, and how the publication approaches health facts, corrections, and advertising.', kepoli_site_name())
                : sprintf('Afla ce publica %s si cum abordeaza faptele despre sanatate, corecturile si publicitatea.', kepoli_site_name()),
            kepoli_profile_slug('author', kepoli_is_english() ? 'about-author' : 'about-author') => kepoli_is_english()
                ? sprintf('Meet %s, the editorial desk behind %s, and learn how the site approaches health explainers.', kepoli_writer_name(), kepoli_site_name())
                : sprintf('Afla cine este %s si cum abordeaza %s explicatiile despre sanatate.', kepoli_writer_name(), kepoli_site_name()),
            'contact' => kepoli_is_english()
                ? sprintf('Contact %s for editorial corrections, partnerships, privacy questions, advertising issues, or technical problems.', kepoli_site_name())
                : sprintf('Contacteaza %s pentru corecturi, parteneriate, intrebari despre confidentialitate sau probleme tehnice.', kepoli_site_name()),
            kepoli_profile_slug('privacy', kepoli_is_english() ? 'privacy-policy' : 'politica-de-confidentialitate') => kepoli_is_english()
                ? sprintf('Read how %s may process technical data, email submissions, newsletter signups, consent choices, and third-party services.', kepoli_site_name())
                : sprintf('Citeste cum poate procesa %s date tehnice, emailuri, abonari si alegeri de consimtamant.', kepoli_site_name()),
            kepoli_profile_slug('cookies', kepoli_is_english() ? 'cookie-policy' : 'politica-de-cookies') => kepoli_is_english()
                ? sprintf('See how %s uses cookies for site operation, preferences, analytics, consent, and advertising when enabled.', kepoli_site_name())
                : sprintf('Vezi cum foloseste %s cookies pentru functionare, preferinte, analiza si publicitate.', kepoli_site_name()),
            kepoli_profile_slug('advertising', kepoli_is_english() ? 'advertising-and-consent' : 'publicitate-si-consimtamant') => kepoli_is_english()
                ? sprintf('Understand how advertising, consent, and third-party monetization tools may work on %s when enabled.', kepoli_site_name())
                : sprintf('Intelege cum pot functiona publicitatea, consimtamantul si instrumentele terte pe %s.', kepoli_site_name()),
            kepoli_profile_slug('editorial', kepoli_is_english() ? 'editorial-policy' : 'politica-editoriala') => kepoli_is_english()
                ? sprintf('Review the editorial standards that guide health explainers, corrections, and sponsored material on %s.', kepoli_site_name())
                : sprintf('Vezi standardele editoriale care ghideaza explicatiile despre sanatate si corecturile pe %s.', kepoli_site_name()),
            kepoli_profile_slug('terms', kepoli_is_english() ? 'terms-and-conditions' : 'termeni-si-conditii') => kepoli_is_english()
                ? sprintf('Read the terms that apply to using %s, including content use, availability, and limitations of liability.', kepoli_site_name())
                : sprintf('Citeste termenii care se aplica folosirii %s, inclusiv utilizarea continutului si limitarile de raspundere.', kepoli_site_name()),
            kepoli_profile_slug('disclaimer', kepoli_is_english() ? 'culinary-disclaimer' : 'disclaimer-culinar') => kepoli_is_english()
                ? sprintf('Read the health disclaimer that applies to general education, body-signal explainers, and wellness guides on %s.', kepoli_site_name())
                : sprintf('Citeste disclaimerul de sanatate aplicabil explicatiilor generale publicate pe %s.', kepoli_site_name()),
        ];

        $page_slug = kepoli_current_page_slug();
        if (isset($page_descriptions[$page_slug])) {
            $description = $page_descriptions[$page_slug];
        }
    }

    return trim(wp_strip_all_tags((string) $description));
}

function kepoli_trim_meta_text(string $text, int $words = 28): string
{
    $text = trim(wp_strip_all_tags($text));
    if ($text === '') {
        return '';
    }

    return wp_trim_words($text, $words, '...');
}

function kepoli_trim_document_title_text(string $title, string $site_name): string
{
    $title = trim(wp_strip_all_tags($title));
    if ($title === '') {
        return '';
    }

    $title = preg_replace('/\s+/', ' ', $title) ?: $title;
    $site_suffix = ' | ' . $site_name;
    $has_site_suffix = $site_suffix !== '' && substr($title, -strlen($site_suffix)) === $site_suffix;
    $title_without_site = $has_site_suffix
        ? trim(substr($title, 0, -strlen($site_suffix)))
        : $title;
    $max_without_site = 58;

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($title_without_site) > $max_without_site) {
            $title_without_site = rtrim((string) mb_substr($title_without_site, 0, $max_without_site - 1), " \t\n\r\0\x0B,.-") . '...';
        }
    } elseif (strlen($title_without_site) > $max_without_site) {
        $title_without_site = rtrim(substr($title_without_site, 0, $max_without_site - 1), " \t\n\r\0\x0B,.-") . '...';
    }

    return $title_without_site;
}

function kepoli_clean_tag_name(string $tag): string
{
    $tag = trim(wp_strip_all_tags($tag));
    $tag = preg_replace('/\s+/', ' ', $tag) ?: $tag;
    $tag = trim($tag, " \t\n\r\0\x0B,.;:");

    if ($tag === '') {
        return '';
    }

    $length = function_exists('mb_strlen') ? mb_strlen($tag) : strlen($tag);
    if ($length > 70) {
        return '';
    }

    return $tag;
}

function kepoli_clean_post_tag_names(int $post_id = 0): array
{
    $post_id = $post_id ?: get_the_ID();
    $tags = wp_get_post_tags($post_id, ['fields' => 'names']);
    if (!is_array($tags)) {
        return [];
    }

    $clean = [];
    $seen = [];
    foreach ($tags as $tag) {
        $tag = kepoli_clean_tag_name((string) $tag);
        if ($tag === '') {
            continue;
        }

        $key = strtolower($tag);
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $clean[] = $tag;
    }

    return $clean;
}

function kepoli_current_seo_title(): string
{
    $site_name = kepoli_site_name();

    if (is_singular('post')) {
        $seo_title = trim((string) get_post_meta(get_the_ID(), '_kepoli_seo_title', true));
        $title = $seo_title !== '' ? $seo_title : single_post_title('', false);
    } elseif (is_front_page()) {
        $title = kepoli_is_english()
            ? 'Shocking health facts and careful body-signal guides | ' . $site_name
            : 'Retete romanesti pentru acasa | ' . $site_name;
    } elseif (($recipes_page = kepoli_recipes_page()) && is_page($recipes_page->ID)) {
        $title = kepoli_is_english()
            ? 'Health facts for curious readers | ' . $site_name
            : 'Fapte despre sanatate | ' . $site_name;
    } elseif (($guides_page = kepoli_guides_page()) && is_page($guides_page->ID)) {
        $title = kepoli_is_english()
            ? 'Health guides and myth checks | ' . $site_name
            : 'Ghiduri despre sanatate | ' . $site_name;
    } elseif (is_category()) {
        $title = single_cat_title('', false) . (kepoli_is_english() ? ' - Health facts and guides | ' : ' - Fapte si ghiduri | ') . $site_name;
    } elseif (is_search()) {
        $title = kepoli_is_english()
            ? sprintf('Search: %s | %s', get_search_query(), $site_name)
            : sprintf('Cautare: %s | %s', get_search_query(), $site_name);
    } elseif (is_404()) {
        $title = (kepoli_is_english() ? 'Page not found | ' : 'Pagina negasita | ') . $site_name;
    } elseif (is_page()) {
        $page_titles = [
            kepoli_profile_slug('about', kepoli_is_english() ? 'about-dr-purg-jr' : 'despre-dr-purg-jr') => kepoli_is_english() ? 'About the publication' : 'Despre publicatie',
            kepoli_profile_slug('author', kepoli_is_english() ? 'about-author' : 'about-author') => kepoli_is_english() ? 'About the author' : 'Despre autor',
            'contact' => kepoli_is_english() ? 'Contact and corrections' : 'Contact si corecturi',
            kepoli_profile_slug('privacy', kepoli_is_english() ? 'privacy-policy' : 'politica-de-confidentialitate') => kepoli_is_english() ? 'Privacy policy' : 'Politica de confidentialitate',
            kepoli_profile_slug('cookies', kepoli_is_english() ? 'cookie-policy' : 'politica-de-cookies') => kepoli_is_english() ? 'Cookie policy' : 'Politica de cookies',
            kepoli_profile_slug('advertising', kepoli_is_english() ? 'advertising-and-consent' : 'publicitate-si-consimtamant') => kepoli_is_english() ? 'Advertising and consent' : 'Publicitate si consimtamant',
            kepoli_profile_slug('editorial', kepoli_is_english() ? 'editorial-policy' : 'politica-editoriala') => kepoli_is_english() ? 'Editorial policy' : 'Politica editoriala',
            kepoli_profile_slug('terms', kepoli_is_english() ? 'terms-and-conditions' : 'termeni-si-conditii') => kepoli_is_english() ? 'Terms and conditions' : 'Termeni si conditii',
            kepoli_profile_slug('disclaimer', kepoli_is_english() ? 'culinary-disclaimer' : 'disclaimer-culinar') => kepoli_is_english() ? 'Health disclaimer' : 'Disclaimer de sanatate',
        ];

        $page_slug = kepoli_current_page_slug();
        $title = $page_titles[$page_slug] ?? single_post_title('', false);
    } elseif (is_archive()) {
        $title = get_the_archive_title();
    } else {
        $title = get_bloginfo('name');
    }

    $paged = max(1, (int) get_query_var('paged'), (int) get_query_var('page'));
    if ($paged > 1) {
        $title .= kepoli_is_english() ? ' - Page ' . $paged : ' - Pagina ' . $paged;
    }

    $title = kepoli_trim_document_title_text($title, $site_name);

    if (!str_contains($title, $site_name)) {
        $title .= ' | ' . $site_name;
    }

    return trim($title);
}

function kepoli_document_title(string $title): string
{
    return kepoli_current_seo_title();
}
add_filter('pre_get_document_title', 'kepoli_document_title');

function kepoli_social_image_url(): string
{
    if (is_singular()) {
        $image = kepoli_post_featured_image_url(get_the_ID(), 'large');
        if ($image !== '') {
            return $image;
        }
    }

    $social_cover_asset = kepoli_social_cover_asset();
    $social_cover = get_template_directory() . '/assets/img/' . $social_cover_asset . '.jpg';
    if (file_exists($social_cover)) {
        return kepoli_asset_uri($social_cover_asset, 'jpg');
    }

    return kepoli_asset_uri('writer-photo', 'jpg');
}

function kepoli_social_image_alt(): string
{
    if (is_singular()) {
        $alt = kepoli_post_featured_image_alt(get_the_ID());
        if ($alt !== '') {
            return $alt;
        }
    }

    $fallback = kepoli_current_description() ?: kepoli_brand_description();
    return kepoli_trim_meta_text($fallback, 32);
}

function kepoli_social_image_dimensions(): array
{
    if (is_singular()) {
        $image_id = kepoli_post_featured_image_id(get_the_ID());
        if ($image_id) {
            $image = wp_get_attachment_image_src($image_id, 'large');
            if (is_array($image)) {
                return [(int) ($image[1] ?? 0), (int) ($image[2] ?? 0)];
            }
        }
    }

    return kepoli_asset_dimensions(kepoli_social_cover_asset());
}

function kepoli_schema_image_object(string $url, array $dimensions = [], string $caption = ''): array
{
    $image = [
        '@type' => 'ImageObject',
        'url' => $url,
    ];

    [$width, $height] = array_pad($dimensions, 2, 0);
    if ((int) $width > 0 && (int) $height > 0) {
        $image['width'] = (int) $width;
        $image['height'] = (int) $height;
    }

    if (trim($caption) !== '') {
        $image['caption'] = trim($caption);
    }

    return $image;
}

function kepoli_schema_asset_image_object(string $basename, string $fallback_extension = 'svg', string $caption = ''): array
{
    return kepoli_schema_image_object(
        kepoli_asset_uri($basename, $fallback_extension),
        kepoli_asset_dimensions($basename),
        $caption
    );
}

function kepoli_social_image_schema_object(): array
{
    if (is_singular('post')) {
        $image_id = kepoli_post_featured_image_id(get_the_ID());
        if ($image_id) {
            $image = wp_get_attachment_image_src($image_id, 'large');
            $url = is_array($image) ? (string) $image[0] : kepoli_post_featured_image_url(get_the_ID(), 'large');
            $dimensions = is_array($image) ? [(int) $image[1], (int) $image[2]] : [];
            $caption = kepoli_post_featured_image_caption(get_the_ID()) ?: kepoli_post_featured_image_alt(get_the_ID());

            if ($url !== '') {
                return kepoli_schema_image_object($url, $dimensions, $caption);
            }
        }
    }

    return kepoli_schema_asset_image_object(kepoli_social_cover_asset(), 'jpg', kepoli_current_description());
}

function kepoli_schema_publisher(): array
{
    $site_name = kepoli_site_name();

    return [
        '@type' => 'Organization',
        '@id' => home_url('/#organization'),
        'name' => $site_name,
        'url' => home_url('/'),
        'email' => kepoli_public_contact_email(),
        'contactPoint' => [
            '@type' => 'ContactPoint',
            'contactType' => 'editorial',
            'email' => kepoli_public_contact_email(),
            'url' => kepoli_contact_page_url(),
            'availableLanguage' => kepoli_available_languages(),
        ],
        'publishingPrinciples' => kepoli_editorial_policy_url(),
        'logo' => kepoli_schema_asset_image_object(kepoli_icon_asset(), 'svg', $site_name),
    ];
}

function kepoli_current_url(): string
{
    if (is_singular()) {
        return get_permalink();
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash((string) $_SERVER['REQUEST_URI']) : '/';
    $path = parse_url($request_uri, PHP_URL_PATH);
    $path = is_string($path) && $path !== '' ? $path : '/';
    $canonical = home_url($path);
    $allowed_query = [];

    if (is_search()) {
        $search_query = get_search_query();
        if ($search_query !== '') {
            $allowed_query['s'] = $search_query;
        }
    }

    $paged = max(1, (int) get_query_var('paged'), (int) get_query_var('page'));
    if ($paged > 1 && !preg_match('#/page/\d+/?$#', $path)) {
        $allowed_query['paged'] = $paged;
    }

    if ($allowed_query !== []) {
        $canonical = add_query_arg($allowed_query, $canonical);
    }

    return $canonical;
}

function kepoli_ads_enabled(): bool
{
    return kepoli_env_bool('ADSENSE_ENABLE', false);
}

function kepoli_display_ads_enabled(): bool
{
    return kepoli_env_bool('DISPLAY_ADS_ENABLE', false);
}

function kepoli_ga_enabled(): bool
{
    return kepoli_env_bool('GA_ENABLE', false);
}

function kepoli_histats_enabled(): bool
{
    return kepoli_env_bool('HISTATS_ENABLE', false);
}

function kepoli_monetag_enabled(): bool
{
    return kepoli_env_bool('MONETAG_ENABLE', false);
}

function kepoli_ad_mode(): string
{
    $mode = strtolower(kepoli_env('KT_AD_MODE', 'baseline'));
    return in_array($mode, ['baseline', 'medium', 'aggressive'], true) ? $mode : 'baseline';
}

function kepoli_prelander_enabled(): bool
{
    return kepoli_env_bool('KT_PRELANDER_ENABLE', false);
}

function kepoli_ad_mode_allows_action_onclick(): bool
{
    return in_array(kepoli_ad_mode(), ['medium', 'aggressive'], true);
}

function kepoli_action_ad_min_seconds(): int
{
    return kepoli_env_int('KT_ACTION_AD_MIN_SECONDS', 45, 0, 3600);
}

function kepoli_action_ad_min_scroll(): int
{
    return kepoli_env_int('KT_ACTION_AD_MIN_SCROLL', 35, 0, 100);
}

function kepoli_monetag_snippet_keys(): array
{
    return [
        'inpage_push' => 'MONETAG_INPAGE_PUSH_BASE64',
        'vignette' => 'MONETAG_VIGNETTE_BASE64',
        'onclick' => 'MONETAG_ONCLICK_BASE64',
        'push' => 'MONETAG_PUSH_BASE64',
    ];
}

function kepoli_monetag_frequency_keys(): array
{
    return [
        'inpage_push' => 'MONETAG_INPAGE_PUSH_MINUTES',
        'vignette' => 'MONETAG_VIGNETTE_MINUTES',
        'onclick' => 'MONETAG_ONCLICK_MINUTES',
        'push' => 'MONETAG_PUSH_MINUTES',
    ];
}

function kepoli_monetag_snippet_code(string $env_key): string
{
    $encoded = kepoli_env($env_key);
    if ($encoded === '') {
        return '';
    }

    $decoded = base64_decode($encoded, true);
    if (!is_string($decoded)) {
        return '';
    }

    return trim($decoded);
}

function kepoli_monetag_snippets(): array
{
    $mode = kepoli_ad_mode();
    $snippets = [];
    foreach (kepoli_monetag_snippet_keys() as $format => $env_key) {
        if ($format === 'onclick') {
            continue;
        }

        if ($format === 'inpage_push' && $mode === 'baseline') {
            continue;
        }

        if ($format === 'vignette' && $mode !== 'aggressive') {
            continue;
        }

        if ($format === 'push' && $mode !== 'aggressive') {
            continue;
        }

        $code = kepoli_monetag_snippet_code($env_key);
        if ($code !== '') {
            $snippets[$format] = $code;
        }
    }

    return $snippets;
}

function kepoli_monetag_action_onclick_code(): string
{
    if (!kepoli_ad_mode_allows_action_onclick()) {
        return '';
    }

    return kepoli_monetag_snippet_code('MONETAG_ONCLICK_BASE64');
}

function kepoli_monetag_frequency_minutes(string $format): int
{
    $frequency_keys = kepoli_monetag_frequency_keys();
    $env_key = $frequency_keys[$format] ?? '';
    return $env_key !== '' ? kepoli_env_int($env_key, 0, 0, 10080) : 0;
}

function kepoli_monetag_render_snippet(string $format, string $code): void
{
    $minutes = kepoli_monetag_frequency_minutes($format);
    if ($minutes <= 0) {
        echo $code . "\n";
        return;
    }

    $storage_key = 'kepoli_monetag_' . sanitize_key($format);
    $interval_ms = $minutes * MINUTE_IN_SECONDS * 1000;
    ?>
    <script>
    (function () {
      var key = <?php echo wp_json_encode($storage_key); ?>;
      var intervalMs = <?php echo (int) $interval_ms; ?>;
      try {
        var now = Date.now();
        var last = parseInt(window.localStorage.getItem(key) || '0', 10);
        if (last && now - last < intervalMs) {
          return;
        }
        window.localStorage.setItem(key, String(now));
      } catch (error) {}

      var html = <?php echo wp_json_encode($code, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
      var holder = document.createElement('div');
      holder.innerHTML = html;
      Array.prototype.slice.call(holder.childNodes).forEach(function (node) {
        if (node.nodeName && node.nodeName.toLowerCase() === 'script') {
          var script = document.createElement('script');
          Array.prototype.slice.call(node.attributes || []).forEach(function (attr) {
            script.setAttribute(attr.name, attr.value);
          });
          if (node.text) {
            script.text = node.text;
          }
          document.head.appendChild(script);
          return;
        }

        (document.body || document.documentElement).appendChild(node.cloneNode(true));
      });
    }());
    </script>
    <?php
}

function kepoli_monetag_install_check_enabled(): bool
{
    return kepoli_env_bool('MONETAG_INSTALL_CHECK', false);
}

function kepoli_primary_category(int $post_id = 0): ?WP_Term
{
    $post_id = $post_id ?: get_the_ID();
    $categories = get_the_category($post_id);
    return !empty($categories) ? $categories[0] : null;
}

function kepoli_tone_class(string $slug = ''): string
{
    $map = [
        'quick-recipes' => 'tone-mains',
        'main-dishes' => 'tone-mains',
        'desserts' => 'tone-sweets',
        'seasonal-cooking' => 'tone-pantry',
        'guides' => 'tone-guides',
    ];

    return $map[$slug] ?? 'tone-default';
}

function kepoli_post_tone_class(int $post_id = 0): string
{
    $category = kepoli_primary_category($post_id);
    return kepoli_tone_class($category ? $category->slug : '');
}

function kepoli_post_kind_label(int $post_id = 0): string
{
    return kepoli_post_kind($post_id) === 'article' ? kepoli_ui_text('Articol', 'Guide') : kepoli_ui_text('Reteta', 'Recipe');
}

function kepoli_browse_items(): array
{
    $items = [
        [
            'label' => kepoli_ui_text('Toate faptele', 'All health facts'),
            'url' => kepoli_recipes_page_url(),
            'meta' => kepoli_ui_text('Fapte organizate pe categorii', 'Facts organized by category'),
            'class' => 'tone-mains',
        ],
        [
            'label' => kepoli_ui_text('Articole utile', 'Useful guides'),
            'url' => kepoli_guides_page_url(),
            'meta' => kepoli_ui_text('Ghiduri, obiceiuri si mituri', 'Guides, habits, and myths'),
            'class' => 'tone-guides',
        ],
    ];

    foreach (['quick-recipes', 'main-dishes', 'desserts', 'seasonal-cooking'] as $slug) {
        $category = get_category_by_slug($slug);
        if (!$category instanceof WP_Term) {
            continue;
        }

        $items[] = [
            'label' => $category->name,
            'url' => get_category_link($category),
            'meta' => sprintf(kepoli_is_english() ? _n('%d fact', '%d facts', $category->count, 'kepoli') : _n('%d fapt', '%d fapte', $category->count, 'kepoli'), $category->count),
            'class' => kepoli_tone_class($slug),
        ];
    }

    return $items;
}

function kepoli_render_browse_links(string $class = 'browse-links'): void
{
    echo '<div class="' . esc_attr($class) . '" aria-label="' . esc_attr(kepoli_ui_text('Descoperire rapida', 'Quick discovery')) . '">';
    foreach (kepoli_browse_items() as $item) {
        echo '<a class="browse-link ' . esc_attr($item['class']) . '" href="' . esc_url($item['url']) . '">';
        echo '<strong>' . esc_html($item['label']) . '</strong>';
        echo '<span>' . esc_html($item['meta']) . '</span>';
        echo '</a>';
    }
    echo '</div>';
}

function kepoli_footer_menu_items(): array
{
    return [
        [
            'label' => sprintf(kepoli_ui_text('Despre %s', 'About %s'), kepoli_site_name()),
            'url' => kepoli_about_page_url(),
        ],
        [
            'label' => kepoli_ui_text('Politica editoriala', 'Editorial policy'),
            'url' => kepoli_editorial_policy_url(),
        ],
        [
            'label' => kepoli_ui_text('Publicitate si consimtamant', 'Advertising and consent'),
            'url' => kepoli_advertising_page_url(),
        ],
        [
            'label' => kepoli_ui_text('Politica de confidentialitate', 'Privacy policy'),
            'url' => kepoli_privacy_policy_url(),
        ],
        [
            'label' => kepoli_ui_text('Politica de cookies', 'Cookie policy'),
            'url' => kepoli_cookie_policy_url(),
        ],
        [
            'label' => kepoli_ui_text('Disclaimer culinar', 'Culinary disclaimer'),
            'url' => kepoli_disclaimer_page_url(),
        ],
        [
            'label' => kepoli_ui_text('Termeni si conditii', 'Terms and conditions'),
            'url' => kepoli_terms_page_url(),
        ],
    ];
}

function kepoli_footer_menu_fallback(array $args = []): void
{
    $menu_class = !empty($args['menu_class']) ? (string) $args['menu_class'] : 'menu';

    echo '<ul class="' . esc_attr($menu_class) . '">';
    foreach (kepoli_footer_menu_items() as $item) {
        echo '<li class="menu-item">';
        echo '<a href="' . esc_url($item['url']) . '">' . esc_html($item['label']) . '</a>';
        echo '</li>';
    }
    echo '</ul>';
}

function kepoli_primary_menu_items(): array
{
    return [
        [
            'label' => kepoli_ui_text('Acasa', 'Home'),
            'url' => home_url('/'),
        ],
        [
            'label' => kepoli_ui_text('Fapte sanatate', 'Health facts'),
            'url' => kepoli_recipes_page_url(),
        ],
        [
            'label' => kepoli_ui_text('Articole', 'Guides'),
            'url' => kepoli_guides_page_url(),
        ],
        [
            'label' => kepoli_ui_text('Despre', 'About'),
            'url' => kepoli_about_page_url(),
        ],
        [
            'label' => kepoli_ui_text('Contact', 'Contact'),
            'url' => kepoli_contact_page_url(),
        ],
    ];
}

function kepoli_primary_menu_fallback(array $args = []): void
{
    $menu_class = !empty($args['menu_class']) ? (string) $args['menu_class'] : 'menu';

    echo '<ul class="' . esc_attr($menu_class) . '">';
    foreach (kepoli_primary_menu_items() as $item) {
        echo '<li class="menu-item">';
        echo '<a href="' . esc_url($item['url']) . '">' . esc_html($item['label']) . '</a>';
        echo '</li>';
    }
    echo '</ul>';
}

function kepoli_fragment_cache_version(): string
{
    $version = get_option('kepoli_fragment_cache_version', '');
    if ($version === '') {
        $version = (string) microtime(true);
        add_option('kepoli_fragment_cache_version', $version, '', false);
    }

    return $version;
}

function kepoli_fragment_cache_key(string $fragment, string $suffix = ''): string
{
    return 'kepoli_' . sanitize_key($fragment) . '_' . md5(kepoli_fragment_cache_version() . '|' . $suffix);
}

function kepoli_bump_fragment_cache_version(): void
{
    update_option('kepoli_fragment_cache_version', (string) microtime(true), false);
}

add_action('clean_post_cache', 'kepoli_bump_fragment_cache_version');
add_action('created_category', 'kepoli_bump_fragment_cache_version');
add_action('edited_category', 'kepoli_bump_fragment_cache_version');
add_action('delete_category', 'kepoli_bump_fragment_cache_version');

function kepoli_editorial_paths(): array
{
    $cache_key = kepoli_fragment_cache_key('editorial_paths');
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return $cached;
    }

    $definitions = [
        [
            'eyebrow' => kepoli_ui_text('Mancare si obiceiuri', 'Food and habits'),
            'title' => kepoli_ui_text('Incepe cu alegeri zilnice usor de observat', 'Start with everyday choices that are easy to notice'),
            'summary' => kepoli_ui_text('Ghiduri despre etichete, bauturi, fibre, sare si obiceiuri care apar in viata de zi cu zi.', 'Guides about labels, drinks, fiber, salt, and habits that show up in everyday life.'),
            'class' => 'tone-pantry',
            'slugs' => ['label-sugar-health-halo', 'hidden-salt-restaurant-meals', 'fiber-facts-most-people-miss'],
        ],
        [
            'eyebrow' => kepoli_ui_text('Somn si energie', 'Sleep and energy'),
            'title' => kepoli_ui_text('Intelege ritmul care iti schimba ziua', 'Understand the rhythm that changes your day'),
            'summary' => kepoli_ui_text('Articole despre lumina telefonului, cafeina, somn de recuperare si micile alegeri care schimba energia.', 'Articles about phone light, caffeine, catch-up sleep, and small choices that shape energy.'),
            'class' => 'tone-mains',
            'slugs' => ['phone-light-sleep-delay', 'caffeine-timing-energy-crash', 'sleep-debt-weekend-catchup'],
        ],
        [
            'eyebrow' => kepoli_ui_text('Semnale ale corpului', 'Body signals'),
            'title' => kepoli_ui_text('Citeste semnalele cu mai mult context', 'Read signals with more context'),
            'summary' => kepoli_ui_text('Explicatii despre gura uscata, maini reci, ameteli scurte si alte semnale care merita inteles calm.', 'Explainers about dry mouth, cold hands, brief dizziness, and other signals worth reading calmly.'),
            'class' => 'tone-guides',
            'slugs' => ['morning-mouth-warning-signals', 'cold-hands-common-reasons', 'standing-too-fast-dizzy'],
        ],
    ];

    $paths = [];

    foreach ($definitions as $definition) {
        $articles = [];

        foreach ($definition['slugs'] as $slug) {
            $post = get_page_by_path($slug, OBJECT, 'post');
            if (!$post instanceof WP_Post || kepoli_post_kind($post->ID) !== 'article') {
                continue;
            }

            $articles[] = [
                'title' => get_the_title($post),
                'url' => get_permalink($post),
            ];
        }

        if ($articles === []) {
            continue;
        }

        $paths[] = [
            'eyebrow' => $definition['eyebrow'],
            'title' => $definition['title'],
            'summary' => $definition['summary'],
            'class' => $definition['class'],
            'articles' => $articles,
        ];
    }

    set_transient($cache_key, $paths, 12 * HOUR_IN_SECONDS);

    return $paths;
}

function kepoli_reader_trust_items(): array
{
    $site_name = kepoli_site_name();
    $author_page = kepoli_find_page_by_candidates(['about-author', 'about-author']);
    $author_label = $author_page instanceof WP_Post ? get_the_title($author_page) : kepoli_ui_text('Autor', 'Author');

    return [
        [
            'label' => sprintf(kepoli_ui_text('Despre %s', 'About %s'), $site_name),
            'url' => kepoli_about_page_url(),
            'meta' => kepoli_ui_text('Cine scrie si cum lucram', 'Who writes and how we work'),
            'class' => 'tone-guides',
        ],
        [
            'label' => $author_label,
            'url' => kepoli_author_page_url(),
            'meta' => kepoli_ui_text('Pagina autoarei si date editoriale', 'Author page and editorial details'),
            'class' => 'tone-default',
        ],
        [
            'label' => kepoli_ui_text('Politica editoriala', 'Editorial policy'),
            'url' => kepoli_editorial_policy_url(),
            'meta' => kepoli_ui_text('Originalitate, corecturi si independenta', 'Originality, corrections, and independence'),
            'class' => 'tone-guides',
        ],
        [
            'label' => kepoli_ui_text('Contact', 'Contact'),
            'url' => kepoli_contact_page_url(),
            'meta' => kepoli_ui_text('Intrebari, corecturi si colaborari', 'Questions, corrections, and collaboration'),
            'class' => 'tone-default',
        ],
    ];
}

function kepoli_render_reader_trust_links(string $class = 'browse-links browse-links--trust'): void
{
    echo '<div class="' . esc_attr($class) . '" aria-label="' . esc_attr(sprintf(kepoli_ui_text('Transparenta %s', '%s transparency'), kepoli_site_name())) . '">';
    foreach (kepoli_reader_trust_items() as $item) {
        echo '<a class="browse-link ' . esc_attr($item['class']) . '" href="' . esc_url($item['url']) . '">';
        echo '<strong>' . esc_html($item['label']) . '</strong>';
        echo '<span>' . esc_html($item['meta']) . '</span>';
        echo '</a>';
    }
    echo '</div>';
}

function kepoli_official_page_slugs(): array
{
    return array_values(array_unique(array_filter([
        kepoli_profile_slug('about', kepoli_is_english() ? 'about-dr-purg-jr' : 'despre-dr-purg-jr'),
        kepoli_profile_slug('author', kepoli_is_english() ? 'about-author' : 'about-author'),
        kepoli_profile_slug('privacy', kepoli_is_english() ? 'privacy-policy' : 'politica-de-confidentialitate'),
        kepoli_profile_slug('cookies', kepoli_is_english() ? 'cookie-policy' : 'politica-de-cookies'),
        kepoli_profile_slug('advertising', kepoli_is_english() ? 'advertising-and-consent' : 'publicitate-si-consimtamant'),
        kepoli_profile_slug('editorial', kepoli_is_english() ? 'editorial-policy' : 'politica-editoriala'),
        kepoli_profile_slug('terms', kepoli_is_english() ? 'terms-and-conditions' : 'termeni-si-conditii'),
        kepoli_profile_slug('disclaimer', kepoli_is_english() ? 'culinary-disclaimer' : 'disclaimer-culinar'),
        'about-dr-purg-jr',
        'despre-dr-purg-jr',
        'about-author',
        'about-author',
        'contact',
        'privacy-policy',
        'politica-de-confidentialitate',
        'cookie-policy',
        'politica-de-cookies',
        'advertising-and-consent',
        'publicitate-si-consimtamant',
        'editorial-policy',
        'politica-editoriala',
        'terms-and-conditions',
        'termeni-si-conditii',
        'culinary-disclaimer',
        'disclaimer-culinar',
    ])));
}

function kepoli_is_official_page(): bool
{
    return is_page() && in_array(kepoli_current_page_slug(), kepoli_official_page_slugs(), true);
}

function kepoli_official_page_header_items(): array
{
    if (!kepoli_is_official_page()) {
        return [];
    }

    $items = [
        [
            'label' => kepoli_ui_text('Pagina oficiala', 'Official page'),
            'value' => kepoli_ui_text('Informatii editoriale si de publicare', 'Publishing and editorial information'),
        ],
        [
            'label' => kepoli_ui_text('Actualizat', 'Updated'),
            'value' => get_the_modified_date(kepoli_is_english() ? 'F j, Y' : 'j F Y'),
        ],
        [
            'label' => kepoli_ui_text('Contact', 'Contact'),
            'value' => kepoli_public_contact_email(),
            'url' => 'mailto:' . kepoli_public_contact_email(),
        ],
    ];

    if (kepoli_current_page_slug() === kepoli_profile_slug('author', kepoli_is_english() ? 'about-author' : 'about-author')) {
        $items[2] = [
            'label' => kepoli_ui_text('Profil', 'Profile'),
            'value' => 'Gravatar',
            'url' => kepoli_writer_gravatar_url(),
        ];
    }

    return $items;
}

function kepoli_render_official_page_header_meta(string $class = 'page-header-meta'): void
{
    $items = kepoli_official_page_header_items();
    if ($items === []) {
        return;
    }

    echo '<div class="' . esc_attr($class) . '" aria-label="' . esc_attr(kepoli_ui_text('Repere de pagina', 'Page details')) . '">';
    foreach ($items as $item) {
        echo '<div class="page-header-meta__item">';
        echo '<span class="page-header-meta__label">' . esc_html($item['label']) . '</span>';

        if (!empty($item['url'])) {
            echo '<a class="page-header-meta__value" href="' . esc_url($item['url']) . '">' . esc_html($item['value']) . '</a>';
        } else {
            echo '<span class="page-header-meta__value">' . esc_html($item['value']) . '</span>';
        }

        echo '</div>';
    }
    echo '</div>';
}

function kepoli_category_card_meta(WP_Term $category): array
{
    $category_map = [
        'quick-recipes' => [
            'icon' => 'Q',
            'description' => __('Fast recipes for busy evenings, quick lunches, and practical everyday cooking.', 'kepoli'),
        ],
        'main-dishes' => [
            'icon' => 'M',
            'description' => __('Comforting lunches and dinners with clear timing, serving notes, and useful kitchen cues.', 'kepoli'),
        ],
        'desserts' => [
            'icon' => 'D',
            'description' => __('Cakes, bakes, chilled sweets, and simple desserts with texture and storage notes.', 'kepoli'),
        ],
        'seasonal-cooking' => [
            'icon' => 'S',
            'description' => __('Ingredient-led ideas and seasonal recipes for cooking with what is actually available.', 'kepoli'),
        ],
        'guides' => [
            'icon' => 'G',
            'description' => __('Practical food guides about ingredients, planning, storage, and everyday kitchen habits.', 'kepoli'),
        ],
    ];

    return $category_map[$category->slug] ?? [
        'icon' => '*',
        'description' => sprintf(kepoli_ui_text('Idei %s organizate pentru rasfoire rapida.', '%s ideas organized for quick browsing.'), kepoli_site_name()),
    ];

}

function kepoli_category_card_image_data(WP_Term $category): array
{
    static $cache = [];

    if (array_key_exists($category->term_id, $cache)) {
        return $cache[$category->term_id];
    }

    $cache_key = kepoli_fragment_cache_key('category_card_image', (string) $category->term_id);
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        if (!empty($cached['url']) && is_string($cached['url'])) {
            $cached['url'] = kepoli_normalize_media_url($cached['url']);
        }
        if (!empty($cached['gallery']) && is_array($cached['gallery'])) {
            foreach ($cached['gallery'] as &$cached_item) {
                if (is_array($cached_item) && !empty($cached_item['url']) && is_string($cached_item['url'])) {
                    $cached_item['url'] = kepoli_normalize_media_url($cached_item['url']);
                }
            }
            unset($cached_item);
        }
        $cache[$category->term_id] = $cached;
        return $cached;
    }

    $query = new WP_Query([
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => 3,
        'fields' => 'ids',
        'cat' => $category->term_id,
        'no_found_rows' => true,
        'ignore_sticky_posts' => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'meta_query' => [
            [
                'key' => '_thumbnail_id',
                'compare' => 'EXISTS',
            ],
        ],
    ]);

    $data = [];

    if ($query->have_posts()) {
        $gallery = [];

        foreach ($query->posts as $index => $post_id) {
            $cover_size = $index === 0 ? 'medium_large' : 'thumbnail';
            $post_id = (int) $post_id;
            $thumbnail_id = (int) get_post_thumbnail_id($post_id);
            $image = $thumbnail_id ? wp_get_attachment_image_src($thumbnail_id, $cover_size) : false;
            $image_url = is_array($image) ? kepoli_normalize_media_url((string) $image[0]) : '';
            if (!$image_url) {
                continue;
            }

            $item = [
                'url' => $image_url,
                'alt' => kepoli_post_featured_image_alt($post_id),
                'title' => get_the_title($post_id),
                'width' => is_array($image) ? (int) $image[1] : 0,
                'height' => is_array($image) ? (int) $image[2] : 0,
            ];

            if ($index === 0) {
                $data = [
                    'url' => $item['url'],
                    'alt' => $item['alt'],
                    'sample' => $item['title'],
                ];
            }

            $gallery[] = $item;
        }

        if ($gallery !== []) {
            $data['gallery'] = $gallery;
        }
    }

    wp_reset_postdata();
    set_transient($cache_key, $data, 12 * HOUR_IN_SECONDS);
    $cache[$category->term_id] = $data;

    return $data;
}

function kepoli_normalize_media_url(string $url): string
{
    if ($url === '') {
        return '';
    }

    return set_url_scheme($url, 'https');
}

function kepoli_archive_count_label(WP_Term $category): string
{
    if (kepoli_is_editorial_category_slug($category->slug)) {
        return sprintf(kepoli_is_english() ? _n('%d guide published', '%d guides published', $category->count, 'kepoli') : _n('%d ghid publicat', '%d ghiduri publicate', $category->count, 'kepoli'), $category->count);
    }

    return sprintf(kepoli_is_english() ? _n('%d recipe published', '%d recipes published', $category->count, 'kepoli') : _n('%d reteta publicata', '%d retete publicate', $category->count, 'kepoli'), $category->count);
}

function kepoli_archive_guidance_items(): array
{
    $category = get_queried_object();
    if (!$category instanceof WP_Term || $category->taxonomy !== 'category') {
        return [];
    }

    $guidance_map = [
        'quick-recipes' => [
            [
                'title' => __('Start with timing', 'kepoli'),
                'body' => __('Quick recipes are organized around realistic prep, simple cleanup, and steps that keep dinner moving.', 'kepoli'),
            ],
            [
                'title' => __('Choose by effort', 'kepoli'),
                'body' => __('Look for the recipe that matches the time and attention you have, then use the notes to adjust serving or storage.', 'kepoli'),
            ],
            [
                'title' => __('What you will find', 'kepoli'),
                'body' => __('Each page keeps ingredients, method, timing, and nearby links close enough to scan before you start cooking.', 'kepoli'),
            ],
        ],
        'main-dishes' => [
            [
                'title' => __('Choose by the meal', 'kepoli'),
                'body' => __('Main dishes focus on lunches and dinners that feel complete, with practical cues for texture, timing, and serving.', 'kepoli'),
            ],
            [
                'title' => __('What matters most', 'kepoli'),
                'body' => __('Browning, sauce consistency, resting time, and the right side often matter more than adding extra ingredients.', 'kepoli'),
            ],
            [
                'title' => __('How to compare', 'kepoli'),
                'body' => __('Use the excerpts, prep times, and related guides to decide whether a recipe fits a weeknight or a slower weekend meal.', 'kepoli'),
            ],
        ],
        'desserts' => [
            [
                'title' => __('Watch the texture', 'kepoli'),
                'body' => __('Desserts depend on cues like doneness, cooling, batter thickness, and storage, so those details stay close to the method.', 'kepoli'),
            ],
            [
                'title' => __('Pick the right pace', 'kepoli'),
                'body' => __('Some sweets are simple bakes, while others need chilling, resting, or careful timing before they taste their best.', 'kepoli'),
            ],
            [
                'title' => __('What you will find', 'kepoli'),
                'body' => __('The archive groups cakes, skillet sweets, fruit desserts, and approachable bakes with clear notes for home kitchens.', 'kepoli'),
            ],
        ],
        'seasonal-cooking' => [
            [
                'title' => __('Start with the ingredient', 'kepoli'),
                'body' => __('Seasonal recipes are built around what is fresh, practical, or especially useful at a given time of year.', 'kepoli'),
            ],
            [
                'title' => __('Use what you have', 'kepoli'),
                'body' => __('The notes help you adapt produce, herbs, and pantry staples without turning a simple recipe into a project.', 'kepoli'),
            ],
            [
                'title' => __('Keep it flexible', 'kepoli'),
                'body' => __('These pages work best as a starting point for meals that follow the season but still fit an ordinary kitchen.', 'kepoli'),
            ],
        ],
        'guides' => [
            [
                'title' => __('Start with the question', 'kepoli'),
                'body' => __('Guides are grouped around real kitchen problems: planning, storage, seasoning, ingredients, and repeatable habits.', 'kepoli'),
            ],
            [
                'title' => __('Use guides with recipes', 'kepoli'),
                'body' => __('The articles are written to support cooking decisions, then point readers back toward recipes they can actually make.', 'kepoli'),
            ],
            [
                'title' => __('Trust signals', 'kepoli'),
                'body' => __('Author, contact, and editorial policy links stay visible so readers can understand who is responsible for the content.', 'kepoli'),
            ],
        ],
    ];

    return $guidance_map[$category->slug] ?? [
        [
            'title' => kepoli_ui_text('Ce gasesti aici', 'What you will find here'),
            'body' => kepoli_ui_text('Continutul este organizat pentru rasfoire rapida, cu imagini, context practic si trimiteri utile spre pagini inrudite.', 'Content is organized for quick browsing, with images, practical context, and useful links to related pages.'),
        ],
        [
            'title' => kepoli_ui_text('Cum te orientezi', 'How to choose'),
            'body' => kepoli_ui_text('Porneste de la descrierea categoriei si apoi compara titlurile, timpii si extrasele ca sa alegi pagina potrivita.', 'Start with the category description, then compare titles, timings, and excerpts to choose the right page.'),
        ],
        [
            'title' => kepoli_ui_text('Transparenta', 'Transparency'),
            'body' => kepoli_ui_text('Linkurile de autor, contact si politica editoriala raman aproape de continut pentru orientare rapida.', 'Author, contact, and editorial policy links stay close to the content for quick orientation.'),
        ],
    ];

}

function kepoli_page_resource_links(): array
{
    if (!is_page()) {
        return [];
    }

    $page_id = get_queried_object_id();
    if (!$page_id) {
        return [];
    }

    $slug = (string) get_post_field('post_name', $page_id);
    $about_page = kepoli_about_page();
    $about_slug = $about_page instanceof WP_Post ? (string) $about_page->post_name : kepoli_profile_slug('about', kepoli_is_english() ? 'about-dr-purg-jr' : 'despre-dr-purg-jr');
    $author_slug = kepoli_profile_slug('author', kepoli_is_english() ? 'about-author' : 'about-author');
    $editorial_slug = kepoli_profile_slug('editorial', kepoli_is_english() ? 'editorial-policy' : 'politica-editoriala');
    $privacy_slug = kepoli_profile_slug('privacy', kepoli_is_english() ? 'privacy-policy' : 'politica-de-confidentialitate');
    $cookies_slug = kepoli_profile_slug('cookies', kepoli_is_english() ? 'cookie-policy' : 'politica-de-cookies');
    $advertising_slug = kepoli_profile_slug('advertising', kepoli_is_english() ? 'advertising-and-consent' : 'publicitate-si-consimtamant');
    $disclaimer_slug = kepoli_profile_slug('disclaimer', kepoli_is_english() ? 'culinary-disclaimer' : 'disclaimer-culinar');
    $terms_slug = kepoli_profile_slug('terms', kepoli_is_english() ? 'terms-and-conditions' : 'termeni-si-conditii');
    $clusters = [
        $about_slug => [$author_slug, $editorial_slug, 'contact', $advertising_slug, $privacy_slug],
        $author_slug => [$about_slug, $editorial_slug, 'contact', $disclaimer_slug],
        'contact' => [$about_slug, $author_slug, $editorial_slug, $privacy_slug, $cookies_slug, $terms_slug],
        $privacy_slug => [$cookies_slug, $advertising_slug, $terms_slug, $editorial_slug, 'contact'],
        $cookies_slug => [$privacy_slug, $advertising_slug, $terms_slug, 'contact'],
        $advertising_slug => [$privacy_slug, $cookies_slug, $editorial_slug, $terms_slug, 'contact'],
        $editorial_slug => [$about_slug, $author_slug, $advertising_slug, $disclaimer_slug, 'contact'],
        $disclaimer_slug => [$editorial_slug, $terms_slug, $author_slug, 'contact'],
        $terms_slug => [$privacy_slug, $cookies_slug, $disclaimer_slug, 'contact'],
    ];

    if (!isset($clusters[$slug])) {
        return [];
    }

    $items = [];
    foreach ($clusters[$slug] as $target_slug) {
        $page = get_page_by_path($target_slug, OBJECT, 'page');
        if (!$page instanceof WP_Post) {
            continue;
        }

        $items[] = [
            'label' => get_the_title($page),
            'url' => get_permalink($page),
        ];
    }

    return $items;
}

function kepoli_attachment_image_url(int $attachment_id, string $size = 'large'): string
{
    if ($attachment_id <= 0) {
        return '';
    }

    $file = get_attached_file($attachment_id, true);
    if (!is_string($file) || $file === '' || !is_readable($file)) {
        return '';
    }

    $url = wp_get_attachment_image_url($attachment_id, $size);
    if (!is_string($url) || $url === '') {
        $url = wp_get_attachment_url($attachment_id);
    }

    return is_string($url) ? kepoli_normalize_media_url($url) : '';
}

function kepoli_post_featured_image_id(int $post_id = 0): int
{
    static $cache = [];

    $post_id = $post_id ?: get_the_ID();
    if (!$post_id) {
        return 0;
    }

    if (isset($cache[$post_id])) {
        return $cache[$post_id];
    }

    $thumbnail_id = (int) get_post_thumbnail_id($post_id);
    if ($thumbnail_id && kepoli_attachment_image_url($thumbnail_id, 'full') !== '') {
        $cache[$post_id] = $thumbnail_id;
        return $cache[$post_id];
    }

    $filename = sanitize_file_name((string) get_post_meta($post_id, '_kepoli_image_plan_filename', true));
    if ($filename !== '') {
        $ids = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_key' => '_kepoli_seed_image_filename',
            'meta_value' => $filename,
        ]);

        if (!empty($ids) && kepoli_attachment_image_url((int) $ids[0], 'full') !== '') {
            $cache[$post_id] = (int) $ids[0];
            return $cache[$post_id];
        }
    }

    $slug = sanitize_title((string) get_post_field('post_name', $post_id));
    if ($slug !== '') {
        $ids = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_key' => '_kepoli_seed_image_slug',
            'meta_value' => $slug,
        ]);

        if (!empty($ids) && kepoli_attachment_image_url((int) $ids[0], 'full') !== '') {
            $cache[$post_id] = (int) $ids[0];
            return $cache[$post_id];
        }
    }

    $attached_images = get_posts([
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'post_parent' => $post_id,
        'post_mime_type' => 'image',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'orderby' => 'ID',
        'order' => 'ASC',
        'no_found_rows' => true,
    ]);

    $cache[$post_id] = !empty($attached_images) && kepoli_attachment_image_url((int) $attached_images[0], 'full') !== ''
        ? (int) $attached_images[0]
        : 0;
    return $cache[$post_id];
}

function kepoli_post_featured_image_url(int $post_id = 0, string $size = 'large'): string
{
    return kepoli_attachment_image_url(kepoli_post_featured_image_id($post_id), $size);
}

function kepoli_post_featured_image_alt(int $post_id = 0): string
{
    $post_id = $post_id ?: get_the_ID();
    if (!$post_id) {
        return '';
    }

    $image_id = kepoli_post_featured_image_id($post_id);
    if ($image_id) {
        $alt = trim((string) get_post_meta($image_id, '_wp_attachment_image_alt', true));
        if ($alt !== '') {
            return $alt;
        }
    }

    $planned_alt = trim((string) get_post_meta($post_id, '_kepoli_image_plan_alt', true));
    if ($planned_alt !== '') {
        return $planned_alt;
    }

    $post_title = trim(wp_strip_all_tags(get_the_title($post_id)));
    if ($post_title !== '') {
        return sprintf(kepoli_ui_text('Imagine pentru %s', 'Image for %s'), $post_title);
    }

    return kepoli_site_name();
}

function kepoli_post_featured_image_caption(int $post_id = 0): string
{
    $image_id = kepoli_post_featured_image_id($post_id);
    if ($image_id) {
        $caption = wp_get_attachment_caption($image_id);
        if (is_string($caption) && $caption !== '') {
            return $caption;
        }
    }

    $post_id = $post_id ?: get_the_ID();
    return $post_id ? trim((string) get_post_meta($post_id, '_kepoli_image_plan_caption', true)) : '';
}

function kepoli_post_featured_image_markup(int $post_id = 0, string $size = 'large', array $attr = []): string
{
    $post_id = $post_id ?: get_the_ID();
    $image_id = kepoli_post_featured_image_id($post_id);
    if (!$image_id) {
        return '';
    }

    $fallback_alt = kepoli_post_featured_image_alt($post_id);
    if ($fallback_alt !== '' && (!array_key_exists('alt', $attr) || trim((string) $attr['alt']) === '')) {
        $attr['alt'] = $fallback_alt;
    }

    $markup = wp_get_attachment_image($image_id, $size, false, $attr);
    if (is_string($markup) && $markup !== '') {
        return $markup;
    }

    $url = kepoli_attachment_image_url($image_id, $size);
    if ($url === '') {
        return '';
    }

    $fallback_alt = isset($attr['alt']) ? (string) $attr['alt'] : $fallback_alt;
    unset($attr['alt'], $attr['src']);
    $attributes = '';
    foreach ($attr as $name => $value) {
        if ($value === false || $value === null || $value === '') {
            continue;
        }
        $attributes .= sprintf(' %s="%s"', esc_attr((string) $name), esc_attr((string) $value));
    }

    return sprintf(
        '<img src="%1$s" alt="%2$s"%3$s>',
        esc_url($url),
        esc_attr($fallback_alt),
        $attributes
    );
}

function kepoli_post_media_image_attrs(string $context = 'card', string $class = 'post-media__image', bool $priority = false): array
{
    $sizes = match ($context) {
        'sidebar' => '(max-width: 640px) 82px, 96px',
        'related' => '(max-width: 640px) 96px, (max-width: 980px) 46vw, 320px',
        default => '(max-width: 640px) 104px, (max-width: 980px) 44vw, 300px',
    };

    $attrs = [
        'class' => $class,
        'loading' => $priority ? 'eager' : 'lazy',
        'decoding' => 'async',
        'sizes' => $sizes,
    ];

    if ($priority) {
        $attrs['fetchpriority'] = 'high';
    }

    return $attrs;
}

function kepoli_post_card_media_markup(int $post_id = 0, string $context = 'card', bool $priority = false): string
{
    $post_id = $post_id ?: get_the_ID();
    $size = match ($context) {
        'related' => 'medium_large',
        'sidebar' => 'thumbnail',
        default => 'medium_large',
    };
    $featured_image = kepoli_post_featured_image_markup($post_id, $size, kepoli_post_media_image_attrs($context, 'post-media__image', $priority));

    if ($featured_image !== '') {
        return sprintf(
            '%1$s<span class="post-media__shade"></span><img class="post-media__mark" src="%2$s" alt="%3$s" loading="lazy" decoding="async">',
            $featured_image,
            esc_url(kepoli_asset_uri(kepoli_icon_asset())),
            esc_attr(kepoli_site_name())
        );
    }

    return kepoli_post_media_markup($post_id, $context, $priority);
}

function kepoli_post_media_mode(int $post_id = 0): string
{
    $post_id = $post_id ?: get_the_ID();

    if (kepoli_post_featured_image_id($post_id)) {
        return 'photo';
    }

    return kepoli_post_kind($post_id) === 'article' ? 'photo' : 'mark';
}

function kepoli_post_media_url(int $post_id = 0, string $size = 'large'): string
{
    $post_id = $post_id ?: get_the_ID();

    $image = kepoli_post_featured_image_url($post_id, $size);
    if ($image !== '') {
        return $image;
    }

    if (kepoli_post_kind($post_id) === 'article') {
        return kepoli_asset_uri('writer-photo', 'svg');
    }

    return kepoli_asset_uri(kepoli_icon_asset());
}

function kepoli_post_media_markup(int $post_id = 0, string $context = 'card', bool $priority = false): string
{
    $post_id = $post_id ?: get_the_ID();
    $mode = kepoli_post_media_mode($post_id);
    $media_class = 'post-media post-media--' . sanitize_html_class($context) . ' post-media--' . sanitize_html_class($mode) . ' ' . kepoli_post_tone_class($post_id);
    $size = $context === 'related' ? 'medium_large' : 'medium_large';
    $image = kepoli_post_media_url($post_id, $size);
    $image_alt = $mode === 'photo' && kepoli_post_featured_image_id($post_id) ? kepoli_post_featured_image_alt($post_id) : '';
    if ($image_alt === '') {
        $image_alt = trim(sprintf('%s - %s', get_the_title($post_id), kepoli_site_name()), ' -');
    }

    if ($mode === 'photo') {
        $featured_image = kepoli_post_featured_image_markup($post_id, $size, kepoli_post_media_image_attrs($context, 'post-media__image', $priority));
        if ($featured_image !== '') {
            return sprintf(
                '<div class="%1$s">%2$s<span class="post-media__shade"></span><img class="post-media__mark" src="%3$s" alt="%4$s" loading="lazy" decoding="async"></div>',
                esc_attr($media_class),
                $featured_image,
                esc_url(kepoli_asset_uri(kepoli_icon_asset())),
                esc_attr(kepoli_site_name())
            );
        }

        $priority_attributes = $priority ? ' loading="eager" fetchpriority="high"' : ' loading="lazy"';
        return sprintf(
            '<div class="%1$s"><img class="post-media__image" src="%2$s" alt="%3$s"%4$s decoding="async"><span class="post-media__shade"></span><img class="post-media__mark" src="%5$s" alt="%6$s" loading="lazy" decoding="async"></div>',
            esc_attr($media_class),
            esc_url($image),
            esc_attr($image_alt),
            $priority_attributes,
            esc_url(kepoli_asset_uri(kepoli_icon_asset())),
            esc_attr(kepoli_site_name())
        );
    }

    return sprintf(
        '<div class="%1$s"><span class="post-media__fill"></span><img class="post-media__icon" src="%2$s" alt="%3$s" loading="lazy" decoding="async"></div>',
        esc_attr($media_class),
        esc_url($image),
        esc_attr($image_alt)
    );
}

function kepoli_related_posts_by_kind(int $post_id = 0, string $kind = 'recipe'): array
{
    $post_id = $post_id ?: get_the_ID();
    $meta_key = $kind === 'article' ? '_kepoli_related_article_slugs' : '_kepoli_related_recipe_slugs';
    $slugs = get_post_meta($post_id, $meta_key, true);
    $slugs = is_array($slugs) ? $slugs : [];

    if (!$slugs) {
        $fallback = get_post_meta($post_id, '_kepoli_related_slugs', true);
        $fallback = is_array($fallback) ? $fallback : [];
        foreach ($fallback as $slug) {
            $candidate = get_page_by_path($slug, OBJECT, 'post');
            if ($candidate && get_post_meta($candidate->ID, '_kepoli_post_kind', true) === $kind) {
                $slugs[] = $slug;
            }
        }
    }

    return kepoli_get_posts_by_slugs(array_slice($slugs, 0, 3));
}

function kepoli_related_card_reason(int $current_post_id = 0, int $related_post_id = 0): string
{
    $current_post_id = $current_post_id ?: get_the_ID();
    $related_post_id = $related_post_id ?: get_the_ID();

    if (!$current_post_id || !$related_post_id || $current_post_id === $related_post_id) {
        return '';
    }

    $current_kind = kepoli_post_kind($current_post_id);
    $related_kind = kepoli_post_kind($related_post_id);
    $current_category = kepoli_primary_category($current_post_id);
    $related_category = kepoli_primary_category($related_post_id);

    if ($current_kind === 'recipe' && $related_kind === 'article') {
        if ($current_category && !kepoli_is_editorial_category_slug($current_category->slug)) {
            return sprintf(
                kepoli_ui_text('Ghid ales pentru ingredientele, pasii si contextul din zona %s.', 'Guide chosen for the ingredients, steps, and context around %s.'),
                $current_category->name
            );
        }

        return kepoli_ui_text('Ghid ales pentru ingredientele si pasii care completeaza reteta aceasta.', 'Guide chosen to support the ingredients and steps in this recipe.');
    }

    if ($current_kind === 'article' && $related_kind === 'recipe') {
        if ($related_category && !kepoli_is_editorial_category_slug($related_category->slug)) {
            return sprintf(
                kepoli_ui_text('Reteta din %s care pune in practica ideile din articol.', 'Recipe from %s that puts the article ideas into practice.'),
                $related_category->name
            );
        }

        return kepoli_ui_text('Reteta aleasa ca sa pui imediat in practica ideile din articol.', 'Recipe chosen so readers can put the article ideas into practice.');
    }

    if ($current_category && $related_category && $current_category->term_id === $related_category->term_id) {
        return sprintf(
            kepoli_ui_text('Din aceeasi zona culinara: %s.', 'From the same cooking area: %s.'),
            $current_category->name
        );
    }

    if ($related_category && !kepoli_is_editorial_category_slug($related_category->slug)) {
        return sprintf(
            kepoli_ui_text('Ales din zona %s pentru un pas firesc mai departe.', 'Chosen from %s as a natural next step.'),
            $related_category->name
        );
    }

    return kepoli_ui_text('Ales editorial pentru a continua lectura intr-un mod util.', 'Editorially chosen as a useful next read.');
}

function kepoli_post_next_steps(int $post_id = 0): array
{
    $post_id = $post_id ?: get_the_ID();
    if (!$post_id) {
        return ['items' => []];
    }

    $is_recipe = kepoli_post_kind($post_id) === 'recipe';
    $category = kepoli_primary_category($post_id);
    $related_posts = kepoli_related_posts_by_kind($post_id, $is_recipe ? 'article' : 'recipe');
    $items = [];
    $seen = [];

    $push_item = static function (string $url, string $eyebrow, string $label, string $meta, string $class = 'tone-default') use (&$items, &$seen): void {
        $url = trim($url);
        if ($url === '' || isset($seen[$url])) {
            return;
        }

        $seen[$url] = true;
        $items[] = [
            'url' => $url,
            'eyebrow' => $eyebrow,
            'label' => $label,
            'meta' => $meta,
            'class' => $class,
        ];
    };

    if ($related_posts) {
        $primary = $related_posts[0];
        $push_item(
            get_permalink($primary),
            $is_recipe ? kepoli_ui_text('Ghid recomandat', 'Recommended guide') : kepoli_ui_text('Reteta de incercat', 'Recipe to try'),
            get_the_title($primary),
            wp_trim_words(get_the_excerpt($primary), 18, '...'),
            kepoli_post_tone_class($primary->ID)
        );
    }

    if ($category && !kepoli_is_editorial_category_slug($category->slug)) {
        $push_item(
            get_category_link($category),
            kepoli_ui_text('Din aceeasi categorie', 'Same category'),
            sprintf(kepoli_ui_text('Mai multe din %s', 'More from %s'), $category->name),
            kepoli_archive_count_label($category),
            kepoli_tone_class($category->slug)
        );
    }

    if ($is_recipe) {
        $push_item(
            kepoli_recipes_page_url(),
            kepoli_ui_text('Rasfoire rapida', 'Quick browsing'),
            kepoli_ui_text('Toate retetele', 'All recipes'),
            kepoli_ui_text('Mergi spre alte retete pentru urmatoarea masa, desert sau garnitura.', 'Browse more recipes for the next meal, dessert, or side.'),
            'tone-mains'
        );
        $push_item(
            kepoli_guides_page_url(),
            kepoli_ui_text('Mai mult context', 'More context'),
            kepoli_ui_text('Articole utile', 'Useful guides'),
            kepoli_ui_text('Ingrediente, tehnici si organizare pentru bucataria de acasa.', 'Ingredients, techniques, and organization for home cooking.'),
            'tone-guides'
        );
    } else {
        $push_item(
            kepoli_recipes_page_url(),
            kepoli_ui_text('Pune in practica', 'Put it into practice'),
            kepoli_ui_text('Retete de incercat', 'Recipes to try'),
            kepoli_ui_text('Retete alese pentru a transforma lectura in ceva concret de pus pe masa.', 'Recipes chosen to turn the reading into something practical at the table.'),
            'tone-mains'
        );
        $push_item(
            kepoli_guides_page_url(),
            kepoli_ui_text('Continua lectura', 'Keep reading'),
            kepoli_ui_text('Mai multe ghiduri', 'More guides'),
            kepoli_ui_text('Mai multe articole despre ingrediente, tehnici si planificare simpla.', 'More articles about ingredients, techniques, and simple planning.'),
            'tone-guides'
        );
    }

    return [
        'eyebrow' => $is_recipe ? kepoli_ui_text('Dupa reteta', 'After the recipe') : kepoli_ui_text('Dupa articol', 'After the article'),
        'title' => $is_recipe ? kepoli_ui_text('Alege urmatorul pas', 'Choose the next step') : kepoli_ui_text('Pune ideile in practica', 'Put the ideas into practice'),
        'description' => $is_recipe
            ? kepoli_ui_text('Continua cu un ghid util, cu mai multe retete din aceeasi zona sau cu o rasfoire mai larga prin site.', 'Continue with a useful guide, more recipes from the same area, or broader browsing across the site.')
            : kepoli_ui_text('Treci direct spre o reteta relevanta, spre mai multe retete sau spre alte ghiduri utile.', 'Go straight to a relevant recipe, more recipes, or another useful guide.'),
        'items' => array_slice($items, 0, 3),
    ];
}

function kepoli_post_updated_label(int $post_id = 0): string
{
    $post_id = $post_id ?: get_the_ID();
    $published = get_post_time('U', true, $post_id);
    $modified = get_post_modified_time('U', true, $post_id);

    if (!$published || !$modified || ($modified - $published) < DAY_IN_SECONDS) {
        return '';
    }

    return sprintf(kepoli_ui_text('Actualizat %s', 'Updated %s'), get_the_modified_date('', $post_id));
}

function kepoli_article_freshness_label(int $post_id = 0): string
{
    $post_id = $post_id ?: get_the_ID();
    $updated = kepoli_post_updated_label($post_id);
    if ($updated !== '') {
        return $updated;
    }

    return sprintf(kepoli_ui_text('Publicat %s', 'Published %s'), get_the_date('', $post_id));
}

function kepoli_article_collection_meta_items(int $count = 0): array
{
    $items = [];

    if ($count > 0) {
        $items[] = sprintf(kepoli_is_english() ? _n('%d guide published', '%d guides published', $count, 'kepoli') : _n('%d ghid publicat', '%d ghiduri publicate', $count, 'kepoli'), $count);
    }

    $items[] = kepoli_ui_text('Ghiduri revizuite periodic cand apar clarificari utile', 'Guides are reviewed when useful clarifications appear');
    $items[] = kepoli_ui_text('Cardurile si paginile arata data de actualizare atunci cand un ghid este revizuit', 'Cards and pages show the update date when a guide is reviewed');

    return $items;
}

function kepoli_article_heading_index(int $post_id = 0): array
{
    $post_id = $post_id ?: get_the_ID();

    if (!$post_id || kepoli_post_kind($post_id) !== 'article') {
        return [];
    }

    $content = (string) get_post_field('post_content', $post_id);
    if ($content === '') {
        return [];
    }

    preg_match_all('/<h2(?:\s[^>]*)?>(.*?)<\/h2>/i', $content, $matches);
    if (empty($matches[1])) {
        return [];
    }

    $headings = [];
    $seen = [];

    foreach ($matches[1] as $heading_html) {
        $label = trim(wp_strip_all_tags($heading_html));
        if ($label === '') {
            continue;
        }

        $base = sanitize_title($label);
        if ($base === '') {
            $base = 'sectiune';
        }

        $id = $base;
        $suffix = 2;
        while (isset($seen[$id])) {
            $id = $base . '-' . $suffix;
            $suffix++;
        }

        $seen[$id] = true;
        $headings[] = [
            'id' => $id,
            'label' => $label,
        ];
    }

    return $headings;
}

function kepoli_share_links(int $post_id = 0): array
{
    $post_id = $post_id ?: get_the_ID();
    $url = get_permalink($post_id);
    $title = html_entity_decode(wp_strip_all_tags(get_the_title($post_id)), ENT_QUOTES, 'UTF-8');
    $text = rawurlencode($title . ' - ' . $url);
    $subject = rawurlencode($title);
    $body = rawurlencode($title . "\n\n" . $url);

    $links = [
        [
            'type' => 'facebook',
            'label' => 'Facebook',
            'url' => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($url),
        ],
        [
            'type' => 'whatsapp',
            'label' => 'WhatsApp',
            'url' => 'https://wa.me/?text=' . $text,
        ],
        [
            'type' => 'email',
            'label' => 'Email',
            'url' => 'mailto:?subject=' . $subject . '&body=' . $body,
        ],
        [
            'type' => 'copy',
            'label' => kepoli_ui_text('Copiaza linkul', 'Copy link'),
            'url' => $url,
        ],
    ];

    if (kepoli_post_kind($post_id) === 'recipe') {
        $links[] = [
            'type' => 'print',
            'label' => kepoli_ui_text('Printeaza', 'Print'),
            'url' => '#print',
        ];
    }

    return $links;
}

function kepoli_published_kind_count(string $kind = ''): int
{
    global $wpdb;

    $meta_key = '_kepoli_post_kind';

    if ($kind === '') {
        return (int) $wpdb->get_var(
            "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish'"
        );
    }

    return (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
            WHERE p.post_type = 'post'
              AND p.post_status = 'publish'
              AND pm.meta_key = %s
              AND pm.meta_value = %s",
            $meta_key,
            $kind
        )
    );
}

function kepoli_latest_post_by_kind(string $kind): ?WP_Post
{
    $cache_key = kepoli_fragment_cache_key('latest_kind', $kind);
    $cached_post_id = (int) get_transient($cache_key);
    if ($cached_post_id > 0) {
        $cached_post = get_post($cached_post_id);
        if ($cached_post instanceof WP_Post) {
            return $cached_post;
        }
    }

    $posts = get_posts([
        'post_type' => 'post',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'ignore_sticky_posts' => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'meta_key' => '_kepoli_post_kind',
        'meta_value' => $kind,
    ]);

    $post_id = $posts ? (int) $posts[0] : 0;
    set_transient($cache_key, $post_id, 12 * HOUR_IN_SECONDS);

    return $post_id > 0 ? get_post($post_id) : null;
}

function kepoli_post_count_by_kind(string $kind): int
{
    static $cache = [];

    if (isset($cache[$kind])) {
        return $cache[$kind];
    }

    $query = new WP_Query([
        'post_type' => 'post',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'no_found_rows' => false,
        'ignore_sticky_posts' => true,
        'meta_key' => '_kepoli_post_kind',
        'meta_value' => $kind,
    ]);

    $cache[$kind] = (int) $query->found_posts;

    return $cache[$kind];
}

function kepoli_recently_touched_posts_by_kind(string $kind, int $limit = 3, array $exclude_ids = []): array
{
    sort($exclude_ids);
    $cache_key = kepoli_fragment_cache_key('recently_touched_kind', $kind . '|' . $limit . '|' . implode(',', array_map('intval', $exclude_ids)));
    $cached_ids = get_transient($cache_key);
    if (is_array($cached_ids)) {
        return array_values(array_filter(array_map('get_post', array_map('intval', $cached_ids)), static function ($post): bool {
            return $post instanceof WP_Post;
        }));
    }

    $posts = get_posts([
        'post_type' => 'post',
        'posts_per_page' => $limit,
        'fields' => 'ids',
        'no_found_rows' => true,
        'ignore_sticky_posts' => true,
        'post__not_in' => array_map('intval', $exclude_ids),
        'orderby' => 'modified',
        'order' => 'DESC',
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'meta_key' => '_kepoli_post_kind',
        'meta_value' => $kind,
    ]);

    $post_ids = array_map('intval', $posts);
    set_transient($cache_key, $post_ids, 12 * HOUR_IN_SECONDS);

    return array_values(array_filter(array_map('get_post', $post_ids), static function ($post): bool {
        return $post instanceof WP_Post;
    }));
}

function kepoli_setup(): void
{
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('custom-logo', ['height' => 120, 'width' => 320, 'flex-height' => true, 'flex-width' => true]);
    add_theme_support('responsive-embeds');

    register_nav_menus([
        'primary' => __('Navigatie principala', 'kepoli'),
        'footer' => __('Navigatie footer', 'kepoli'),
    ]);
}
add_action('after_setup_theme', 'kepoli_setup');

function kepoli_trim_wordpress_frontend_output(): void
{
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_action('wp_head', 'wp_generator');
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'wp_shortlink_wp_head');
    remove_action('wp_head', 'rel_canonical');
    remove_action('wp_head', 'rest_output_link_wp_head');
    remove_action('wp_head', 'wp_oembed_add_discovery_links');
    remove_action('wp_head', 'wp_oembed_add_host_js');
    remove_action('template_redirect', 'rest_output_link_header', 11);
    add_filter('emoji_svg_url', '__return_false');
    add_filter('show_recent_comments_widget_style', '__return_false');
}
add_action('after_setup_theme', 'kepoli_trim_wordpress_frontend_output');

function kepoli_redirect_attachment_pages(): void
{
    if (!is_attachment()) {
        return;
    }

    $attachment = get_post();
    if (!$attachment instanceof WP_Post) {
        return;
    }

    $target = '';

    if ((int) $attachment->post_parent > 0) {
        $parent_permalink = get_permalink((int) $attachment->post_parent);
        if (is_string($parent_permalink) && $parent_permalink !== '') {
            $target = $parent_permalink;
        }
    }

    if ($target === '') {
        $attachment_url = wp_get_attachment_url($attachment->ID);
        if (is_string($attachment_url) && $attachment_url !== '') {
            $target = $attachment_url;
        }
    }

    if ($target === '' || $target === get_permalink($attachment)) {
        return;
    }

    wp_safe_redirect($target, 301, kepoli_site_name());
    exit;
}
add_action('template_redirect', 'kepoli_redirect_attachment_pages', 1);

function kepoli_redirect_author_archives(): void
{
    if (!is_author()) {
        return;
    }

    $target = kepoli_author_page_url();
    if ($target === '') {
        return;
    }

    wp_safe_redirect($target, 301, kepoli_site_name());
    exit;
}
add_action('template_redirect', 'kepoli_redirect_author_archives', 2);

function kepoli_prelander_request_slug(): string
{
    $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash((string) $_SERVER['REQUEST_URI']) : '';
    $path = wp_parse_url($request_uri, PHP_URL_PATH);
    $path = is_string($path) ? trim($path, '/') : '';

    if (!preg_match('#^prelander/([^/]+)/?$#', $path, $matches)) {
        return '';
    }

    return sanitize_title($matches[1]);
}

function kepoli_prelander_post_from_request(): ?WP_Post
{
    $slug = kepoli_prelander_request_slug();
    if ($slug === '') {
        return null;
    }

    $post = get_page_by_path($slug, OBJECT, 'post');
    if (!$post instanceof WP_Post || $post->post_status !== 'publish') {
        return null;
    }

    return $post;
}

function kepoli_prelander_target_url(WP_Post $post): string
{
    $target = get_permalink($post);
    if (!is_string($target) || $target === '') {
        $target = home_url('/');
    }

    $allowed_params = [];
    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $key) {
        if (isset($_GET[$key])) {
            $value = sanitize_text_field(wp_unslash((string) $_GET[$key]));
            if ($value !== '') {
                $allowed_params[$key] = $value;
            }
        }
    }

    return $allowed_params ? add_query_arg($allowed_params, $target) : $target;
}

function kepoli_render_prelander(WP_Post $post): void
{
    status_header(200);
    $GLOBALS['post'] = $post;
    setup_postdata($post);

    $target_url = kepoli_prelander_target_url($post);
    $featured_image = kepoli_post_featured_image_markup($post->ID, 'large', [
        'class' => 'prelander-card__image',
        'loading' => 'eager',
        'fetchpriority' => 'high',
        'decoding' => 'async',
        'sizes' => '(max-width: 760px) 100vw, 760px',
    ]);
    $teaser = has_excerpt($post) ? get_the_excerpt($post) : wp_trim_words(wp_strip_all_tags($post->post_content), 34, '...');

    get_header();
    ?>
    <main id="primary" class="prelander-layout" data-kepoli-prelander>
        <article class="prelander-card">
            <p class="eyebrow"><?php echo esc_html(kepoli_ui_text('Reteta completa', 'Full recipe')); ?></p>
            <h1><?php echo esc_html(get_the_title($post)); ?></h1>
            <?php if ($featured_image !== '') : ?>
                <figure class="prelander-card__media">
                    <?php echo $featured_image; ?>
                </figure>
            <?php endif; ?>
            <p class="prelander-card__teaser"><?php echo esc_html($teaser); ?></p>
            <a class="button prelander-card__cta" href="<?php echo esc_url($target_url); ?>" data-prelander-cta data-prelander-target="<?php echo esc_attr(get_post_field('post_name', $post)); ?>">
                <?php echo esc_html(kepoli_ui_text('Vezi reteta completa', 'See the full recipe')); ?>
            </a>
            <p class="prelander-card__note"><?php echo esc_html(kepoli_ui_text('Ingrediente, pasi, sfaturi si idei de servire pe pagina retetei.', 'Ingredients, steps, tips, and serving ideas are on the full recipe page.')); ?></p>
        </article>
    </main>
    <script>
    window.dataLayer = window.dataLayer || [];
    document.querySelectorAll('[data-prelander-cta]').forEach(function (link) {
      link.addEventListener('click', function () {
        window.dataLayer.push({
          event: 'prelander_cta_click',
          target: link.getAttribute('data-prelander-target') || ''
        });
      });
    });
    </script>
    <?php
    get_footer();
    wp_reset_postdata();
}

function kepoli_maybe_render_prelander(): void
{
    if (kepoli_prelander_request_slug() === '') {
        return;
    }

    if (!kepoli_prelander_enabled()) {
        status_header(404);
        include get_404_template();
        exit;
    }

    $post = kepoli_prelander_post_from_request();
    if (!$post instanceof WP_Post) {
        status_header(404);
        include get_404_template();
        exit;
    }

    kepoli_render_prelander($post);
    exit;
}
add_action('template_redirect', 'kepoli_maybe_render_prelander', 3);

function kepoli_scripts(): void
{
    $style_path = get_template_directory() . '/style.min.css';
    $style_uri = get_template_directory_uri() . '/style.min.css';
    if (!file_exists($style_path)) {
        $style_path = get_stylesheet_directory() . '/style.css';
        $style_uri = get_stylesheet_uri();
    }
    wp_enqueue_style('kepoli-style', $style_uri, [], (string) filemtime($style_path));

    $global_script = get_template_directory() . '/assets/js/site.min.js';
    $global_script_uri = get_template_directory_uri() . '/assets/js/site.min.js';
    if (!file_exists($global_script)) {
        $global_script = get_template_directory() . '/assets/js/site.js';
        $global_script_uri = get_template_directory_uri() . '/assets/js/site.js';
    }

    if (file_exists($global_script)) {
        wp_enqueue_script(
            'kepoli-site',
            $global_script_uri,
            [],
            (string) filemtime($global_script),
            true
        );
        wp_script_add_data('kepoli-site', 'strategy', 'defer');
    }

    if (is_singular('post')) {
        $article_script = get_template_directory() . '/assets/js/article.min.js';
        $article_script_uri = get_template_directory_uri() . '/assets/js/article.min.js';
        if (!file_exists($article_script)) {
            $article_script = get_template_directory() . '/assets/js/article.js';
            $article_script_uri = get_template_directory_uri() . '/assets/js/article.js';
        }

        if (file_exists($article_script)) {
            wp_enqueue_script(
                'kepoli-article',
                $article_script_uri,
                [],
                (string) filemtime($article_script),
                true
            );
            wp_add_inline_script(
                'kepoli-article',
                'window.kepoliAdStrategy = ' . wp_json_encode([
                    'mode' => kepoli_ad_mode(),
                    'onclickHtml' => kepoli_monetag_action_onclick_code(),
                    'onclickCooldownMinutes' => max(30, kepoli_monetag_frequency_minutes('onclick')),
                    'avoidVignetteMinutes' => 2,
                    'minActionSeconds' => kepoli_action_ad_min_seconds(),
                    'minActionScroll' => kepoli_action_ad_min_scroll(),
                ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) . ';',
                'before'
            );
            wp_script_add_data('kepoli-article', 'strategy', 'defer');
        }
    }
}
add_action('wp_enqueue_scripts', 'kepoli_scripts');

function kepoli_dequeue_unused_frontend_assets(): void
{
    if (is_admin()) {
        return;
    }

    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('global-styles');
    wp_dequeue_style('classic-theme-styles');

    if (!is_user_logged_in()) {
        wp_deregister_style('dashicons');
    }
}
add_action('wp_enqueue_scripts', 'kepoli_dequeue_unused_frontend_assets', 20);

function kepoli_resource_hints(array $urls, string $relation_type): array
{
    $hints = [];

    if (kepoli_ga_enabled() && kepoli_env('GA_MEASUREMENT_ID') !== '') {
        $hints[] = 'https://www.googletagmanager.com';
    }

    if (kepoli_ads_enabled() && kepoli_env('ADSENSE_CLIENT_ID') !== '') {
        $hints[] = 'https://pagead2.googlesyndication.com';
        $hints[] = 'https://googleads.g.doubleclick.net';
    }

    if ($relation_type === 'dns-prefetch' || $relation_type === 'preconnect') {
        $urls = array_merge($urls, $hints);
    }

    return array_values(array_unique($urls));
}
add_filter('wp_resource_hints', 'kepoli_resource_hints', 10, 2);

function kepoli_register_sidebars(): void
{
    register_sidebar([
        'name' => __('Bara laterala reteta', 'kepoli'),
        'id' => 'recipe-sidebar',
        'before_widget' => '<section class="sidebar-section widget %2$s">',
        'after_widget' => '</section>',
        'before_title' => '<h2>',
        'after_title' => '</h2>',
    ]);
}
add_action('widgets_init', 'kepoli_register_sidebars');

function kepoli_robots_content(): string
{
    if (kepoli_prelander_request_slug() !== '') {
        return 'noindex,follow,max-image-preview:large';
    }

    if (is_search() || is_404()) {
        return 'noindex,follow,max-image-preview:large';
    }

    return 'index,follow,max-image-preview:large';
}

function kepoli_meta_description(): void
{
    $description = kepoli_current_description();
    $canonical_url = kepoli_current_url();
    $language = kepoli_language_tag();

    if ($description !== '') {
        printf("<meta name=\"description\" content=\"%s\">\n", esc_attr(kepoli_trim_meta_text($description, 28)));
    }

    printf("<meta name=\"robots\" content=\"%s\">\n", esc_attr(kepoli_robots_content()));
    printf("<link rel=\"canonical\" href=\"%s\">\n", esc_url($canonical_url));
    printf("<link rel=\"alternate\" hreflang=\"%s\" href=\"%s\">\n", esc_attr($language), esc_url($canonical_url));
    printf("<meta name=\"theme-color\" content=\"#252416\">\n");
    printf("<link rel=\"manifest\" href=\"%s\">\n", esc_url(home_url('/site.webmanifest')));

    if (is_singular('post')) {
        printf("<meta name=\"author\" content=\"%s\">\n", esc_attr(kepoli_post_author_name()));
    }

    $verification = kepoli_env('SEARCH_CONSOLE_VERIFICATION');
    if ($verification !== '') {
        printf("<meta name=\"google-site-verification\" content=\"%s\">\n", esc_attr($verification));
    }

    printf("<link rel=\"icon\" href=\"%s\" type=\"image/svg+xml\">\n", esc_url(kepoli_asset_uri(kepoli_icon_asset())));
}
add_action('wp_head', 'kepoli_meta_description', 2);

function kepoli_priority_image_preloads(): void
{
    if (is_front_page()) {
        $hero_sources = kepoli_home_hero_sources();
        $hero_srcset = kepoli_home_hero_srcset();
        $hero_href = $hero_sources !== [] ? $hero_sources[0]['url'] : kepoli_asset_uri('hero-homepage', 'jpg');
        $hero_sizes = '(max-width: 640px) 100vw, (max-width: 1024px) 92vw, 1536px';

        printf(
            "<link rel=\"preload\" as=\"image\" href=\"%1\$s\"%2\$s imagesizes=\"%3\$s\" fetchpriority=\"high\">\n",
            esc_url($hero_href),
            $hero_srcset !== '' ? ' imagesrcset="' . esc_attr($hero_srcset) . '"' : '',
            esc_attr($hero_sizes)
        );
        return;
    }

    if (!is_singular('post')) {
        return;
    }

    $image_id = kepoli_post_featured_image_id(get_the_ID());
    if (!$image_id) {
        return;
    }

    $href = wp_get_attachment_image_url($image_id, 'large');
    if (!is_string($href) || $href === '') {
        return;
    }

    $srcset = wp_get_attachment_image_srcset($image_id, 'large');
    $sizes = '(max-width: 760px) 100vw, 760px';

    printf(
        "<link rel=\"preload\" as=\"image\" href=\"%1\$s\"%2\$s imagesizes=\"%3\$s\" fetchpriority=\"high\">\n",
        esc_url($href),
        is_string($srcset) && $srcset !== '' ? ' imagesrcset="' . esc_attr($srcset) . '"' : '',
        esc_attr($sizes)
    );
}
add_action('wp_head', 'kepoli_priority_image_preloads', 1);

function kepoli_social_meta(): void
{
    $title = kepoli_current_seo_title();
    $description = kepoli_trim_meta_text(kepoli_current_description(), 28);
    $url = kepoli_current_url();
    $type = is_singular('post') ? 'article' : 'website';
    $image = kepoli_social_image_url();
    $image_alt = kepoli_social_image_alt();
    [$image_width, $image_height] = array_pad(kepoli_social_image_dimensions(), 2, 0);

    printf("<meta property=\"og:locale\" content=\"%s\">\n", esc_attr(kepoli_og_locale()));
    printf("<meta property=\"og:site_name\" content=\"%s\">\n", esc_attr(get_bloginfo('name')));
    printf("<meta property=\"og:title\" content=\"%s\">\n", esc_attr($title));
    printf("<meta property=\"og:description\" content=\"%s\">\n", esc_attr($description));
    printf("<meta property=\"og:url\" content=\"%s\">\n", esc_url($url));
    printf("<meta property=\"og:type\" content=\"%s\">\n", esc_attr($type));
    printf("<meta property=\"og:image\" content=\"%s\">\n", esc_url($image));
    printf("<meta property=\"og:image:alt\" content=\"%s\">\n", esc_attr($image_alt));
    if ($image_width > 0 && $image_height > 0) {
        printf("<meta property=\"og:image:width\" content=\"%d\">\n", $image_width);
        printf("<meta property=\"og:image:height\" content=\"%d\">\n", $image_height);
    }
    printf("<meta name=\"twitter:card\" content=\"summary_large_image\">\n");
    printf("<meta name=\"twitter:title\" content=\"%s\">\n", esc_attr($title));
    printf("<meta name=\"twitter:description\" content=\"%s\">\n", esc_attr($description));
    printf("<meta name=\"twitter:image\" content=\"%s\">\n", esc_url($image));
    printf("<meta name=\"twitter:image:alt\" content=\"%s\">\n", esc_attr($image_alt));

    if (is_singular('post')) {
        printf("<meta property=\"article:published_time\" content=\"%s\">\n", esc_attr(get_the_date('c')));
        printf("<meta property=\"article:modified_time\" content=\"%s\">\n", esc_attr(get_the_modified_date('c')));
        printf("<meta property=\"article:author\" content=\"%s\">\n", esc_attr(kepoli_post_author_name()));
        printf("<meta property=\"article:publisher\" content=\"%s\">\n", esc_url(home_url('/')));

        $category = kepoli_primary_category();
        if ($category) {
            printf("<meta property=\"article:section\" content=\"%s\">\n", esc_attr($category->name));
        }

        foreach (kepoli_clean_post_tag_names(get_the_ID()) as $tag) {
            printf("<meta property=\"article:tag\" content=\"%s\">\n", esc_attr($tag));
        }
    }
}
add_action('wp_head', 'kepoli_social_meta', 3);

function kepoli_code_seed_version(): string
{
    static $version = null;

    if ($version !== null) {
        return $version;
    }

    if (!function_exists('kepoli_seed_target_version') && file_exists('/seed/version.php')) {
        require_once '/seed/version.php';
    }

    $version = function_exists('kepoli_seed_target_version')
        ? (string) kepoli_seed_target_version()
        : '';

    return $version;
}

function kepoli_deploy_fingerprint_meta(): void
{
    if (!kepoli_env_bool('KEPOLI_DEPLOY_FINGERPRINT')) {
        return;
    }

    $target_version = kepoli_code_seed_version();
    $current_version = (string) get_option('kepoli_seed_version', '');

    if ($target_version !== '') {
        printf("<meta name=\"kepoli-seed-target\" content=\"%s\">\n", esc_attr($target_version));
    }

    if ($current_version !== '') {
        printf("<meta name=\"kepoli-seed-current\" content=\"%s\">\n", esc_attr($current_version));
    }
}
add_action('wp_head', 'kepoli_deploy_fingerprint_meta', 4);

function kepoli_adsense_head(): void
{
    $client = kepoli_env('ADSENSE_CLIENT_ID');
    if ($client === '' || !kepoli_ads_enabled()) {
        return;
    }

    printf(
        "<script async src=\"https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=%s\" crossorigin=\"anonymous\"></script>\n",
        esc_attr($client)
    );
}
add_action('wp_head', 'kepoli_adsense_head', 8);

function kepoli_ga_head(): void
{
    $measurement_id = kepoli_env('GA_MEASUREMENT_ID');
    if ($measurement_id === '' || !kepoli_ga_enabled()) {
        return;
    }
    ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($measurement_id); ?>"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', '<?php echo esc_js($measurement_id); ?>');
    </script>
    <?php
}
add_action('wp_head', 'kepoli_ga_head', 9);

function kepoli_histats_should_render(): bool
{
    if (!kepoli_histats_enabled()) {
        return false;
    }

    if (is_admin() || wp_doing_ajax() || is_feed()) {
        return false;
    }

    if (kepoli_env_bool('HISTATS_EXCLUDE_ADMINS', true) && is_user_logged_in() && current_user_can('manage_options')) {
        return false;
    }

    return true;
}

function kepoli_histats_footer(): void
{
    if (!kepoli_histats_should_render()) {
        return;
    }

    $encoded = kepoli_env('HISTATS_CODE_BASE64');
    if ($encoded === '') {
        return;
    }

    $decoded = base64_decode($encoded, true);
    if (!is_string($decoded)) {
        return;
    }

    $code = trim($decoded);
    if ($code === '') {
        return;
    }

    echo "\n<!-- Histats analytics -->\n" . $code . "\n";
}
add_action('wp_footer', 'kepoli_histats_footer', 90);

function kepoli_monetag_should_render(): bool
{
    if (!kepoli_monetag_enabled()) {
        return false;
    }

    if (is_admin() || wp_doing_ajax() || is_feed() || is_search() || is_404()) {
        return false;
    }

    if (is_user_logged_in() && current_user_can('manage_options')) {
        return false;
    }

    if (is_front_page() || is_home()) {
        return kepoli_monetag_install_check_enabled();
    }

    if (kepoli_env_bool('MONETAG_POST_ONLY', true)) {
        return is_singular('post');
    }

    return is_singular('post');
}

function kepoli_monetag_head(): void
{
    if (!kepoli_monetag_should_render()) {
        return;
    }

    foreach (kepoli_monetag_snippets() as $format => $code) {
        printf(
            "<!-- Monetag %s%s -->\n",
            esc_html($format),
            kepoli_monetag_frequency_minutes($format) > 0 ? ' frequency gated' : ''
        );
        kepoli_monetag_render_snippet($format, $code);
    }

}
add_action('wp_head', 'kepoli_monetag_head', 11);

function kepoli_newsletter_cta(string $class = ''): string
{
    $classes = trim('newsletter-cta ' . $class);
    $email_field_id = 'newsletter-email-' . wp_generate_uuid4();
    $honeypot_id = 'newsletter-website-' . wp_generate_uuid4();
    $current_url = kepoli_current_url();
    $contact_email = kepoli_public_contact_email();
    $source_label = is_front_page()
        ? sprintf(kepoli_ui_text('Prima pagina %s', '%s front page'), kepoli_site_name())
        : wp_strip_all_tags(get_the_title() ?: wp_get_document_title());

    $status = isset($_GET['newsletter']) ? sanitize_key((string) wp_unslash($_GET['newsletter'])) : '';
    $notice_message = '';
    $notice_class = '';

    if ($status === 'success') {
        $notice_message = sprintf(kepoli_ui_text('Multumim. Te-am trecut pe lista newsletterului %s.', 'Thank you. You are now on the %s newsletter list.'), kepoli_site_name());
        $notice_class = 'newsletter-cta__notice newsletter-cta__notice--success';
    } elseif ($status === 'duplicate') {
        $notice_message = kepoli_ui_text('Adresa aceasta este deja inscrisa.', 'This address is already subscribed.');
        $notice_class = 'newsletter-cta__notice newsletter-cta__notice--success';
    } elseif ($status === 'busy') {
        $notice_message = kepoli_ui_text('Ai trimis prea multe incercari intr-un timp scurt. Mai incearca peste cateva minute.', 'Too many attempts in a short time. Please try again in a few minutes.');
        $notice_class = 'newsletter-cta__notice newsletter-cta__notice--error';
    } elseif ($status === 'invalid') {
        $notice_message = kepoli_ui_text('Te rog verifica adresa de email si incearca din nou.', 'Please check the email address and try again.');
        $notice_class = 'newsletter-cta__notice newsletter-cta__notice--error';
    } elseif ($status === 'error') {
        $notice_message = kepoli_ui_text('Nu am putut salva inscrierea acum. Mai incearca o data.', 'The signup could not be saved right now. Please try again.');
        $notice_class = 'newsletter-cta__notice newsletter-cta__notice--error';
    }

    $notice_markup = '';
    if ($notice_message !== '') {
        $notice_markup = sprintf(
            '<p class="%1$s" role="%2$s">%3$s</p>',
            esc_attr($notice_class),
            $status === 'success' || $status === 'duplicate' ? 'status' : 'alert',
            esc_html($notice_message)
        );
    }

    return sprintf(
        '<section class="%1$s" aria-labelledby="newsletter-title"><div class="newsletter-cta__inner"><p class="eyebrow">%2$s</p><h2 id="newsletter-title" class="newsletter-cta__title">%3$s</h2><p class="newsletter-cta__copy">%4$s</p>%5$s<form class="newsletter-cta__form" action="%6$s" method="post"><input type="hidden" name="action" value="kepoli_newsletter_signup"><input type="hidden" name="redirect_to" value="%7$s"><input type="hidden" name="source_label" value="%8$s"><input type="hidden" name="source_url" value="%7$s">%9$s<label class="screen-reader-text" for="%10$s">%11$s</label><input class="newsletter-cta__input" id="%10$s" name="newsletter_email" type="email" inputmode="email" autocomplete="email" placeholder="%12$s" maxlength="190" required><button class="newsletter-cta__submit" type="submit">%13$s</button><div class="newsletter-cta__honeypot" aria-hidden="true"><label for="%14$s">%15$s</label><input id="%14$s" name="website" type="text" tabindex="-1" autocomplete="off"></div></form><p class="newsletter-cta__fine-print">%16$s <a href="mailto:%17$s">%17$s</a>.</p></div></section>',
        esc_attr($classes),
        esc_html(kepoli_ui_text('Newsletter', 'Newsletter')),
        esc_html(kepoli_ui_text('Primeste articolele noi pe email', 'Get new health facts by email')),
        esc_html(kepoli_ui_text('Trimitem un mesaj scurt cand publicam ceva util: fapte noi, ghiduri practice si actualizari importante.', 'We send a short message when something useful is published: new facts, practical guides, and important updates.')),
        $notice_markup,
        esc_url(admin_url('admin-post.php')),
        esc_url($current_url),
        esc_attr($source_label),
        wp_nonce_field('kepoli_newsletter_signup', 'kepoli_newsletter_nonce', true, false),
        esc_attr($email_field_id),
        esc_html(kepoli_ui_text('Adresa de email', 'Email address')),
        esc_attr(kepoli_ui_text('Adresa ta de email', 'Your email address')),
        esc_html(kepoli_ui_text('Aboneaza-ma', 'Subscribe')),
        esc_attr($honeypot_id),
        esc_html(kepoli_ui_text('Lasa gol acest camp', 'Leave this field empty')),
        esc_html(kepoli_ui_text('Pentru retragere sau corecturi, ne poti scrie la', 'For unsubscribe requests or corrections, write to us at')),
        esc_attr($contact_email)
    );
}

function kepoli_ad_slot(string $slot, string $class = ''): string
{
    $client = kepoli_env('ADSENSE_CLIENT_ID');
    $slot_key = 'ADSENSE_SLOT_' . strtoupper($slot);
    $slot_id = kepoli_env($slot_key);
    $classes = trim('ad-slot ad-slot--' . sanitize_html_class(str_replace('_', '-', $slot)) . ' ' . $class);

    if (kepoli_ads_enabled() && $client !== '' && $slot_id !== '') {
        return sprintf(
            '<div class="%1$s"><ins class="adsbygoogle" style="display:block" data-ad-client="%2$s" data-ad-slot="%3$s" data-ad-format="auto" data-full-width-responsive="true"></ins><script>(adsbygoogle = window.adsbygoogle || []).push({});</script></div>',
            esc_attr($classes . ' ad-slot--live'),
            esc_attr($client),
            esc_attr($slot_id)
        );
    }

    return kepoli_display_ad_slot($slot, $classes);
}

function kepoli_ad_shortcode(array $atts): string
{
    $atts = shortcode_atts(['slot' => 'mid_content'], $atts, 'kepoli_ad');
    return kepoli_ad_slot((string) $atts['slot']);
}
add_shortcode('kepoli_ad', 'kepoli_ad_shortcode');

function kepoli_display_ads_should_render(bool $allow_listing = false): bool
{
    if (!kepoli_display_ads_enabled()) {
        return false;
    }

    if (is_admin() || wp_doing_ajax() || is_feed() || is_search() || is_404()) {
        return false;
    }

    if (is_user_logged_in() && current_user_can('manage_options')) {
        return false;
    }

    if (is_singular('post')) {
        return true;
    }

    if (!$allow_listing) {
        return false;
    }

    if (is_front_page() || is_home() || is_archive()) {
        return true;
    }

    return is_page([kepoli_profile_slug('recipes'), kepoli_profile_slug('guides'), kepoli_profile_slug('author')]);
}

function kepoli_display_ad_code(string $slot): string
{
    $slot_key = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '_', $slot));
    $encoded = kepoli_env('DISPLAY_AD_' . $slot_key . '_BASE64');
    if ($encoded === '') {
        return '';
    }

    $decoded = base64_decode($encoded, true);
    if (!is_string($decoded)) {
        return '';
    }

    return trim($decoded);
}

function kepoli_display_ad_slot(string $slot, string $classes = '', bool $allow_listing = false): string
{
    if (!kepoli_display_ads_should_render($allow_listing)) {
        return '';
    }

    $ad_code = kepoli_display_ad_code($slot);
    if ($ad_code === '') {
        return '';
    }

    $provider = sanitize_key(kepoli_env('DISPLAY_ADS_PROVIDER', 'display'));
    $provider = $provider !== '' ? $provider : 'display';
    $label = kepoli_is_english() ? 'Advertisement' : 'Publicitate';

    return sprintf(
        '<aside class="%1$s" data-display-ad-slot="%2$s" data-display-ad-provider="%3$s" aria-label="%4$s"><span class="ad-slot__label">%4$s</span><div class="ad-slot__creative">%5$s</div></aside>',
        esc_attr(trim($classes . ' ad-slot--display ad-slot--live')),
        esc_attr($slot),
        esc_attr($provider),
        esc_attr($label),
        $ad_code
    );
}

function kepoli_display_sticky_bottom_ad(): void
{
    $debug = isset($_GET['kt_sticky_debug']) && sanitize_text_field(wp_unslash($_GET['kt_sticky_debug'])) === '1';

    if (!is_singular('post')) {
        return;
    }

    if (!$debug && !kepoli_display_ads_should_render(false)) {
        return;
    }

    $ad_code = kepoli_display_ad_code('sticky_bottom');
    if ($ad_code === '') {
        return;
    }

    $label = kepoli_is_english() ? 'Advertisement' : 'Publicitate';
    $settings = [
        'html' => $ad_code,
        'debug' => $debug,
        'minSeconds' => $debug ? 0 : kepoli_env_int('DISPLAY_AD_STICKY_BOTTOM_MIN_SECONDS', 35, 0, 600),
        'minScroll' => $debug ? 0 : kepoli_env_int('DISPLAY_AD_STICKY_BOTTOM_MIN_SCROLL', 30, 0, 100),
        'cooldownMinutes' => $debug ? 0 : kepoli_env_int('DISPLAY_AD_STICKY_BOTTOM_COOLDOWN_MINUTES', 30, 0, 10080),
        'storageKey' => 'kepoli_display_sticky_bottom_closed',
    ];
    ?>
    <aside class="ad-slot ad-slot--display ad-slot--live ad-slot--sticky-bottom" data-sticky-bottom-ad<?php echo $debug ? ' data-sticky-bottom-ad-debug="1"' : ''; ?> hidden aria-label="<?php echo esc_attr($label); ?>">
        <button class="ad-slot__close" type="button" data-sticky-bottom-ad-close aria-label="<?php echo esc_attr(kepoli_ui_text('Inchide reclama', 'Close advertisement')); ?>">&times;</button>
        <span class="ad-slot__label"><?php echo esc_html($label); ?></span>
        <div class="ad-slot__creative" data-sticky-bottom-ad-creative></div>
    </aside>
    <script>
    (function () {
      var config = <?php echo wp_json_encode($settings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
      var slot = document.querySelector('[data-sticky-bottom-ad]');
      var creative = document.querySelector('[data-sticky-bottom-ad-creative]');
      var close = document.querySelector('[data-sticky-bottom-ad-close]');
      if (!slot || !creative || !config.html) {
        return;
      }

      if (!config.debug && !window.matchMedia('(max-width: 780px)').matches) {
        return;
      }

      var startedAt = Date.now();
      var loaded = false;
      var storageKey = String(config.storageKey || 'kepoli_display_sticky_bottom_closed');
      var cooldownMs = Math.max(0, Number(config.cooldownMinutes || 0)) * 60000;

      if (!config.debug) {
        try {
          var lastClosed = parseInt(window.localStorage.getItem(storageKey) || '0', 10);
          if (lastClosed && cooldownMs > 0 && Date.now() - lastClosed < cooldownMs) {
            return;
          }
        } catch (error) {}
      }

      function scrollPercent() {
        var doc = document.documentElement;
        var max = Math.max(1, (doc.scrollHeight || 0) - window.innerHeight);
        return Math.max(0, Math.min(100, Math.round(((window.scrollY || doc.scrollTop || 0) / max) * 100)));
      }

      function appendAdHtml() {
        var holder = document.createElement('div');
        holder.innerHTML = config.html;
        Array.prototype.slice.call(holder.childNodes).forEach(function (node) {
          if (node.nodeName && node.nodeName.toLowerCase() === 'script') {
            var script = document.createElement('script');
            Array.prototype.slice.call(node.attributes || []).forEach(function (attr) {
              script.setAttribute(attr.name, attr.value);
            });
            if (node.text) {
              script.text = node.text;
            }
            creative.appendChild(script);
            return;
          }

          creative.appendChild(node.cloneNode(true));
        });
      }

      function maybeShow() {
        if (loaded) {
          return;
        }

        var waitedEnough = Date.now() - startedAt >= Math.max(0, Number(config.minSeconds || 0)) * 1000;
        var scrolledEnough = scrollPercent() >= Math.max(0, Number(config.minScroll || 0));
        if (!waitedEnough || !scrolledEnough) {
          return;
        }

        loaded = true;
        appendAdHtml();
        slot.hidden = false;
        slot.classList.add('is-visible');
        if (window.dataLayer && Array.isArray(window.dataLayer)) {
          window.dataLayer.push({ event: 'display_ad_sticky_bottom_show' });
        }
      }

      if (close) {
        close.addEventListener('click', function () {
          slot.hidden = true;
          slot.classList.remove('is-visible');
          try {
            window.localStorage.setItem(storageKey, String(Date.now()));
          } catch (error) {}
          if (window.dataLayer && Array.isArray(window.dataLayer)) {
            window.dataLayer.push({ event: 'display_ad_sticky_bottom_close' });
          }
        });
      }

      window.addEventListener('scroll', maybeShow, { passive: true });
      window.addEventListener('resize', maybeShow);
      window.setTimeout(maybeShow, Math.max(0, Number(config.minSeconds || 0)) * 1000 + 250);
      maybeShow();
    }());
    </script>
    <?php
}
add_action('wp_footer', 'kepoli_display_sticky_bottom_ad', 20);

function kepoli_display_card_grid_ad(): string
{
    return kepoli_display_ad_slot('card_grid', 'ad-slot--card-grid', true);
}

function kepoli_post_part_count(): int
{
    global $numpages;
    return max(1, (int) $numpages);
}

function kepoli_current_post_part(): int
{
    global $page;
    return max(1, (int) $page);
}

function kepoli_is_multipart_post(): bool
{
    global $multipage;
    return !empty($multipage) && kepoli_post_part_count() > 1;
}

function kepoli_post_part_anchor(int $part, string $label, string $class = ''): string
{
    if (!function_exists('_wp_link_page')) {
        return '';
    }

    $open = _wp_link_page($part);
    if (!is_string($open) || $open === '') {
        return '';
    }

    $class = trim($class);
    if ($class !== '') {
        $open = (string) preg_replace('/^<a\b/', '<a class="' . esc_attr($class) . '"', $open, 1);
    }

    return $open . esc_html($label) . '</a>';
}

function kepoli_multipart_page_links(): string
{
    if (!kepoli_is_multipart_post()) {
        return '';
    }

    $current = kepoli_current_post_part();
    $total = kepoli_post_part_count();
    $items = [];

    for ($part = 1; $part <= $total; $part++) {
        $label = sprintf(kepoli_ui_text('Partea %d', 'Part %d'), $part);
        if ($part === $current) {
            $items[] = '<span class="post-page-links__item post-page-links__item--current" aria-current="page">' . esc_html($label) . '</span>';
            continue;
        }

        $items[] = kepoli_post_part_anchor($part, $label, 'post-page-links__item');
    }

    return sprintf(
        '<nav class="post-page-links" aria-label="%1$s"><span class="post-page-links__label">%2$s</span><div class="post-page-links__items">%3$s</div></nav>',
        esc_attr(kepoli_ui_text('Navigatie pagini articol', 'Article page navigation')),
        esc_html(sprintf(kepoli_ui_text('Partea %1$d din %2$d', 'Part %1$d of %2$d'), $current, $total)),
        implode('', array_filter($items))
    );
}

function kepoli_multipart_continue_ad(): string
{
    if (!kepoli_is_multipart_post()) {
        return '';
    }

    $current = kepoli_current_post_part();
    $total = kepoli_post_part_count();
    if ($current >= $total) {
        return '';
    }

    return kepoli_ad_slot('part_continue', 'ad-slot--part-continue');
}

function kepoli_admin_adsense_notice(): void
{
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }

    if (kepoli_env('ADSENSE_CLIENT_ID') === '' || kepoli_ads_enabled()) {
        return;
    }

    $consent_link = kepoli_advertising_page_url();

    echo '<div class="notice notice-warning"><p>';
    echo esc_html__('AdSense is configured, but ad code stays disabled until consent is ready for EEA/UK visitors.', 'kepoli');
    echo ' ';
    echo wp_kses_post(sprintf(
        __('Configure Google Privacy & Messaging or a certified CMP, then set <code>ADSENSE_ENABLE=1</code> in Coolify. See the <a href="%s">Advertising and consent</a> page too.', 'kepoli'),
        esc_url($consent_link)
    ));
    echo '</p></div>';
}
add_action('admin_notices', 'kepoli_admin_adsense_notice');

function kepoli_read_time(int $post_id = 0): string
{
    $post_id = $post_id ?: get_the_ID();
    $words = str_word_count(wp_strip_all_tags((string) get_post_field('post_content', $post_id)));
    $minutes = max(1, (int) ceil($words / 220));
    if (kepoli_is_english()) {
        return sprintf(_n('%d min read', '%d min read', $minutes, 'kepoli'), $minutes);
    }

    return sprintf(_n('%d min de citit', '%d min de citit', $minutes, 'kepoli'), $minutes);
}

function kepoli_post_kind(int $post_id = 0): string
{
    $post_id = $post_id ?: get_the_ID();
    return (string) get_post_meta($post_id, '_kepoli_post_kind', true);
}

function kepoli_recipe_data(int $post_id = 0): array
{
    $post_id = $post_id ?: get_the_ID();
    $json = (string) get_post_meta($post_id, '_kepoli_recipe_json', true);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function kepoli_format_iso_duration(string $duration): string
{
    $duration = strtoupper(trim($duration));
    if ($duration === '') {
        return '';
    }

    if (!preg_match('/^PT(?:(\d+)H)?(?:(\d+)M)?$/', $duration, $matches)) {
        return $duration;
    }

    $hours = isset($matches[1]) ? (int) $matches[1] : 0;
    $minutes = isset($matches[2]) ? (int) $matches[2] : 0;
    $parts = [];

    if ($hours > 0) {
        $parts[] = sprintf('%d h', $hours);
    }

    if ($minutes > 0 || !$parts) {
        $parts[] = sprintf('%d min', $minutes);
    }

    return implode(' ', $parts);
}

function kepoli_recipe_snapshot_items(int $post_id = 0): array
{
    $data = kepoli_recipe_data($post_id);
    if (!$data) {
        return [];
    }

    $items = [];
    $candidates = [
        [
            'label' => kepoli_ui_text('Pregatire', 'Prep'),
            'value' => trim((string) ($data['prep'] ?? kepoli_format_iso_duration((string) ($data['prep_iso'] ?? '')))),
            'icon' => 'prep',
        ],
        [
            'label' => kepoli_ui_text('Gatire', 'Cook'),
            'value' => trim((string) ($data['cook'] ?? kepoli_format_iso_duration((string) ($data['cook_iso'] ?? '')))),
            'icon' => 'clock',
        ],
        [
            'label' => kepoli_ui_text('Total', 'Total'),
            'value' => trim((string) ($data['total_label'] ?? kepoli_format_iso_duration((string) ($data['total_iso'] ?? '')))),
            'icon' => 'refresh',
        ],
        [
            'label' => kepoli_ui_text('Portii', 'Servings'),
            'value' => trim((string) ($data['servings'] ?? '')),
            'icon' => 'ingredients',
        ],
    ];

    foreach ($candidates as $candidate) {
        if ($candidate['value'] === '') {
            continue;
        }

        $items[] = $candidate;
    }

    return $items;
}

function kepoli_recipe_step_anchor(int $position): string
{
    return (kepoli_is_english() ? 'method-step-' : 'mod-de-preparare-step-') . max(1, $position);
}

function kepoli_recipe_step_name(string $step): string
{
    $text = trim(wp_strip_all_tags($step));
    if ($text === '') {
        return kepoli_ui_text('Pas de preparare', 'Recipe step');
    }

    $name = wp_trim_words($text, 8, '');
    return rtrim($name, " \t\n\r\0\x0B.,;:") ?: kepoli_ui_text('Pas de preparare', 'Recipe step');
}

function kepoli_recipe_keywords(int $post_id = 0): string
{
    $post_id = $post_id ?: get_the_ID();
    return implode(', ', kepoli_clean_post_tag_names($post_id));
}

function kepoli_article_snapshot_data(int $post_id = 0): array
{
    $post_id = $post_id ?: get_the_ID();
    $json = (string) get_post_meta($post_id, '_kepoli_article_snapshot', true);
    $data = json_decode($json, true);

    if (is_array($data)) {
        return $data;
    }

    $headings = array_column(kepoli_article_heading_index($post_id), 'label');

    return [
        'takeaways' => [],
        'section_headings' => $headings,
        'section_count' => count($headings),
        'faq_count' => 0,
        'related_recipe_count' => count(kepoli_related_posts_by_kind($post_id, 'recipe')),
    ];
}

function kepoli_article_snapshot_items(int $post_id = 0): array
{
    $data = kepoli_article_snapshot_data($post_id);
    if (!$data) {
        return [];
    }

    $takeaways = array_values(array_filter(array_map('trim', $data['takeaways'] ?? [])));
    $headings = array_values(array_filter(array_map('trim', $data['section_headings'] ?? [])));
    $section_count = (int) ($data['section_count'] ?? count($headings));
    $faq_count = (int) ($data['faq_count'] ?? 0);
    $related_recipe_count = (int) ($data['related_recipe_count'] ?? 0);
    $items = [];

    if (!empty($takeaways[0])) {
        $items[] = [
            'label' => kepoli_ui_text('Ideea cheie', 'Key idea'),
            'value' => $takeaways[0],
            'icon' => 'tips',
        ];
    }

    if (!empty($headings[0])) {
        $items[] = [
            'label' => kepoli_ui_text('Pornesti cu', 'Starts with'),
            'value' => $headings[0],
            'icon' => 'steps',
        ];
    } elseif (!empty($takeaways[1])) {
        $items[] = [
            'label' => kepoli_ui_text('Urmaresti', 'You follow'),
            'value' => $takeaways[1],
            'icon' => 'steps',
        ];
    }

    $structure = [];
    if ($section_count > 0) {
        $structure[] = sprintf(kepoli_is_english() ? _n('%d practical section', '%d practical sections', $section_count, 'kepoli') : _n('%d sectiune practica', '%d sectiuni practice', $section_count, 'kepoli'), $section_count);
    }
    if ($faq_count > 0) {
        $structure[] = sprintf(kepoli_is_english() ? _n('%d quick answer', '%d quick answers', $faq_count, 'kepoli') : _n('%d raspuns rapid', '%d raspunsuri rapide', $faq_count, 'kepoli'), $faq_count);
    }
    if ($structure !== []) {
        $items[] = [
            'label' => kepoli_ui_text('Include', 'Includes'),
            'value' => implode(kepoli_ui_text(' si ', ' and '), $structure),
            'icon' => 'question',
        ];
    }

    if ($related_recipe_count > 0) {
        $items[] = [
            'label' => kepoli_ui_text('Aplici cu', 'Use with'),
            'value' => sprintf(kepoli_is_english() ? _n('%d related recipe', '%d related recipes', $related_recipe_count, 'kepoli') : _n('%d reteta legata', '%d retete legate', $related_recipe_count, 'kepoli'), $related_recipe_count),
            'icon' => 'arrow-right',
        ];
    }

    return $items;
}

function kepoli_post_card_meta_items(int $post_id = 0): array
{
    $post_id = $post_id ?: get_the_ID();

    if (kepoli_post_kind($post_id) === 'recipe') {
        $data = kepoli_recipe_data($post_id);
        $items = [];
        $total = trim((string) ($data['total_label'] ?? kepoli_format_iso_duration((string) ($data['total_iso'] ?? ''))));
        $servings = trim((string) ($data['servings'] ?? ''));

        if ($total !== '') {
            $items[] = sprintf(kepoli_ui_text('Total %s', 'Total %s'), $total);
        } elseif (!empty($data['prep_iso'])) {
            $prep = kepoli_format_iso_duration((string) $data['prep_iso']);
            if ($prep !== '') {
                $items[] = sprintf(kepoli_ui_text('Pregatire %s', 'Prep %s'), $prep);
            }
        }

        if ($servings !== '') {
            $items[] = $servings;
        }

        if ($items !== []) {
            return $items;
        }
    }

    if (kepoli_post_kind($post_id) === 'article') {
        return [
            kepoli_article_freshness_label($post_id),
            kepoli_read_time($post_id),
        ];
    }

    return [
        get_the_date('j M Y', $post_id),
        kepoli_read_time($post_id),
    ];
}

function kepoli_render_post_card_meta(int $post_id = 0, string $class = 'post-card__meta', string $item_class = ''): string
{
    $items = array_values(array_filter(kepoli_post_card_meta_items($post_id), static function ($item) {
        return trim((string) $item) !== '';
    }));

    if ($items === []) {
        return '';
    }

    $html = '<div class="' . esc_attr(trim($class)) . '">';
    $item_attr = $item_class !== '' ? ' class="' . esc_attr($item_class) . '"' : '';

    foreach ($items as $item) {
        $html .= '<span' . $item_attr . '>' . esc_html($item) . '</span>';
    }

    $html .= '</div>';

    return $html;
}

function kepoli_recipe_json_ld(): void
{
    if (!is_singular('post') || kepoli_post_kind() !== 'recipe') {
        return;
    }

    $data = kepoli_recipe_data();
    if (!$data) {
        return;
    }

    $author_name = kepoli_post_author_name();
    $recipe_image = kepoli_social_image_schema_object();

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Recipe',
        'name' => get_the_title(),
        'description' => wp_strip_all_tags(get_the_excerpt()),
        'image' => [$recipe_image],
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => get_permalink(),
        ],
        'inLanguage' => kepoli_language_tag(),
        'author' => [
            '@type' => 'Person',
            'name' => $author_name ?: kepoli_writer_name(),
            'url' => kepoli_author_page_url(),
        ],
        'publisher' => kepoli_schema_publisher(),
        'datePublished' => get_the_date('c'),
        'dateModified' => get_the_modified_date('c'),
        'recipeCategory' => $data['category'] ?? '',
        'recipeCuisine' => kepoli_is_english() ? 'International' : 'Romanian',
        'recipeYield' => $data['servings'] ?? '',
        'prepTime' => $data['prep_iso'] ?? '',
        'cookTime' => $data['cook_iso'] ?? '',
        'totalTime' => $data['total_iso'] ?? '',
        'recipeIngredient' => $data['ingredients'] ?? [],
        'recipeInstructions' => array_map(static function ($step, $index) use ($recipe_image) {
            $position = (int) $index + 1;
            return [
                '@type' => 'HowToStep',
                'name' => kepoli_recipe_step_name((string) $step),
                'text' => $step,
                'url' => get_permalink() . '#' . kepoli_recipe_step_anchor($position),
                'image' => $recipe_image,
            ];
        }, $data['steps'] ?? [], array_keys($data['steps'] ?? [])),
    ];

    $keywords = kepoli_recipe_keywords();
    if ($keywords !== '') {
        $schema['keywords'] = $keywords;
    }

    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
}
add_action('wp_head', 'kepoli_recipe_json_ld', 20);

function kepoli_article_json_ld(): void
{
    if (!is_singular('post') || kepoli_post_kind() === 'recipe') {
        return;
    }

    $author_name = kepoli_post_author_name();

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => get_the_title(),
        'description' => kepoli_current_description(),
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => get_permalink(),
        ],
        'image' => [kepoli_social_image_schema_object()],
        'inLanguage' => kepoli_language_tag(),
        'datePublished' => get_the_date('c'),
        'dateModified' => get_the_modified_date('c'),
        'author' => [
            '@type' => 'Person',
            'name' => $author_name,
            'url' => kepoli_author_page_url(),
        ],
        'publisher' => kepoli_schema_publisher(),
    ];

    $keywords = kepoli_recipe_keywords();
    if ($keywords !== '') {
        $schema['keywords'] = $keywords;
    }

    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
}
add_action('wp_head', 'kepoli_article_json_ld', 21);

function kepoli_site_json_ld(): void
{
    if (is_admin()) {
        return;
    }

    $site_name = kepoli_site_name();
    $site_host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
    $writer_name = kepoli_writer_name();

    $graph = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                '@id' => home_url('/#organization'),
                'name' => $site_name,
                'url' => home_url('/'),
                'email' => kepoli_public_contact_email(),
                'description' => kepoli_brand_description(),
                'contactPoint' => [
                    '@type' => 'ContactPoint',
                    'contactType' => 'editorial',
                    'email' => kepoli_public_contact_email(),
                    'url' => kepoli_contact_page_url(),
                    'availableLanguage' => kepoli_available_languages(),
                ],
                'publishingPrinciples' => kepoli_editorial_policy_url(),
                'logo' => kepoli_schema_asset_image_object(kepoli_wordmark_asset(), 'svg', $site_name),
            ],
            [
                '@type' => 'WebSite',
                '@id' => home_url('/#website'),
                'url' => home_url('/'),
                'name' => $site_name,
                'alternateName' => $site_host,
                'description' => kepoli_brand_description(),
                'inLanguage' => kepoli_language_tag(),
                'publisher' => ['@id' => home_url('/#organization')],
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => home_url('/?s={search_term_string}'),
                    'query-input' => 'required name=search_term_string',
                ],
            ],
            [
                '@type' => 'Person',
                '@id' => kepoli_author_page_url() . '#person',
                'name' => $writer_name,
                'url' => kepoli_author_page_url(),
                'email' => kepoli_writer_email(),
                'image' => kepoli_schema_asset_image_object('writer-photo', 'jpg', $writer_name),
                'worksFor' => ['@id' => home_url('/#organization')],
                'jobTitle' => kepoli_ui_text('Autor culinar', 'Food writer'),
                'description' => kepoli_writer_description(),
            ],
        ],
    ];

    echo '<script type="application/ld+json">' . wp_json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
}
add_action('wp_head', 'kepoli_site_json_ld', 19);

function kepoli_static_page_json_ld(): void
{
    if (is_admin() || !is_page()) {
        return;
    }

    $schema = null;
    $about_page = kepoli_about_page();
    $current_slug = kepoli_current_page_slug();
    $policy_page_types = [
        kepoli_profile_slug('privacy', kepoli_is_english() ? 'privacy-policy' : 'politica-de-confidentialitate') => 'WebPage',
        kepoli_profile_slug('cookies', kepoli_is_english() ? 'cookie-policy' : 'politica-de-cookies') => 'WebPage',
        kepoli_profile_slug('advertising', kepoli_is_english() ? 'advertising-and-consent' : 'publicitate-si-consimtamant') => 'WebPage',
        kepoli_profile_slug('editorial', kepoli_is_english() ? 'editorial-policy' : 'politica-editoriala') => 'AboutPage',
        kepoli_profile_slug('terms', kepoli_is_english() ? 'terms-and-conditions' : 'termeni-si-conditii') => 'WebPage',
        kepoli_profile_slug('disclaimer', kepoli_is_english() ? 'culinary-disclaimer' : 'disclaimer-culinar') => 'WebPage',
        'privacy-policy' => 'WebPage',
        'politica-de-confidentialitate' => 'WebPage',
        'cookie-policy' => 'WebPage',
        'politica-de-cookies' => 'WebPage',
        'advertising-and-consent' => 'WebPage',
        'publicitate-si-consimtamant' => 'WebPage',
        'editorial-policy' => 'AboutPage',
        'politica-editoriala' => 'AboutPage',
        'terms-and-conditions' => 'WebPage',
        'termeni-si-conditii' => 'WebPage',
        'culinary-disclaimer' => 'WebPage',
        'disclaimer-culinar' => 'WebPage',
    ];

    if ($about_page instanceof WP_Post && is_page($about_page->ID)) {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'AboutPage',
            'name' => get_the_title(),
            'url' => get_permalink(),
            'description' => kepoli_current_description(),
            'inLanguage' => kepoli_language_tag(),
            'isPartOf' => ['@id' => home_url('/#website')],
            'mainEntity' => ['@id' => home_url('/#organization')],
            'about' => ['@id' => home_url('/#organization')],
        ];
    } elseif (($author_page = kepoli_find_page_by_candidates(array_unique(array_filter([kepoli_profile_slug('author', ''), 'about-author', 'about-author'])))) && is_page($author_page->ID)) {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'ProfilePage',
            'name' => get_the_title(),
            'url' => get_permalink(),
            'description' => kepoli_current_description(),
            'inLanguage' => kepoli_language_tag(),
            'isPartOf' => ['@id' => home_url('/#website')],
            'mainEntity' => ['@id' => kepoli_author_page_url() . '#person'],
        ];
    } elseif (is_page('contact')) {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'ContactPage',
            'name' => get_the_title(),
            'url' => get_permalink(),
            'description' => kepoli_current_description(),
            'inLanguage' => kepoli_language_tag(),
            'isPartOf' => ['@id' => home_url('/#website')],
            'mainEntity' => [
                '@type' => 'ContactPoint',
                'contactType' => 'editorial',
                'email' => kepoli_public_contact_email(),
                'url' => kepoli_contact_page_url(),
                'availableLanguage' => kepoli_available_languages(),
            ],
        ];
    } elseif (isset($policy_page_types[$current_slug])) {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => $policy_page_types[$current_slug],
            'name' => get_the_title(),
            'url' => get_permalink(),
            'description' => kepoli_current_description(),
            'inLanguage' => kepoli_language_tag(),
            'isPartOf' => ['@id' => home_url('/#website')],
            'about' => ['@id' => home_url('/#organization')],
        ];
    }

    if ($schema === null) {
        return;
    }

    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
}
add_action('wp_head', 'kepoli_static_page_json_ld', 24);

function kepoli_collection_schema_posts(): array
{
    if (($recipes_page = kepoli_recipes_page()) && is_page($recipes_page->ID)) {
        return get_posts([
            'post_type' => 'post',
            'posts_per_page' => 24,
            'ignore_sticky_posts' => true,
            'meta_key' => '_kepoli_post_kind',
            'meta_value' => 'recipe',
        ]);
    }

    if (($guides_page = kepoli_guides_page()) && is_page($guides_page->ID)) {
        return get_posts([
            'post_type' => 'post',
            'posts_per_page' => 24,
            'ignore_sticky_posts' => true,
            'meta_key' => '_kepoli_post_kind',
            'meta_value' => 'article',
        ]);
    }

    if (is_category()) {
        $term = get_queried_object();
        if (!$term instanceof WP_Term) {
            return [];
        }

        return get_posts([
            'post_type' => 'post',
            'posts_per_page' => 24,
            'ignore_sticky_posts' => true,
            'cat' => (int) $term->term_id,
        ]);
    }

    return [];
}

function kepoli_collection_json_ld(): void
{
    if (is_admin() || is_singular()) {
        return;
    }

    $posts = kepoli_collection_schema_posts();
    if (!$posts) {
        return;
    }

    $items = [];
    foreach (array_values($posts) as $index => $post) {
        $items[] = [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'url' => get_permalink($post),
            'name' => get_the_title($post),
        ];
    }

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => is_category() ? single_cat_title('', false) : (is_page() ? get_the_title() : get_bloginfo('name')),
        'description' => kepoli_current_description(),
        'url' => kepoli_current_url(),
        'inLanguage' => kepoli_language_tag(),
        'isPartOf' => ['@id' => home_url('/#website')],
        'mainEntity' => [
            '@type' => 'ItemList',
            'itemListOrder' => 'https://schema.org/ItemListOrderAscending',
            'numberOfItems' => count($items),
            'itemListElement' => $items,
        ],
    ];

    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
}
add_action('wp_head', 'kepoli_collection_json_ld', 22);

function kepoli_recipe_content_anchors(string $content): string
{
    if (!is_singular('post') || kepoli_post_kind() !== 'recipe') {
        return $content;
    }

    $content = strtr($content, [
        '<h2>Pe scurt</h2>' => '<h2 id="pe-scurt">Pe scurt</h2>',
        '<h2>Ingrediente</h2>' => '<h2 id="ingrediente">Ingrediente</h2>',
        '<h2>Mod de preparare</h2>' => '<h2 id="mod-de-preparare">Mod de preparare</h2>',
        '<h2>Sfaturi pentru reusita</h2>' => '<h2 id="sfaturi-pentru-reusita">Sfaturi pentru reusita</h2>',
        '<section class="related-posts"><h2>Legaturi utile</h2>' => '<section class="related-posts" id="legaturi-utile"><h2>Legaturi utile</h2>',
    ]);

    $anchored_content = preg_replace_callback(
        '/(<h2 id="mod-de-preparare">Mod de preparare<\/h2>\s*<ol>)(.*?)(<\/ol>)/is',
        static function (array $matches): string {
            $position = 0;
            $steps = (string) preg_replace_callback(
                '/<li(?![^>]*\sid=)([^>]*)>/i',
                static function (array $li_matches) use (&$position): string {
                    $position++;
                    return '<li id="' . esc_attr(kepoli_recipe_step_anchor($position)) . '"' . $li_matches[1] . '>';
                },
                $matches[2]
            );

            return $matches[1] . $steps . $matches[3];
        },
        $content,
        1
    );

    return is_string($anchored_content) ? $anchored_content : $content;
}
add_filter('the_content', 'kepoli_recipe_content_anchors', 5);

function kepoli_article_content_anchors(string $content): string
{
    if (!is_singular('post') || kepoli_post_kind() !== 'article') {
        return $content;
    }

    $headings = kepoli_article_heading_index(get_the_ID());
    if (!$headings) {
        return $content;
    }

    $index = 0;

    return (string) preg_replace_callback(
        '/<h2(?![^>]*\sid=)([^>]*)>(.*?)<\/h2>/i',
        static function (array $matches) use (&$index, $headings): string {
            if (!isset($headings[$index])) {
                return $matches[0];
            }

            $attrs = trim($matches[1]);
            $attrs = $attrs !== '' ? ' ' . $attrs : '';
            $id = $headings[$index]['id'];
            $index++;

            return '<h2 id="' . esc_attr($id) . '"' . $attrs . '>' . $matches[2] . '</h2>';
        },
        $content
    );
}
add_filter('the_content', 'kepoli_article_content_anchors', 6);

function kepoli_insert_after_nth_paragraph(string $content, string $insertion, int $paragraph_number): string
{
    if ($insertion === '' || $paragraph_number < 1) {
        return $content;
    }

    $position = 0;
    $inserted = false;
    $updated = preg_replace_callback(
        '/<\/p>/i',
        static function (array $matches) use (&$position, &$inserted, $paragraph_number, $insertion): string {
            $position++;
            if (!$inserted && $position === $paragraph_number) {
                $inserted = true;
                return $matches[0] . $insertion;
            }

            return $matches[0];
        },
        $content
    );

    return $inserted && is_string($updated) ? $updated : $content;
}

function kepoli_content_display_ads(string $content): string
{
    if (!is_singular('post') || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    $after_intro = kepoli_ad_slot('after_intro');
    $mid_content = kepoli_ad_slot('mid_content');
    $reading_option = kepoli_ad_slot('reading_option', 'ad-slot--reading-option');
    if ($after_intro === '' && $mid_content === '' && $reading_option === '') {
        return $content;
    }

    if ($after_intro !== '') {
        $content = kepoli_insert_after_nth_paragraph($content, $after_intro, 2);
    }

    if ($reading_option !== '') {
        $content = kepoli_insert_after_nth_paragraph($content, $reading_option, 6);
    }

    if ($mid_content !== '') {
        $paragraph_count = substr_count(strtolower($content), '</p>');
        $mid_position = max(5, (int) floor($paragraph_count / 2));
        $content = kepoli_insert_after_nth_paragraph($content, $mid_content, $mid_position);
    }

    return $content;
}
add_filter('the_content', 'kepoli_content_display_ads', 12);

function kepoli_breadcrumbs(): void
{
    $items = kepoli_breadcrumb_items();

    echo '<nav class="breadcrumbs" aria-label="' . esc_attr(kepoli_ui_text('Fir de navigare', 'Breadcrumbs')) . '">';
    foreach ($items as $index => $item) {
        if ($index > 0) {
            echo ' / ';
        }

        if (!empty($item['url'])) {
            echo '<a href="' . esc_url($item['url']) . '">' . esc_html($item['name']) . '</a>';
        } else {
            echo '<span>' . esc_html($item['name']) . '</span>';
        }
    }
    echo '</nav>';
}

function kepoli_breadcrumb_items(): array
{
    $items = [
        [
            'name' => kepoli_ui_text('Acasa', 'Home'),
            'url' => home_url('/'),
        ],
    ];

    if (is_singular('post')) {
        $category = get_the_category();
        if ($category) {
            $items[] = [
                'name' => $category[0]->name,
                'url' => get_category_link($category[0]),
            ];
        }
        $items[] = [
            'name' => get_the_title(),
            'url' => '',
        ];
    } elseif (is_category()) {
        $items[] = [
            'name' => single_cat_title('', false),
            'url' => '',
        ];
    } elseif (is_page()) {
        $items[] = [
            'name' => get_the_title(),
            'url' => '',
        ];
    } elseif (is_search()) {
        $items[] = [
            'name' => sprintf(kepoli_ui_text('Rezultate pentru "%s"', 'Results for "%s"'), get_search_query()),
            'url' => '',
        ];
    }

    return $items;
}

function kepoli_breadcrumb_json_ld(): void
{
    if (is_admin() || is_front_page()) {
        return;
    }

    $items = kepoli_breadcrumb_items();
    if (count($items) < 2) {
        return;
    }

    $list = [];
    foreach ($items as $index => $item) {
        $entry = [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'name' => $item['name'],
        ];

        if (!empty($item['url'])) {
            $entry['item'] = $item['url'];
        }

        $list[] = $entry;
    }

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $list,
    ];

    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
}
add_action('wp_head', 'kepoli_breadcrumb_json_ld', 23);

function kepoli_get_posts_by_slugs(array $slugs): array
{
    $posts = [];
    foreach ($slugs as $slug) {
        $post = get_page_by_path($slug, OBJECT, 'post');
        if ($post && $post->post_status === 'publish') {
            $posts[] = $post;
        }
    }
    return $posts;
}
