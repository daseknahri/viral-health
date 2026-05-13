<?php
/**
 * Plugin Name: Dr Purg Jr. Auto Seed
 * Description: Self-heals a fresh WordPress install when the one-shot WP-CLI seed did not run in the host platform.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (file_exists('/seed/version.php')) {
    require_once '/seed/version.php';
}

function kepoli_autoseed_env(string $key, string $default = ''): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }

    return (string) $value;
}

function kepoli_autoseed_env_bool(string $key, bool $default = false): bool
{
    $value = strtolower(trim(kepoli_autoseed_env($key, $default ? '1' : '0')));
    if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }
    if (in_array($value, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }

    return $default;
}

function kepoli_autoseed_activate_plugin(string $plugin): void
{
    $plugin_path = WP_PLUGIN_DIR . '/' . $plugin;
    if (!file_exists($plugin_path)) {
        return;
    }

    if (!function_exists('is_plugin_active') || !function_exists('activate_plugin')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    if (!is_plugin_active($plugin)) {
        activate_plugin($plugin, '', false, true);
    }
}

function kepoli_autoseed_has_real_content(): bool
{
    $content = get_posts([
        'post_type' => ['post', 'page'],
        'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
        'posts_per_page' => 20,
        'orderby' => 'ID',
        'order' => 'ASC',
        'no_found_rows' => true,
    ]);

    $starter_slugs = ['hello-world', 'sample-page', 'privacy-policy'];
    foreach ($content as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }
        if (!in_array((string) $post->post_name, $starter_slugs, true)) {
            return true;
        }
    }

    return false;
}

add_action('init', static function (): void {
    if (defined('WP_INSTALLING') && WP_INSTALLING) {
        return;
    }

    if (function_exists('is_blog_installed') && !is_blog_installed()) {
        return;
    }

    kepoli_autoseed_activate_plugin('kepoli-author-tools/kepoli-author-tools.php');
    kepoli_autoseed_activate_plugin('dr-purg-social-syndicator/dr-purg-social-syndicator.php');

    if (!kepoli_autoseed_env_bool('KEPOLI_AUTOSEED_ENABLE', true)) {
        return;
    }

    $target_version = function_exists('kepoli_seed_target_version')
        ? kepoli_seed_target_version()
        : 'seed-fallback';

    $current_version = (string) get_option('kepoli_seed_version', '');
    $force_reseed = kepoli_autoseed_env_bool('KEPOLI_FORCE_RESEED', false);

    if (!$force_reseed && $current_version !== '') {
        return;
    }

    if (!$force_reseed && kepoli_autoseed_has_real_content()) {
        return;
    }

    if ($current_version === $target_version && wp_get_theme()->get_stylesheet() === 'kepoli') {
        return;
    }

    if (!file_exists('/seed/bootstrap.php') || !file_exists('/content/posts.json')) {
        return;
    }

    if (get_transient('kepoli_seed_lock')) {
        return;
    }

    set_transient('kepoli_seed_lock', '1', 5 * MINUTE_IN_SECONDS);

    ob_start();
    try {
        require '/seed/bootstrap.php';
    } finally {
        ob_end_clean();
        delete_transient('kepoli_seed_lock');
    }
}, 20);
