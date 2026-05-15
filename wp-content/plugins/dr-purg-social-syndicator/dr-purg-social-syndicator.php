<?php
/**
 * Plugin Name: Dr Purg Jr. Social Syndicator
 * Description: Creates per-post social packages and posts reviewed Facebook Page updates through the Graph API.
 * Version: 0.4.0
 * Author: Site tools
 * Text Domain: dr-purg-social-syndicator
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Dr_Purg_Social_Syndicator
{
    private const VERSION = '0.4.0';
    private const SETTINGS_OPTION = 'dpj_social_syndicator_settings';
    private const PIXAZO_SDXL_FREE_ENDPOINT = 'https://gateway.pixazo.ai/getImage/v1/getSDXLImage';
    private const QUEUE_SLUG = 'dr-purg-social-queue';
    private const EDITOR_SLUG = 'dr-purg-social-editor';
    private const SETTINGS_SLUG = 'dr-purg-social-settings';
    private const REDIRECT_TRANSIENT_PREFIX = 'dpj_social_redirect_';
    private const STATUS_NEEDS = 'needs_social';
    private const STATUS_DRAFT = 'draft';
    private const STATUS_POSTED = 'posted';
    private const STATUS_FAILED = 'failed';
    private const STATUS_SKIPPED = 'skipped';
    private const SOCIAL_IMAGE_VARIANTS = [
        'facebook' => [
            'label' => 'Facebook photo',
            'width' => 1080,
            'height' => 1350,
            'assign_meta' => '_dpj_social_facebook_media_id',
            'generated_meta' => '_dpj_social_generated_facebook_media_id',
        ],
        'pinterest' => [
            'label' => 'Pinterest pin',
            'width' => 1000,
            'height' => 1500,
            'assign_meta' => '_dpj_social_pinterest_media_id',
            'generated_meta' => '_dpj_social_generated_pinterest_media_id',
        ],
        'og' => [
            'label' => 'OG / Reddit preview',
            'width' => 1200,
            'height' => 630,
            'assign_meta' => ['_dpj_social_og_media_id', '_dpj_social_reddit_media_id'],
            'generated_meta' => '_dpj_social_og_media_id',
        ],
    ];
    private const PIXAZO_PLATFORM_VARIANTS = [
        'facebook' => [
            'label' => 'Facebook AI image',
            'width' => 819,
            'height' => 1024,
            'assign_meta' => ['_dpj_social_facebook_media_id', '_dpj_social_generated_facebook_media_id'],
        ],
        'pinterest' => [
            'label' => 'Pinterest AI image',
            'width' => 683,
            'height' => 1024,
            'assign_meta' => ['_dpj_social_pinterest_media_id', '_dpj_social_generated_pinterest_media_id'],
        ],
        'og' => [
            'label' => 'OG / Reddit AI image',
            'width' => 1024,
            'height' => 538,
            'assign_meta' => ['_dpj_social_og_media_id', '_dpj_social_reddit_media_id'],
        ],
    ];

    private const PLATFORM_META = [
        'facebook' => [
            '_dpj_social_facebook_hook',
            '_dpj_social_facebook_summary',
            '_dpj_social_facebook_media_id',
            '_dpj_social_facebook_link',
            '_dpj_social_facebook_first_comment',
            '_dpj_social_facebook_status',
            '_dpj_social_facebook_remote_post_id',
            '_dpj_social_facebook_remote_photo_id',
            '_dpj_social_facebook_comment_id',
            '_dpj_social_facebook_last_error',
            '_dpj_social_facebook_posted_at',
            '_dpj_social_generated_facebook_media_id',
            '_dpj_social_pixazo_prompt',
            '_dpj_social_pixazo_negative_prompt',
            '_dpj_social_pixazo_last_error',
            '_dpj_social_pixazo_last_run',
            '_dpj_social_local_overlay_text',
            '_dpj_social_local_overlay_enable',
            '_dpj_social_local_hint_text',
            '_dpj_social_local_hint_enable',
            '_dpj_social_image_generation_last_error',
            '_dpj_social_image_generation_last_run',
            '_dpj_social_ai_last_error',
            '_dpj_social_ai_last_run',
            '_dpj_social_ai_model',
        ],
        'pinterest' => [
            '_dpj_social_pinterest_title',
            '_dpj_social_pinterest_description',
            '_dpj_social_pinterest_media_id',
            '_dpj_social_pinterest_board',
            '_dpj_social_pinterest_url',
            '_dpj_social_pinterest_alt_text',
            '_dpj_social_pinterest_status',
            '_dpj_social_generated_pinterest_media_id',
        ],
        'reddit' => [
            '_dpj_social_reddit_subreddit',
            '_dpj_social_reddit_title',
            '_dpj_social_reddit_body',
            '_dpj_social_reddit_link',
            '_dpj_social_reddit_media_id',
            '_dpj_social_reddit_rules_notes',
            '_dpj_social_reddit_status',
            '_dpj_social_og_media_id',
        ],
    ];

    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'register_admin_pages']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_assets']);
        add_action('admin_init', [self::class, 'handle_admin_actions']);
        add_action('transition_post_status', [self::class, 'handle_post_transition'], 10, 3);
        add_filter('redirect_post_location', [self::class, 'maybe_redirect_after_publish'], 10, 2);
        add_filter('post_row_actions', [self::class, 'add_post_row_action'], 10, 2);
        add_filter('kepoli_social_image_url', [self::class, 'filter_theme_social_image_url']);
        add_filter('kepoli_social_image_alt', [self::class, 'filter_theme_social_image_alt']);
        add_filter('kepoli_social_image_dimensions', [self::class, 'filter_theme_social_image_dimensions']);
    }

    public static function activate(): void
    {
        $settings = get_option(self::SETTINGS_OPTION);
        if (!is_array($settings)) {
            add_option(self::SETTINGS_OPTION, self::default_settings(), '', false);
        }
    }

    private static function default_settings(): array
    {
        return [
            'facebook_page_id' => '',
            'facebook_app_id' => '',
            'facebook_app_secret' => '',
            'facebook_page_access_token' => '',
            'facebook_graph_version' => 'v24.0',
            'pixazo_api_key' => '',
            'pixazo_model' => 'sdxl_v1_free',
            'redirect_after_publish' => '1',
        ];
    }

    private static function settings(): array
    {
        $stored = get_option(self::SETTINGS_OPTION);
        return array_replace(self::default_settings(), is_array($stored) ? $stored : []);
    }

    private static function clean_graph_version(string $version): string
    {
        $version = trim($version);
        return preg_match('/^v\d+\.\d+$/', $version) ? $version : 'v24.0';
    }

    private static function env(string $key, string $default = ''): string
    {
        $value = getenv($key);
        if ($value === false && isset($_ENV[$key])) {
            $value = $_ENV[$key];
        }
        if ($value === false && isset($_SERVER[$key])) {
            $value = $_SERVER[$key];
        }

        $value = is_scalar($value) ? trim((string) $value) : '';
        return $value !== '' ? $value : $default;
    }

    private static function env_bool(string $key, bool $default = false): bool
    {
        $value = strtolower(self::env($key, $default ? '1' : '0'));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    private static function env_int(string $key, int $default, int $min, int $max): int
    {
        $value = (int) self::env($key, (string) $default);
        return max($min, min($max, $value));
    }

    private static function social_ai_provider(): string
    {
        return strtolower(self::env('SOCIAL_AI_PROVIDER', self::env('AI_EXTRACTION_PROVIDER', 'openrouter')));
    }

    private static function social_ai_api_key(): string
    {
        return self::env('SOCIAL_AI_API_KEY', self::env('AI_EXTRACTION_API_KEY', self::env('OPENROUTER_API_KEY')));
    }

    private static function social_ai_model(): string
    {
        return self::env('SOCIAL_AI_MODEL', self::env('AI_EXTRACTION_MODEL', 'inclusionai/ling-2.6-1t:free'));
    }

    private static function social_ai_enabled(): bool
    {
        $enabled = self::env_bool('SOCIAL_AI_ENABLE', self::env_bool('AI_EXTRACTION_ENABLE', false));
        return $enabled && self::social_ai_provider() === 'openrouter' && self::social_ai_api_key() !== '';
    }

    private static function can_manage(): bool
    {
        return current_user_can('edit_posts');
    }

    private static function post_type_is_supported(?WP_Post $post): bool
    {
        return $post instanceof WP_Post && $post->post_type === 'post';
    }

    public static function register_admin_pages(): void
    {
        add_menu_page(
            __('Social Queue', 'dr-purg-social-syndicator'),
            __('Social Queue', 'dr-purg-social-syndicator'),
            'edit_posts',
            self::QUEUE_SLUG,
            [self::class, 'render_queue_page'],
            'dashicons-share-alt2',
            25
        );

        add_submenu_page(
            self::QUEUE_SLUG,
            __('Social Queue', 'dr-purg-social-syndicator'),
            __('Social Queue', 'dr-purg-social-syndicator'),
            'edit_posts',
            self::QUEUE_SLUG,
            [self::class, 'render_queue_page']
        );

        add_submenu_page(
            self::QUEUE_SLUG,
            __('Social Settings', 'dr-purg-social-syndicator'),
            __('Settings', 'dr-purg-social-syndicator'),
            'manage_options',
            self::SETTINGS_SLUG,
            [self::class, 'render_settings_page']
        );

        add_submenu_page(
            null,
            __('Social Editor', 'dr-purg-social-syndicator'),
            __('Social Editor', 'dr-purg-social-syndicator'),
            'edit_posts',
            self::EDITOR_SLUG,
            [self::class, 'render_editor_page']
        );
    }

    public static function enqueue_admin_assets(string $hook): void
    {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
        $is_social_page = in_array($page, [self::QUEUE_SLUG, self::EDITOR_SLUG, self::SETTINGS_SLUG], true);

        if (!$is_social_page) {
            return;
        }

        wp_enqueue_style(
            'dpj-social-syndicator-admin',
            plugins_url('assets/admin.css', __FILE__),
            [],
            self::VERSION
        );

        wp_enqueue_script(
            'dpj-social-syndicator-admin',
            plugins_url('assets/admin.js', __FILE__),
            ['jquery'],
            self::VERSION,
            true
        );

        if ($page === self::EDITOR_SLUG) {
            wp_enqueue_media();
        }
    }

    public static function add_post_row_action(array $actions, WP_Post $post): array
    {
        if (!self::post_type_is_supported($post) || !current_user_can('edit_post', $post->ID)) {
            return $actions;
        }

        $actions['dpj_social_editor'] = sprintf(
            '<a href="%1$s">%2$s</a>',
            esc_url(self::editor_url($post->ID)),
            esc_html__('Social editor', 'dr-purg-social-syndicator')
        );

        return $actions;
    }

    public static function handle_post_transition(string $new_status, string $old_status, WP_Post $post): void
    {
        if ($new_status !== 'publish' || $old_status === 'publish' || !self::post_type_is_supported($post)) {
            return;
        }

        self::ensure_social_package($post->ID);

        if ($old_status === 'future') {
            return;
        }

        if (!self::post_redirect_enabled($post->ID)) {
            return;
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0 || !current_user_can('edit_post', $post->ID)) {
            return;
        }

        set_transient(self::REDIRECT_TRANSIENT_PREFIX . $user_id . '_' . $post->ID, '1', 5 * MINUTE_IN_SECONDS);
    }

    public static function maybe_redirect_after_publish(string $location, int $post_id): string
    {
        $post = get_post($post_id);
        if (!self::post_type_is_supported($post) || !current_user_can('edit_post', $post_id)) {
            return $location;
        }

        $key = self::REDIRECT_TRANSIENT_PREFIX . get_current_user_id() . '_' . $post_id;
        if (!get_transient($key)) {
            return $location;
        }

        delete_transient($key);

        return add_query_arg(
            [
                'post_id' => $post_id,
                'dpj_social_notice' => 'created',
            ],
            admin_url('admin.php?page=' . self::EDITOR_SLUG)
        );
    }

    private static function post_redirect_enabled(int $post_id): bool
    {
        $value = get_post_meta($post_id, '_dpj_social_redirect_after_publish', true);
        if ($value === '') {
            return self::settings()['redirect_after_publish'] === '1';
        }

        return $value === '1';
    }

    private static function editor_url(int $post_id, array $args = []): string
    {
        return add_query_arg(
            array_merge(['page' => self::EDITOR_SLUG, 'post_id' => $post_id], $args),
            admin_url('admin.php')
        );
    }

    private static function queue_url(array $args = []): string
    {
        return add_query_arg(array_merge(['page' => self::QUEUE_SLUG], $args), admin_url('admin.php'));
    }

    public static function handle_admin_actions(): void
    {
        if (!is_admin()) {
            return;
        }

        if (isset($_POST['dpj_social_editor_action'])) {
            self::handle_editor_post();
            return;
        }

        if (isset($_POST['dpj_social_settings_action'])) {
            self::handle_settings_post();
        }
    }

    private static function handle_settings_post(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage social settings.', 'dr-purg-social-syndicator'));
        }

        check_admin_referer('dpj_social_save_settings', 'dpj_social_settings_nonce');

        $settings = [
            'facebook_page_id' => isset($_POST['facebook_page_id']) ? sanitize_text_field(wp_unslash((string) $_POST['facebook_page_id'])) : '',
            'facebook_app_id' => isset($_POST['facebook_app_id']) ? sanitize_text_field(wp_unslash((string) $_POST['facebook_app_id'])) : '',
            'facebook_app_secret' => isset($_POST['facebook_app_secret']) ? sanitize_text_field(wp_unslash((string) $_POST['facebook_app_secret'])) : '',
            'facebook_page_access_token' => isset($_POST['facebook_page_access_token']) ? sanitize_text_field(wp_unslash((string) $_POST['facebook_page_access_token'])) : '',
            'facebook_graph_version' => isset($_POST['facebook_graph_version']) ? self::clean_graph_version(sanitize_text_field(wp_unslash((string) $_POST['facebook_graph_version']))) : 'v24.0',
            'pixazo_api_key' => isset($_POST['pixazo_api_key']) ? sanitize_text_field(wp_unslash((string) $_POST['pixazo_api_key'])) : '',
            'pixazo_model' => 'sdxl_v1_free',
            'redirect_after_publish' => isset($_POST['redirect_after_publish']) ? '1' : '0',
        ];

        update_option(self::SETTINGS_OPTION, $settings, false);

        wp_safe_redirect(add_query_arg(['page' => self::SETTINGS_SLUG, 'dpj_social_notice' => 'settings_saved'], admin_url('admin.php')));
        exit;
    }

    private static function handle_editor_post(): void
    {
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $post = get_post($post_id);
        if (!self::post_type_is_supported($post) || !current_user_can('edit_post', $post_id)) {
            wp_die(esc_html__('You do not have permission to edit this social package.', 'dr-purg-social-syndicator'));
        }

        check_admin_referer('dpj_social_save_editor_' . $post_id, 'dpj_social_editor_nonce');

        self::ensure_social_package($post_id);
        self::save_editor_fields($post_id);

        $action = sanitize_key(wp_unslash((string) $_POST['dpj_social_editor_action']));
        $notice = 'saved';

        if ($action === 'post_facebook') {
            $result = self::post_to_facebook($post_id);
            $notice = is_wp_error($result) ? 'facebook_failed' : 'facebook_posted';
        } elseif ($action === 'reset_facebook') {
            self::reset_facebook($post_id);
            $notice = 'facebook_reset';
        } elseif ($action === 'generate_ai_social_draft') {
            $result = self::generate_ai_social_draft($post_id);
            $notice = is_wp_error($result) ? 'ai_draft_failed' : 'ai_draft_generated';
        } elseif ($action === 'generate_social_images') {
            $result = self::generate_social_images_for_post($post_id);
            $notice = is_wp_error($result) ? 'images_failed' : 'images_generated';
        } elseif ($action === 'generate_pixazo_images') {
            $result = self::generate_pixazo_images_for_post($post_id);
            $notice = is_wp_error($result) ? 'pixazo_failed' : 'pixazo_generated';
        } elseif ($action === 'mark_pinterest_posted') {
            update_post_meta($post_id, '_dpj_social_pinterest_status', self::STATUS_POSTED);
            $notice = 'pinterest_posted';
        } elseif ($action === 'mark_reddit_posted') {
            update_post_meta($post_id, '_dpj_social_reddit_status', self::STATUS_POSTED);
            $notice = 'reddit_posted';
        } elseif ($action === 'skip_social') {
            update_post_meta($post_id, '_dpj_social_skip', '1');
            $notice = 'skipped';
        } elseif ($action === 'unskip_social') {
            update_post_meta($post_id, '_dpj_social_skip', '0');
            $notice = 'unskipped';
        }

        wp_safe_redirect(self::editor_url($post_id, ['dpj_social_notice' => $notice]));
        exit;
    }

    private static function save_editor_fields(int $post_id): void
    {
        $text_fields = [
            '_dpj_social_facebook_hook' => 'facebook_hook',
            '_dpj_social_facebook_summary' => 'facebook_summary',
            '_dpj_social_facebook_link' => 'facebook_link',
            '_dpj_social_facebook_first_comment' => 'facebook_first_comment',
            '_dpj_social_pixazo_prompt' => 'pixazo_prompt',
            '_dpj_social_pixazo_negative_prompt' => 'pixazo_negative_prompt',
            '_dpj_social_local_overlay_text' => 'local_overlay_text',
            '_dpj_social_local_hint_text' => 'local_hint_text',
            '_dpj_social_pinterest_title' => 'pinterest_title',
            '_dpj_social_pinterest_description' => 'pinterest_description',
            '_dpj_social_pinterest_board' => 'pinterest_board',
            '_dpj_social_pinterest_url' => 'pinterest_url',
            '_dpj_social_pinterest_alt_text' => 'pinterest_alt_text',
            '_dpj_social_reddit_subreddit' => 'reddit_subreddit',
            '_dpj_social_reddit_title' => 'reddit_title',
            '_dpj_social_reddit_body' => 'reddit_body',
            '_dpj_social_reddit_link' => 'reddit_link',
            '_dpj_social_reddit_rules_notes' => 'reddit_rules_notes',
        ];

        foreach ($text_fields as $meta_key => $field_name) {
            $value = isset($_POST[$field_name]) ? wp_unslash((string) $_POST[$field_name]) : '';
            if (str_ends_with($meta_key, '_link') || str_ends_with($meta_key, '_url')) {
                $value = esc_url_raw(trim($value));
            } elseif (str_contains($meta_key, 'body') || str_contains($meta_key, 'summary') || str_contains($meta_key, 'description') || str_contains($meta_key, 'comment') || str_contains($meta_key, 'notes') || str_contains($meta_key, 'prompt') || str_contains($meta_key, 'overlay') || str_contains($meta_key, 'hint')) {
                $value = sanitize_textarea_field($value);
            } else {
                $value = sanitize_text_field($value);
            }

            update_post_meta($post_id, $meta_key, $value);
        }

        $facebook_link = trim((string) get_post_meta($post_id, '_dpj_social_facebook_link', true));
        if ($facebook_link !== '') {
            $facebook_summary = self::strip_article_url_from_text(
                (string) get_post_meta($post_id, '_dpj_social_facebook_summary', true),
                $facebook_link
            );
            update_post_meta($post_id, '_dpj_social_facebook_summary', $facebook_summary);

            if (trim((string) get_post_meta($post_id, '_dpj_social_facebook_first_comment', true)) === '') {
                update_post_meta($post_id, '_dpj_social_facebook_first_comment', self::default_facebook_first_comment($facebook_link));
            }
        }

        update_post_meta($post_id, '_dpj_social_facebook_media_id', isset($_POST['facebook_media_id']) ? (string) absint($_POST['facebook_media_id']) : '0');
        update_post_meta($post_id, '_dpj_social_pinterest_media_id', isset($_POST['pinterest_media_id']) ? (string) absint($_POST['pinterest_media_id']) : '0');
        $reddit_media_id = isset($_POST['reddit_media_id']) ? absint($_POST['reddit_media_id']) : 0;
        update_post_meta($post_id, '_dpj_social_reddit_media_id', (string) $reddit_media_id);
        update_post_meta($post_id, '_dpj_social_og_media_id', (string) $reddit_media_id);
        update_post_meta($post_id, '_dpj_social_use_featured_image', isset($_POST['use_featured_image']) ? '1' : '0');
        update_post_meta($post_id, '_dpj_social_redirect_after_publish', isset($_POST['redirect_after_publish']) ? '1' : '0');
        update_post_meta($post_id, '_dpj_social_do_not_repost', isset($_POST['do_not_repost']) ? '1' : '0');
        update_post_meta($post_id, '_dpj_social_local_overlay_enable', isset($_POST['local_overlay_enable']) ? '1' : '0');
        update_post_meta($post_id, '_dpj_social_local_hint_enable', isset($_POST['local_hint_enable']) ? '1' : '0');

        if (self::facebook_status($post_id) !== self::STATUS_POSTED) {
            update_post_meta($post_id, '_dpj_social_facebook_status', self::STATUS_DRAFT);
        }
        if (!in_array(self::platform_status($post_id, 'pinterest'), [self::STATUS_POSTED, self::STATUS_SKIPPED], true)) {
            update_post_meta($post_id, '_dpj_social_pinterest_status', self::STATUS_DRAFT);
        }
        if (!in_array(self::platform_status($post_id, 'reddit'), [self::STATUS_POSTED, self::STATUS_SKIPPED], true)) {
            update_post_meta($post_id, '_dpj_social_reddit_status', self::STATUS_DRAFT);
        }
    }

    private static function ensure_social_package(int $post_id): void
    {
        $post = get_post($post_id);
        if (!self::post_type_is_supported($post)) {
            return;
        }

        $created = (string) get_post_meta($post_id, '_dpj_social_package_created', true);
        $title = get_the_title($post_id);
        $intro = self::source_intro($post_id);
        $permalink = get_permalink($post_id) ?: '';
        $featured_id = get_post_thumbnail_id($post_id);
        $image_alt = $featured_id ? trim((string) get_post_meta($featured_id, '_wp_attachment_image_alt', true)) : '';

        self::maybe_set_meta($post_id, '_dpj_social_facebook_hook', $title);
        self::maybe_set_meta($post_id, '_dpj_social_facebook_summary', $intro);
        self::maybe_set_meta($post_id, '_dpj_social_facebook_link', $permalink);
        self::maybe_set_meta($post_id, '_dpj_social_facebook_media_id', (string) $featured_id);
        self::maybe_set_meta($post_id, '_dpj_social_facebook_first_comment', self::default_facebook_first_comment($permalink));
        self::maybe_set_meta($post_id, '_dpj_social_facebook_status', self::STATUS_NEEDS);
        self::maybe_set_meta($post_id, '_dpj_social_pixazo_prompt', self::default_pixazo_prompt($post_id));
        self::maybe_set_meta($post_id, '_dpj_social_pixazo_negative_prompt', self::default_pixazo_negative_prompt());
        self::maybe_set_meta($post_id, '_dpj_social_local_overlay_text', self::default_local_overlay_text($post_id));
        self::maybe_set_meta($post_id, '_dpj_social_local_overlay_enable', '1');
        self::maybe_set_meta($post_id, '_dpj_social_local_hint_text', self::default_local_hint_text());
        self::maybe_set_meta($post_id, '_dpj_social_local_hint_enable', '0');

        self::maybe_set_meta($post_id, '_dpj_social_pinterest_title', $title);
        self::maybe_set_meta($post_id, '_dpj_social_pinterest_description', $intro);
        self::maybe_set_meta($post_id, '_dpj_social_pinterest_media_id', (string) $featured_id);
        self::maybe_set_meta($post_id, '_dpj_social_pinterest_board', '');
        self::maybe_set_meta($post_id, '_dpj_social_pinterest_url', $permalink);
        self::maybe_set_meta($post_id, '_dpj_social_pinterest_alt_text', $image_alt);
        self::maybe_set_meta($post_id, '_dpj_social_pinterest_status', self::STATUS_NEEDS);

        self::maybe_set_meta($post_id, '_dpj_social_reddit_subreddit', '');
        self::maybe_set_meta($post_id, '_dpj_social_reddit_title', $title);
        self::maybe_set_meta($post_id, '_dpj_social_reddit_body', trim($intro . "\n\n" . $permalink));
        self::maybe_set_meta($post_id, '_dpj_social_reddit_link', $permalink);
        self::maybe_set_meta($post_id, '_dpj_social_reddit_media_id', (string) $featured_id);
        self::maybe_set_meta($post_id, '_dpj_social_reddit_rules_notes', '');
        self::maybe_set_meta($post_id, '_dpj_social_reddit_status', self::STATUS_NEEDS);

        self::maybe_set_meta($post_id, '_dpj_social_use_featured_image', '1');
        self::maybe_set_meta($post_id, '_dpj_social_redirect_after_publish', self::settings()['redirect_after_publish'] === '1' ? '1' : '0');
        self::maybe_set_meta($post_id, '_dpj_social_do_not_repost', '1');
        self::maybe_set_meta($post_id, '_dpj_social_skip', '0');

        if ($created === '') {
            update_post_meta($post_id, '_dpj_social_package_created', '1');
            update_post_meta($post_id, '_dpj_social_package_created_at', gmdate('c'));
        }
    }

    private static function maybe_set_meta(int $post_id, string $key, string $value): void
    {
        if ((string) get_post_meta($post_id, $key, true) !== '') {
            return;
        }

        update_post_meta($post_id, $key, $value);
    }

    private static function default_facebook_first_comment(string $permalink): string
    {
        $permalink = trim($permalink);
        if ($permalink === '') {
            return '';
        }

        return sprintf(
            /* translators: %s is the article URL. */
            __('Read the full article here: %s', 'dr-purg-social-syndicator'),
            $permalink
        );
    }

    private static function default_pixazo_prompt(int $post_id): string
    {
        $title = get_the_title($post_id);
        $intro = self::source_intro($post_id);
        $categories = implode(', ', wp_get_post_categories($post_id, ['fields' => 'names']));

        return trim(sprintf(
            "Create a realistic, click-worthy editorial health image for a mobile viral health article.\nArticle title: %s\nIntro angle: %s\nCategory: %s\nVisual direction: human-centered, modern home or clinic-adjacent setting, clear body-signal theme, clean natural light, sage green and burgundy accents, trustworthy but scroll-stopping, no text inside the image, no logos, no medical claims.",
            $title,
            $intro,
            $categories
        ));
    }

    private static function default_pixazo_negative_prompt(): string
    {
        return 'text, words, captions, logo, watermark, gore, blood, surgery, scary hospital, panic, before and after, miracle cure, pills spilling, deformed hands, extra fingers, distorted face, blurry, low quality, spammy clickbait';
    }

    private static function default_local_overlay_text(int $post_id): string
    {
        $hook = trim((string) get_post_meta($post_id, '_dpj_social_facebook_hook', true));
        $text = $hook !== '' ? $hook : get_the_title($post_id);
        return self::short_overlay_text($text);
    }

    private static function default_local_hint_text(): string
    {
        return __('LINK IN FIRST COMMENT', 'dr-purg-social-syndicator');
    }

    private static function pixazo_generated_meta_key(string $variant): string
    {
        return '_dpj_social_pixazo_' . sanitize_key($variant) . '_media_id';
    }

    private static function strip_article_url_from_text(string $text, string $permalink): string
    {
        $text = trim($text);
        $permalink = trim($permalink);
        if ($text === '' || $permalink === '') {
            return $text;
        }

        $variants = array_unique([
            $permalink,
            untrailingslashit($permalink),
            trailingslashit($permalink),
        ]);

        foreach ($variants as $variant) {
            if ($variant !== '') {
                $text = str_replace($variant, '', $text);
            }
        }

        $text = (string) preg_replace("/[ \t]+\n/", "\n", $text);
        $text = (string) preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    private static function source_intro(int $post_id): string
    {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post) {
            return '';
        }

        $candidates = [
            (string) $post->post_excerpt,
            (string) get_post_meta($post_id, '_kepoli_meta_description', true),
            (string) $post->post_content,
        ];

        foreach ($candidates as $candidate) {
            $candidate = wp_strip_all_tags(strip_shortcodes($candidate));
            $candidate = html_entity_decode($candidate, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8');
            $candidate = trim((string) preg_replace('/\s+/', ' ', $candidate));
            if ($candidate !== '') {
                return wp_trim_words($candidate, 34, '');
            }
        }

        return '';
    }

    private static function status_label(string $status): string
    {
        return [
            self::STATUS_NEEDS => __('Needs social', 'dr-purg-social-syndicator'),
            self::STATUS_DRAFT => __('Draft', 'dr-purg-social-syndicator'),
            self::STATUS_POSTED => __('Posted', 'dr-purg-social-syndicator'),
            self::STATUS_FAILED => __('Failed', 'dr-purg-social-syndicator'),
            self::STATUS_SKIPPED => __('Skipped', 'dr-purg-social-syndicator'),
        ][$status] ?? __('Needs social', 'dr-purg-social-syndicator');
    }

    private static function platform_status(int $post_id, string $platform): string
    {
        $status = (string) get_post_meta($post_id, '_dpj_social_' . $platform . '_status', true);
        return in_array($status, [self::STATUS_NEEDS, self::STATUS_DRAFT, self::STATUS_POSTED, self::STATUS_FAILED, self::STATUS_SKIPPED], true)
            ? $status
            : self::STATUS_NEEDS;
    }

    private static function facebook_status(int $post_id): string
    {
        return self::platform_status($post_id, 'facebook');
    }

    private static function queue_status(int $post_id): string
    {
        if ((string) get_post_meta($post_id, '_dpj_social_skip', true) === '1') {
            return self::STATUS_SKIPPED;
        }

        return self::facebook_status($post_id);
    }

    private static function notice_message(string $notice): string
    {
        return [
            'created' => __('Social package created. Complete the platform fields before sharing.', 'dr-purg-social-syndicator'),
            'saved' => __('Social draft saved.', 'dr-purg-social-syndicator'),
            'settings_saved' => __('Social settings saved.', 'dr-purg-social-syndicator'),
            'facebook_posted' => __('Facebook post sent and logged.', 'dr-purg-social-syndicator'),
            'facebook_failed' => __('Facebook posting failed. Review the error in the Facebook section.', 'dr-purg-social-syndicator'),
            'facebook_reset' => __('Facebook posting lock reset. You can post this package again.', 'dr-purg-social-syndicator'),
            'ai_draft_generated' => __('AI social draft generated. Review every field before posting.', 'dr-purg-social-syndicator'),
            'ai_draft_failed' => __('AI social draft failed. Review the assistant message.', 'dr-purg-social-syndicator'),
            'images_generated' => __('Local social cards generated and assigned.', 'dr-purg-social-syndicator'),
            'images_failed' => __('Social image generation failed. Review the converter message.', 'dr-purg-social-syndicator'),
            'pixazo_generated' => __('Pixazo images generated and assigned.', 'dr-purg-social-syndicator'),
            'pixazo_failed' => __('Pixazo image generation failed. Review the AI image generator message.', 'dr-purg-social-syndicator'),
            'pinterest_posted' => __('Pinterest item marked posted.', 'dr-purg-social-syndicator'),
            'reddit_posted' => __('Reddit item marked posted.', 'dr-purg-social-syndicator'),
            'skipped' => __('Social work skipped for this post.', 'dr-purg-social-syndicator'),
            'unskipped' => __('Social work restored for this post.', 'dr-purg-social-syndicator'),
        ][$notice] ?? '';
    }

    private static function render_notice_from_query(): void
    {
        $notice = isset($_GET['dpj_social_notice']) ? sanitize_key(wp_unslash((string) $_GET['dpj_social_notice'])) : '';
        $message = $notice !== '' ? self::notice_message($notice) : '';
        if ($message === '') {
            return;
        }

        $class = str_contains($notice, 'failed') ? 'notice notice-error' : 'notice notice-success';
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    }

    public static function render_queue_page(): void
    {
        if (!self::can_manage()) {
            wp_die(esc_html__('You do not have permission to view the social queue.', 'dr-purg-social-syndicator'));
        }

        $active_status = isset($_GET['status']) ? sanitize_key(wp_unslash((string) $_GET['status'])) : 'all';
        $allowed_filters = ['all', self::STATUS_NEEDS, self::STATUS_DRAFT, self::STATUS_POSTED, self::STATUS_FAILED, self::STATUS_SKIPPED];
        if (!in_array($active_status, $allowed_filters, true)) {
            $active_status = 'all';
        }

        $posts = get_posts([
            'post_type' => 'post',
            'post_status' => ['publish', 'future'],
            'posts_per_page' => 100,
            'orderby' => 'modified',
            'order' => 'DESC',
        ]);

        $rows = [];
        foreach ($posts as $post) {
            if (!$post instanceof WP_Post) {
                continue;
            }
            $status = self::queue_status($post->ID);
            if ($active_status !== 'all' && $status !== $active_status) {
                continue;
            }
            $rows[] = [$post, $status];
        }

        ?>
        <div class="wrap dpj-social-wrap">
            <h1><?php esc_html_e('Social Queue', 'dr-purg-social-syndicator'); ?></h1>
            <?php self::render_notice_from_query(); ?>
            <p><?php esc_html_e('Review article social packages before posting them to Facebook or copying manual platform drafts.', 'dr-purg-social-syndicator'); ?></p>
            <nav class="nav-tab-wrapper dpj-social-tabs" aria-label="<?php esc_attr_e('Social queue filters', 'dr-purg-social-syndicator'); ?>">
                <?php foreach ($allowed_filters as $filter) : ?>
                    <a class="nav-tab <?php echo $filter === $active_status ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(self::queue_url(['status' => $filter])); ?>">
                        <?php echo esc_html($filter === 'all' ? __('All', 'dr-purg-social-syndicator') : self::status_label($filter)); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <table class="widefat striped dpj-social-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Post', 'dr-purg-social-syndicator'); ?></th>
                        <th><?php esc_html_e('Status', 'dr-purg-social-syndicator'); ?></th>
                        <th><?php esc_html_e('Facebook', 'dr-purg-social-syndicator'); ?></th>
                        <th><?php esc_html_e('Pinterest', 'dr-purg-social-syndicator'); ?></th>
                        <th><?php esc_html_e('Reddit', 'dr-purg-social-syndicator'); ?></th>
                        <th><?php esc_html_e('Actions', 'dr-purg-social-syndicator'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []) : ?>
                        <tr><td colspan="6"><?php esc_html_e('No posts match this filter.', 'dr-purg-social-syndicator'); ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as [$post, $status]) : ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html(get_the_title($post)); ?></strong>
                                <div class="row-actions">
                                    <span><a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>"><?php esc_html_e('Edit article', 'dr-purg-social-syndicator'); ?></a></span>
                                    |
                                    <span><a href="<?php echo esc_url(get_permalink($post)); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('View', 'dr-purg-social-syndicator'); ?></a></span>
                                </div>
                            </td>
                            <td><span class="dpj-status dpj-status--<?php echo esc_attr($status); ?>"><?php echo esc_html(self::status_label($status)); ?></span></td>
                            <td><?php echo esc_html(self::status_label(self::platform_status($post->ID, 'facebook'))); ?></td>
                            <td><?php echo esc_html(self::status_label(self::platform_status($post->ID, 'pinterest'))); ?></td>
                            <td><?php echo esc_html(self::status_label(self::platform_status($post->ID, 'reddit'))); ?></td>
                            <td><a class="button button-primary" href="<?php echo esc_url(self::editor_url($post->ID)); ?>"><?php esc_html_e('Open Social Editor', 'dr-purg-social-syndicator'); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage social settings.', 'dr-purg-social-syndicator'));
        }

        $settings = self::settings();
        ?>
        <div class="wrap dpj-social-wrap">
            <h1><?php esc_html_e('Social Settings', 'dr-purg-social-syndicator'); ?></h1>
            <?php self::render_notice_from_query(); ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=' . self::SETTINGS_SLUG)); ?>" class="dpj-social-card">
                <?php wp_nonce_field('dpj_social_save_settings', 'dpj_social_settings_nonce'); ?>
                <input type="hidden" name="dpj_social_settings_action" value="save">
                <h2><?php esc_html_e('Facebook Page API', 'dr-purg-social-syndicator'); ?></h2>
                <p><?php esc_html_e('Use a long-lived Facebook Page access token with pages_manage_posts and pages_read_engagement. First-comment posting may also require pages_manage_engagement. This plugin does not post to personal profiles.', 'dr-purg-social-syndicator'); ?></p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="facebook_page_id"><?php esc_html_e('Facebook Page ID', 'dr-purg-social-syndicator'); ?></label></th>
                        <td><input class="regular-text" id="facebook_page_id" name="facebook_page_id" value="<?php echo esc_attr($settings['facebook_page_id']); ?>" autocomplete="off"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="facebook_app_id"><?php esc_html_e('Meta App ID', 'dr-purg-social-syndicator'); ?></label></th>
                        <td><input class="regular-text" id="facebook_app_id" name="facebook_app_id" value="<?php echo esc_attr($settings['facebook_app_id']); ?>" autocomplete="off"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="facebook_app_secret"><?php esc_html_e('Meta App Secret', 'dr-purg-social-syndicator'); ?></label></th>
                        <td><input class="regular-text" type="password" id="facebook_app_secret" name="facebook_app_secret" value="<?php echo esc_attr($settings['facebook_app_secret']); ?>" autocomplete="new-password"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="facebook_page_access_token"><?php esc_html_e('Long-lived Page Access Token', 'dr-purg-social-syndicator'); ?></label></th>
                        <td><textarea class="large-text code" rows="3" id="facebook_page_access_token" name="facebook_page_access_token" autocomplete="off"><?php echo esc_textarea($settings['facebook_page_access_token']); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="facebook_graph_version"><?php esc_html_e('Graph API version', 'dr-purg-social-syndicator'); ?></label></th>
                        <td><input class="regular-text" id="facebook_graph_version" name="facebook_graph_version" value="<?php echo esc_attr(self::clean_graph_version($settings['facebook_graph_version'])); ?>" placeholder="v24.0"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="pixazo_api_key"><?php esc_html_e('Pixazo API key', 'dr-purg-social-syndicator'); ?></label></th>
                        <td>
                            <input class="regular-text" type="password" id="pixazo_api_key" name="pixazo_api_key" value="<?php echo esc_attr($settings['pixazo_api_key']); ?>" autocomplete="new-password">
                            <p class="description"><?php esc_html_e('Used only when you click Generate Pixazo images. The first test provider is Pixazo SDXL v1.0 Free.', 'dr-purg-social-syndicator'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('AI social drafts', 'dr-purg-social-syndicator'); ?></th>
                        <td>
                            <p>
                                <?php if (self::social_ai_enabled()) : ?>
                                    <span class="dpj-status dpj-status--posted"><?php esc_html_e('Enabled', 'dr-purg-social-syndicator'); ?></span>
                                <?php else : ?>
                                    <span class="dpj-status dpj-status--skipped"><?php esc_html_e('Disabled', 'dr-purg-social-syndicator'); ?></span>
                                <?php endif; ?>
                            </p>
                            <p class="description">
                                <?php printf(esc_html__('Uses environment variables only: SOCIAL_AI_ENABLE or AI_EXTRACTION_ENABLE, SOCIAL_AI_API_KEY or AI_EXTRACTION_API_KEY, and model %s. Drafts are reviewed before posting.', 'dr-purg-social-syndicator'), esc_html(self::social_ai_model())); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Publishing workflow', 'dr-purg-social-syndicator'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="redirect_after_publish" value="1" <?php checked($settings['redirect_after_publish'], '1'); ?>>
                                <?php esc_html_e('Open the Social Editor after first publish.', 'dr-purg-social-syndicator'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Save social settings', 'dr-purg-social-syndicator')); ?>
            </form>
        </div>
        <?php
    }

    public static function render_editor_page(): void
    {
        if (!self::can_manage()) {
            wp_die(esc_html__('You do not have permission to edit social packages.', 'dr-purg-social-syndicator'));
        }

        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
        $post = get_post($post_id);
        if (!self::post_type_is_supported($post) || !current_user_can('edit_post', $post_id)) {
            wp_die(esc_html__('Choose a valid article post for the Social Editor.', 'dr-purg-social-syndicator'));
        }

        self::ensure_social_package($post_id);

        $source = self::source_payload($post_id);
        ?>
        <div class="wrap dpj-social-wrap dpj-social-editor">
            <h1><?php esc_html_e('Social Editor', 'dr-purg-social-syndicator'); ?></h1>
            <?php self::render_notice_from_query(); ?>
            <p>
                <a href="<?php echo esc_url(self::queue_url()); ?>">&larr; <?php esc_html_e('Back to Social Queue', 'dr-purg-social-syndicator'); ?></a>
                |
                <a href="<?php echo esc_url(get_edit_post_link($post_id)); ?>"><?php esc_html_e('Edit article', 'dr-purg-social-syndicator'); ?></a>
                |
                <a href="<?php echo esc_url(get_permalink($post_id)); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('View article', 'dr-purg-social-syndicator'); ?></a>
            </p>
            <form method="post" action="<?php echo esc_url(self::editor_url($post_id)); ?>">
                <?php wp_nonce_field('dpj_social_save_editor_' . $post_id, 'dpj_social_editor_nonce'); ?>
                <input type="hidden" name="post_id" value="<?php echo esc_attr((string) $post_id); ?>">

                <?php self::render_source_section($source); ?>
                <?php self::render_ai_social_draft_section($post_id); ?>
                <?php self::render_pixazo_section($post_id); ?>
                <?php self::render_social_image_converter_section($post_id); ?>
                <?php self::render_facebook_section($post_id); ?>
                <?php self::render_pinterest_section($post_id); ?>
                <?php self::render_reddit_section($post_id); ?>
                <?php self::render_post_settings_section($post_id); ?>

                <p class="submit dpj-social-submit">
                    <button class="button button-primary" type="submit" name="dpj_social_editor_action" value="save"><?php esc_html_e('Save social draft', 'dr-purg-social-syndicator'); ?></button>
                    <button class="button" type="submit" name="dpj_social_editor_action" value="skip_social"><?php esc_html_e('Skip social for this post', 'dr-purg-social-syndicator'); ?></button>
                    <button class="button" type="submit" name="dpj_social_editor_action" value="unskip_social"><?php esc_html_e('Restore social work', 'dr-purg-social-syndicator'); ?></button>
                </p>
            </form>
        </div>
        <?php
    }

    private static function source_payload(int $post_id): array
    {
        $featured_id = get_post_thumbnail_id($post_id);
        return [
            'title' => get_the_title($post_id),
            'intro' => self::source_intro($post_id),
            'url' => get_permalink($post_id) ?: '',
            'featured_id' => $featured_id,
            'image_html' => $featured_id ? wp_get_attachment_image($featured_id, 'medium', false, ['class' => 'dpj-source-image']) : '',
            'categories' => implode(', ', wp_get_post_categories($post_id, ['fields' => 'names'])),
            'published' => get_the_date('', $post_id),
        ];
    }

    private static function render_source_section(array $source): void
    {
        ?>
        <section class="dpj-social-card dpj-social-source">
            <div>
                <h2><?php esc_html_e('Source Material', 'dr-purg-social-syndicator'); ?></h2>
                <dl class="dpj-source-list">
                    <dt><?php esc_html_e('Title', 'dr-purg-social-syndicator'); ?></dt>
                    <dd><?php echo esc_html($source['title']); ?></dd>
                    <dt><?php esc_html_e('Intro', 'dr-purg-social-syndicator'); ?></dt>
                    <dd><?php echo esc_html($source['intro']); ?></dd>
                    <dt><?php esc_html_e('Article URL', 'dr-purg-social-syndicator'); ?></dt>
                    <dd><a href="<?php echo esc_url($source['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($source['url']); ?></a></dd>
                    <dt><?php esc_html_e('Categories', 'dr-purg-social-syndicator'); ?></dt>
                    <dd><?php echo esc_html($source['categories'] ?: __('No category', 'dr-purg-social-syndicator')); ?></dd>
                    <dt><?php esc_html_e('Published', 'dr-purg-social-syndicator'); ?></dt>
                    <dd><?php echo esc_html($source['published']); ?></dd>
                </dl>
            </div>
            <div class="dpj-source-media">
                <?php echo $source['image_html'] !== '' ? wp_kses_post($source['image_html']) : '<span class="dpj-media-empty">' . esc_html__('No featured image', 'dr-purg-social-syndicator') . '</span>'; ?>
            </div>
        </section>
        <?php
    }

    private static function render_ai_social_draft_section(int $post_id): void
    {
        $enabled = self::social_ai_enabled();
        $last_error = (string) get_post_meta($post_id, '_dpj_social_ai_last_error', true);
        $last_run = (string) get_post_meta($post_id, '_dpj_social_ai_last_run', true);
        $model = (string) get_post_meta($post_id, '_dpj_social_ai_model', true);
        if ($model === '') {
            $model = self::social_ai_model();
        }
        ?>
        <section class="dpj-social-card dpj-platform dpj-platform--ai-social">
            <header class="dpj-platform__header">
                <h2><?php esc_html_e('AI Social Draft Assistant', 'dr-purg-social-syndicator'); ?></h2>
                <button class="button button-primary" type="submit" name="dpj_social_editor_action" value="generate_ai_social_draft" <?php disabled(!$enabled); ?>><?php esc_html_e('Generate AI social draft', 'dr-purg-social-syndicator'); ?></button>
            </header>
            <?php if (!$enabled) : ?>
                <div class="notice notice-warning inline"><p><?php esc_html_e('Enable SOCIAL_AI_ENABLE=1 or AI_EXTRACTION_ENABLE=1 and provide an OpenRouter API key in the environment before generating drafts.', 'dr-purg-social-syndicator'); ?></p></div>
            <?php endif; ?>
            <?php if ($last_error !== '') : ?>
                <div class="notice notice-error inline"><p><?php echo esc_html($last_error); ?></p></div>
            <?php endif; ?>
            <p class="dpj-social-note">
                <?php esc_html_e('Creates reviewed drafts for Facebook, Pinterest, Reddit, overlay text, and the optional bottom hint. It never posts automatically and never changes Facebook remote IDs.', 'dr-purg-social-syndicator'); ?>
            </p>
            <p class="dpj-social-note">
                <?php printf(esc_html__('Model: %s', 'dr-purg-social-syndicator'), esc_html($model)); ?>
                <?php if ($last_run !== '') : ?>
                    <?php printf(esc_html__(' Last run: %s', 'dr-purg-social-syndicator'), esc_html($last_run)); ?>
                <?php endif; ?>
            </p>
        </section>
        <?php
    }

    private static function render_pixazo_section(int $post_id): void
    {
        $settings = self::settings();
        $has_key = trim((string) $settings['pixazo_api_key']) !== '';
        $last_error = (string) get_post_meta($post_id, '_dpj_social_pixazo_last_error', true);
        $last_run = (string) get_post_meta($post_id, '_dpj_social_pixazo_last_run', true);
        ?>
        <section class="dpj-social-card dpj-platform dpj-platform--pixazo">
            <header class="dpj-platform__header">
                <h2><?php esc_html_e('AI Image Generator', 'dr-purg-social-syndicator'); ?></h2>
                <button class="button button-primary" type="submit" name="dpj_social_editor_action" value="generate_pixazo_images" <?php disabled(!$has_key); ?>><?php esc_html_e('Generate Pixazo images', 'dr-purg-social-syndicator'); ?></button>
            </header>
            <?php if (!$has_key) : ?>
                <div class="notice notice-warning inline"><p><?php esc_html_e('Add your Pixazo API key in Social Settings before generating images.', 'dr-purg-social-syndicator'); ?></p></div>
            <?php endif; ?>
            <?php if ($last_error !== '') : ?>
                <div class="notice notice-error inline"><p><?php echo esc_html($last_error); ?></p></div>
            <?php endif; ?>
            <?php if ($last_run !== '') : ?>
                <p class="dpj-social-note"><?php printf(esc_html__('Last Pixazo run: %s', 'dr-purg-social-syndicator'), esc_html($last_run)); ?></p>
            <?php endif; ?>
            <p class="dpj-social-note"><?php esc_html_e('Generates separate reviewable images for Facebook, Pinterest, and OG/Reddit using Pixazo SDXL v1.0 Free. No image is posted automatically.', 'dr-purg-social-syndicator'); ?></p>
            <?php self::render_textarea('pixazo_prompt', __('Image prompt', 'dr-purg-social-syndicator'), (string) get_post_meta($post_id, '_dpj_social_pixazo_prompt', true), 6, 'dpj-pixazo-prompt'); ?>
            <?php self::render_textarea('pixazo_negative_prompt', __('Negative prompt', 'dr-purg-social-syndicator'), (string) get_post_meta($post_id, '_dpj_social_pixazo_negative_prompt', true), 3, 'dpj-pixazo-negative-prompt'); ?>
            <div class="dpj-generated-grid">
                <?php foreach (self::PIXAZO_PLATFORM_VARIANTS as $variant => $config) : ?>
                    <?php
                    $media_id = (int) get_post_meta($post_id, self::pixazo_generated_meta_key($variant), true);
                    $image = $media_id > 0 ? wp_get_attachment_image($media_id, 'medium', false, ['class' => 'dpj-selected-image']) : '';
                    $media_url = $media_id > 0 ? wp_get_attachment_url($media_id) : '';
                    ?>
                    <div class="dpj-generated-card">
                        <h3><?php echo esc_html((string) $config['label']); ?></h3>
                        <p><?php printf(esc_html__('%1$dx%2$d, platform aspect', 'dr-purg-social-syndicator'), (int) $config['width'], (int) $config['height']); ?></p>
                        <div class="dpj-media-preview">
                            <?php echo $image !== '' ? wp_kses_post($image) : '<span class="dpj-media-empty">' . esc_html__('Not generated yet', 'dr-purg-social-syndicator') . '</span>'; ?>
                        </div>
                        <?php if (is_string($media_url) && $media_url !== '') : ?>
                            <p><a href="<?php echo esc_url($media_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open Pixazo image', 'dr-purg-social-syndicator'); ?></a></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }

    private static function render_social_image_converter_section(int $post_id): void
    {
        $source_id = self::social_image_source_id($post_id);
        $source_label = $source_id > 0 ? get_the_title($source_id) : '';
        $last_error = (string) get_post_meta($post_id, '_dpj_social_image_generation_last_error', true);
        ?>
        <section class="dpj-social-card dpj-platform dpj-platform--image-converter">
            <header class="dpj-platform__header">
                <h2><?php esc_html_e('Social Image Converter', 'dr-purg-social-syndicator'); ?></h2>
                <button class="button button-primary" type="submit" name="dpj_social_editor_action" value="generate_social_images"><?php esc_html_e('Generate local social cards', 'dr-purg-social-syndicator'); ?></button>
            </header>
            <?php if ($last_error !== '') : ?>
                <div class="notice notice-error inline"><p><?php echo esc_html($last_error); ?></p></div>
            <?php endif; ?>
            <p class="dpj-social-note">
                <?php esc_html_e('Creates full-bleed JPG social cards from the source image, sized for Facebook, Pinterest, and OG/Reddit. This uses no external API and never changes the original image.', 'dr-purg-social-syndicator'); ?>
                <?php if ($source_label !== '') : ?>
                    <?php printf(esc_html__(' Source: %s', 'dr-purg-social-syndicator'), esc_html($source_label)); ?>
                <?php endif; ?>
            </p>
            <?php self::render_textarea('local_overlay_text', __('Overlay text', 'dr-purg-social-syndicator'), (string) get_post_meta($post_id, '_dpj_social_local_overlay_text', true), 2, 'dpj-local-overlay-text'); ?>
            <label class="dpj-check">
                <input type="checkbox" name="local_overlay_enable" value="1" <?php checked(get_post_meta($post_id, '_dpj_social_local_overlay_enable', true), '1'); ?>>
                <?php esc_html_e('Add a short readable text overlay to the generated cards.', 'dr-purg-social-syndicator'); ?>
            </label>
            <div class="dpj-social-grid">
                <?php self::render_input('local_hint_text', __('Bottom hint text', 'dr-purg-social-syndicator'), (string) get_post_meta($post_id, '_dpj_social_local_hint_text', true)); ?>
                <label class="dpj-check dpj-check--inline">
                    <input type="checkbox" name="local_hint_enable" value="1" <?php checked(get_post_meta($post_id, '_dpj_social_local_hint_enable', true), '1'); ?>>
                    <?php esc_html_e('Add bottom hint for link-in-comment posts.', 'dr-purg-social-syndicator'); ?>
                </label>
            </div>
            <div class="dpj-generated-grid">
                <?php foreach (self::SOCIAL_IMAGE_VARIANTS as $variant => $config) : ?>
                    <?php
                    $media_id = (int) get_post_meta($post_id, $config['generated_meta'], true);
                    $image = $media_id > 0 ? wp_get_attachment_image($media_id, 'medium', false, ['class' => 'dpj-selected-image']) : '';
                    $media_url = $media_id > 0 ? wp_get_attachment_url($media_id) : '';
                    ?>
                    <div class="dpj-generated-card">
                        <h3><?php echo esc_html((string) $config['label']); ?></h3>
                        <p><?php printf(esc_html__('%1$dx%2$d JPG', 'dr-purg-social-syndicator'), (int) $config['width'], (int) $config['height']); ?></p>
                        <div class="dpj-media-preview">
                            <?php echo $image !== '' ? wp_kses_post($image) : '<span class="dpj-media-empty">' . esc_html__('Not generated yet', 'dr-purg-social-syndicator') . '</span>'; ?>
                        </div>
                        <?php if (is_string($media_url) && $media_url !== '') : ?>
                            <p><a href="<?php echo esc_url($media_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open generated image', 'dr-purg-social-syndicator'); ?></a></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }

    private static function render_facebook_section(int $post_id): void
    {
        $remote_id = (string) get_post_meta($post_id, '_dpj_social_facebook_remote_post_id', true);
        $last_error = (string) get_post_meta($post_id, '_dpj_social_facebook_last_error', true);
        $status = self::facebook_status($post_id);
        ?>
        <section class="dpj-social-card dpj-platform dpj-platform--facebook">
            <header class="dpj-platform__header">
                <h2><?php esc_html_e('Facebook', 'dr-purg-social-syndicator'); ?></h2>
                <span class="dpj-status dpj-status--<?php echo esc_attr($status); ?>"><?php echo esc_html(self::status_label($status)); ?></span>
            </header>
            <?php if ($remote_id !== '') : ?>
                <p class="dpj-social-note"><?php printf(esc_html__('Remote post ID: %s', 'dr-purg-social-syndicator'), esc_html($remote_id)); ?></p>
            <?php endif; ?>
            <?php if ($last_error !== '') : ?>
                <div class="notice notice-error inline"><p><?php echo esc_html($last_error); ?></p></div>
            <?php endif; ?>
            <div class="dpj-social-grid">
                <?php self::render_textarea('facebook_hook', __('Hook', 'dr-purg-social-syndicator'), (string) get_post_meta($post_id, '_dpj_social_facebook_hook', true), 2, 'dpj-facebook-hook'); ?>
                <?php self::render_textarea('facebook_summary', __('Summary', 'dr-purg-social-syndicator'), (string) get_post_meta($post_id, '_dpj_social_facebook_summary', true), 4, 'dpj-facebook-summary'); ?>
            </div>
            <?php self::render_media_picker('facebook_media_id', __('Media', 'dr-purg-social-syndicator'), (int) get_post_meta($post_id, '_dpj_social_facebook_media_id', true)); ?>
            <?php self::render_input('facebook_link', __('Link', 'dr-purg-social-syndicator'), (string) get_post_meta($post_id, '_dpj_social_facebook_link', true), 'url'); ?>
            <?php self::render_textarea('facebook_first_comment', __('First comment', 'dr-purg-social-syndicator'), (string) get_post_meta($post_id, '_dpj_social_facebook_first_comment', true), 3, 'dpj-facebook-first-comment'); ?>
            <div class="dpj-social-preview">
                <h3><?php esc_html_e('Preview text', 'dr-purg-social-syndicator'); ?></h3>
                <pre><?php echo esc_html(self::facebook_message($post_id)); ?></pre>
                <h3><?php esc_html_e('First comment preview', 'dr-purg-social-syndicator'); ?></h3>
                <pre><?php echo esc_html((string) get_post_meta($post_id, '_dpj_social_facebook_first_comment', true)); ?></pre>
            </div>
            <p class="dpj-platform__actions">
                <button class="button button-primary" type="submit" name="dpj_social_editor_action" value="post_facebook"><?php esc_html_e('Post to Facebook', 'dr-purg-social-syndicator'); ?></button>
                <button class="button" type="submit" name="dpj_social_editor_action" value="reset_facebook"><?php esc_html_e('Reset Facebook posting lock', 'dr-purg-social-syndicator'); ?></button>
                <button class="button" type="button" data-dpj-copy="#dpj-facebook-hook"><?php esc_html_e('Copy hook', 'dr-purg-social-syndicator'); ?></button>
                <button class="button" type="button" data-dpj-copy="#dpj-facebook-summary"><?php esc_html_e('Copy summary', 'dr-purg-social-syndicator'); ?></button>
                <button class="button" type="button" data-dpj-copy="#dpj-facebook-first-comment"><?php esc_html_e('Copy first comment', 'dr-purg-social-syndicator'); ?></button>
            </p>
        </section>
        <?php
    }

    private static function render_pinterest_section(int $post_id): void
    {
        $status = self::platform_status($post_id, 'pinterest');
        ?>
        <section class="dpj-social-card dpj-platform dpj-platform--pinterest">
            <header class="dpj-platform__header">
                <h2><?php esc_html_e('Pinterest', 'dr-purg-social-syndicator'); ?></h2>
                <span class="dpj-status dpj-status--<?php echo esc_attr($status); ?>"><?php echo esc_html(self::status_label($status)); ?></span>
            </header>
            <div class="dpj-social-grid">
                <?php self::render_input('pinterest_title', __('Pin title', 'dr-purg-social-syndicator'), (string) get_post_meta($post_id, '_dpj_social_pinterest_title', true)); ?>
                <?php self::render_input('pinterest_board', __('Board', 'dr-purg-social-syndicator'), (string) get_post_meta($post_id, '_dpj_social_pinterest_board', true)); ?>
            </div>
            <?php self::render_textarea('pinterest_description', __('Pin description', 'dr-purg-social-syndicator'), (string) get_post_meta($post_id, '_dpj_social_pinterest_description', true), 4, 'dpj-pinterest-description'); ?>
            <?php self::render_media_picker('pinterest_media_id', __('Media', 'dr-purg-social-syndicator'), (int) get_post_meta($post_id, '_dpj_social_pinterest_media_id', true)); ?>
            <div class="dpj-social-grid">
                <?php self::render_input('pinterest_url', __('Destination URL', 'dr-purg-social-syndicator'), (string) get_post_meta($post_id, '_dpj_social_pinterest_url', true), 'url'); ?>
                <?php self::render_input('pinterest_alt_text', __('Alt text', 'dr-purg-social-syndicator'), (string) get_post_meta($post_id, '_dpj_social_pinterest_alt_text', true)); ?>
            </div>
            <p class="dpj-platform__actions">
                <button class="button" type="button" data-dpj-copy="#dpj-pinterest-description"><?php esc_html_e('Copy description', 'dr-purg-social-syndicator'); ?></button>
                <button class="button" type="submit" name="dpj_social_editor_action" value="mark_pinterest_posted"><?php esc_html_e('Mark Pinterest posted', 'dr-purg-social-syndicator'); ?></button>
            </p>
        </section>
        <?php
    }

    private static function render_reddit_section(int $post_id): void
    {
        $status = self::platform_status($post_id, 'reddit');
        $media_id = (int) get_post_meta($post_id, '_dpj_social_reddit_media_id', true);
        $media_url = $media_id > 0 ? wp_get_attachment_url($media_id) : '';
        ?>
        <section class="dpj-social-card dpj-platform dpj-platform--reddit">
            <header class="dpj-platform__header">
                <h2><?php esc_html_e('Reddit', 'dr-purg-social-syndicator'); ?></h2>
                <span class="dpj-status dpj-status--<?php echo esc_attr($status); ?>"><?php echo esc_html(self::status_label($status)); ?></span>
            </header>
            <div class="dpj-social-grid">
                <?php self::render_input('reddit_subreddit', __('Subreddit', 'dr-purg-social-syndicator'), (string) get_post_meta($post_id, '_dpj_social_reddit_subreddit', true)); ?>
                <?php self::render_input('reddit_link', __('Link', 'dr-purg-social-syndicator'), (string) get_post_meta($post_id, '_dpj_social_reddit_link', true), 'url'); ?>
            </div>
            <?php self::render_input('reddit_title', __('Post title', 'dr-purg-social-syndicator'), (string) get_post_meta($post_id, '_dpj_social_reddit_title', true)); ?>
            <?php self::render_textarea('reddit_body', __('Body', 'dr-purg-social-syndicator'), (string) get_post_meta($post_id, '_dpj_social_reddit_body', true), 6, 'dpj-reddit-body'); ?>
            <?php self::render_media_picker('reddit_media_id', __('Media / OG preview image', 'dr-purg-social-syndicator'), $media_id); ?>
            <?php if (is_string($media_url) && $media_url !== '') : ?>
                <label class="dpj-field">
                    <span><?php esc_html_e('Image URL', 'dr-purg-social-syndicator'); ?></span>
                    <input type="url" id="dpj-reddit-image-url" value="<?php echo esc_url($media_url); ?>" readonly>
                </label>
            <?php endif; ?>
            <p class="dpj-social-note"><?php esc_html_e('For link posts, Reddit usually pulls the image from the article Open Graph tags. Use this field as your visible reference or for image-post/manual workflows.', 'dr-purg-social-syndicator'); ?></p>
            <?php self::render_textarea('reddit_rules_notes', __('Rules notes', 'dr-purg-social-syndicator'), (string) get_post_meta($post_id, '_dpj_social_reddit_rules_notes', true), 3, 'dpj-reddit-notes'); ?>
            <p class="dpj-platform__actions">
                <button class="button" type="button" data-dpj-copy="#dpj-reddit-body"><?php esc_html_e('Copy body', 'dr-purg-social-syndicator'); ?></button>
                <?php if (is_string($media_url) && $media_url !== '') : ?>
                    <button class="button" type="button" data-dpj-copy="#dpj-reddit-image-url"><?php esc_html_e('Copy image URL', 'dr-purg-social-syndicator'); ?></button>
                <?php endif; ?>
                <button class="button" type="submit" name="dpj_social_editor_action" value="mark_reddit_posted"><?php esc_html_e('Mark Reddit posted', 'dr-purg-social-syndicator'); ?></button>
            </p>
        </section>
        <?php
    }

    private static function render_post_settings_section(int $post_id): void
    {
        ?>
        <section class="dpj-social-card dpj-platform dpj-platform--settings">
            <h2><?php esc_html_e('Post Settings', 'dr-purg-social-syndicator'); ?></h2>
            <label class="dpj-check">
                <input type="checkbox" name="use_featured_image" value="1" <?php checked(get_post_meta($post_id, '_dpj_social_use_featured_image', true), '1'); ?>>
                <?php esc_html_e('Use featured image by default for new platform drafts.', 'dr-purg-social-syndicator'); ?>
            </label>
            <label class="dpj-check">
                <input type="checkbox" name="redirect_after_publish" value="1" <?php checked(self::post_redirect_enabled($post_id)); ?>>
                <?php esc_html_e('Open Social Editor after first publish.', 'dr-purg-social-syndicator'); ?>
            </label>
            <label class="dpj-check">
                <input type="checkbox" name="do_not_repost" value="1" <?php checked(get_post_meta($post_id, '_dpj_social_do_not_repost', true), '1'); ?>>
                <?php esc_html_e('Block duplicate Facebook posting after a remote post ID exists.', 'dr-purg-social-syndicator'); ?>
            </label>
            <p><?php printf(esc_html__('Current skip state: %s', 'dr-purg-social-syndicator'), get_post_meta($post_id, '_dpj_social_skip', true) === '1' ? esc_html__('Skipped', 'dr-purg-social-syndicator') : esc_html__('Active', 'dr-purg-social-syndicator')); ?></p>
        </section>
        <?php
    }

    private static function render_input(string $name, string $label, string $value, string $type = 'text'): void
    {
        ?>
        <label class="dpj-field">
            <span><?php echo esc_html($label); ?></span>
            <input type="<?php echo esc_attr($type); ?>" name="<?php echo esc_attr($name); ?>" id="dpj-<?php echo esc_attr(str_replace('_', '-', $name)); ?>" value="<?php echo esc_attr($value); ?>">
        </label>
        <?php
    }

    private static function render_textarea(string $name, string $label, string $value, int $rows, string $id = ''): void
    {
        $id = $id !== '' ? $id : 'dpj-' . str_replace('_', '-', $name);
        ?>
        <label class="dpj-field">
            <span><?php echo esc_html($label); ?></span>
            <textarea name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($id); ?>" rows="<?php echo esc_attr((string) $rows); ?>"><?php echo esc_textarea($value); ?></textarea>
        </label>
        <?php
    }

    private static function render_media_picker(string $name, string $label, int $media_id): void
    {
        $preview = $media_id > 0 ? wp_get_attachment_image($media_id, 'medium', false, ['class' => 'dpj-selected-image']) : '';
        ?>
        <div class="dpj-field dpj-media-field">
            <span><?php echo esc_html($label); ?></span>
            <input type="hidden" name="<?php echo esc_attr($name); ?>" id="dpj-<?php echo esc_attr(str_replace('_', '-', $name)); ?>" value="<?php echo esc_attr((string) $media_id); ?>" data-dpj-media-input>
            <div class="dpj-media-preview" data-dpj-media-preview>
                <?php echo $preview !== '' ? wp_kses_post($preview) : '<span class="dpj-media-empty">' . esc_html__('No image selected', 'dr-purg-social-syndicator') . '</span>'; ?>
            </div>
            <p class="dpj-media-actions">
                <button class="button" type="button" data-dpj-media-select><?php esc_html_e('Choose image', 'dr-purg-social-syndicator'); ?></button>
                <button class="button" type="button" data-dpj-media-clear><?php esc_html_e('Clear image', 'dr-purg-social-syndicator'); ?></button>
            </p>
        </div>
        <?php
    }

    private static function social_image_source_id(int $post_id): int
    {
        $candidates = [
            (int) get_post_thumbnail_id($post_id),
            (int) get_post_meta($post_id, '_dpj_social_facebook_media_id', true),
            (int) get_post_meta($post_id, '_dpj_social_pinterest_media_id', true),
            (int) get_post_meta($post_id, '_dpj_social_reddit_media_id', true),
        ];

        foreach ($candidates as $candidate_id) {
            $candidate_id = self::original_source_media_id($candidate_id);
            if ($candidate_id > 0 && wp_attachment_is_image($candidate_id)) {
                return $candidate_id;
            }
        }

        return 0;
    }

    private static function original_source_media_id(int $media_id): int
    {
        if ($media_id <= 0) {
            return 0;
        }

        $source_id = (int) get_post_meta($media_id, '_dpj_social_generated_source_id', true);
        return $source_id > 0 ? $source_id : $media_id;
    }

    private static function generate_ai_social_draft(int $post_id)
    {
        if (!self::social_ai_enabled()) {
            $error = __('AI social drafts are not enabled. Configure SOCIAL_AI_ENABLE=1 or AI_EXTRACTION_ENABLE=1 and an OpenRouter API key in the environment.', 'dr-purg-social-syndicator');
            update_post_meta($post_id, '_dpj_social_ai_last_error', $error);
            return new WP_Error('dpj_social_ai_disabled', $error);
        }

        $payload = self::request_ai_social_draft($post_id);
        if (is_wp_error($payload)) {
            update_post_meta($post_id, '_dpj_social_ai_last_error', $payload->get_error_message());
            return $payload;
        }

        self::apply_ai_social_draft($post_id, $payload);
        update_post_meta($post_id, '_dpj_social_ai_last_run', gmdate('c'));
        update_post_meta($post_id, '_dpj_social_ai_model', self::social_ai_model());
        delete_post_meta($post_id, '_dpj_social_ai_last_error');

        return true;
    }

    private static function request_ai_social_draft(int $post_id)
    {
        $model = self::social_ai_model();
        $prompt = self::ai_social_prompt($post_id);
        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', [
            'timeout' => self::env_int('SOCIAL_AI_TIMEOUT_SECONDS', self::env_int('AI_EXTRACTION_TIMEOUT_SECONDS', 16, 4, 45), 4, 45),
            'headers' => [
                'Authorization' => 'Bearer ' . self::social_ai_api_key(),
                'Content-Type' => 'application/json',
                'HTTP-Referer' => home_url('/'),
                'X-Title' => 'Dr Purg Jr. Social Syndicator',
            ],
            'body' => wp_json_encode([
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a strict social distribution editor for a responsible health publication. Return only valid JSON. Never diagnose, promise cures, invent facts, or write fear-mongering medical claims.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.55,
                'max_tokens' => self::env_int('SOCIAL_AI_MAX_TOKENS', self::env_int('AI_EXTRACTION_MAX_TOKENS', 1200, 300, 2600), 300, 2600),
                'response_format' => ['type' => 'json_object'],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        if ($status < 200 || $status >= 300) {
            $message = is_array($decoded) && is_array($decoded['error'] ?? null)
                ? (string) ($decoded['error']['message'] ?? __('OpenRouter request failed.', 'dr-purg-social-syndicator'))
                : sprintf(__('OpenRouter returned HTTP error %d.', 'dr-purg-social-syndicator'), $status);
            return new WP_Error('dpj_social_ai_http_error', $message);
        }

        $content = is_array($decoded) ? (string) ($decoded['choices'][0]['message']['content'] ?? '') : '';
        $payload = self::decode_ai_json_object($content);
        if (!is_array($payload)) {
            return new WP_Error('dpj_social_ai_bad_json', __('The AI model did not return valid JSON.', 'dr-purg-social-syndicator'));
        }

        return self::sanitize_ai_social_payload($payload, $post_id);
    }

    private static function ai_social_prompt(int $post_id): string
    {
        $post = get_post($post_id);
        $title = get_the_title($post_id);
        $intro = self::source_intro($post_id);
        $permalink = get_permalink($post_id) ?: '';
        $categories = implode(', ', wp_get_post_categories($post_id, ['fields' => 'names']));
        $tags = implode(', ', wp_get_post_tags($post_id, ['fields' => 'names']));
        $image_alt = '';
        $featured_id = get_post_thumbnail_id($post_id);
        if ($featured_id) {
            $image_alt = trim((string) get_post_meta($featured_id, '_wp_attachment_image_alt', true));
        }

        $content = $post instanceof WP_Post ? self::ai_source_text((string) $post->post_content) : '';
        $schema = '{"facebook_hook":"curiosity hook under 95 characters","facebook_summary":"2 short sentences, 180-260 characters, no URL","facebook_first_comment":"CTA with article URL","pinterest_title":"pin title under 90 characters","pinterest_description":"Pinterest description under 420 characters","pinterest_alt_text":"natural image alt under 125 characters","reddit_title":"calmer Reddit title under 120 characters","reddit_body":"Reddit-safe body with article link at the end","overlay_text":"image overlay hook, 5-9 words","bottom_hint_text":"LINK IN FIRST COMMENT 👇"}';

        return trim(
            "Create a reviewed social distribution draft for Dr Purg Jr., an English-language viral health-facts publication for mobile readers in the United States.\n"
            . "Return ONLY valid JSON, no markdown, no commentary.\n"
            . "Required JSON shape: {$schema}\n\n"
            . "Rules:\n"
            . "- Facebook hook: clickable but calm, no fake urgency, no diagnosis, no cure promise.\n"
            . "- Facebook summary: do NOT include the URL. Do not say 'click the link' in the summary.\n"
            . "- Facebook first comment: include the exact URL and a simple CTA.\n"
            . "- Pinterest can be slightly search-friendly and descriptive.\n"
            . "- Reddit must be less clickbait and more transparent. Include the URL at the end of reddit_body.\n"
            . "- Overlay text should be short enough to read on an image.\n"
            . "- Bottom hint text may use LINK IN FIRST COMMENT with a down pointer; the app will render the arrow safely.\n"
            . "- General health information only. No medical advice, diagnosis, treatment, miracle claims, fear-mongering, or invented facts.\n\n"
            . "Article title: {$title}\n"
            . "Intro/excerpt: {$intro}\n"
            . "Categories: {$categories}\n"
            . "Tags: {$tags}\n"
            . "Featured image alt: {$image_alt}\n"
            . "Canonical URL: {$permalink}\n\n"
            . "Article content:\n{$content}"
        );
    }

    private static function ai_source_text(string $value): string
    {
        $value = (string) preg_replace('/<(br|\/p|\/li|\/h[1-6]|\/div|\/section)\b[^>]*>/i', "\n", $value);
        $value = wp_strip_all_tags(strip_shortcodes($value));
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8');
        $value = (string) preg_replace("/[ \t]+/", ' ', $value);
        $value = (string) preg_replace("/\n{3,}/", "\n\n", $value);
        $value = trim($value);
        $limit = self::env_int('SOCIAL_AI_MAX_CHARS', self::env_int('AI_EXTRACTION_MAX_CHARS', 6500, 1000, 14000), 1000, 14000);

        return function_exists('mb_substr') ? mb_substr($value, 0, $limit) : substr($value, 0, $limit);
    }

    private static function decode_ai_json_object(string $content): ?array
    {
        $content = trim($content);
        $content = (string) preg_replace('/^```(?:json)?\s*/i', '', $content);
        $content = (string) preg_replace('/\s*```$/', '', $content);

        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{[\s\S]*\}/', $content, $match)) {
            $decoded = json_decode($match[0], true);
            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    private static function sanitize_ai_social_payload(array $payload, int $post_id): array
    {
        $permalink = get_permalink($post_id) ?: '';
        $facebook_hook = self::clean_ai_text((string) ($payload['facebook_hook'] ?? ''), 110);
        $facebook_summary = self::strip_urls(self::clean_ai_text((string) ($payload['facebook_summary'] ?? ''), 320));
        $first_comment = self::clean_ai_text((string) ($payload['facebook_first_comment'] ?? ''), 260, true);
        if ($permalink !== '' && !str_contains($first_comment, $permalink)) {
            $first_comment = self::default_facebook_first_comment($permalink);
        }

        $reddit_body = self::clean_ai_text((string) ($payload['reddit_body'] ?? ''), 900, true);
        if ($permalink !== '' && !str_contains($reddit_body, $permalink)) {
            $reddit_body = trim($reddit_body . "\n\n" . $permalink);
        }

        return [
            'facebook_hook' => $facebook_hook !== '' ? $facebook_hook : get_the_title($post_id),
            'facebook_summary' => $facebook_summary !== '' ? $facebook_summary : self::source_intro($post_id),
            'facebook_first_comment' => $first_comment !== '' ? $first_comment : self::default_facebook_first_comment($permalink),
            'pinterest_title' => self::clean_ai_text((string) ($payload['pinterest_title'] ?? ''), 100),
            'pinterest_description' => self::clean_ai_text((string) ($payload['pinterest_description'] ?? ''), 460),
            'pinterest_alt_text' => self::clean_ai_text((string) ($payload['pinterest_alt_text'] ?? ''), 140),
            'reddit_title' => self::clean_ai_text((string) ($payload['reddit_title'] ?? ''), 130),
            'reddit_body' => $reddit_body,
            'overlay_text' => self::short_overlay_text((string) ($payload['overlay_text'] ?? $facebook_hook)),
            'bottom_hint_text' => self::short_hint_text((string) ($payload['bottom_hint_text'] ?? self::default_local_hint_text())),
        ];
    }

    private static function clean_ai_text(string $value, int $max_chars, bool $allow_url = false): string
    {
        $value = wp_strip_all_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8');
        $value = (string) preg_replace("/[ \t]+/", ' ', $value);
        $value = (string) preg_replace("/\n{3,}/", "\n\n", $value);
        $value = trim($value);
        if (!$allow_url) {
            $value = self::strip_urls($value);
        }

        if (function_exists('mb_substr')) {
            return trim(mb_substr($value, 0, $max_chars));
        }

        return trim(substr($value, 0, $max_chars));
    }

    private static function strip_urls(string $value): string
    {
        $value = (string) preg_replace('~https?://\S+~i', '', $value);
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    private static function apply_ai_social_draft(int $post_id, array $payload): void
    {
        $permalink = get_permalink($post_id) ?: '';
        $fields = [
            '_dpj_social_facebook_hook' => $payload['facebook_hook'],
            '_dpj_social_facebook_summary' => self::strip_article_url_from_text((string) $payload['facebook_summary'], $permalink),
            '_dpj_social_facebook_link' => $permalink,
            '_dpj_social_facebook_first_comment' => $payload['facebook_first_comment'],
            '_dpj_social_pinterest_title' => $payload['pinterest_title'],
            '_dpj_social_pinterest_description' => $payload['pinterest_description'],
            '_dpj_social_pinterest_url' => $permalink,
            '_dpj_social_pinterest_alt_text' => $payload['pinterest_alt_text'],
            '_dpj_social_reddit_title' => $payload['reddit_title'],
            '_dpj_social_reddit_body' => $payload['reddit_body'],
            '_dpj_social_reddit_link' => $permalink,
            '_dpj_social_local_overlay_text' => $payload['overlay_text'],
            '_dpj_social_local_hint_text' => $payload['bottom_hint_text'],
        ];

        foreach ($fields as $meta_key => $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                update_post_meta($post_id, $meta_key, $value);
            }
        }

        if (self::facebook_status($post_id) !== self::STATUS_POSTED) {
            update_post_meta($post_id, '_dpj_social_facebook_status', self::STATUS_DRAFT);
        }
        if (!in_array(self::platform_status($post_id, 'pinterest'), [self::STATUS_POSTED, self::STATUS_SKIPPED], true)) {
            update_post_meta($post_id, '_dpj_social_pinterest_status', self::STATUS_DRAFT);
        }
        if (!in_array(self::platform_status($post_id, 'reddit'), [self::STATUS_POSTED, self::STATUS_SKIPPED], true)) {
            update_post_meta($post_id, '_dpj_social_reddit_status', self::STATUS_DRAFT);
        }
    }

    private static function generate_pixazo_images_for_post(int $post_id)
    {
        $settings = self::settings();
        $api_key = trim((string) $settings['pixazo_api_key']);
        if ($api_key === '') {
            $error = __('Pixazo API key is required in Social Settings.', 'dr-purg-social-syndicator');
            update_post_meta($post_id, '_dpj_social_pixazo_last_error', $error);
            return new WP_Error('dpj_social_pixazo_missing_key', $error);
        }

        $base_prompt = trim((string) get_post_meta($post_id, '_dpj_social_pixazo_prompt', true));
        if ($base_prompt === '') {
            $base_prompt = self::default_pixazo_prompt($post_id);
            update_post_meta($post_id, '_dpj_social_pixazo_prompt', $base_prompt);
        }

        $negative_prompt = trim((string) get_post_meta($post_id, '_dpj_social_pixazo_negative_prompt', true));
        if ($negative_prompt === '') {
            $negative_prompt = self::default_pixazo_negative_prompt();
            update_post_meta($post_id, '_dpj_social_pixazo_negative_prompt', $negative_prompt);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $generated = [];
        foreach (self::PIXAZO_PLATFORM_VARIANTS as $variant => $config) {
            $prompt = self::platform_pixazo_prompt($base_prompt, $variant, $config);
            $image_url = self::request_pixazo_image_url($prompt, $negative_prompt, (int) $config['width'], (int) $config['height'], $api_key);
            if (is_wp_error($image_url)) {
                update_post_meta($post_id, '_dpj_social_pixazo_last_error', $image_url->get_error_message());
                return $image_url;
            }

            $attachment_id = self::sideload_pixazo_image($post_id, (string) $image_url, $variant, $config);
            if (is_wp_error($attachment_id)) {
                update_post_meta($post_id, '_dpj_social_pixazo_last_error', $attachment_id->get_error_message());
                return $attachment_id;
            }

            $attachment_id = (int) $attachment_id;
            update_post_meta($post_id, self::pixazo_generated_meta_key($variant), (string) $attachment_id);
            foreach ((array) $config['assign_meta'] as $assign_meta_key) {
                update_post_meta($post_id, (string) $assign_meta_key, (string) $attachment_id);
            }

            $generated[$variant] = $attachment_id;
        }

        update_post_meta($post_id, '_dpj_social_pixazo_last_run', gmdate('c'));
        delete_post_meta($post_id, '_dpj_social_pixazo_last_error');

        return $generated;
    }

    private static function platform_pixazo_prompt(string $base_prompt, string $variant, array $config): string
    {
        $platform_notes = [
            'facebook' => 'Composition for Facebook mobile feed, portrait 4:5 feel, strong center subject, readable at small size, natural expression, high curiosity.',
            'pinterest' => 'Composition for Pinterest pin, tall vertical 2:3 feel, clean lifestyle-health visual, strong top-to-bottom visual flow, no text overlay.',
            'og' => 'Composition for link preview and Reddit/Open Graph, wide 1.91:1 feel, main subject centered with safe margins, clear visual story.',
        ];

        return trim($base_prompt . "\nPlatform image: " . ($platform_notes[$variant] ?? '') . sprintf(
            "\nGenerate at %dx%d. Avoid embedded text, logos, watermarks, scary medical imagery, and exaggerated medical claims.",
            (int) $config['width'],
            (int) $config['height']
        ));
    }

    private static function request_pixazo_image_url(string $prompt, string $negative_prompt, int $width, int $height, string $api_key)
    {
        $response = wp_remote_post(self::PIXAZO_SDXL_FREE_ENDPOINT, [
            'timeout' => 90,
            'headers' => [
                'Content-Type' => 'application/json',
                'Cache-Control' => 'no-cache',
                'Ocp-Apim-Subscription-Key' => $api_key,
            ],
            'body' => wp_json_encode([
                'prompt' => $prompt,
                'negative_prompt' => $negative_prompt,
                'width' => $width,
                'height' => $height,
                'num_steps' => 20,
                'guidance_scale' => 5,
                'seed' => wp_rand(1, 2147483647),
            ]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $raw = (string) wp_remote_retrieve_body($response);
        $decoded = json_decode($raw, true);
        if ($status < 200 || $status >= 300) {
            $message = is_array($decoded) && isset($decoded['message'])
                ? (string) $decoded['message']
                : __('Pixazo API request failed.', 'dr-purg-social-syndicator');
            return new WP_Error('dpj_social_pixazo_http_error', $message, $decoded);
        }

        $image_url = self::extract_pixazo_image_url(is_array($decoded) ? $decoded : []);
        if ($image_url === '') {
            return new WP_Error('dpj_social_pixazo_no_image', __('Pixazo did not return an image URL.', 'dr-purg-social-syndicator'), $decoded);
        }

        return $image_url;
    }

    private static function extract_pixazo_image_url(array $decoded): string
    {
        foreach (['imageUrl', 'image_url', 'url', 'output'] as $key) {
            if (isset($decoded[$key]) && is_string($decoded[$key]) && filter_var($decoded[$key], FILTER_VALIDATE_URL)) {
                return $decoded[$key];
            }
        }

        if (isset($decoded['output']) && is_array($decoded['output'])) {
            foreach ($decoded['output'] as $item) {
                if (is_string($item) && filter_var($item, FILTER_VALIDATE_URL)) {
                    return $item;
                }
                if (is_array($item)) {
                    foreach (['imageUrl', 'image_url', 'url'] as $key) {
                        if (isset($item[$key]) && is_string($item[$key]) && filter_var($item[$key], FILTER_VALIDATE_URL)) {
                            return $item[$key];
                        }
                    }
                }
            }
        }

        return '';
    }

    private static function sideload_pixazo_image(int $post_id, string $image_url, string $variant, array $config)
    {
        $tmp = download_url($image_url, 90);
        if (is_wp_error($tmp)) {
            return $tmp;
        }

        $post_slug = sanitize_title((string) get_post_field('post_name', $post_id));
        if ($post_slug === '') {
            $post_slug = 'post-' . $post_id;
        }

        $image_info = wp_getimagesize($tmp);
        $extension = self::image_extension_from_mime(is_array($image_info) ? (string) ($image_info['mime'] ?? '') : '');
        $filename = sprintf('%s-pixazo-%s-%dx%d.%s', $post_slug, sanitize_key($variant), (int) $config['width'], (int) $config['height'], $extension);
        $file = [
            'name' => sanitize_file_name($filename),
            'tmp_name' => $tmp,
        ];

        $attachment_id = media_handle_sideload($file, $post_id, sprintf(
            '%1$s - %2$s',
            get_the_title($post_id),
            (string) $config['label']
        ));

        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            return $attachment_id;
        }

        $attachment_id = (int) $attachment_id;
        update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field(sprintf(
            /* translators: %s is the post title. */
            __('AI social image for %s', 'dr-purg-social-syndicator'),
            get_the_title($post_id)
        )));
        update_post_meta($attachment_id, '_dpj_social_generated_provider', 'pixazo');
        update_post_meta($attachment_id, '_dpj_social_generated_variant', sanitize_key($variant));
        update_post_meta($attachment_id, '_dpj_social_generated_size', (int) $config['width'] . 'x' . (int) $config['height']);

        return $attachment_id;
    }

    private static function image_extension_from_mime(string $mime): string
    {
        return [
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ][$mime] ?? 'jpg';
    }

    private static function generate_social_images_for_post(int $post_id)
    {
        $source_id = self::social_image_source_id($post_id);
        if ($source_id <= 0) {
            $error = __('Choose a featured image before generating social images.', 'dr-purg-social-syndicator');
            update_post_meta($post_id, '_dpj_social_image_generation_last_error', $error);
            return new WP_Error('dpj_social_missing_source_image', $error);
        }

        $source_path = get_attached_file($source_id);
        if (!is_string($source_path) || $source_path === '' || !file_exists($source_path)) {
            $error = __('The source image file could not be found on the server.', 'dr-purg-social-syndicator');
            update_post_meta($post_id, '_dpj_social_image_generation_last_error', $error);
            return new WP_Error('dpj_social_missing_source_file', $error);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $overlay_text = self::local_overlay_text($post_id);
        $use_overlay = (string) get_post_meta($post_id, '_dpj_social_local_overlay_enable', true) === '1';
        $hint_text = self::local_hint_text($post_id);
        $use_hint = (string) get_post_meta($post_id, '_dpj_social_local_hint_enable', true) === '1';
        $generated = [];
        foreach (self::SOCIAL_IMAGE_VARIANTS as $variant => $config) {
            $attachment_id = self::create_social_image_variant($post_id, $source_id, $source_path, $variant, $config, $overlay_text, $use_overlay, $hint_text, $use_hint);
            if (is_wp_error($attachment_id)) {
                update_post_meta($post_id, '_dpj_social_image_generation_last_error', $attachment_id->get_error_message());
                return $attachment_id;
            }

            $attachment_id = (int) $attachment_id;
            update_post_meta($post_id, $config['generated_meta'], (string) $attachment_id);
            if (!empty($config['assign_meta'])) {
                foreach ((array) $config['assign_meta'] as $assign_meta_key) {
                    update_post_meta($post_id, (string) $assign_meta_key, (string) $attachment_id);
                }
            }

            $generated[$variant] = $attachment_id;
        }

        update_post_meta($post_id, '_dpj_social_image_generation_last_run', gmdate('c'));
        delete_post_meta($post_id, '_dpj_social_image_generation_last_error');

        return $generated;
    }

    private static function create_social_image_variant(int $post_id, int $source_id, string $source_path, string $variant, array $config, string $overlay_text, bool $use_overlay, string $hint_text, bool $use_hint)
    {
        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) {
            return new WP_Error('dpj_social_upload_dir_error', (string) $uploads['error']);
        }

        $upload_dir = (string) ($uploads['path'] ?? '');
        if ($upload_dir === '') {
            return new WP_Error('dpj_social_upload_dir_missing', __('WordPress upload directory is unavailable.', 'dr-purg-social-syndicator'));
        }

        if (!is_dir($upload_dir) && !wp_mkdir_p($upload_dir)) {
            return new WP_Error('dpj_social_upload_dir_unwritable', __('WordPress could not create the upload directory.', 'dr-purg-social-syndicator'));
        }

        $width = (int) $config['width'];
        $height = (int) $config['height'];
        $post_slug = sanitize_title((string) get_post_field('post_name', $post_id));
        if ($post_slug === '') {
            $post_slug = 'post-' . $post_id;
        }

        $filename = wp_unique_filename($upload_dir, sprintf('%s-local-card-%s-%dx%d.jpg', $post_slug, sanitize_key($variant), $width, $height));
        $target_path = trailingslashit($upload_dir) . $filename;
        $rendered = self::render_social_image_jpeg($source_path, $target_path, $width, $height, $overlay_text, $use_overlay, $variant, $hint_text, $use_hint);
        if (is_wp_error($rendered)) {
            return $rendered;
        }

        $attachment_id = wp_insert_attachment(wp_slash([
            'guid' => trailingslashit((string) ($uploads['url'] ?? '')) . $filename,
            'post_mime_type' => 'image/jpeg',
            'post_title' => sprintf('%1$s - Local social card %2$s %3$dx%4$d', get_the_title($post_id), (string) $config['label'], $width, $height),
            'post_status' => 'inherit',
            'post_parent' => $post_id,
        ]), $target_path, $post_id, true);

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        $attachment_id = (int) $attachment_id;
        $metadata = wp_generate_attachment_metadata($attachment_id, $target_path);
        if (!is_wp_error($metadata) && !empty($metadata)) {
            wp_update_attachment_metadata($attachment_id, $metadata);
        }

        $source_alt = trim((string) get_post_meta($source_id, '_wp_attachment_image_alt', true));
        $alt = $source_alt !== '' ? $source_alt : sprintf(
            /* translators: %s is the post title. */
            __('Social image for %s', 'dr-purg-social-syndicator'),
            get_the_title($post_id)
        );
        update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt));
        update_post_meta($attachment_id, '_dpj_social_generated_provider', 'local-card');
        update_post_meta($attachment_id, '_dpj_social_generated_variant', sanitize_key($variant));
        update_post_meta($attachment_id, '_dpj_social_generated_source_id', (string) $source_id);
        update_post_meta($attachment_id, '_dpj_social_generated_size', $width . 'x' . $height);

        return $attachment_id;
    }

    private static function render_social_image_jpeg(string $source_path, string $target_path, int $target_width, int $target_height, string $overlay_text = '', bool $use_overlay = true, string $variant = '', string $hint_text = '', bool $use_hint = false)
    {
        if (!function_exists('imagecreatetruecolor')) {
            return new WP_Error('dpj_social_gd_missing', __('The PHP GD image extension is required to generate social image copies.', 'dr-purg-social-syndicator'));
        }

        $info = wp_getimagesize($source_path);
        if (!is_array($info) || empty($info[0]) || empty($info[1])) {
            return new WP_Error('dpj_social_bad_image', __('The source image is not a readable image file.', 'dr-purg-social-syndicator'));
        }

        $source = self::load_gd_image($source_path, (string) ($info['mime'] ?? ''));
        if (is_wp_error($source)) {
            return $source;
        }

        $source_width = imagesx($source);
        $source_height = imagesy($source);
        if ($source_width <= 0 || $source_height <= 0) {
            imagedestroy($source);
            return new WP_Error('dpj_social_bad_dimensions', __('The source image has invalid dimensions.', 'dr-purg-social-syndicator'));
        }

        $canvas = imagecreatetruecolor($target_width, $target_height);
        if (!$canvas) {
            imagedestroy($source);
            return new WP_Error('dpj_social_canvas_failed', __('Could not create the social image canvas.', 'dr-purg-social-syndicator'));
        }

        imageinterlace($canvas, true);
        imagealphablending($canvas, true);
        $background = imagecolorallocate($canvas, 238, 245, 240);
        imagefilledrectangle($canvas, 0, 0, $target_width, $target_height, $background);

        self::copy_cover_image($canvas, $source, $source_width, $source_height, $target_width, $target_height);
        if ($use_overlay) {
            self::draw_social_overlay($canvas, $overlay_text, $target_width, $target_height, $variant);
        }
        if ($use_hint) {
            self::draw_bottom_hint($canvas, $hint_text, $target_width, $target_height);
        }

        $saved = imagejpeg($canvas, $target_path, 86);
        imagedestroy($canvas);
        imagedestroy($source);

        if (!$saved) {
            return new WP_Error('dpj_social_save_failed', __('Could not save the generated social image.', 'dr-purg-social-syndicator'));
        }

        return true;
    }

    private static function local_overlay_text(int $post_id): string
    {
        $text = trim((string) get_post_meta($post_id, '_dpj_social_local_overlay_text', true));
        if ($text === '') {
            $text = self::default_local_overlay_text($post_id);
        }

        return self::short_overlay_text($text);
    }

    private static function local_hint_text(int $post_id): string
    {
        $text = trim((string) get_post_meta($post_id, '_dpj_social_local_hint_text', true));
        if ($text === '') {
            $text = self::default_local_hint_text();
        }

        return self::short_hint_text($text);
    }

    private static function short_overlay_text(string $text): string
    {
        $text = wp_strip_all_tags($text);
        $text = (string) preg_replace('~https?://\S+~i', '', $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8');
        $text = trim((string) preg_replace('/\s+/', ' ', $text));
        if ($text === '') {
            return '';
        }

        $trimmed = wp_trim_words($text, 12, '');
        return trim($trimmed, " \t\n\r\0\x0B-.,:;|");
    }

    private static function short_hint_text(string $text): string
    {
        $text = wp_strip_all_tags($text);
        $text = (string) preg_replace('~https?://\S+~i', '', $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8');
        $text = trim((string) preg_replace('/\s+/', ' ', $text));
        if ($text === '') {
            return '';
        }

        return trim(wp_trim_words($text, 6, ''), " \t\n\r\0\x0B-.,:;|");
    }

    private static function copy_cover_image($canvas, $source, int $source_width, int $source_height, int $target_width, int $target_height): void
    {
        $source_ratio = $source_width / $source_height;
        $target_ratio = $target_width / $target_height;

        if ($source_ratio > $target_ratio) {
            $crop_height = $source_height;
            $crop_width = max(1, min($source_width, (int) round($source_height * $target_ratio)));
            $source_x = (int) floor(($source_width - $crop_width) / 2);
            $source_y = 0;
        } else {
            $crop_width = $source_width;
            $crop_height = max(1, min($source_height, (int) round($source_width / $target_ratio)));
            $source_x = 0;
            $source_y = (int) floor(($source_height - $crop_height) / 2);
        }

        imagecopyresampled($canvas, $source, 0, 0, $source_x, $source_y, $target_width, $target_height, $crop_width, $crop_height);
    }

    private static function draw_social_overlay($canvas, string $text, int $target_width, int $target_height, string $variant): void
    {
        $headline = self::overlay_display_text(self::short_overlay_text($text));
        if ($headline === '') {
            return;
        }

        $bold_font = self::font_path(true);
        $regular_font = self::font_path(false);
        if ($bold_font !== '' && function_exists('imagettftext') && function_exists('imagettfbbox')) {
            self::draw_ttf_social_text($canvas, $headline, $target_width, $target_height, $variant, $bold_font, $regular_font !== '' ? $regular_font : $bold_font);
            return;
        }

        self::draw_fallback_social_text($canvas, $headline, $target_width, $target_height, $variant);
    }

    private static function draw_bottom_hint($canvas, string $text, int $target_width, int $target_height): void
    {
        $raw_hint = self::short_hint_text($text);
        $draw_pointer = self::hint_has_down_pointer($raw_hint);
        $hint = self::overlay_display_text(self::strip_hint_pointer_symbols($raw_hint));
        if ($hint === '' && !$draw_pointer) {
            return;
        }
        if ($hint === '') {
            $hint = self::overlay_display_text(self::default_local_hint_text());
        }

        $bold_font = self::font_path(true);
        if ($bold_font !== '' && function_exists('imagettftext') && function_exists('imagettfbbox')) {
            $white = imagecolorallocate($canvas, 255, 255, 255);
            $outline = imagecolorallocatealpha($canvas, 5, 12, 14, 4);
            $shadow = imagecolorallocatealpha($canvas, 92, 17, 48, 24);
            $size = max(28, (int) round($target_width * 0.041));
            $pointer_space = $draw_pointer ? (int) round($size * 1.45) : 0;
            $max_width = max(80, (int) round($target_width * 0.82) - $pointer_space);
            while ($size > 22 && self::ttf_text_width($hint, $bold_font, $size) > $max_width) {
                $size -= 2;
            }
            $baseline_y = $target_height - max(34, (int) round($target_height * 0.045));
            self::draw_centered_ttf_hint_text($canvas, $hint, $bold_font, $size, $baseline_y, $target_width, $white, $outline, $shadow, max(3, (int) round($size * 0.08)), $draw_pointer);
            return;
        }

        $font = 5;
        $scale = max(3, (int) round($target_width / 320));
        $pointer_space = $draw_pointer ? (int) round(imagefontheight($font) * $scale * 1.65) : 0;
        $max_chars = max(10, (int) floor((($target_width * 0.82) - $pointer_space) / max(1, imagefontwidth($font) * $scale)));
        if (strlen($hint) > $max_chars) {
            $hint = rtrim(substr($hint, 0, max(3, $max_chars - 3))) . '...';
        }
        $y = $target_height - (imagefontheight($font) * $scale) - max(28, (int) round($target_height * 0.035));
        self::draw_centered_scaled_hint_string($canvas, $hint, $font, $y, $scale, $target_width, [255, 255, 255, 0], [3, 9, 11, 5], [92, 17, 48, 30], max(3, (int) round($scale * 1.2)), $draw_pointer);
    }

    private static function hint_has_down_pointer(string $text): bool
    {
        return preg_match('/[\x{1F447}\x{261F}\x{2B07}]/u', $text) === 1;
    }

    private static function strip_hint_pointer_symbols(string $text): string
    {
        $text = (string) preg_replace('/[\x{1F447}\x{261F}\x{2B07}\x{FE0F}\x{1F3FB}-\x{1F3FF}]/u', '', $text);
        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    private static function overlay_display_text(string $headline): string
    {
        $headline = trim($headline);
        if ($headline === '') {
            return '';
        }

        return function_exists('mb_strtoupper') ? mb_strtoupper($headline, 'UTF-8') : strtoupper($headline);
    }

    private static function draw_ttf_social_text($canvas, string $headline, int $target_width, int $target_height, string $variant, string $bold_font, string $regular_font): void
    {
        $margin = max(46, (int) round($target_width * 0.068));
        $max_width = max(120, $target_width - ($margin * 2));
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $sage = imagecolorallocate($canvas, 198, 232, 218);
        $outline = imagecolorallocatealpha($canvas, 5, 12, 14, 8);
        $shadow = imagecolorallocatealpha($canvas, 92, 17, 48, 28);
        $brand_size = max(25, (int) round($target_width * 0.034));
        $headline_size = max(62, (int) round($target_width * ($variant === 'og' ? 0.083 : 0.098)));
        $max_lines = 3;

        do {
            $lines = self::wrap_ttf_lines($headline, $bold_font, $headline_size, $max_width);
            if (count($lines) <= $max_lines) {
                break;
            }
            $headline_size -= 6;
        } while ($headline_size > 48);

        $lines = self::limit_ttf_lines($lines, $max_lines, $bold_font, $headline_size, $max_width);
        $line_height = (int) round($headline_size * 1.04);
        $brand_gap = max(24, (int) round($headline_size * 0.34));
        $accent_gap = max(24, (int) round($headline_size * 0.28));
        $accent_height = max(8, (int) round($target_height * 0.008));
        $block_height = $brand_size + $brand_gap + (count($lines) * $line_height) + $accent_gap + $accent_height;
        $brand_baseline = max($brand_size + 16, (int) floor(($target_height - $block_height) / 2) + $brand_size);

        self::draw_centered_ttf_text($canvas, 'DR PURG JR.', $regular_font, $brand_size, $brand_baseline, $target_width, $sage !== false ? $sage : $white, $outline, $shadow, 2);

        $headline_baseline = $brand_baseline + $brand_gap + $headline_size;
        foreach ($lines as $line) {
            self::draw_centered_ttf_text($canvas, $line, $bold_font, $headline_size, $headline_baseline, $target_width, $white, $outline, $shadow, max(4, (int) round($headline_size * 0.055)));
            $headline_baseline += $line_height;
        }

        $accent_width = min((int) round($target_width * 0.38), max(160, (int) round($target_width * 0.18)));
        $accent_y = $headline_baseline - $line_height + $accent_gap;
        self::draw_centered_accent($canvas, $target_width, $accent_y, $accent_width, $accent_height);
    }

    private static function draw_centered_ttf_text($canvas, string $text, string $font, int $size, int $baseline_y, int $target_width, $fill, $outline, $shadow, int $outline_radius): void
    {
        $text_width = self::ttf_text_width($text, $font, $size);
        $x = (int) floor(($target_width - $text_width) / 2);
        self::draw_ttf_text_at($canvas, $text, $font, $size, $x, $baseline_y, $fill, $outline, $shadow, $outline_radius);
    }

    private static function draw_centered_ttf_hint_text($canvas, string $text, string $font, int $size, int $baseline_y, int $target_width, $fill, $outline, $shadow, int $outline_radius, bool $draw_pointer): void
    {
        $text_width = self::ttf_text_width($text, $font, $size);
        $pointer_size = $draw_pointer ? max(26, (int) round($size * 0.92)) : 0;
        $gap = $draw_pointer ? max(10, (int) round($size * 0.28)) : 0;
        $group_width = $text_width + $gap + $pointer_size;
        $x = (int) floor(($target_width - $group_width) / 2);

        self::draw_ttf_text_at($canvas, $text, $font, $size, $x, $baseline_y, $fill, $outline, $shadow, $outline_radius);

        if ($draw_pointer) {
            $center_x = $x + $text_width + $gap + (int) round($pointer_size / 2);
            $center_y = $baseline_y - (int) round($size * 0.35);
            self::draw_down_pointer_icon($canvas, $center_x, $center_y, $pointer_size);
        }
    }

    private static function draw_ttf_text_at($canvas, string $text, string $font, int $size, int $x, int $baseline_y, $fill, $outline, $shadow, int $outline_radius): void
    {
        if ($shadow !== false) {
            imagettftext($canvas, $size, 0, $x + max(4, (int) round($size * 0.05)), $baseline_y + max(5, (int) round($size * 0.06)), $shadow, $font, $text);
        }

        if ($outline !== false) {
            for ($dx = -$outline_radius; $dx <= $outline_radius; $dx++) {
                for ($dy = -$outline_radius; $dy <= $outline_radius; $dy++) {
                    if ($dx === 0 && $dy === 0) {
                        continue;
                    }
                    if (($dx * $dx) + ($dy * $dy) > ($outline_radius * $outline_radius)) {
                        continue;
                    }
                    imagettftext($canvas, $size, 0, $x + $dx, $baseline_y + $dy, $outline, $font, $text);
                }
            }
        }

        imagettftext($canvas, $size, 0, $x, $baseline_y, $fill, $font, $text);
    }

    private static function draw_down_pointer_icon($canvas, int $center_x, int $center_y, int $size): void
    {
        $shadow = imagecolorallocatealpha($canvas, 0, 0, 0, 58);
        $outline = imagecolorallocate($canvas, 255, 255, 255);
        $accent = imagecolorallocate($canvas, 142, 42, 79);

        if ($shadow !== false) {
            self::draw_down_pointer_shape($canvas, $center_x + 4, $center_y + 5, $size, $shadow, max(5, (int) round($size * 0.18)));
        }
        if ($outline !== false) {
            self::draw_down_pointer_shape($canvas, $center_x, $center_y, $size + max(8, (int) round($size * 0.20)), $outline, max(7, (int) round($size * 0.22)));
        }
        if ($accent !== false) {
            self::draw_down_pointer_shape($canvas, $center_x, $center_y, $size, $accent, max(5, (int) round($size * 0.17)));
        }
    }

    private static function draw_down_pointer_shape($canvas, int $center_x, int $center_y, int $size, $color, int $thickness): void
    {
        $top = $center_y - (int) round($size * 0.44);
        $neck = $center_y + (int) round($size * 0.02);
        $tip = $center_y + (int) round($size * 0.45);
        $half = (int) round($size * 0.34);

        imagesetthickness($canvas, $thickness);
        imageline($canvas, $center_x, $top, $center_x, $neck, $color);
        imagesetthickness($canvas, 1);
        imagefilledpolygon($canvas, [
            $center_x - $half,
            $neck,
            $center_x + $half,
            $neck,
            $center_x,
            $tip,
        ], 3, $color);
    }

    private static function draw_centered_accent($canvas, int $target_width, int $y, int $width, int $height): void
    {
        $x = (int) floor(($target_width - $width) / 2);
        $shadow = imagecolorallocatealpha($canvas, 0, 0, 0, 62);
        if ($shadow !== false) {
            imagefilledrectangle($canvas, $x + 4, $y + 5, $x + $width + 4, $y + $height + 5, $shadow);
        }

        $accent = imagecolorallocate($canvas, 142, 42, 79);
        if ($accent !== false) {
            imagefilledrectangle($canvas, $x, $y, $x + $width, $y + $height, $accent);
        }
    }

    private static function wrap_ttf_lines(string $text, string $font, int $font_size, int $max_width): array
    {
        $words = preg_split('/\s+/', $text) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = trim($current . ' ' . $word);
            if ($candidate === '') {
                continue;
            }

            if ($current !== '' && self::ttf_text_width($candidate, $font, $font_size) > $max_width) {
                $lines[] = $current;
                $current = $word;
                continue;
            }

            $current = $candidate;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    private static function limit_ttf_lines(array $lines, int $max_lines, string $font, int $font_size, int $max_width): array
    {
        if (count($lines) <= $max_lines) {
            return $lines;
        }

        $limited = array_slice($lines, 0, $max_lines);
        $last = trim((string) end($limited));
        while ($last !== '' && self::ttf_text_width($last . '...', $font, $font_size) > $max_width) {
            $last = trim((string) preg_replace('/\s+\S+$/', '', $last));
        }

        $limited[$max_lines - 1] = $last !== '' ? $last . '...' : '...';
        return $limited;
    }

    private static function ttf_text_width(string $text, string $font, int $font_size): int
    {
        $box = imagettfbbox($font_size, 0, $font, $text);
        if (!is_array($box)) {
            return 0;
        }

        return abs((int) $box[2] - (int) $box[0]);
    }

    private static function draw_fallback_social_text($canvas, string $headline, int $target_width, int $target_height, string $variant): void
    {
        $margin = max(46, (int) round($target_width * 0.066));
        $font = 5;
        $brand_scale = max(3, (int) round($target_width / 330));
        $headline_scale = max(5, (int) round($target_width / ($variant === 'og' ? 220 : 205)));
        $max_lines = 3;
        $max_chars = max(12, (int) floor(($target_width - ($margin * 2)) / max(1, imagefontwidth($font) * $headline_scale)));
        $lines = self::fallback_wrap_lines($headline, $max_chars, $max_lines);
        $brand_height = imagefontheight($font) * $brand_scale;
        $headline_height = imagefontheight($font) * $headline_scale;
        $line_gap = max(12, (int) round($headline_height * 0.16));
        $brand_gap = max(26, (int) round($headline_height * 0.36));
        $accent_gap = max(24, (int) round($headline_height * 0.30));
        $accent_height = max(8, (int) round($target_height * 0.008));
        $block_height = $brand_height + $brand_gap + (count($lines) * $headline_height) + ((count($lines) - 1) * $line_gap) + $accent_gap + $accent_height;
        $y = max(24, (int) floor(($target_height - $block_height) / 2));

        self::draw_centered_scaled_string($canvas, 'DR PURG JR.', $font, $y, $brand_scale, $target_width, [213, 232, 224, 0], [0, 0, 0, 26], [92, 17, 48, 48], max(2, (int) round($brand_scale * 0.7)));

        $y += $brand_height + $brand_gap;
        foreach ($lines as $line) {
            self::draw_centered_scaled_string($canvas, $line, $font, $y, $headline_scale, $target_width, [255, 255, 255, 0], [3, 9, 11, 7], [92, 17, 48, 32], max(4, (int) round($headline_scale * 1.1)));
            $y += $headline_height + $line_gap;
        }

        $accent_width = min((int) round($target_width * 0.38), max(160, (int) round($target_width * 0.18)));
        self::draw_centered_accent($canvas, $target_width, $y + $accent_gap - $line_gap, $accent_width, $accent_height);
    }

    private static function fallback_wrap_lines(string $headline, int $max_chars, int $max_lines): array
    {
        $lines = explode("\n", wordwrap($headline, $max_chars, "\n", true));
        $lines = array_values(array_filter(array_map('trim', $lines)));
        if (count($lines) <= $max_lines) {
            return $lines;
        }

        $limited = array_slice($lines, 0, $max_lines);
        $last = trim((string) end($limited));
        while (strlen($last . '...') > $max_chars && $last !== '') {
            $last = trim((string) preg_replace('/\s+\S+$/', '', $last));
        }
        $limited[$max_lines - 1] = $last !== '' ? $last . '...' : '...';

        return $limited;
    }

    private static function draw_centered_scaled_string($canvas, string $text, int $font, int $y, int $scale, int $target_width, array $fill, array $outline, array $shadow, int $outline_radius): void
    {
        $text_width = imagefontwidth($font) * strlen($text) * $scale;
        $x = (int) floor(($target_width - $text_width) / 2);

        self::draw_scaled_string_at($canvas, $text, $font, $x, $y, $scale, $fill, $outline, $shadow, $outline_radius);
    }

    private static function draw_centered_scaled_hint_string($canvas, string $text, int $font, int $y, int $scale, int $target_width, array $fill, array $outline, array $shadow, int $outline_radius, bool $draw_pointer): void
    {
        $text_width = imagefontwidth($font) * strlen($text) * $scale;
        $pointer_size = $draw_pointer ? max(24, (int) round(imagefontheight($font) * $scale * 0.96)) : 0;
        $gap = $draw_pointer ? max(10, (int) round($scale * 3.2)) : 0;
        $group_width = $text_width + $gap + $pointer_size;
        $x = (int) floor(($target_width - $group_width) / 2);

        self::draw_scaled_string_at($canvas, $text, $font, $x, $y, $scale, $fill, $outline, $shadow, $outline_radius);
        if ($draw_pointer) {
            $center_x = $x + $text_width + $gap + (int) round($pointer_size / 2);
            $center_y = $y + (int) round((imagefontheight($font) * $scale) / 2);
            self::draw_down_pointer_icon($canvas, $center_x, $center_y, $pointer_size);
        }
    }

    private static function draw_scaled_string_at($canvas, string $text, int $font, int $x, int $y, int $scale, array $fill, array $outline, array $shadow, int $outline_radius): void
    {
        self::draw_scaled_string($canvas, $text, $font, $x + max(4, (int) round($scale * 1.25)), $y + max(5, (int) round($scale * 1.4)), $scale, $shadow);

        for ($dx = -$outline_radius; $dx <= $outline_radius; $dx += max(1, (int) floor($outline_radius / 2))) {
            for ($dy = -$outline_radius; $dy <= $outline_radius; $dy += max(1, (int) floor($outline_radius / 2))) {
                if ($dx === 0 && $dy === 0) {
                    continue;
                }
                if (($dx * $dx) + ($dy * $dy) > ($outline_radius * $outline_radius)) {
                    continue;
                }
                self::draw_scaled_string($canvas, $text, $font, $x + $dx, $y + $dy, $scale, $outline);
            }
        }

        self::draw_scaled_string($canvas, $text, $font, $x, $y, $scale, $fill);
    }

    private static function draw_scaled_string($canvas, string $text, int $font, int $x, int $y, int $scale, array $rgba): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }

        $source_width = max(1, imagefontwidth($font) * strlen($text));
        $source_height = max(1, imagefontheight($font));
        $text_canvas = imagecreatetruecolor($source_width, $source_height);
        if (!$text_canvas) {
            return;
        }

        imagealphablending($text_canvas, false);
        imagesavealpha($text_canvas, true);
        $transparent = imagecolorallocatealpha($text_canvas, 0, 0, 0, 127);
        imagefilledrectangle($text_canvas, 0, 0, $source_width, $source_height, $transparent);
        imagealphablending($text_canvas, true);
        $color = imagecolorallocatealpha(
            $text_canvas,
            max(0, min(255, (int) ($rgba[0] ?? 255))),
            max(0, min(255, (int) ($rgba[1] ?? 255))),
            max(0, min(255, (int) ($rgba[2] ?? 255))),
            max(0, min(127, (int) ($rgba[3] ?? 0)))
        );
        imagestring($text_canvas, $font, 0, 0, $text, $color);
        imagecopyresampled($canvas, $text_canvas, $x, $y, 0, 0, $source_width * $scale, $source_height * $scale, $source_width, $source_height);
        imagedestroy($text_canvas);
    }

    private static function font_path(bool $bold): string
    {
        $paths = $bold
            ? [
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                '/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf',
                'C:\\Windows\\Fonts\\arialbd.ttf',
            ]
            : [
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                '/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf',
                'C:\\Windows\\Fonts\\arial.ttf',
            ];

        foreach ($paths as $path) {
            if (is_string($path) && is_readable($path)) {
                return $path;
            }
        }

        return '';
    }

    private static function load_gd_image(string $path, string $mime)
    {
        $image = false;

        if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) {
            $image = imagecreatefromjpeg($path);
        } elseif ($mime === 'image/png' && function_exists('imagecreatefrompng')) {
            $image = imagecreatefrompng($path);
        } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
            $image = imagecreatefromwebp($path);
        } elseif ($mime === 'image/gif' && function_exists('imagecreatefromgif')) {
            $image = imagecreatefromgif($path);
        } else {
            return new WP_Error('dpj_social_unsupported_image', __('Use a JPG, PNG, WebP, or GIF source image for social image generation.', 'dr-purg-social-syndicator'));
        }

        if (!$image) {
            return new WP_Error('dpj_social_image_load_failed', __('WordPress could not load the source image for resizing.', 'dr-purg-social-syndicator'));
        }

        return $image;
    }

    private static function copy_blurred_cover_background($canvas, $source, int $source_width, int $source_height, int $target_width, int $target_height): void
    {
        $scale = max($target_width / $source_width, $target_height / $source_height);
        $cover_width = max($target_width, (int) ceil($source_width * $scale));
        $cover_height = max($target_height, (int) ceil($source_height * $scale));
        $cover = imagecreatetruecolor($cover_width, $cover_height);
        if (!$cover) {
            return;
        }

        imagecopyresampled($cover, $source, 0, 0, 0, 0, $cover_width, $cover_height, $source_width, $source_height);
        for ($i = 0; $i < 8; $i++) {
            imagefilter($cover, IMG_FILTER_GAUSSIAN_BLUR);
        }

        $source_x = max(0, (int) floor(($cover_width - $target_width) / 2));
        $source_y = max(0, (int) floor(($cover_height - $target_height) / 2));
        imagecopy($canvas, $cover, 0, 0, $source_x, $source_y, $target_width, $target_height);
        imagedestroy($cover);

        $wash = imagecolorallocatealpha($canvas, 245, 248, 246, 32);
        imagefilledrectangle($canvas, 0, 0, $target_width, $target_height, $wash);
    }

    private static function copy_contained_foreground($canvas, $source, int $source_width, int $source_height, int $target_width, int $target_height): void
    {
        $padding = max(0, (int) round(min($target_width, $target_height) * 0.045));
        $available_width = max(1, $target_width - ($padding * 2));
        $available_height = max(1, $target_height - ($padding * 2));
        $scale = min($available_width / $source_width, $available_height / $source_height);
        $foreground_width = max(1, (int) floor($source_width * $scale));
        $foreground_height = max(1, (int) floor($source_height * $scale));
        $x = (int) floor(($target_width - $foreground_width) / 2);
        $y = (int) floor(($target_height - $foreground_height) / 2);

        imagecopyresampled($canvas, $source, $x, $y, 0, 0, $foreground_width, $foreground_height, $source_width, $source_height);
    }

    private static function facebook_message(int $post_id): string
    {
        $link = trim((string) get_post_meta($post_id, '_dpj_social_facebook_link', true));
        $summary = self::strip_article_url_from_text(
            trim((string) get_post_meta($post_id, '_dpj_social_facebook_summary', true)),
            $link
        );
        $parts = array_filter([
            trim((string) get_post_meta($post_id, '_dpj_social_facebook_hook', true)),
            $summary,
        ]);

        return implode("\n\n", $parts);
    }

    private static function post_to_facebook(int $post_id)
    {
        $remote_id = trim((string) get_post_meta($post_id, '_dpj_social_facebook_remote_post_id', true));
        $do_not_repost = (string) get_post_meta($post_id, '_dpj_social_do_not_repost', true) !== '0';
        if ($remote_id !== '' && $do_not_repost) {
            $error = __('This article already has a Facebook remote post ID. Reset the posting lock before reposting.', 'dr-purg-social-syndicator');
            update_post_meta($post_id, '_dpj_social_facebook_last_error', $error);
            update_post_meta($post_id, '_dpj_social_facebook_status', self::STATUS_FAILED);
            return new WP_Error('dpj_social_duplicate', $error);
        }

        $settings = self::settings();
        $page_id = trim((string) $settings['facebook_page_id']);
        $token = trim((string) $settings['facebook_page_access_token']);
        if ($page_id === '' || $token === '') {
            $error = __('Facebook Page ID and Page Access Token are required in Social Settings.', 'dr-purg-social-syndicator');
            update_post_meta($post_id, '_dpj_social_facebook_last_error', $error);
            update_post_meta($post_id, '_dpj_social_facebook_status', self::STATUS_FAILED);
            return new WP_Error('dpj_social_missing_credentials', $error);
        }

        $message = self::facebook_message($post_id);
        if ($message === '') {
            $error = __('Facebook hook or summary is required before posting.', 'dr-purg-social-syndicator');
            update_post_meta($post_id, '_dpj_social_facebook_last_error', $error);
            update_post_meta($post_id, '_dpj_social_facebook_status', self::STATUS_FAILED);
            return new WP_Error('dpj_social_empty_message', $error);
        }

        $facebook_link = trim((string) get_post_meta($post_id, '_dpj_social_facebook_link', true));
        $first_comment = trim((string) get_post_meta($post_id, '_dpj_social_facebook_first_comment', true));
        if ($first_comment === '' && $facebook_link !== '') {
            $first_comment = self::default_facebook_first_comment($facebook_link);
            update_post_meta($post_id, '_dpj_social_facebook_first_comment', $first_comment);
        }

        $media_id = (int) get_post_meta($post_id, '_dpj_social_facebook_media_id', true);
        $image_url = $media_id > 0 ? (string) wp_get_attachment_image_url($media_id, 'full') : '';
        $graph_version = self::clean_graph_version((string) $settings['facebook_graph_version']);
        $base = 'https://graph.facebook.com/' . rawurlencode($graph_version) . '/' . rawurlencode($page_id);

        if ($image_url !== '') {
            $result = self::facebook_request($base . '/photos', [
                'url' => $image_url,
                'caption' => $message,
                'published' => 'true',
            ], $settings);
        } else {
            $result = self::facebook_request($base . '/feed', [
                'message' => $message,
            ], $settings);
        }

        if (is_wp_error($result)) {
            update_post_meta($post_id, '_dpj_social_facebook_last_error', $result->get_error_message());
            update_post_meta($post_id, '_dpj_social_facebook_status', self::STATUS_FAILED);
            return $result;
        }

        $photo_id = isset($result['id']) ? sanitize_text_field((string) $result['id']) : '';
        $post_remote_id = isset($result['post_id']) ? sanitize_text_field((string) $result['post_id']) : $photo_id;
        update_post_meta($post_id, '_dpj_social_facebook_remote_post_id', $post_remote_id);
        update_post_meta($post_id, '_dpj_social_facebook_remote_photo_id', $photo_id);
        update_post_meta($post_id, '_dpj_social_facebook_status', self::STATUS_POSTED);
        update_post_meta($post_id, '_dpj_social_facebook_posted_at', gmdate('c'));
        delete_post_meta($post_id, '_dpj_social_facebook_last_error');

        if ($first_comment !== '' && $post_remote_id !== '') {
            $comment_result = self::facebook_request('https://graph.facebook.com/' . rawurlencode($graph_version) . '/' . rawurlencode($post_remote_id) . '/comments', [
                'message' => $first_comment,
            ], $settings);
            if (is_wp_error($comment_result)) {
                update_post_meta($post_id, '_dpj_social_facebook_last_error', sprintf(
                    /* translators: %s is an API error. */
                    __('Post was published, but the first comment failed: %s', 'dr-purg-social-syndicator'),
                    $comment_result->get_error_message()
                ));
            } elseif (isset($comment_result['id'])) {
                update_post_meta($post_id, '_dpj_social_facebook_comment_id', sanitize_text_field((string) $comment_result['id']));
            }
        }

        return $result;
    }

    private static function facebook_request(string $endpoint, array $body, array $settings)
    {
        $token = trim((string) $settings['facebook_page_access_token']);
        $body['access_token'] = $token;

        $secret = trim((string) $settings['facebook_app_secret']);
        if ($secret !== '') {
            $body['appsecret_proof'] = hash_hmac('sha256', $token, $secret);
        }

        $response = wp_remote_post($endpoint, [
            'timeout' => 20,
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $raw = (string) wp_remote_retrieve_body($response);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return new WP_Error('dpj_social_bad_response', __('Facebook returned an unreadable response.', 'dr-purg-social-syndicator'));
        }

        if ($status < 200 || $status >= 300 || isset($decoded['error'])) {
            $message = is_array($decoded['error'] ?? null)
                ? (string) ($decoded['error']['message'] ?? __('Unknown Facebook API error.', 'dr-purg-social-syndicator'))
                : __('Facebook API request failed.', 'dr-purg-social-syndicator');
            return new WP_Error('dpj_social_facebook_error', $message, $decoded);
        }

        return $decoded;
    }

    private static function reset_facebook(int $post_id): void
    {
        foreach ([
            '_dpj_social_facebook_remote_post_id',
            '_dpj_social_facebook_remote_photo_id',
            '_dpj_social_facebook_comment_id',
            '_dpj_social_facebook_last_error',
            '_dpj_social_facebook_posted_at',
        ] as $key) {
            delete_post_meta($post_id, $key);
        }

        update_post_meta($post_id, '_dpj_social_facebook_status', self::STATUS_DRAFT);
    }

    public static function filter_theme_social_image_url(string $url): string
    {
        $media_id = self::public_og_media_id();
        if ($media_id <= 0) {
            return $url;
        }

        $image = wp_get_attachment_image_url($media_id, 'full');
        return is_string($image) && $image !== '' ? $image : $url;
    }

    public static function filter_theme_social_image_alt(string $alt): string
    {
        $media_id = self::public_og_media_id();
        if ($media_id <= 0) {
            return $alt;
        }

        $generated_alt = trim((string) get_post_meta($media_id, '_wp_attachment_image_alt', true));
        return $generated_alt !== '' ? $generated_alt : $alt;
    }

    public static function filter_theme_social_image_dimensions(array $dimensions): array
    {
        $media_id = self::public_og_media_id();
        if ($media_id <= 0) {
            return $dimensions;
        }

        $image = wp_get_attachment_image_src($media_id, 'full');
        if (!is_array($image)) {
            return $dimensions;
        }

        return [(int) ($image[1] ?? 0), (int) ($image[2] ?? 0)];
    }

    private static function public_og_media_id(): int
    {
        if (!is_singular('post')) {
            return 0;
        }

        $post_id = (int) get_queried_object_id();
        if ($post_id <= 0) {
            return 0;
        }

        $media_id = (int) get_post_meta($post_id, '_dpj_social_og_media_id', true);
        if ($media_id <= 0 || !wp_attachment_is_image($media_id)) {
            return 0;
        }

        return $media_id;
    }
}

Dr_Purg_Social_Syndicator::init();
register_activation_hook(__FILE__, ['Dr_Purg_Social_Syndicator', 'activate']);
