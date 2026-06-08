<?php
/**
 * Plugin Name: Dr Purg Jr. Social Syndicator
 * Description: Creates per-post social packages and posts reviewed Facebook Page updates through the Graph API.
 * Version: 0.7.3
 * Author: Site tools
 * Text Domain: dr-purg-social-syndicator
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Dr_Purg_Social_Syndicator
{
    private const VERSION = '0.7.3';
    private const SETTINGS_OPTION = 'dpj_social_syndicator_settings';
    private const CALENDAR_OPTION = 'dpj_social_calendar';
    private const CALENDAR_ERROR_OPTION = 'dpj_social_calendar_error';
    private const PIXAZO_SDXL_FREE_ENDPOINT = 'https://gateway.pixazo.ai/getImage/v1/getSDXLImage';
    private const QUEUE_SLUG = 'dr-purg-social-queue';
    private const EDITOR_SLUG = 'dr-purg-social-editor';
    private const SETTINGS_SLUG = 'dr-purg-social-settings';
    private const PERF_SLUG = 'dr-purg-social-performance';
    private const COCKPIT_SLUG = 'dr-purg-social-cockpit';
    private const CALENDAR_SLUG = 'dr-purg-social-calendar';
    private const IMPORT_SLUG = 'dr-purg-social-import';
    private const REDIRECT_TRANSIENT_PREFIX = 'dpj_social_redirect_';
    private const STATUS_NEEDS = 'needs_social';
    private const STATUS_DRAFT = 'draft';
    private const STATUS_POSTED = 'posted';
    private const STATUS_SCHEDULED = 'scheduled';
    private const STATUS_FAILED = 'failed';
    private const STATUS_SKIPPED = 'skipped';
    private const OVERLAY_WORD_MAX = 12;
    private const OVERLAY_WORD_IDEAL_MIN = 8;
    private const OVERLAY_WORD_MIN = 3;
    private const CROP_KEEP_WARN = 0.6;
    private const UPSCALE_WARN = 1.15;
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
            '_dpj_social_facebook_scheduled_at',
            '_dpj_social_generated_facebook_media_id',
            '_dpj_social_pixazo_prompt',
            '_dpj_social_pixazo_negative_prompt',
            '_dpj_social_pixazo_last_error',
            '_dpj_social_pixazo_last_run',
            '_dpj_social_local_overlay_text',
            '_dpj_social_local_overlay_enable',
            '_dpj_social_local_overlay_pos',
            '_dpj_social_local_crop_focus',
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
            '_dpj_social_pinterest_remote_id',
            '_dpj_social_pinterest_last_error',
            '_dpj_social_pinterest_posted_at',
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
        add_action('add_meta_boxes', [self::class, 'register_image_prompt_metabox']);
        add_action('dpj_social_post_scheduled_comment', [self::class, 'run_scheduled_comment'], 10, 1);
        add_action('wp_head', [self::class, 'print_ga4_tag'], 5);
        add_action('wp_ajax_dpj_social_card_preview', [self::class, 'ajax_card_preview']);
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
            'pinterest_access_token' => '',
            'pinterest_board_id' => '',
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
        $default = self::social_ai_provider() === 'anthropic'
            ? 'claude-opus-4-8'
            : 'inclusionai/ling-2.6-1t:free';

        return self::env('SOCIAL_AI_MODEL', self::env('AI_EXTRACTION_MODEL', $default));
    }

    private static function social_ai_enabled(): bool
    {
        $enabled = self::env_bool('SOCIAL_AI_ENABLE', self::env_bool('AI_EXTRACTION_ENABLE', false));
        return $enabled
            && in_array(self::social_ai_provider(), ['openrouter', 'anthropic'], true)
            && self::social_ai_api_key() !== '';
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
            __('Posting Cockpit', 'dr-purg-social-syndicator'),
            __('Cockpit', 'dr-purg-social-syndicator'),
            'edit_posts',
            self::COCKPIT_SLUG,
            [self::class, 'render_cockpit_page']
        );

        add_submenu_page(
            self::QUEUE_SLUG,
            __('Content Calendar', 'dr-purg-social-syndicator'),
            __('Calendar', 'dr-purg-social-syndicator'),
            'edit_posts',
            self::CALENDAR_SLUG,
            [self::class, 'render_calendar_page']
        );

        add_submenu_page(
            self::QUEUE_SLUG,
            __('New from Claude', 'dr-purg-social-syndicator'),
            __('New from Claude', 'dr-purg-social-syndicator'),
            'edit_posts',
            self::IMPORT_SLUG,
            [self::class, 'render_import_page']
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
            self::QUEUE_SLUG,
            __('Social Performance', 'dr-purg-social-syndicator'),
            __('Performance', 'dr-purg-social-syndicator'),
            'edit_posts',
            self::PERF_SLUG,
            [self::class, 'render_performance_page']
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
        $is_social_page = in_array($page, [self::QUEUE_SLUG, self::EDITOR_SLUG, self::SETTINGS_SLUG, self::COCKPIT_SLUG, self::PERF_SLUG, self::CALENDAR_SLUG, self::IMPORT_SLUG], true);

        // Also load on the post editor so the "Featured image prompt" meta box can
        // use the copy button.
        $is_post_editor = in_array($hook, ['post.php', 'post-new.php'], true);
        if ($is_post_editor) {
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
            $is_post_editor = $screen instanceof WP_Screen && $screen->post_type === 'post';
        }

        if (!$is_social_page && !$is_post_editor) {
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
            wp_localize_script('dpj-social-syndicator-admin', 'dpjSocialPreview', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('dpj_social_card_preview'),
                'rendering' => __('Rendering preview…', 'dr-purg-social-syndicator'),
                'failed' => __('Preview failed. Save the post if you just changed the image, then try again.', 'dr-purg-social-syndicator'),
            ]);
        }
    }

    public static function register_image_prompt_metabox(): void
    {
        add_meta_box(
            'dpj_social_image_prompt',
            __('Featured image prompt', 'dr-purg-social-syndicator'),
            [self::class, 'render_image_prompt_metabox'],
            'post',
            'side',
            'default'
        );
    }

    public static function render_image_prompt_metabox(WP_Post $post): void
    {
        $prompt = (string) get_post_meta($post->ID, '_dpj_social_image_prompt', true);
        if ($prompt === '') {
            echo '<p class="dpj-social-note">' . esc_html__('No image prompt yet. Import a Claude bundle that includes an "image_prompt" to fill this.', 'dr-purg-social-syndicator') . '</p>';
            return;
        }
        ?>
        <p><?php esc_html_e('Copy this into your image generator (e.g. Gemini), make a portrait image, then set it as the featured image.', 'dr-purg-social-syndicator'); ?></p>
        <textarea readonly rows="6" id="dpj-image-prompt" class="widefat code"><?php echo esc_textarea($prompt); ?></textarea>
        <p><button type="button" class="button" data-dpj-copy="#dpj-image-prompt"><?php esc_html_e('Copy image prompt', 'dr-purg-social-syndicator'); ?></button></p>
        <?php
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

        if (isset($_POST['dpj_cockpit_action'])) {
            self::handle_cockpit_post();
            return;
        }

        if (isset($_POST['dpj_calendar_action'])) {
            self::handle_calendar_post();
            return;
        }

        if (isset($_POST['dpj_social_perf_action'])) {
            if (sanitize_key(wp_unslash((string) $_POST['dpj_social_perf_action'])) === 'ga4_pull') {
                self::handle_ga4_pull();
            } else {
                self::handle_performance_import();
            }
            return;
        }

        if (isset($_POST['dpj_social_import_action'])) {
            self::handle_import_post();
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
            'pinterest_access_token' => isset($_POST['pinterest_access_token']) ? sanitize_textarea_field(wp_unslash((string) $_POST['pinterest_access_token'])) : '',
            'pinterest_board_id' => isset($_POST['pinterest_board_id']) ? sanitize_text_field(wp_unslash((string) $_POST['pinterest_board_id'])) : '',
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
        } elseif ($action === 'schedule_facebook') {
            $timestamp = self::parse_schedule_timestamp(isset($_POST['facebook_schedule']) ? sanitize_text_field(wp_unslash((string) $_POST['facebook_schedule'])) : '');
            if ($timestamp <= 0) {
                update_post_meta($post_id, '_dpj_social_facebook_last_error', __('Enter a valid schedule date and time.', 'dr-purg-social-syndicator'));
                update_post_meta($post_id, '_dpj_social_facebook_status', self::STATUS_FAILED);
                $notice = 'schedule_failed';
            } else {
                $result = self::post_to_facebook($post_id, $timestamp);
                $notice = is_wp_error($result) ? 'schedule_failed' : 'scheduled';
            }
        } elseif ($action === 'post_facebook_comment') {
            $result = self::post_facebook_comment($post_id);
            $notice = is_wp_error($result) ? 'comment_failed' : 'comment_posted';
        } elseif ($action === 'reset_facebook') {
            self::reset_facebook($post_id);
            $notice = 'facebook_reset';
        } elseif ($action === 'generate_ai_social_draft') {
            $result = self::generate_ai_social_draft($post_id);
            $notice = is_wp_error($result) ? 'ai_draft_failed' : 'ai_draft_generated';
        } elseif ($action === 'generate_hook_variants') {
            $result = self::generate_hook_variants($post_id);
            $notice = is_wp_error($result) ? 'hook_variants_failed' : 'hook_variants_generated';
        } elseif (strpos($action, 'apply_hook_variant_') === 0) {
            $applied = self::apply_hook_variant($post_id, (int) substr($action, strlen('apply_hook_variant_')));
            $notice = $applied ? 'hook_variant_applied' : 'saved';
        } elseif ($action === 'generate_social_images') {
            $result = self::generate_social_images_for_post($post_id);
            $notice = is_wp_error($result) ? 'images_failed' : 'images_generated';
        } elseif ($action === 'generate_pixazo_images') {
            $result = self::generate_pixazo_images_for_post($post_id);
            $notice = is_wp_error($result) ? 'pixazo_failed' : 'pixazo_generated';
        } elseif ($action === 'publish_pinterest') {
            $result = self::publish_pinterest($post_id);
            $notice = is_wp_error($result) ? 'pinterest_failed' : 'pinterest_published';
        } elseif ($action === 'reset_pinterest') {
            self::reset_pinterest($post_id);
            $notice = 'pinterest_reset';
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

    private static function handle_cockpit_post(): void
    {
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $post = get_post($post_id);
        if (!self::post_type_is_supported($post) || !current_user_can('edit_post', $post_id)) {
            wp_die(esc_html__('You do not have permission to update this posting log.', 'dr-purg-social-syndicator'));
        }

        check_admin_referer('dpj_social_cockpit_' . $post_id, 'dpj_social_cockpit_nonce');

        $action = sanitize_key(wp_unslash((string) $_POST['dpj_cockpit_action']));
        if ($action === 'log_posted') {
            $target = isset($_POST['posting_target']) ? sanitize_text_field(wp_unslash((string) $_POST['posting_target'])) : '';
            self::add_posting_log_entry($post_id, $target);
        }

        wp_safe_redirect(add_query_arg(['page' => self::COCKPIT_SLUG, 'dpj_social_notice' => 'posting_logged'], admin_url('admin.php')));
        exit;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function posting_log(int $post_id): array
    {
        $raw = (string) get_post_meta($post_id, '_dpj_social_posting_log', true);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function add_posting_log_entry(int $post_id, string $target): void
    {
        $target = trim($target);
        if ($target === '') {
            $target = __('Facebook', 'dr-purg-social-syndicator');
        }

        $log = self::posting_log($post_id);
        array_unshift($log, ['target' => $target, 'time' => gmdate('c')]);
        $log = array_slice($log, 0, 50);
        update_post_meta($post_id, '_dpj_social_posting_log', wp_json_encode($log));
    }

    private static function handle_calendar_post(): void
    {
        if (!self::can_manage()) {
            wp_die(esc_html__('You do not have permission to manage the content calendar.', 'dr-purg-social-syndicator'));
        }

        check_admin_referer('dpj_social_calendar', 'dpj_social_calendar_nonce');

        $action = sanitize_key(wp_unslash((string) $_POST['dpj_calendar_action']));
        $notice = 'saved';

        if ($action === 'generate_ideas') {
            $seed = isset($_POST['calendar_seed']) ? sanitize_text_field(wp_unslash((string) $_POST['calendar_seed'])) : '';
            $result = self::generate_calendar_ideas($seed);
            $notice = is_wp_error($result) ? 'ideas_failed' : 'ideas_generated';
        } elseif ($action === 'add_idea') {
            self::add_calendar_idea([
                'title' => isset($_POST['idea_title']) ? sanitize_text_field(wp_unslash((string) $_POST['idea_title'])) : '',
                'angle' => isset($_POST['idea_angle']) ? sanitize_text_field(wp_unslash((string) $_POST['idea_angle'])) : '',
                'hook' => isset($_POST['idea_hook']) ? sanitize_text_field(wp_unslash((string) $_POST['idea_hook'])) : '',
                'date' => isset($_POST['idea_date']) ? sanitize_text_field(wp_unslash((string) $_POST['idea_date'])) : '',
            ]);
            $notice = 'idea_added';
        } elseif ($action === 'update_idea') {
            $id = isset($_POST['idea_id']) ? sanitize_key(wp_unslash((string) $_POST['idea_id'])) : '';
            self::update_calendar_idea($id, [
                'date' => isset($_POST['idea_date']) ? sanitize_text_field(wp_unslash((string) $_POST['idea_date'])) : '',
                'status' => isset($_POST['idea_status']) ? sanitize_text_field(wp_unslash((string) $_POST['idea_status'])) : 'idea',
                'notes' => isset($_POST['idea_notes']) ? sanitize_textarea_field(wp_unslash((string) $_POST['idea_notes'])) : '',
            ]);
            $notice = 'idea_updated';
        } elseif ($action === 'delete_idea') {
            $id = isset($_POST['idea_id']) ? sanitize_key(wp_unslash((string) $_POST['idea_id'])) : '';
            self::delete_calendar_idea($id);
            $notice = 'idea_deleted';
        }

        wp_safe_redirect(add_query_arg(['page' => self::CALENDAR_SLUG, 'dpj_social_notice' => $notice], admin_url('admin.php')));
        exit;
    }

    /**
     * Create a complete draft post from a single Claude "bundle" (one JSON object
     * holding the article HTML + SEO + every social field). The article HTML goes
     * to post_content (the only thing shown publicly); SEO and social fields go to
     * meta, so nothing extra leaks onto the live page. URL-bearing social fields
     * are left for ensure_social_package to fill with the real permalink at
     * publish. Reviewed-only: the post is created as a DRAFT for the editor to
     * review and publish.
     */
    private static function handle_import_post(): void
    {
        if (!self::can_manage()) {
            wp_die(esc_html__('You do not have permission to import content.', 'dr-purg-social-syndicator'));
        }

        check_admin_referer('dpj_social_import', 'dpj_social_import_nonce');

        $redirect = static function (string $notice) {
            wp_safe_redirect(add_query_arg(['page' => self::IMPORT_SLUG, 'dpj_social_notice' => $notice], admin_url('admin.php')));
            exit;
        };

        $raw = isset($_POST['claude_bundle']) ? trim((string) wp_unslash($_POST['claude_bundle'])) : '';
        if ($raw === '') {
            $redirect('import_empty');
        }

        $bundle = self::decode_ai_json_object($raw);
        if (!is_array($bundle)) {
            $redirect('import_bad_json');
        }

        $title = sanitize_text_field((string) ($bundle['title'] ?? ''));
        $content_html = (string) ($bundle['content_html'] ?? '');
        if ($title === '' || trim($content_html) === '') {
            $redirect('import_incomplete');
        }

        $post_id = wp_insert_post([
            'post_title' => $title,
            'post_content' => wp_kses_post($content_html),
            'post_excerpt' => sanitize_textarea_field((string) ($bundle['excerpt'] ?? '')),
            'post_status' => 'draft',
            'post_type' => 'post',
        ], true);

        if (is_wp_error($post_id) || (int) $post_id <= 0) {
            $redirect('import_failed');
        }
        $post_id = (int) $post_id;

        self::apply_imported_bundle($post_id, $bundle);

        wp_safe_redirect(admin_url('post.php?post=' . $post_id . '&action=edit'));
        exit;
    }

    /**
     * Distribute a decoded Claude bundle across SEO meta, taxonomy, and the social
     * package meta for a freshly created post.
     *
     * @param array<string, mixed> $bundle
     */
    private static function apply_imported_bundle(int $post_id, array $bundle): void
    {
        update_post_meta($post_id, '_kepoli_post_kind', 'article');

        $seo_title = self::clean_ai_text((string) ($bundle['seo_title'] ?? $bundle['title'] ?? ''), 58);
        if ($seo_title !== '') {
            update_post_meta($post_id, '_kepoli_seo_title', $seo_title);
        }
        $meta_description = self::clean_ai_text((string) ($bundle['meta_description'] ?? $bundle['excerpt'] ?? ''), 180);
        if ($meta_description !== '') {
            update_post_meta($post_id, '_kepoli_meta_description', $meta_description);
        }

        $tags = $bundle['tags'] ?? [];
        if (is_array($tags) && $tags !== []) {
            $clean_tags = [];
            foreach ($tags as $tag) {
                $tag = sanitize_text_field((string) $tag);
                if ($tag !== '') {
                    $clean_tags[] = $tag;
                }
            }
            if ($clean_tags !== []) {
                wp_set_post_tags($post_id, array_slice($clean_tags, 0, 15), false);
            }
        }

        $category = sanitize_text_field((string) ($bundle['category'] ?? ''));
        if ($category !== '') {
            $term = term_exists($category, 'category');
            if ($term === null || $term === 0) {
                $term = wp_insert_term($category, 'category');
            }
            if (!is_wp_error($term) && isset($term['term_id'])) {
                wp_set_post_categories($post_id, [(int) $term['term_id']], false);
            }
        }

        $image_alt = self::clean_ai_text((string) ($bundle['image_alt'] ?? ''), 160);
        if ($image_alt !== '') {
            update_post_meta($post_id, '_kepoli_image_plan_alt', $image_alt);
        }

        $image_prompt = self::clean_ai_text((string) ($bundle['image_prompt'] ?? ''), 1500);
        if ($image_prompt !== '') {
            update_post_meta($post_id, '_dpj_social_image_prompt', $image_prompt);
        }

        // Social creative fields. URL/link fields (facebook first comment, links,
        // pinterest_url) are deliberately omitted — ensure_social_package fills
        // them with the real permalink when the post is published.
        $social = [
            '_dpj_social_facebook_hook' => self::clean_ai_text((string) ($bundle['facebook_hook'] ?? ''), 110),
            '_dpj_social_facebook_summary' => self::clean_ai_text((string) ($bundle['facebook_summary'] ?? ''), 320),
            '_dpj_social_pinterest_title' => self::clean_ai_text((string) ($bundle['pinterest_title'] ?? ''), 100),
            '_dpj_social_pinterest_description' => self::clean_ai_text((string) ($bundle['pinterest_description'] ?? ''), 460),
            '_dpj_social_pinterest_alt_text' => self::clean_ai_text((string) ($bundle['pinterest_alt_text'] ?? ''), 140),
            '_dpj_social_reddit_title' => self::clean_ai_text((string) ($bundle['reddit_title'] ?? ''), 130),
            '_dpj_social_reddit_body' => self::clean_ai_text((string) ($bundle['reddit_body'] ?? ''), 900, true),
            '_dpj_social_local_overlay_text' => self::short_overlay_text((string) ($bundle['overlay_text'] ?? '')),
            '_dpj_social_local_hint_text' => self::short_hint_text((string) ($bundle['bottom_hint_text'] ?? '')),
        ];
        foreach ($social as $key => $value) {
            if ($value !== '') {
                update_post_meta($post_id, $key, $value);
            }
        }
        update_post_meta($post_id, '_dpj_social_local_overlay_enable', '1');
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function calendar_ideas(): array
    {
        $stored = get_option(self::CALENDAR_OPTION);
        return is_array($stored) ? array_values($stored) : [];
    }

    /**
     * @param array<int, array<string, string>> $ideas
     */
    private static function save_calendar_ideas(array $ideas): void
    {
        update_option(self::CALENDAR_OPTION, array_values($ideas), false);
    }

    /**
     * @param array<int, array<string, string>> $ideas
     */
    private static function next_calendar_id(array $ideas): string
    {
        $max = 0;
        foreach ($ideas as $idea) {
            $current = (int) ($idea['id'] ?? 0);
            if ($current > $max) {
                $max = $current;
            }
        }

        return (string) ($max + 1);
    }

    private static function calendar_idea_count(): int
    {
        return self::env_int('SOCIAL_AI_CALENDAR_IDEAS', 8, 3, 20);
    }

    private static function calendar_statuses(): array
    {
        return ['idea', 'planned', 'drafted', 'published'];
    }

    private static function sanitize_calendar_status(string $value): string
    {
        $value = sanitize_key($value);
        return in_array($value, self::calendar_statuses(), true) ? $value : 'idea';
    }

    private static function calendar_status_label(string $status): string
    {
        $labels = [
            'idea' => __('Idea', 'dr-purg-social-syndicator'),
            'planned' => __('Planned', 'dr-purg-social-syndicator'),
            'drafted' => __('Drafted', 'dr-purg-social-syndicator'),
            'published' => __('Published', 'dr-purg-social-syndicator'),
        ];

        return $labels[$status] ?? $status;
    }

    private static function sanitize_calendar_date(string $value): string
    {
        $value = trim($value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    /**
     * @param array<string, string> $fields
     */
    private static function add_calendar_idea(array $fields): void
    {
        $title = self::clean_ai_text((string) ($fields['title'] ?? ''), 140);
        if ($title === '') {
            return;
        }

        $ideas = self::calendar_ideas();
        $ideas[] = [
            'id' => self::next_calendar_id($ideas),
            'title' => $title,
            'angle' => self::clean_ai_text((string) ($fields['angle'] ?? ''), 60),
            'hook' => self::clean_ai_text((string) ($fields['hook'] ?? ''), 140),
            'date' => self::sanitize_calendar_date((string) ($fields['date'] ?? '')),
            'status' => 'idea',
            'notes' => '',
            'created' => gmdate('c'),
        ];
        self::save_calendar_ideas($ideas);
    }

    /**
     * @param array<string, string> $fields
     */
    private static function update_calendar_idea(string $id, array $fields): void
    {
        if ($id === '') {
            return;
        }

        $ideas = self::calendar_ideas();
        foreach ($ideas as &$idea) {
            if ((string) ($idea['id'] ?? '') !== $id) {
                continue;
            }
            if (isset($fields['date'])) {
                $idea['date'] = self::sanitize_calendar_date((string) $fields['date']);
            }
            if (isset($fields['status'])) {
                $idea['status'] = self::sanitize_calendar_status((string) $fields['status']);
            }
            if (isset($fields['notes'])) {
                $idea['notes'] = self::clean_ai_text((string) $fields['notes'], 300);
            }
            break;
        }
        unset($idea);

        self::save_calendar_ideas($ideas);
    }

    private static function delete_calendar_idea(string $id): void
    {
        if ($id === '') {
            return;
        }

        $ideas = array_filter(self::calendar_ideas(), static function ($idea) use ($id) {
            return (string) ($idea['id'] ?? '') !== $id;
        });
        self::save_calendar_ideas($ideas);
    }

    /**
     * Ask the AI for curiosity-led, guardrail-safe topic ideas and append them to
     * the calendar as `idea` entries for human review. Reviewed-only: ideas are
     * drafts; nothing is published. Recent post titles are sent so the model
     * avoids duplicating topics already covered.
     *
     * @return true|WP_Error
     */
    private static function generate_calendar_ideas(string $seed)
    {
        if (!self::social_ai_enabled()) {
            $error = __('AI is not enabled. Set SOCIAL_AI_ENABLE=1, SOCIAL_AI_PROVIDER=openrouter or anthropic, and the matching API key.', 'dr-purg-social-syndicator');
            update_option(self::CALENDAR_ERROR_OPTION, $error, false);
            return new WP_Error('dpj_social_ai_disabled', $error);
        }

        $count = self::calendar_idea_count();
        $system = 'You are a content strategist for a responsible US health-facts publication distributed on Facebook groups. Return only valid JSON. Propose curiosity-led but calm topic ideas for general audiences. Never diagnose, promise cures, invent facts, or use fear-mongering medical claims.';
        $payload = self::social_ai_complete($system, self::ai_calendar_prompt($count, $seed), self::ai_calendar_schema());
        if (is_wp_error($payload)) {
            update_option(self::CALENDAR_ERROR_OPTION, $payload->get_error_message(), false);
            return $payload;
        }

        $raw = isset($payload['ideas']) && is_array($payload['ideas']) ? $payload['ideas'] : [];
        $ideas = self::calendar_ideas();
        $added = 0;
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $title = self::clean_ai_text((string) ($item['title'] ?? ''), 140);
            if ($title === '') {
                continue;
            }
            $ideas[] = [
                'id' => self::next_calendar_id($ideas),
                'title' => $title,
                'angle' => self::clean_ai_text((string) ($item['angle'] ?? ''), 60),
                'hook' => self::clean_ai_text((string) ($item['hook'] ?? ''), 140),
                'date' => '',
                'status' => 'idea',
                'notes' => self::clean_ai_text((string) ($item['rationale'] ?? ''), 300),
                'created' => gmdate('c'),
            ];
            $added++;
            if ($added >= $count) {
                break;
            }
        }

        if ($added === 0) {
            $error = __('The AI did not return any usable ideas.', 'dr-purg-social-syndicator');
            update_option(self::CALENDAR_ERROR_OPTION, $error, false);
            return new WP_Error('dpj_social_ai_no_ideas', $error);
        }

        self::save_calendar_ideas($ideas);
        delete_option(self::CALENDAR_ERROR_OPTION);

        return true;
    }

    private static function ai_calendar_prompt(int $count, string $seed): string
    {
        $recent = get_posts([
            'post_type' => 'post',
            'post_status' => ['publish', 'future', 'draft'],
            'posts_per_page' => 25,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
        ]);

        $titles = [];
        foreach ($recent as $recent_id) {
            $title = get_the_title((int) $recent_id);
            if ($title !== '') {
                $titles[] = '- ' . $title;
            }
        }
        $recent_block = $titles === [] ? '(none yet)' : implode("\n", $titles);
        $seed_line = $seed !== '' ? "Theme or seed to lean into: {$seed}\n\n" : '';

        return trim(
            "Propose {$count} DISTINCT content ideas for Dr Purg Jr., an English-language viral health-facts publication for mobile readers in the United States, distributed mainly through Facebook groups.\n"
            . "Return ONLY valid JSON, no markdown.\n"
            . "Shape: {\"ideas\":[{\"title\":\"working article title\",\"angle\":\"short angle label\",\"hook\":\"curiosity hook under 95 characters\",\"rationale\":\"one sentence on why it can earn clicks responsibly\"}]}\n\n"
            . "Rules:\n"
            . "- Curiosity-led but calm and responsible: general health information only.\n"
            . "- No diagnosis, cure, treatment, prevention, or personal medical advice claims; no fear-mongering or invented facts.\n"
            . "- Each idea must be a genuinely different topic and angle.\n"
            . "- Prefer everyday, relatable body-signal, habit, and nutrition topics a broad audience clicks.\n"
            . "- Do NOT repeat topics already covered (listed below).\n\n"
            . $seed_line
            . "Recently covered titles (avoid duplicating these):\n{$recent_block}"
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function ai_calendar_schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'ideas' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'angle' => ['type' => 'string'],
                            'hook' => ['type' => 'string'],
                            'rationale' => ['type' => 'string'],
                        ],
                        'required' => ['title', 'angle', 'hook', 'rationale'],
                    ],
                ],
            ],
            'required' => ['ideas'],
        ];
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
        update_post_meta($post_id, '_dpj_social_local_overlay_pos', self::sanitize_overlay_position(isset($_POST['local_overlay_pos']) ? (string) wp_unslash($_POST['local_overlay_pos']) : 'center'));
        update_post_meta($post_id, '_dpj_social_local_crop_focus', self::sanitize_crop_focus(isset($_POST['local_crop_focus']) ? (string) wp_unslash($_POST['local_crop_focus']) : 'center'));
        update_post_meta($post_id, '_dpj_social_local_hint_enable', isset($_POST['local_hint_enable']) ? '1' : '0');

        self::save_performance_fields($post_id);

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

    private static function save_performance_fields(int $post_id): void
    {
        $clicks = isset($_POST['perf_clicks']) ? trim((string) wp_unslash($_POST['perf_clicks'])) : '';
        $rpm = isset($_POST['perf_rpm']) ? trim((string) wp_unslash($_POST['perf_rpm'])) : '';
        $revenue = isset($_POST['perf_revenue']) ? trim((string) wp_unslash($_POST['perf_revenue'])) : '';
        $pps = isset($_POST['perf_pps']) ? trim((string) wp_unslash($_POST['perf_pps'])) : '';
        $notes = isset($_POST['perf_notes']) ? sanitize_textarea_field((string) wp_unslash($_POST['perf_notes'])) : '';

        $clicks = $clicks === '' ? '' : (string) absint($clicks);
        $rpm = self::sanitize_decimal($rpm);
        $revenue = self::sanitize_decimal($revenue);
        $pps = self::sanitize_decimal($pps);

        update_post_meta($post_id, '_dpj_social_perf_clicks', $clicks);
        update_post_meta($post_id, '_dpj_social_perf_rpm', $rpm);
        update_post_meta($post_id, '_dpj_social_perf_revenue', $revenue);
        update_post_meta($post_id, '_dpj_social_perf_pps', $pps);
        update_post_meta($post_id, '_dpj_social_perf_notes', $notes);

        if ($clicks !== '' || $rpm !== '' || $revenue !== '' || $pps !== '' || $notes !== '') {
            update_post_meta($post_id, '_dpj_social_perf_updated', gmdate('c'));
        }
    }

    private static function sanitize_decimal(string $value): string
    {
        $value = trim(str_replace(',', '.', $value));
        if ($value === '' || !is_numeric($value)) {
            return '';
        }

        return number_format(max(0, (float) $value), 2, '.', '');
    }

    /**
     * Import a CSV (for example a GA4 export) and fill the performance log by
     * matching each row's campaign/slug to a post's utm_campaign (its slug).
     * Recognised columns (header text, case-insensitive, first match wins):
     *  - campaign: contains "campaign", "slug", or "utm"
     *  - clicks:   contains "click", "session", "user", or "view"
     *  - rpm:      contains "rpm"
     *  - revenue:  contains "revenue" or "earnings"
     * Only the columns present are written; missing ones are left untouched.
     */
    private static function handle_performance_import(): void
    {
        if (!self::can_manage()) {
            wp_die(esc_html__('You do not have permission to import performance data.', 'dr-purg-social-syndicator'));
        }

        check_admin_referer('dpj_social_perf_import', 'dpj_social_perf_nonce');

        $redirect = static function (string $notice, array $args = []) {
            wp_safe_redirect(add_query_arg(array_merge(['page' => self::PERF_SLUG, 'dpj_social_notice' => $notice], $args), admin_url('admin.php')));
            exit;
        };

        if (!isset($_FILES['perf_csv']) || !is_array($_FILES['perf_csv']) || (int) ($_FILES['perf_csv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $redirect('perf_import_failed');
        }

        $tmp = (string) ($_FILES['perf_csv']['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            $redirect('perf_import_failed');
        }

        $handle = fopen($tmp, 'r');
        if ($handle === false) {
            $redirect('perf_import_failed');
        }

        $header = fgetcsv($handle);
        if (!is_array($header)) {
            fclose($handle);
            $redirect('perf_import_failed');
        }

        $cols = ['campaign' => -1, 'clicks' => -1, 'pps' => -1, 'rpm' => -1, 'revenue' => -1];

        // Find the campaign/slug column first. GA4 often labels it "Session
        // campaign", which also contains "session", so it must be claimed before
        // the metric columns are matched or it would be mistaken for clicks.
        foreach ($header as $index => $name) {
            $key = strtolower(trim((string) $name));
            if (str_contains($key, 'campaign') || str_contains($key, 'slug') || str_contains($key, 'utm')) {
                $cols['campaign'] = $index;
                break;
            }
        }

        // Then match the metric columns, skipping the campaign column.
        foreach ($header as $index => $name) {
            if ($index === $cols['campaign']) {
                continue;
            }
            $key = strtolower(trim((string) $name));
            // Pages/session must be claimed BEFORE clicks — "views per session"
            // contains "view"/"session" and would otherwise be grabbed as clicks.
            if ($cols['pps'] < 0 && (str_contains($key, 'per session') || str_contains($key, 'pages/session') || str_contains($key, 'pages per') || str_contains($key, 'screenpageviewspersession') || str_contains($key, 'views per session'))) {
                $cols['pps'] = $index;
                continue;
            }
            if ($cols['clicks'] < 0 && (str_contains($key, 'click') || str_contains($key, 'session') || str_contains($key, 'user') || str_contains($key, 'view'))) {
                $cols['clicks'] = $index;
            }
            if ($cols['rpm'] < 0 && str_contains($key, 'rpm')) {
                $cols['rpm'] = $index;
            }
            if ($cols['revenue'] < 0 && (str_contains($key, 'revenue') || str_contains($key, 'earning'))) {
                $cols['revenue'] = $index;
            }
        }

        if ($cols['campaign'] < 0) {
            fclose($handle);
            $redirect('perf_import_no_campaign');
        }

        // Map every post slug (its utm_campaign) to its ID once.
        $slug_to_id = [];
        foreach (get_posts([
            'post_type' => 'post',
            'post_status' => ['publish', 'future', 'draft'],
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]) as $pid) {
            $slug = sanitize_title((string) get_post_field('post_name', (int) $pid));
            if ($slug !== '') {
                $slug_to_id[$slug] = (int) $pid;
            }
        }

        $rows = 0;
        $updated = 0;
        $unmatched = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if (!is_array($row) || $row === [null]) {
                continue;
            }
            $campaign_raw = isset($row[$cols['campaign']]) ? (string) $row[$cols['campaign']] : '';
            if (trim($campaign_raw) === '') {
                continue;
            }
            $rows++;
            $slug = sanitize_title($campaign_raw);
            if ($slug === '' || !isset($slug_to_id[$slug])) {
                $unmatched++;
                continue;
            }

            $post_id = $slug_to_id[$slug];
            $changed = false;

            if ($cols['clicks'] >= 0 && isset($row[$cols['clicks']])) {
                $clicks_digits = preg_replace('/[^0-9]/', '', (string) $row[$cols['clicks']]);
                if ($clicks_digits !== '' && $clicks_digits !== null) {
                    update_post_meta($post_id, '_dpj_social_perf_clicks', (string) absint($clicks_digits));
                    $changed = true;
                }
            }
            if ($cols['rpm'] >= 0 && isset($row[$cols['rpm']])) {
                $rpm = self::sanitize_decimal(self::strip_number((string) $row[$cols['rpm']]));
                if ($rpm !== '') {
                    update_post_meta($post_id, '_dpj_social_perf_rpm', $rpm);
                    $changed = true;
                }
            }
            if ($cols['revenue'] >= 0 && isset($row[$cols['revenue']])) {
                $revenue = self::sanitize_decimal(self::strip_number((string) $row[$cols['revenue']]));
                if ($revenue !== '') {
                    update_post_meta($post_id, '_dpj_social_perf_revenue', $revenue);
                    $changed = true;
                }
            }
            if ($cols['pps'] >= 0 && isset($row[$cols['pps']])) {
                $pps = self::sanitize_decimal(self::strip_number((string) $row[$cols['pps']]));
                if ($pps !== '') {
                    update_post_meta($post_id, '_dpj_social_perf_pps', $pps);
                    $changed = true;
                }
            }

            if ($changed) {
                update_post_meta($post_id, '_dpj_social_perf_updated', gmdate('c'));
                $updated++;
            }
        }
        fclose($handle);

        $redirect('perf_imported', [
            'dpj_perf_updated' => $updated,
            'dpj_perf_rows' => $rows,
            'dpj_perf_unmatched' => $unmatched,
        ]);
    }

    /**
     * Strip currency symbols and thousands separators from a number string,
     * leaving a plain decimal that sanitize_decimal() can parse.
     */
    private static function strip_number(string $value): string
    {
        return preg_replace('/[^0-9.]/', '', str_replace(',', '', trim($value))) ?? '';
    }

    private static function hook_variant_count(): int
    {
        return self::env_int('SOCIAL_AI_HOOK_VARIANTS', 4, 2, 8);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function hook_variants(int $post_id): array
    {
        $raw = (string) get_post_meta($post_id, '_dpj_social_hook_variants', true);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function selected_variant(int $post_id): string
    {
        return (string) get_post_meta($post_id, '_dpj_social_selected_variant', true);
    }

    /**
     * Generate several distinct Facebook hook angles (with overlay text) for A/B
     * testing. Each is tagged v1..vN for utm_content so the performance log can
     * tell them apart. Reviewed-only: this fills draft variants, never posts.
     *
     * @return true|WP_Error
     */
    private static function generate_hook_variants(int $post_id)
    {
        if (!self::social_ai_enabled()) {
            $error = __('AI is not enabled. Set SOCIAL_AI_ENABLE=1, SOCIAL_AI_PROVIDER=openrouter or anthropic, and the matching API key.', 'dr-purg-social-syndicator');
            update_post_meta($post_id, '_dpj_social_ai_last_error', $error);
            return new WP_Error('dpj_social_ai_disabled', $error);
        }

        $count = self::hook_variant_count();
        $system = 'You are a careful social copy editor for a responsible health publication. Return only valid JSON. Write curiosity-led but calm hooks. Never diagnose, promise cures, invent facts, or use fear-mongering medical claims.';
        $payload = self::social_ai_complete($system, self::ai_hook_variants_prompt($post_id, $count), self::ai_hook_variants_schema());
        if (is_wp_error($payload)) {
            update_post_meta($post_id, '_dpj_social_ai_last_error', $payload->get_error_message());
            return $payload;
        }

        $raw = isset($payload['variants']) && is_array($payload['variants']) ? $payload['variants'] : [];
        $variants = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $hook = self::clean_ai_text((string) ($item['hook'] ?? ''), 120);
            if ($hook === '') {
                continue;
            }
            $variants[] = [
                'content' => 'v' . (count($variants) + 1),
                'angle' => self::clean_ai_text((string) ($item['angle'] ?? ''), 60),
                'hook' => $hook,
                'overlay' => self::clean_ai_text((string) ($item['overlay'] ?? ''), 90),
            ];
            if (count($variants) >= $count) {
                break;
            }
        }

        if ($variants === []) {
            $error = __('The AI did not return any usable hook variants.', 'dr-purg-social-syndicator');
            update_post_meta($post_id, '_dpj_social_ai_last_error', $error);
            return new WP_Error('dpj_social_ai_no_variants', $error);
        }

        update_post_meta($post_id, '_dpj_social_hook_variants', wp_json_encode($variants));
        update_post_meta($post_id, '_dpj_social_hook_variants_run', gmdate('c'));
        update_post_meta($post_id, '_dpj_social_ai_model', self::social_ai_model());
        delete_post_meta($post_id, '_dpj_social_ai_last_error');

        return true;
    }

    private static function apply_hook_variant(int $post_id, int $index): bool
    {
        $variants = self::hook_variants($post_id);
        if (!isset($variants[$index]) || !is_array($variants[$index])) {
            return false;
        }

        $variant = $variants[$index];
        update_post_meta($post_id, '_dpj_social_facebook_hook', (string) ($variant['hook'] ?? ''));
        if (!empty($variant['overlay'])) {
            update_post_meta($post_id, '_dpj_social_local_overlay_text', (string) $variant['overlay']);
        }
        update_post_meta($post_id, '_dpj_social_selected_variant', (string) ($variant['content'] ?? ''));

        return true;
    }

    private static function ai_hook_variants_prompt(int $post_id, int $count): string
    {
        $title = get_the_title($post_id);
        $intro = self::source_intro($post_id);
        $categories = implode(', ', wp_get_post_categories($post_id, ['fields' => 'names']));
        $post = get_post($post_id);
        $content = $post instanceof WP_Post ? self::ai_source_text((string) $post->post_content) : '';

        return trim(
            "Write {$count} DISTINCT Facebook hook options for this health-facts article, for curiosity-led but responsible distribution to US mobile readers.\n"
            . "Return ONLY valid JSON, no markdown.\n"
            . "Shape: {\"variants\":[{\"angle\":\"short label for the angle\",\"hook\":\"curiosity hook under 95 characters\",\"overlay\":\"on-image version, 5-9 words\"}]}\n\n"
            . "Rules:\n"
            . "- Each variant must take a genuinely different angle (for example: curiosity gap, surprising number, common mistake, body signal, myth correction).\n"
            . "- Hooks are clickable but calm: no fake urgency, no diagnosis, no cure promise, no invented facts.\n"
            . "- overlay is a short on-image version of the hook.\n"
            . "- General health information only.\n\n"
            . "Article title: {$title}\n"
            . "Intro/excerpt: {$intro}\n"
            . "Categories: {$categories}\n\n"
            . "Article content:\n{$content}"
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function ai_hook_variants_schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'variants' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'angle' => ['type' => 'string'],
                            'hook' => ['type' => 'string'],
                            'overlay' => ['type' => 'string'],
                        ],
                        'required' => ['angle', 'hook', 'overlay'],
                    ],
                ],
            ],
            'required' => ['variants'],
        ];
    }

    private static function render_hook_variants_section(int $post_id): void
    {
        $enabled = self::social_ai_enabled();
        $variants = self::hook_variants($post_id);
        $selected = self::selected_variant($post_id);
        ?>
        <section class="dpj-social-card dpj-hook-variants" data-dpj-hook-variants>
            <header class="dpj-platform__header">
                <h2><?php esc_html_e('AI hook variants', 'dr-purg-social-syndicator'); ?></h2>
                <button class="button button-primary" type="submit" name="dpj_social_editor_action" value="generate_hook_variants" <?php disabled(!$enabled); ?>><?php esc_html_e('Generate hook variants', 'dr-purg-social-syndicator'); ?></button>
            </header>
            <?php if (!$enabled) : ?>
                <div class="notice notice-warning inline"><p><?php esc_html_e('Set SOCIAL_AI_ENABLE=1, SOCIAL_AI_PROVIDER=openrouter or anthropic, and the matching API key to generate variants.', 'dr-purg-social-syndicator'); ?></p></div>
            <?php endif; ?>
            <p class="dpj-social-note"><?php esc_html_e('Generate several hook angles to A/B test. Apply one to the Facebook hook and overlay, then copy its tracked link to post. Each variant carries a utm_content tag (v1, v2, …) so the Performance log can tell them apart.', 'dr-purg-social-syndicator'); ?></p>
            <?php if ($variants === []) : ?>
                <p><em><?php esc_html_e('No variants yet. Generate some to compare angles.', 'dr-purg-social-syndicator'); ?></em></p>
            <?php else : ?>
                <ol class="dpj-variant-list">
                    <?php foreach ($variants as $index => $variant) :
                        $content_tag = (string) ($variant['content'] ?? ('v' . ((int) $index + 1)));
                        $is_selected = $selected !== '' && $selected === $content_tag;
                        $tracked = self::social_utm_url($post_id, 'facebook', $content_tag);
                        $field_id = 'dpj-variant-link-' . (int) $index;
                        ?>
                        <li class="dpj-variant <?php echo $is_selected ? 'dpj-variant--selected' : ''; ?>">
                            <p class="dpj-variant__meta"><strong><?php echo esc_html($content_tag); ?></strong> · <?php echo esc_html((string) ($variant['angle'] ?? '')); ?></p>
                            <p class="dpj-variant__hook"><?php echo esc_html((string) ($variant['hook'] ?? '')); ?></p>
                            <?php if (!empty($variant['overlay'])) : ?>
                                <p class="dpj-variant__overlay"><?php esc_html_e('Overlay:', 'dr-purg-social-syndicator'); ?> <?php echo esc_html((string) $variant['overlay']); ?></p>
                            <?php endif; ?>
                            <?php if ($tracked !== '') : ?>
                                <input type="text" readonly id="<?php echo esc_attr($field_id); ?>" value="<?php echo esc_attr($tracked); ?>">
                            <?php endif; ?>
                            <p class="dpj-variant__actions">
                                <button class="button button-primary" type="submit" name="dpj_social_editor_action" value="apply_hook_variant_<?php echo (int) $index; ?>"><?php echo $is_selected ? esc_html__('Applied', 'dr-purg-social-syndicator') : esc_html__('Apply to Facebook', 'dr-purg-social-syndicator'); ?></button>
                                <?php if ($tracked !== '') : ?>
                                    <button class="button" type="button" data-dpj-copy="#<?php echo esc_attr($field_id); ?>"><?php esc_html_e('Copy tracked link', 'dr-purg-social-syndicator'); ?></button>
                                <?php endif; ?>
                            </p>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
        <?php
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
        self::maybe_set_meta($post_id, '_dpj_social_local_overlay_pos', 'center');
        self::maybe_set_meta($post_id, '_dpj_social_local_crop_focus', 'center');
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

    private static function social_utm_enabled(): bool
    {
        return self::env_bool('SOCIAL_UTM_ENABLE', false);
    }

    private static function utm_platform_medium(string $source): string
    {
        switch ($source) {
            case 'facebook':
                return self::env('SOCIAL_UTM_FACEBOOK_MEDIUM', 'group');
            case 'pinterest':
                return self::env('SOCIAL_UTM_PINTEREST_MEDIUM', 'pin');
            case 'reddit':
                return self::env('SOCIAL_UTM_REDDIT_MEDIUM', 'social');
            default:
                return 'social';
        }
    }

    /**
     * Build a UTM-tagged tracking URL for a post on a given platform.
     *
     * Campaign defaults to the post slug; an optional $variant fills utm_content
     * (the hook variant), and an optional $group fills utm_term as g_<slug> so
     * clicks can be attributed to the specific Facebook group it was posted in.
     * Returns the plain permalink when tracking is disabled or the post has no
     * URL, so callers are safe to use it unconditionally.
     */
    private static function social_utm_url(int $post_id, string $source, string $variant = '', string $group = ''): string
    {
        $permalink = get_permalink($post_id) ?: '';
        if ($permalink === '' || !self::social_utm_enabled()) {
            return $permalink;
        }

        $base = remove_query_arg(['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'], $permalink);
        $campaign = sanitize_title((string) get_post_field('post_name', $post_id));
        if ($campaign === '') {
            $campaign = (string) $post_id;
        }

        $args = [
            'utm_source' => $source,
            'utm_medium' => self::utm_platform_medium($source),
            'utm_campaign' => $campaign,
        ];
        $variant = sanitize_title($variant);
        if ($variant !== '') {
            $args['utm_content'] = $variant;
        }
        $group = sanitize_title($group);
        if ($group !== '') {
            $args['utm_term'] = 'g_' . $group;
        }

        return add_query_arg($args, $base);
    }

    /**
     * Replace the post's plain permalink inside a text with the tracked URL.
     *
     * No-op when tracking is disabled, the permalink is absent, or the text
     * already carries a utm_source (idempotent — safe to call before posting).
     */
    private static function apply_utm_to_text(string $text, int $post_id, string $source, string $variant = ''): string
    {
        if (!self::social_utm_enabled() || strpos($text, 'utm_source=') !== false) {
            return $text;
        }

        $permalink = get_permalink($post_id) ?: '';
        if ($permalink === '' || strpos($text, $permalink) === false) {
            return $text;
        }

        return str_replace($permalink, self::social_utm_url($post_id, $source, $variant), $text);
    }

    private static function render_tracked_links_section(int $post_id): void
    {
        if (!self::social_utm_enabled()) {
            return;
        }

        $links = [
            'facebook' => __('Facebook (groups / page)', 'dr-purg-social-syndicator'),
            'pinterest' => __('Pinterest', 'dr-purg-social-syndicator'),
            'reddit' => __('Reddit', 'dr-purg-social-syndicator'),
        ];
        ?>
        <section class="dpj-social-card dpj-tracked-links" data-dpj-tracked-links>
            <h2><?php esc_html_e('Tracked links (UTM)', 'dr-purg-social-syndicator'); ?></h2>
            <p class="dpj-social-note"><?php esc_html_e('Use these UTM-tagged links when you post so clicks show up by source in Analytics. The Facebook first comment is auto-tagged when you post through the API.', 'dr-purg-social-syndicator'); ?></p>
            <?php foreach ($links as $source => $label) :
                $field_id = 'dpj-utm-' . $source;
                ?>
                <div class="dpj-tracked-link">
                    <label for="<?php echo esc_attr($field_id); ?>"><?php echo esc_html($label); ?></label>
                    <input type="text" readonly id="<?php echo esc_attr($field_id); ?>" value="<?php echo esc_attr(self::social_utm_url($post_id, (string) $source)); ?>">
                    <button class="button" type="button" data-dpj-copy="#<?php echo esc_attr($field_id); ?>"><?php esc_html_e('Copy', 'dr-purg-social-syndicator'); ?></button>
                </div>
            <?php endforeach; ?>
        </section>
        <?php
    }

    private static function render_performance_section(int $post_id): void
    {
        $hook = (string) get_post_meta($post_id, '_dpj_social_facebook_hook', true);
        $campaign = sanitize_title((string) get_post_field('post_name', $post_id));
        $posted_at = (string) get_post_meta($post_id, '_dpj_social_facebook_posted_at', true);
        $clicks = (string) get_post_meta($post_id, '_dpj_social_perf_clicks', true);
        $rpm = (string) get_post_meta($post_id, '_dpj_social_perf_rpm', true);
        $revenue = (string) get_post_meta($post_id, '_dpj_social_perf_revenue', true);
        $pps = (string) get_post_meta($post_id, '_dpj_social_perf_pps', true);
        $engagement = (string) get_post_meta($post_id, '_dpj_social_perf_engagement', true);
        $notes = (string) get_post_meta($post_id, '_dpj_social_perf_notes', true);
        ?>
        <section class="dpj-social-card dpj-performance" data-dpj-performance>
            <h2><?php esc_html_e('Performance', 'dr-purg-social-syndicator'); ?></h2>
            <p class="dpj-social-note"><?php esc_html_e('Record real results so you can compare which hooks earn clicks and revenue. Read clicks from Analytics by UTM campaign; enter RPM and revenue from your ad network once finalized.', 'dr-purg-social-syndicator'); ?></p>
            <ul class="dpj-perf-context">
                <li><strong><?php esc_html_e('Hook:', 'dr-purg-social-syndicator'); ?></strong> <?php echo esc_html($hook !== '' ? $hook : '—'); ?></li>
                <li><strong><?php esc_html_e('UTM campaign:', 'dr-purg-social-syndicator'); ?></strong> <code><?php echo esc_html($campaign !== '' ? $campaign : (string) $post_id); ?></code></li>
                <li><strong><?php esc_html_e('Facebook posted:', 'dr-purg-social-syndicator'); ?></strong> <?php echo esc_html($posted_at !== '' ? $posted_at : '—'); ?></li>
            </ul>
            <div class="dpj-perf-grid">
                <label><?php esc_html_e('Clicks', 'dr-purg-social-syndicator'); ?>
                    <input type="number" min="0" step="1" name="perf_clicks" value="<?php echo esc_attr($clicks); ?>">
                </label>
                <label><?php esc_html_e('RPM (USD / 1k)', 'dr-purg-social-syndicator'); ?>
                    <input type="number" min="0" step="0.01" name="perf_rpm" value="<?php echo esc_attr($rpm); ?>">
                </label>
                <label><?php esc_html_e('Revenue (USD)', 'dr-purg-social-syndicator'); ?>
                    <input type="number" min="0" step="0.01" name="perf_revenue" value="<?php echo esc_attr($revenue); ?>">
                </label>
                <label><?php esc_html_e('Pages/session', 'dr-purg-social-syndicator'); ?>
                    <input type="number" min="0" step="0.01" name="perf_pps" value="<?php echo esc_attr($pps); ?>">
                </label>
            </div>
            <?php if ($engagement !== '') : ?>
                <p class="dpj-social-note"><?php printf(/* translators: %s is an engagement rate. */ esc_html__('GA4 engagement rate: %s', 'dr-purg-social-syndicator'), esc_html($engagement)); ?></p>
            <?php endif; ?>
            <p class="dpj-social-note"><?php esc_html_e('Pages/session is the second revenue multiplier (clicks × pages/session × RPM). A high-clicks, ~1.0 pages/session post is a broken payoff — the hook out-ran the article. Pulled by GA4, or enter it by hand.', 'dr-purg-social-syndicator'); ?></p>
            <?php self::render_textarea('perf_notes', __('Notes', 'dr-purg-social-syndicator'), $notes, 2, 'dpj-perf-notes'); ?>
        </section>
        <?php
    }

    public static function render_cockpit_page(): void
    {
        if (!self::can_manage()) {
            wp_die(esc_html__('You do not have permission to view the posting cockpit.', 'dr-purg-social-syndicator'));
        }

        $posts = get_posts([
            'post_type' => 'post',
            'post_status' => ['publish', 'future'],
            'posts_per_page' => 50,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        $cards = [];
        foreach ($posts as $post) {
            if (!$post instanceof WP_Post) {
                continue;
            }
            if ((string) get_post_meta($post->ID, '_dpj_social_skip', true) === '1') {
                continue;
            }
            if ((string) get_post_meta($post->ID, '_dpj_social_facebook_hook', true) === '') {
                continue;
            }
            $cards[] = $post;
        }

        ?>
        <div class="wrap dpj-social-wrap dpj-cockpit">
            <h1><?php esc_html_e('Posting Cockpit', 'dr-purg-social-syndicator'); ?></h1>
            <?php self::render_notice_from_query(); ?>
            <p><?php esc_html_e('Everything you need to post each article in seconds: copy the caption and first comment, grab the image, then log where and when you posted. You post manually — this only removes the prep work.', 'dr-purg-social-syndicator'); ?></p>
            <?php if ($cards === []) : ?>
                <p><em><?php esc_html_e('No packaged posts yet. Build a social package in the Social Editor first.', 'dr-purg-social-syndicator'); ?></em></p>
            <?php endif; ?>
            <?php foreach ($cards as $post) :
                $post_id = $post->ID;
                $caption = self::facebook_message($post_id);
                $first_comment = (string) get_post_meta($post_id, '_dpj_social_facebook_first_comment', true);
                if ($first_comment === '') {
                    $first_comment = self::default_facebook_first_comment(get_permalink($post_id) ?: '');
                }
                $first_comment = self::apply_utm_to_text($first_comment, $post_id, 'facebook', self::selected_variant($post_id));
                $tracked = self::social_utm_url($post_id, 'facebook', self::selected_variant($post_id));
                $media_id = (int) get_post_meta($post_id, '_dpj_social_facebook_media_id', true);
                if ($media_id <= 0) {
                    $media_id = (int) get_post_thumbnail_id($post_id);
                }
                $image = $media_id > 0 ? wp_get_attachment_image($media_id, 'medium', false, ['class' => 'dpj-cockpit-image']) : '';
                $log = self::posting_log($post_id);
                $fb_status = self::status_label(self::platform_status($post_id, 'facebook'));
                ?>
                <section class="dpj-social-card dpj-cockpit-card">
                    <header class="dpj-cockpit-card__head">
                        <h2><?php echo esc_html(get_the_title($post)); ?></h2>
                        <span class="dpj-cockpit-status"><?php echo esc_html(sprintf(/* translators: %s is the Facebook status. */ __('Facebook: %s', 'dr-purg-social-syndicator'), $fb_status)); ?></span>
                    </header>
                    <div class="dpj-cockpit-body">
                        <?php if ($image !== '') : ?>
                            <div class="dpj-cockpit-media"><?php echo wp_kses_post($image); ?></div>
                        <?php endif; ?>
                        <div class="dpj-cockpit-fields">
                            <label><?php esc_html_e('Caption', 'dr-purg-social-syndicator'); ?>
                                <textarea readonly rows="4" id="dpj-cockpit-caption-<?php echo (int) $post_id; ?>"><?php echo esc_textarea($caption); ?></textarea>
                            </label>
                            <p><button class="button" type="button" data-dpj-copy="#dpj-cockpit-caption-<?php echo (int) $post_id; ?>"><?php esc_html_e('Copy caption', 'dr-purg-social-syndicator'); ?></button></p>
                            <label><?php esc_html_e('First comment', 'dr-purg-social-syndicator'); ?>
                                <textarea readonly rows="3" id="dpj-cockpit-comment-<?php echo (int) $post_id; ?>"><?php echo esc_textarea($first_comment); ?></textarea>
                            </label>
                            <p><button class="button" type="button" data-dpj-copy="#dpj-cockpit-comment-<?php echo (int) $post_id; ?>"><?php esc_html_e('Copy first comment', 'dr-purg-social-syndicator'); ?></button></p>
                            <label><?php esc_html_e('Tracked link', 'dr-purg-social-syndicator'); ?>
                                <input type="text" readonly id="dpj-cockpit-link-<?php echo (int) $post_id; ?>" value="<?php echo esc_attr($tracked); ?>">
                            </label>
                            <p>
                                <button class="button" type="button" data-dpj-copy="#dpj-cockpit-link-<?php echo (int) $post_id; ?>"><?php esc_html_e('Copy link', 'dr-purg-social-syndicator'); ?></button>
                                <a class="button" href="<?php echo esc_url(self::editor_url($post_id)); ?>"><?php esc_html_e('Open editor', 'dr-purg-social-syndicator'); ?></a>
                            </p>
                            <?php if (self::social_utm_enabled()) : ?>
                                <label><?php esc_html_e('Group link (type the group below to tag it)', 'dr-purg-social-syndicator'); ?>
                                    <input type="text" readonly id="dpj-cockpit-grouplink-<?php echo (int) $post_id; ?>" data-dpj-group-base="<?php echo esc_attr($tracked); ?>" value="<?php echo esc_attr($tracked); ?>">
                                </label>
                                <p><button class="button" type="button" data-dpj-copy="#dpj-cockpit-grouplink-<?php echo (int) $post_id; ?>"><?php esc_html_e('Copy group link', 'dr-purg-social-syndicator'); ?></button></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="dpj-cockpit-log">
                        <h3><?php esc_html_e('Posting log', 'dr-purg-social-syndicator'); ?></h3>
                        <?php if ($log === []) : ?>
                            <p><em><?php esc_html_e('Not posted anywhere yet.', 'dr-purg-social-syndicator'); ?></em></p>
                        <?php else : ?>
                            <ul>
                                <?php foreach ($log as $entry) : ?>
                                    <li><strong><?php echo esc_html((string) ($entry['target'] ?? '')); ?></strong> — <?php echo esc_html((string) ($entry['time'] ?? '')); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <form method="post" action="<?php echo esc_url(add_query_arg(['page' => self::COCKPIT_SLUG], admin_url('admin.php'))); ?>" class="dpj-cockpit-log-form">
                            <?php wp_nonce_field('dpj_social_cockpit_' . $post_id, 'dpj_social_cockpit_nonce'); ?>
                            <input type="hidden" name="post_id" value="<?php echo (int) $post_id; ?>">
                            <input type="text" name="posting_target" data-dpj-group-link-for="dpj-cockpit-grouplink-<?php echo (int) $post_id; ?>" placeholder="<?php esc_attr_e('Group or destination', 'dr-purg-social-syndicator'); ?>">
                            <button class="button button-primary" type="submit" name="dpj_cockpit_action" value="log_posted"><?php esc_html_e('Mark posted', 'dr-purg-social-syndicator'); ?></button>
                        </form>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
        <?php
    }

    public static function render_calendar_page(): void
    {
        if (!self::can_manage()) {
            wp_die(esc_html__('You do not have permission to view the content calendar.', 'dr-purg-social-syndicator'));
        }

        $enabled = self::social_ai_enabled();
        $ideas = self::calendar_ideas();
        $error = (string) get_option(self::CALENDAR_ERROR_OPTION, '');
        $statuses = self::calendar_statuses();

        // Dated ideas first (ascending), undated after, each group oldest-created first.
        usort($ideas, static function ($a, $b) {
            $da = (string) ($a['date'] ?? '');
            $db = (string) ($b['date'] ?? '');
            if ($da === '' && $db === '') {
                return strcmp((string) ($a['created'] ?? ''), (string) ($b['created'] ?? ''));
            }
            if ($da === '') {
                return 1;
            }
            if ($db === '') {
                return -1;
            }
            return strcmp($da, $db);
        });

        $action_url = add_query_arg(['page' => self::CALENDAR_SLUG], admin_url('admin.php'));
        ?>
        <div class="wrap dpj-social-wrap dpj-calendar">
            <h1><?php esc_html_e('Content Calendar', 'dr-purg-social-syndicator'); ?></h1>
            <?php self::render_notice_from_query(); ?>
            <p><?php esc_html_e('Plan the pipeline: brainstorm curiosity-led, guardrail-safe topics with the AI, then schedule and track each idea from idea to published. Ideas are drafts for review — nothing here is posted automatically.', 'dr-purg-social-syndicator'); ?></p>

            <section class="dpj-social-card">
                <header class="dpj-platform__header">
                    <h2><?php esc_html_e('Brainstorm ideas with AI', 'dr-purg-social-syndicator'); ?></h2>
                </header>
                <?php if (!$enabled) : ?>
                    <p class="dpj-social-note"><?php esc_html_e('Enable the AI provider (SOCIAL_AI_ENABLE=1 with a provider and API key) to generate ideas. You can still add ideas manually below.', 'dr-purg-social-syndicator'); ?></p>
                <?php endif; ?>
                <?php if ($error !== '') : ?>
                    <p class="dpj-social-note dpj-qa-line--warning"><?php echo esc_html($error); ?></p>
                <?php endif; ?>
                <form method="post" action="<?php echo esc_url($action_url); ?>">
                    <?php wp_nonce_field('dpj_social_calendar', 'dpj_social_calendar_nonce'); ?>
                    <label class="dpj-field" for="dpj-calendar-seed"><?php esc_html_e('Optional theme or seed (e.g. "sleep", "gut health", "winter")', 'dr-purg-social-syndicator'); ?>
                        <input type="text" id="dpj-calendar-seed" name="calendar_seed" value="">
                    </label>
                    <p>
                        <button class="button button-primary" type="submit" name="dpj_calendar_action" value="generate_ideas" <?php disabled(!$enabled); ?>><?php esc_html_e('Generate ideas', 'dr-purg-social-syndicator'); ?></button>
                        <span class="dpj-social-note"><?php echo esc_html(sprintf(/* translators: %d is the number of ideas generated per run. */ __('Adds up to %d new ideas per run.', 'dr-purg-social-syndicator'), self::calendar_idea_count())); ?></span>
                    </p>
                </form>
            </section>

            <section class="dpj-social-card">
                <header class="dpj-platform__header">
                    <h2><?php esc_html_e('Add an idea manually', 'dr-purg-social-syndicator'); ?></h2>
                </header>
                <form method="post" action="<?php echo esc_url($action_url); ?>" class="dpj-calendar-add">
                    <?php wp_nonce_field('dpj_social_calendar', 'dpj_social_calendar_nonce'); ?>
                    <label class="dpj-field"><?php esc_html_e('Working title', 'dr-purg-social-syndicator'); ?>
                        <input type="text" name="idea_title" value="" required>
                    </label>
                    <div class="dpj-social-grid">
                        <label class="dpj-field"><?php esc_html_e('Angle', 'dr-purg-social-syndicator'); ?>
                            <input type="text" name="idea_angle" value="">
                        </label>
                        <label class="dpj-field"><?php esc_html_e('Planned date', 'dr-purg-social-syndicator'); ?>
                            <input type="date" name="idea_date" value="">
                        </label>
                    </div>
                    <label class="dpj-field"><?php esc_html_e('Hook seed', 'dr-purg-social-syndicator'); ?>
                        <input type="text" name="idea_hook" value="">
                    </label>
                    <p><button class="button button-primary" type="submit" name="dpj_calendar_action" value="add_idea"><?php esc_html_e('Add idea', 'dr-purg-social-syndicator'); ?></button></p>
                </form>
            </section>

            <h2><?php esc_html_e('Planned ideas', 'dr-purg-social-syndicator'); ?></h2>
            <?php if ($ideas === []) : ?>
                <p><em><?php esc_html_e('No ideas yet. Generate some with the AI or add one manually.', 'dr-purg-social-syndicator'); ?></em></p>
            <?php endif; ?>
            <?php foreach ($ideas as $idea) :
                $id = (string) ($idea['id'] ?? '');
                $status = self::sanitize_calendar_status((string) ($idea['status'] ?? 'idea'));
                ?>
                <section class="dpj-social-card dpj-calendar-idea dpj-calendar-idea--<?php echo esc_attr($status); ?>">
                    <header class="dpj-cockpit-card__head">
                        <h3><?php echo esc_html((string) ($idea['title'] ?? '')); ?></h3>
                        <span class="dpj-status dpj-status--draft"><?php echo esc_html(self::calendar_status_label($status)); ?></span>
                    </header>
                    <?php if (!empty($idea['angle']) || !empty($idea['hook'])) : ?>
                        <ul class="dpj-perf-context">
                            <?php if (!empty($idea['angle'])) : ?>
                                <li><strong><?php esc_html_e('Angle:', 'dr-purg-social-syndicator'); ?></strong> <?php echo esc_html((string) $idea['angle']); ?></li>
                            <?php endif; ?>
                            <?php if (!empty($idea['hook'])) : ?>
                                <li><strong><?php esc_html_e('Hook:', 'dr-purg-social-syndicator'); ?></strong> <?php echo esc_html((string) $idea['hook']); ?></li>
                            <?php endif; ?>
                        </ul>
                    <?php endif; ?>
                    <form method="post" action="<?php echo esc_url($action_url); ?>" class="dpj-calendar-row">
                        <?php wp_nonce_field('dpj_social_calendar', 'dpj_social_calendar_nonce'); ?>
                        <input type="hidden" name="idea_id" value="<?php echo esc_attr($id); ?>">
                        <div class="dpj-perf-grid">
                            <label><?php esc_html_e('Date', 'dr-purg-social-syndicator'); ?>
                                <input type="date" name="idea_date" value="<?php echo esc_attr((string) ($idea['date'] ?? '')); ?>">
                            </label>
                            <label><?php esc_html_e('Status', 'dr-purg-social-syndicator'); ?>
                                <select name="idea_status">
                                    <?php foreach ($statuses as $option) : ?>
                                        <option value="<?php echo esc_attr($option); ?>" <?php selected($status, $option); ?>><?php echo esc_html(self::calendar_status_label($option)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                        <label class="dpj-field"><?php esc_html_e('Notes', 'dr-purg-social-syndicator'); ?>
                            <textarea name="idea_notes" rows="2"><?php echo esc_textarea((string) ($idea['notes'] ?? '')); ?></textarea>
                        </label>
                        <p class="dpj-calendar-actions">
                            <button class="button button-primary" type="submit" name="dpj_calendar_action" value="update_idea"><?php esc_html_e('Save', 'dr-purg-social-syndicator'); ?></button>
                            <button class="button button-link-delete" type="submit" name="dpj_calendar_action" value="delete_idea" onclick="return confirm('<?php echo esc_js(__('Delete this idea?', 'dr-purg-social-syndicator')); ?>');"><?php esc_html_e('Delete', 'dr-purg-social-syndicator'); ?></button>
                        </p>
                    </form>
                </section>
            <?php endforeach; ?>
        </div>
        <?php
    }

    public static function render_import_page(): void
    {
        if (!self::can_manage()) {
            wp_die(esc_html__('You do not have permission to import content.', 'dr-purg-social-syndicator'));
        }

        $notice = isset($_GET['dpj_social_notice']) ? sanitize_key(wp_unslash((string) $_GET['dpj_social_notice'])) : '';
        $errors = [
            'import_empty' => __('Paste the Claude bundle first.', 'dr-purg-social-syndicator'),
            'import_bad_json' => __('That did not parse as JSON. Copy the whole bundle Claude returned (it should start with { and end with }).', 'dr-purg-social-syndicator'),
            'import_incomplete' => __('The bundle needs at least a "title" and "content_html". Check the output and try again.', 'dr-purg-social-syndicator'),
            'import_failed' => __('Could not create the draft post. Try again.', 'dr-purg-social-syndicator'),
        ];
        ?>
        <div class="wrap dpj-social-wrap dpj-import">
            <h1><?php esc_html_e('New from Claude', 'dr-purg-social-syndicator'); ?></h1>
            <?php if (isset($errors[$notice])) : ?>
                <div class="notice notice-error"><p><?php echo esc_html($errors[$notice]); ?></p></div>
            <?php endif; ?>
            <p><?php esc_html_e('Paste ONE Claude bundle (a single JSON object holding the article plus SEO and all social fields). This creates a clean DRAFT post: only the article shows on the live page; SEO and social copy go into their fields for you to review, then publish. URLs in the social fields are filled with the real link automatically when you publish.', 'dr-purg-social-syndicator'); ?></p>
            <p class="dpj-social-note"><?php esc_html_e('Use the "Master bundle prompt" from docs/chatgpt-prompt-pack.md in your Claude editor project, paste your topic, and copy the JSON it returns into the box below.', 'dr-purg-social-syndicator'); ?></p>

            <details class="dpj-import-spec">
                <summary><?php esc_html_e('Expected bundle fields', 'dr-purg-social-syndicator'); ?></summary>
                <pre>{
  "title": "...",
  "seo_title": "... (<=58 chars)",
  "meta_description": "... (<=180 chars)",
  "excerpt": "...",
  "category": "...",
  "tags": ["...", "..."],
  "image_alt": "...",
  "image_prompt": "... (ready-to-paste Gemini prompt for a portrait photo)",
  "content_html": "&lt;p&gt;...&lt;/p&gt;&lt;h2&gt;...&lt;/h2&gt; (article body only)",
  "facebook_hook": "...",
  "facebook_summary": "...",
  "pinterest_title": "...",
  "pinterest_description": "...",
  "pinterest_alt_text": "...",
  "reddit_title": "...",
  "reddit_body": "...",
  "overlay_text": "...",
  "bottom_hint_text": "LINK IN FIRST COMMENT"
}</pre>
            </details>

            <form method="post" action="<?php echo esc_url(add_query_arg(['page' => self::IMPORT_SLUG], admin_url('admin.php'))); ?>" class="dpj-social-card">
                <?php wp_nonce_field('dpj_social_import', 'dpj_social_import_nonce'); ?>
                <label class="dpj-field" for="dpj-claude-bundle"><?php esc_html_e('Claude bundle (JSON)', 'dr-purg-social-syndicator'); ?>
                    <textarea id="dpj-claude-bundle" name="claude_bundle" rows="16" class="large-text code" placeholder='{ "title": "...", "content_html": "<p>...</p>", ... }'></textarea>
                </label>
                <p>
                    <button class="button button-primary" type="submit" name="dpj_social_import_action" value="create"><?php esc_html_e('Create draft from bundle', 'dr-purg-social-syndicator'); ?></button>
                    <span class="dpj-social-note"><?php esc_html_e('Creates a draft and opens it for review. Nothing is published automatically.', 'dr-purg-social-syndicator'); ?></span>
                </p>
            </form>
        </div>
        <?php
    }

    public static function render_performance_page(): void
    {
        if (!self::can_manage()) {
            wp_die(esc_html__('You do not have permission to view social performance.', 'dr-purg-social-syndicator'));
        }

        $posts = get_posts([
            'post_type' => 'post',
            'post_status' => ['publish', 'future'],
            'posts_per_page' => 200,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        $rows = [];
        $total_clicks = 0;
        $total_revenue = 0.0;
        foreach ($posts as $post) {
            if (!$post instanceof WP_Post) {
                continue;
            }

            $clicks = (string) get_post_meta($post->ID, '_dpj_social_perf_clicks', true);
            $rpm = (string) get_post_meta($post->ID, '_dpj_social_perf_rpm', true);
            $revenue = (string) get_post_meta($post->ID, '_dpj_social_perf_revenue', true);
            $pps = (string) get_post_meta($post->ID, '_dpj_social_perf_pps', true);
            $posted_at = (string) get_post_meta($post->ID, '_dpj_social_facebook_posted_at', true);
            $hook = (string) get_post_meta($post->ID, '_dpj_social_facebook_hook', true);

            // Only list posts with real social activity or recorded performance.
            if ($posted_at === '' && $clicks === '' && $rpm === '' && $revenue === '' && $pps === '') {
                continue;
            }

            $total_clicks += (int) $clicks;
            $total_revenue += (float) $revenue;
            $rows[] = compact('post', 'clicks', 'rpm', 'revenue', 'pps', 'posted_at', 'hook');
        }

        ?>
        <div class="wrap dpj-social-wrap">
            <h1><?php esc_html_e('Social Performance', 'dr-purg-social-syndicator'); ?></h1>
            <?php
            $perf_notice = isset($_GET['dpj_social_notice']) ? sanitize_key(wp_unslash((string) $_GET['dpj_social_notice'])) : '';
            if ($perf_notice === 'perf_imported') {
                printf(
                    '<div class="notice notice-success"><p>%s</p></div>',
                    esc_html(sprintf(
                        /* translators: 1: updated count, 2: data row count, 3: unmatched count. */
                        __('Import complete: updated %1$d post(s) from %2$d row(s); %3$d row(s) had no matching post.', 'dr-purg-social-syndicator'),
                        absint($_GET['dpj_perf_updated'] ?? 0),
                        absint($_GET['dpj_perf_rows'] ?? 0),
                        absint($_GET['dpj_perf_unmatched'] ?? 0)
                    ))
                );
            } elseif ($perf_notice === 'perf_import_no_campaign') {
                printf('<div class="notice notice-error"><p>%s</p></div>', esc_html__('Import failed: no campaign or slug column was found in the CSV header.', 'dr-purg-social-syndicator'));
            } elseif ($perf_notice === 'perf_import_failed') {
                printf('<div class="notice notice-error"><p>%s</p></div>', esc_html__('Import failed: the uploaded file could not be read as a CSV.', 'dr-purg-social-syndicator'));
            } elseif ($perf_notice === 'ga4_failed') {
                $ga4_error = (string) get_option('dpj_social_ga4_error', '');
                printf(
                    '<div class="notice notice-error"><p>%s</p></div>',
                    esc_html($ga4_error !== '' ? sprintf(/* translators: %s is an error message. */ __('GA4 pull failed: %s', 'dr-purg-social-syndicator'), $ga4_error) : __('GA4 pull failed.', 'dr-purg-social-syndicator'))
                );
            } else {
                self::render_notice_from_query();
            }
            ?>
            <p><?php esc_html_e('Which hooks earned clicks and revenue. Read clicks from Analytics by UTM campaign; record RPM and finalized revenue per post in the Social Editor.', 'dr-purg-social-syndicator'); ?></p>
            <section class="dpj-social-card dpj-perf-import">
                <h2><?php esc_html_e('Import from CSV', 'dr-purg-social-syndicator'); ?></h2>
                <p class="dpj-social-note"><?php esc_html_e('Upload a CSV (for example a GA4 export). Rows are matched to posts by a campaign or slug column (the post slug = its utm_campaign). Recognised columns: campaign/slug, clicks/sessions, pages/session, RPM, revenue/earnings. Only the columns present are written.', 'dr-purg-social-syndicator'); ?></p>
                <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(add_query_arg(['page' => self::PERF_SLUG], admin_url('admin.php'))); ?>">
                    <?php wp_nonce_field('dpj_social_perf_import', 'dpj_social_perf_nonce'); ?>
                    <input type="file" name="perf_csv" accept=".csv,text/csv" required>
                    <button class="button button-primary" type="submit" name="dpj_social_perf_action" value="import"><?php esc_html_e('Import CSV', 'dr-purg-social-syndicator'); ?></button>
                </form>
            </section>
            <?php if (self::ga4_enabled()) : ?>
                <section class="dpj-social-card dpj-perf-import">
                    <h2><?php esc_html_e('Pull clicks from GA4', 'dr-purg-social-syndicator'); ?></h2>
                    <p class="dpj-social-note"><?php esc_html_e('Pulls clicks, pages/session, engagement, and per-hook-variant (utm_content) results by campaign from the GA4 Data API, matched to posts by slug (= utm_campaign). Revenue and RPM are not in this report — add those via CSV or by hand.', 'dr-purg-social-syndicator'); ?></p>
                    <form method="post" action="<?php echo esc_url(add_query_arg(['page' => self::PERF_SLUG], admin_url('admin.php'))); ?>">
                        <?php wp_nonce_field('dpj_social_ga4_pull', 'dpj_social_ga4_nonce'); ?>
                        <label><?php esc_html_e('Days back', 'dr-purg-social-syndicator'); ?>
                            <input type="number" name="ga4_days" value="<?php echo esc_attr((string) self::ga4_lookback_days()); ?>" min="1" max="365">
                        </label>
                        <button class="button button-primary" type="submit" name="dpj_social_perf_action" value="ga4_pull"><?php esc_html_e('Pull from GA4', 'dr-purg-social-syndicator'); ?></button>
                    </form>
                </section>
            <?php endif; ?>
            <p class="dpj-perf-summary">
                <?php printf(
                    /* translators: 1: post count, 2: total clicks, 3: total revenue. */
                    esc_html__('Tracked posts: %1$d · Total clicks: %2$s · Total revenue: $%3$s', 'dr-purg-social-syndicator'),
                    count($rows),
                    esc_html(number_format_i18n($total_clicks)),
                    esc_html(number_format($total_revenue, 2, '.', ''))
                ); ?>
            </p>
            <table class="widefat striped dpj-social-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Post', 'dr-purg-social-syndicator'); ?></th>
                        <th><?php esc_html_e('Hook', 'dr-purg-social-syndicator'); ?></th>
                        <th><?php esc_html_e('UTM campaign', 'dr-purg-social-syndicator'); ?></th>
                        <th><?php esc_html_e('FB posted', 'dr-purg-social-syndicator'); ?></th>
                        <th><?php esc_html_e('Clicks', 'dr-purg-social-syndicator'); ?></th>
                        <th><?php esc_html_e('Pages/sess', 'dr-purg-social-syndicator'); ?></th>
                        <th><?php esc_html_e('RPM', 'dr-purg-social-syndicator'); ?></th>
                        <th><?php esc_html_e('Revenue', 'dr-purg-social-syndicator'); ?></th>
                        <th><?php esc_html_e('Rev/1k clicks', 'dr-purg-social-syndicator'); ?></th>
                        <th><?php esc_html_e('Actions', 'dr-purg-social-syndicator'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []) : ?>
                        <tr><td colspan="10"><?php esc_html_e('No posted or measured posts yet. Post to a platform, or record results in the Social Editor.', 'dr-purg-social-syndicator'); ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $row) :
                        $campaign = sanitize_title((string) get_post_field('post_name', $row['post']->ID));
                        $clicks_n = (int) $row['clicks'];
                        $rev_per_k = ($clicks_n > 0 && $row['revenue'] !== '') ? number_format(((float) $row['revenue'] / $clicks_n) * 1000, 2, '.', '') : '';
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html(get_the_title($row['post'])); ?></strong></td>
                            <td><?php echo esc_html($row['hook'] !== '' ? $row['hook'] : '—'); ?></td>
                            <td><code><?php echo esc_html($campaign !== '' ? $campaign : (string) $row['post']->ID); ?></code></td>
                            <td><?php echo esc_html($row['posted_at'] !== '' ? $row['posted_at'] : '—'); ?></td>
                            <td><?php echo esc_html($row['clicks'] !== '' ? $row['clicks'] : '—'); ?></td>
                            <td><?php echo esc_html($row['pps'] !== '' ? $row['pps'] : '—'); ?></td>
                            <td><?php echo esc_html($row['rpm'] !== '' ? $row['rpm'] : '—'); ?></td>
                            <td><?php echo esc_html($row['revenue'] !== '' ? '$' . $row['revenue'] : '—'); ?></td>
                            <td><?php echo esc_html($rev_per_k !== '' ? '$' . $rev_per_k : '—'); ?></td>
                            <td><a class="button" href="<?php echo esc_url(self::editor_url($row['post']->ID)); ?>"><?php esc_html_e('Edit', 'dr-purg-social-syndicator'); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
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
            self::STATUS_SCHEDULED => __('Scheduled', 'dr-purg-social-syndicator'),
            self::STATUS_FAILED => __('Failed', 'dr-purg-social-syndicator'),
            self::STATUS_SKIPPED => __('Skipped', 'dr-purg-social-syndicator'),
        ][$status] ?? __('Needs social', 'dr-purg-social-syndicator');
    }

    private static function platform_status(int $post_id, string $platform): string
    {
        $status = (string) get_post_meta($post_id, '_dpj_social_' . $platform . '_status', true);
        return in_array($status, [self::STATUS_NEEDS, self::STATUS_DRAFT, self::STATUS_POSTED, self::STATUS_SCHEDULED, self::STATUS_FAILED, self::STATUS_SKIPPED], true)
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
            'scheduled' => __('Facebook post scheduled. Add the link first comment after it goes live.', 'dr-purg-social-syndicator'),
            'schedule_failed' => __('Facebook scheduling failed. Review the error in the Facebook section.', 'dr-purg-social-syndicator'),
            'comment_posted' => __('First comment posted to Facebook.', 'dr-purg-social-syndicator'),
            'comment_failed' => __('First comment failed. Review the error in the Facebook section.', 'dr-purg-social-syndicator'),
            'ai_draft_generated' => __('AI social draft generated. Review every field before posting.', 'dr-purg-social-syndicator'),
            'ai_draft_failed' => __('AI social draft failed. Review the assistant message.', 'dr-purg-social-syndicator'),
            'hook_variants_generated' => __('Hook variants generated. Apply one and copy its tracked link.', 'dr-purg-social-syndicator'),
            'hook_variants_failed' => __('Hook variant generation failed. Review the assistant message.', 'dr-purg-social-syndicator'),
            'hook_variant_applied' => __('Hook variant applied to the Facebook hook and overlay.', 'dr-purg-social-syndicator'),
            'posting_logged' => __('Posting logged.', 'dr-purg-social-syndicator'),
            'ideas_generated' => __('Ideas added to the calendar. Review and schedule the ones worth writing.', 'dr-purg-social-syndicator'),
            'ideas_failed' => __('Idea generation failed. Review the assistant message above.', 'dr-purg-social-syndicator'),
            'idea_added' => __('Idea added to the calendar.', 'dr-purg-social-syndicator'),
            'idea_updated' => __('Idea updated.', 'dr-purg-social-syndicator'),
            'idea_deleted' => __('Idea removed from the calendar.', 'dr-purg-social-syndicator'),
            'images_generated' => __('Local social cards generated and assigned.', 'dr-purg-social-syndicator'),
            'images_failed' => __('Social image generation failed. Review the converter message.', 'dr-purg-social-syndicator'),
            'pixazo_generated' => __('Pixazo images generated and assigned.', 'dr-purg-social-syndicator'),
            'pixazo_failed' => __('Pixazo image generation failed. Review the AI image generator message.', 'dr-purg-social-syndicator'),
            'pinterest_posted' => __('Pinterest item marked posted.', 'dr-purg-social-syndicator'),
            'pinterest_published' => __('Pin published to Pinterest.', 'dr-purg-social-syndicator'),
            'pinterest_failed' => __('Pinterest publishing failed. Review the error in the Pinterest section.', 'dr-purg-social-syndicator'),
            'pinterest_reset' => __('Pinterest lock reset. You can publish this pin again.', 'dr-purg-social-syndicator'),
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
                        <th scope="row"><label for="pinterest_board_id"><?php esc_html_e('Pinterest board ID', 'dr-purg-social-syndicator'); ?></label></th>
                        <td>
                            <input class="regular-text" id="pinterest_board_id" name="pinterest_board_id" value="<?php echo esc_attr($settings['pinterest_board_id']); ?>" autocomplete="off">
                            <p class="description"><?php esc_html_e('Default board pins are published to. Optional — leave Pinterest blank to keep it copy-and-paste manual.', 'dr-purg-social-syndicator'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="pinterest_access_token"><?php esc_html_e('Pinterest access token', 'dr-purg-social-syndicator'); ?></label></th>
                        <td>
                            <textarea class="large-text code" rows="3" id="pinterest_access_token" name="pinterest_access_token" autocomplete="off"><?php echo esc_textarea($settings['pinterest_access_token']); ?></textarea>
                            <p class="description"><?php esc_html_e('Pinterest API v5 access token with the boards:read, pins:read, and pins:write scopes. Used only when you click Publish to Pinterest.', 'dr-purg-social-syndicator'); ?></p>
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
                <?php self::render_tracked_links_section($post_id); ?>
                <?php self::render_ai_social_draft_section($post_id); ?>
                <?php self::render_hook_variants_section($post_id); ?>
                <?php self::render_social_image_converter_section($post_id); ?>
                <?php self::render_facebook_section($post_id); ?>
                <?php self::render_pinterest_section($post_id); ?>
                <?php self::render_reddit_section($post_id); ?>
                <?php self::render_post_settings_section($post_id); ?>
                <?php self::render_performance_section($post_id); ?>

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
                <div class="notice notice-warning inline"><p><?php esc_html_e('Set SOCIAL_AI_ENABLE=1, SOCIAL_AI_PROVIDER=openrouter or anthropic, and the matching API key in the environment before generating drafts.', 'dr-purg-social-syndicator'); ?></p></div>
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
            <p
                class="dpj-overlay-counter"
                data-dpj-overlay-counter
                data-dpj-overlay-input="#dpj-local-overlay-text"
                data-dpj-overlay-max="<?php echo esc_attr((string) self::OVERLAY_WORD_MAX); ?>"
                data-dpj-overlay-ideal-min="<?php echo esc_attr((string) self::OVERLAY_WORD_IDEAL_MIN); ?>"
                data-dpj-overlay-min="<?php echo esc_attr((string) self::OVERLAY_WORD_MIN); ?>"
            ></p>
            <label class="dpj-check">
                <input type="checkbox" name="local_overlay_enable" value="1" <?php checked(get_post_meta($post_id, '_dpj_social_local_overlay_enable', true), '1'); ?>>
                <?php esc_html_e('Add a short readable text overlay to the generated cards.', 'dr-purg-social-syndicator'); ?>
            </label>
            <?php $current_pos = self::overlay_position($post_id); ?>
            <?php $current_focus = self::crop_focus($post_id); ?>
            <div class="dpj-social-grid">
                <label class="dpj-field dpj-overlay-pos">
                    <?php esc_html_e('Overlay position', 'dr-purg-social-syndicator'); ?>
                    <select name="local_overlay_pos">
                        <?php foreach (self::overlay_positions() as $pos_option) : ?>
                            <option value="<?php echo esc_attr($pos_option); ?>" <?php selected($current_pos, $pos_option); ?>><?php echo esc_html(self::overlay_position_label($pos_option)); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="dpj-social-note"><?php esc_html_e('Move the hook off a face or busy area. The contrast scrim follows the text.', 'dr-purg-social-syndicator'); ?></span>
                </label>
                <label class="dpj-field dpj-crop-focus">
                    <?php esc_html_e('Crop focus', 'dr-purg-social-syndicator'); ?>
                    <select name="local_crop_focus">
                        <?php foreach (self::crop_focus_options() as $focus_option) : ?>
                            <option value="<?php echo esc_attr($focus_option); ?>" <?php selected($current_focus, $focus_option); ?>><?php echo esc_html(self::crop_focus_label($focus_option)); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="dpj-social-note"><?php esc_html_e('Which part of a tall photo to keep when the card crops it (keep top saves faces).', 'dr-purg-social-syndicator'); ?></span>
                </label>
            </div>
            <div class="dpj-social-grid">
                <?php self::render_input('local_hint_text', __('Bottom hint text', 'dr-purg-social-syndicator'), (string) get_post_meta($post_id, '_dpj_social_local_hint_text', true)); ?>
                <label class="dpj-check dpj-check--inline">
                    <input type="checkbox" name="local_hint_enable" value="1" <?php checked(get_post_meta($post_id, '_dpj_social_local_hint_enable', true), '1'); ?>>
                    <?php esc_html_e('Add bottom hint for link-in-comment posts.', 'dr-purg-social-syndicator'); ?>
                </label>
            </div>
            <p class="dpj-card-preview-actions">
                <button class="button" type="button" data-dpj-card-preview-btn><?php esc_html_e('Preview card', 'dr-purg-social-syndicator'); ?></button>
                <span class="dpj-social-note"><?php esc_html_e('Renders a Facebook-sized preview from the current image and text, without saving.', 'dr-purg-social-syndicator'); ?></span>
            </p>
            <div class="dpj-card-preview" data-dpj-card-preview></div>
            <?php self::render_social_image_qa($post_id); ?>
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

    private static function render_social_image_qa(int $post_id): void
    {
        $qa = self::social_image_qa($post_id);
        [$rec_width, $rec_height] = self::recommended_source_size();
        ?>
        <div class="dpj-social-qa" data-dpj-social-qa>
            <h3><?php esc_html_e('Image QA checks', 'dr-purg-social-syndicator'); ?></h3>
            <p class="dpj-social-note"><?php esc_html_e('These checks reflect the last saved overlay text and the current source image. Save the post to refresh them.', 'dr-purg-social-syndicator'); ?></p>
            <p class="dpj-social-note"><?php printf(
                /* translators: 1: recommended width, 2: recommended height. */
                esc_html__('Best source: a portrait or square image at least %1$dx%2$d px. Wide landscape photos crop poorly into the tall Facebook and Pinterest cards.', 'dr-purg-social-syndicator'),
                (int) $rec_width,
                (int) $rec_height
            ); ?></p>
            <?php foreach ($qa['overlay'] as $notice) : ?>
                <p class="dpj-qa-line dpj-qa-line--<?php echo esc_attr($notice['level']); ?>"><?php echo esc_html($notice['text']); ?></p>
            <?php endforeach; ?>
            <?php if ($qa['source_missing']) : ?>
                <p class="dpj-qa-line dpj-qa-line--warning"><?php esc_html_e('No source image is set yet, so crop safety cannot be checked. Set a featured image first.', 'dr-purg-social-syndicator'); ?></p>
            <?php elseif (!empty($qa['crop'])) : ?>
                <p class="dpj-qa-line dpj-qa-line--ok">
                    <?php printf(
                        /* translators: 1: source width, 2: source height. */
                        esc_html__('Source image: %1$dx%2$d px. Each card is a centered crop:', 'dr-purg-social-syndicator'),
                        (int) $qa['source']['width'],
                        (int) $qa['source']['height']
                    ); ?>
                </p>
                <ul class="dpj-qa-crop">
                    <?php foreach ($qa['crop'] as $row) :
                        $keep_percent = (int) round($row['keep'] * 100);
                        if ($row['scale'] > self::UPSCALE_WARN) {
                            $resolution_note = sprintf(
                                /* translators: %d: upscale percentage. */
                                __('upscaled %d%% (may look soft)', 'dr-purg-social-syndicator'),
                                (int) round(($row['scale'] - 1) * 100)
                            );
                        } elseif ($row['scale'] > 1.0) {
                            $resolution_note = __('slight upscale', 'dr-purg-social-syndicator');
                        } else {
                            $resolution_note = __('resolution ok', 'dr-purg-social-syndicator');
                        }
                        ?>
                        <li class="dpj-qa-line dpj-qa-line--<?php echo esc_attr($row['level']); ?>">
                            <?php printf(
                                /* translators: 1: variant label, 2: target size, 3: kept percentage, 4: cropped axis, 5: resolution note. */
                                esc_html__('%1$s (%2$s): keeps %3$d%% of %4$s, %5$s', 'dr-purg-social-syndicator'),
                                esc_html($row['label']),
                                esc_html($row['target']),
                                $keep_percent,
                                esc_html($row['axis']),
                                esc_html($resolution_note)
                            ); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
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
            <?php $scheduled_at = (string) get_post_meta($post_id, '_dpj_social_facebook_scheduled_at', true); ?>
            <div class="dpj-fb-schedule">
                <?php if ($scheduled_at !== '') : ?>
                    <p class="dpj-social-note"><?php printf(
                        /* translators: %s is an ISO 8601 UTC timestamp. */
                        esc_html__('Scheduled to publish at %s UTC. Facebook publishes it automatically. After it goes live, click "Post first comment" to add the tracked link.', 'dr-purg-social-syndicator'),
                        esc_html($scheduled_at)
                    ); ?></p>
                <?php endif; ?>
                <label class="dpj-field">
                    <?php esc_html_e('Schedule for (your site time)', 'dr-purg-social-syndicator'); ?>
                    <input type="datetime-local" name="facebook_schedule" value="">
                </label>
                <p class="dpj-platform__actions">
                    <button class="button" type="submit" name="dpj_social_editor_action" value="schedule_facebook"><?php esc_html_e('Schedule Facebook post', 'dr-purg-social-syndicator'); ?></button>
                    <button class="button" type="submit" name="dpj_social_editor_action" value="post_facebook_comment"><?php esc_html_e('Post first comment', 'dr-purg-social-syndicator'); ?></button>
                </p>
                <p class="dpj-social-note"><?php esc_html_e('Scheduling publishes to your Facebook Page only (10 minutes to 75 days ahead); group posts stay manual. The link first comment is not added to a scheduled post until it goes live — use "Post first comment" after it publishes.', 'dr-purg-social-syndicator'); ?></p>
            </div>
        </section>
        <?php
    }

    private static function render_pinterest_section(int $post_id): void
    {
        $status = self::platform_status($post_id, 'pinterest');
        $api_enabled = self::pinterest_enabled();
        $remote_id = (string) get_post_meta($post_id, '_dpj_social_pinterest_remote_id', true);
        $last_error = (string) get_post_meta($post_id, '_dpj_social_pinterest_last_error', true);
        ?>
        <section class="dpj-social-card dpj-platform dpj-platform--pinterest">
            <header class="dpj-platform__header">
                <h2><?php esc_html_e('Pinterest', 'dr-purg-social-syndicator'); ?></h2>
                <span class="dpj-status dpj-status--<?php echo esc_attr($status); ?>"><?php echo esc_html(self::status_label($status)); ?></span>
            </header>
            <?php if ($remote_id !== '') : ?>
                <p class="dpj-social-note"><?php printf(esc_html__('Pin ID: %s', 'dr-purg-social-syndicator'), esc_html($remote_id)); ?></p>
            <?php endif; ?>
            <?php if ($last_error !== '') : ?>
                <div class="notice notice-error inline"><p><?php echo esc_html($last_error); ?></p></div>
            <?php endif; ?>
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
                <?php if ($api_enabled) : ?>
                    <button class="button button-primary" type="submit" name="dpj_social_editor_action" value="publish_pinterest"><?php esc_html_e('Publish to Pinterest', 'dr-purg-social-syndicator'); ?></button>
                    <button class="button" type="submit" name="dpj_social_editor_action" value="reset_pinterest"><?php esc_html_e('Reset Pinterest lock', 'dr-purg-social-syndicator'); ?></button>
                <?php endif; ?>
                <button class="button" type="button" data-dpj-copy="#dpj-pinterest-description"><?php esc_html_e('Copy description', 'dr-purg-social-syndicator'); ?></button>
                <button class="button" type="submit" name="dpj_social_editor_action" value="mark_pinterest_posted"><?php esc_html_e('Mark Pinterest posted', 'dr-purg-social-syndicator'); ?></button>
            </p>
            <?php if (!$api_enabled) : ?>
                <p class="dpj-social-note"><?php esc_html_e('Add a Pinterest access token and board ID in Social Settings to publish pins through the API. Until then, Pinterest stays copy-and-paste manual.', 'dr-purg-social-syndicator'); ?></p>
            <?php endif; ?>
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
            $error = __('AI social drafts are not enabled. Set SOCIAL_AI_ENABLE=1, SOCIAL_AI_PROVIDER=openrouter or anthropic, and the matching API key in the environment.', 'dr-purg-social-syndicator');
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
        $system = 'You are a strict social distribution editor for a responsible health publication. Return only valid JSON. Never diagnose, promise cures, invent facts, or write fear-mongering medical claims.';
        $prompt = self::ai_social_prompt($post_id);

        $payload = self::social_ai_complete($system, $prompt, self::ai_social_schema());
        if (is_wp_error($payload)) {
            return $payload;
        }

        return self::sanitize_ai_social_payload($payload, $post_id);
    }

    /**
     * Run one AI completion through the configured provider and return the
     * decoded JSON object (associative array) or a WP_Error.
     *
     * @param array<string, mixed> $schema JSON schema for structured outputs (Anthropic only).
     * @return array<string, mixed>|WP_Error
     */
    private static function social_ai_complete(string $system, string $prompt, array $schema)
    {
        $timeout = self::env_int('SOCIAL_AI_TIMEOUT_SECONDS', self::env_int('AI_EXTRACTION_TIMEOUT_SECONDS', 16, 4, 45), 4, 45);
        $max_tokens = self::env_int('SOCIAL_AI_MAX_TOKENS', self::env_int('AI_EXTRACTION_MAX_TOKENS', 1200, 300, 2600), 300, 2600);

        if (self::social_ai_provider() === 'anthropic') {
            return self::anthropic_complete($system, $prompt, $schema, $timeout, $max_tokens);
        }

        return self::openrouter_complete($system, $prompt, $timeout, $max_tokens);
    }

    /**
     * OpenRouter chat-completions call returning the decoded JSON object.
     *
     * @return array<string, mixed>|WP_Error
     */
    private static function openrouter_complete(string $system, string $prompt, int $timeout, int $max_tokens)
    {
        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', [
            'timeout' => $timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . self::social_ai_api_key(),
                'Content-Type' => 'application/json',
                'HTTP-Referer' => home_url('/'),
                'X-Title' => 'Dr Purg Jr. Social Syndicator',
            ],
            'body' => wp_json_encode([
                'model' => self::social_ai_model(),
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.55,
                'max_tokens' => $max_tokens,
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

        return $payload;
    }

    /**
     * Anthropic Messages API call using structured outputs for guaranteed JSON.
     *
     * The static system block carries a cache_control breakpoint so future
     * multi-variant prompts that share a large prefix can hit the prompt cache.
     * temperature and effort are intentionally omitted so the same request shape
     * is valid across every Claude tier (Opus rejects temperature; Haiku rejects
     * effort).
     *
     * @param array<string, mixed> $schema
     * @return array<string, mixed>|WP_Error
     */
    private static function anthropic_complete(string $system, string $prompt, array $schema, int $timeout, int $max_tokens)
    {
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'timeout' => $timeout,
            'headers' => [
                'x-api-key' => self::social_ai_api_key(),
                'anthropic-version' => self::env('SOCIAL_AI_ANTHROPIC_VERSION', self::env('AI_ANTHROPIC_VERSION', '2023-06-01')),
                'content-type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => self::social_ai_model(),
                'max_tokens' => $max_tokens,
                'system' => [[
                    'type' => 'text',
                    'text' => $system,
                    'cache_control' => ['type' => 'ephemeral'],
                ]],
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'output_config' => [
                    'format' => [
                        'type' => 'json_schema',
                        'schema' => $schema,
                    ],
                ],
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
                ? (string) ($decoded['error']['message'] ?? __('Anthropic request failed.', 'dr-purg-social-syndicator'))
                : sprintf(__('Anthropic returned HTTP error %d.', 'dr-purg-social-syndicator'), $status);
            return new WP_Error('dpj_social_ai_http_error', $message);
        }

        if (is_array($decoded) && ($decoded['stop_reason'] ?? '') === 'refusal') {
            return new WP_Error('dpj_social_ai_refusal', __('The AI model declined to draft this post. Review the source content.', 'dr-purg-social-syndicator'));
        }

        $content = '';
        if (is_array($decoded) && is_array($decoded['content'] ?? null)) {
            foreach ($decoded['content'] as $block) {
                if (is_array($block) && ($block['type'] ?? '') === 'text') {
                    $content .= (string) ($block['text'] ?? '');
                }
            }
        }

        $payload = self::decode_ai_json_object($content);
        if (!is_array($payload)) {
            return new WP_Error('dpj_social_ai_bad_json', __('The AI model did not return valid JSON.', 'dr-purg-social-syndicator'));
        }

        return $payload;
    }

    /**
     * JSON schema for the reviewed social package (Anthropic structured outputs).
     *
     * @return array<string, mixed>
     */
    private static function ai_social_schema(): array
    {
        $fields = [
            'facebook_hook',
            'facebook_summary',
            'facebook_first_comment',
            'pinterest_title',
            'pinterest_description',
            'pinterest_alt_text',
            'reddit_title',
            'reddit_body',
            'overlay_text',
            'bottom_hint_text',
        ];

        $properties = [];
        foreach ($fields as $field) {
            $properties[$field] = ['type' => 'string'];
        }

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => $properties,
            'required' => $fields,
        ];
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

    /**
     * Render a Facebook-sized card preview from the editor's *unsaved* overlay
     * text, position, and hint controls, using the real renderer so the preview
     * is pixel-accurate. Returns a base64 data URI; writes no attachment. The
     * preview only fails the preview itself — it never touches saved cards.
     */
    public static function ajax_card_preview(): void
    {
        check_ajax_referer('dpj_social_card_preview', 'nonce');

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        if ($post_id <= 0 || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => __('You do not have permission to preview this card.', 'dr-purg-social-syndicator')]);
        }

        $source_id = self::social_image_source_id($post_id);
        $source_path = $source_id > 0 ? get_attached_file($source_id) : '';
        if (!is_string($source_path) || $source_path === '' || !file_exists($source_path)) {
            wp_send_json_error(['message' => __('Choose and save a featured image before previewing.', 'dr-purg-social-syndicator')]);
        }

        $overlay_text = isset($_POST['overlay_text']) ? sanitize_textarea_field(wp_unslash((string) $_POST['overlay_text'])) : '';
        if ($overlay_text === '') {
            $overlay_text = self::default_local_overlay_text($post_id);
        }
        $use_overlay = isset($_POST['overlay_enable']) && (string) $_POST['overlay_enable'] === '1';
        $position = self::sanitize_overlay_position(isset($_POST['overlay_pos']) ? (string) wp_unslash($_POST['overlay_pos']) : 'center');
        $hint_text = isset($_POST['hint_text']) ? sanitize_text_field(wp_unslash((string) $_POST['hint_text'])) : '';
        if ($hint_text === '') {
            $hint_text = self::default_local_hint_text();
        }
        $use_hint = isset($_POST['hint_enable']) && (string) $_POST['hint_enable'] === '1';
        $crop_focus = self::sanitize_crop_focus(isset($_POST['crop_focus']) ? (string) wp_unslash($_POST['crop_focus']) : 'center');

        // Reduced Facebook-card size keeps the payload small; overlay sizing
        // scales with width, so it matches the full-size render.
        $width = 540;
        $height = 675;
        $temp = wp_tempnam('dpj-social-card-preview.jpg');
        if (!is_string($temp) || $temp === '') {
            wp_send_json_error(['message' => __('Could not create a temporary preview file.', 'dr-purg-social-syndicator')]);
        }

        $rendered = self::render_social_image_jpeg($source_path, $temp, $width, $height, $overlay_text, $use_overlay, 'facebook', $hint_text, $use_hint, $position, $crop_focus);
        if (is_wp_error($rendered)) {
            wp_delete_file($temp);
            wp_send_json_error(['message' => $rendered->get_error_message()]);
        }

        $data = file_get_contents($temp);
        wp_delete_file($temp);
        if ($data === false) {
            wp_send_json_error(['message' => __('Could not read the rendered preview.', 'dr-purg-social-syndicator')]);
        }

        wp_send_json_success(['image' => 'data:image/jpeg;base64,' . base64_encode($data)]);
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
        $position = self::overlay_position($post_id);
        $crop_focus = self::crop_focus($post_id);
        $generated = [];
        foreach (self::SOCIAL_IMAGE_VARIANTS as $variant => $config) {
            $attachment_id = self::create_social_image_variant($post_id, $source_id, $source_path, $variant, $config, $overlay_text, $use_overlay, $hint_text, $use_hint, $position, $crop_focus);
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

    private static function create_social_image_variant(int $post_id, int $source_id, string $source_path, string $variant, array $config, string $overlay_text, bool $use_overlay, string $hint_text, bool $use_hint, string $position = 'center', string $crop_focus = 'center')
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
        $rendered = self::render_social_image_jpeg($source_path, $target_path, $width, $height, $overlay_text, $use_overlay, $variant, $hint_text, $use_hint, $position, $crop_focus);
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

    private static function render_social_image_jpeg(string $source_path, string $target_path, int $target_width, int $target_height, string $overlay_text = '', bool $use_overlay = true, string $variant = '', string $hint_text = '', bool $use_hint = false, string $position = 'center', string $crop_focus = 'center')
    {
        $position = self::sanitize_overlay_position($position);
        $crop_focus = self::sanitize_crop_focus($crop_focus);
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

        self::copy_cover_image($canvas, $source, $source_width, $source_height, $target_width, $target_height, self::crop_focus_factor($crop_focus));

        // Soft, feathered contrast scrim behind the text so the hook stays
        // legible on bright or busy photos. It is a gentle gradient, not a solid
        // panel, so the image still shows through. Tunable via SOCIAL_CARD_SCRIM
        // (0 disables it). The headline is always centered vertically and the
        // hint sits at the bottom, so the bands track those positions.
        $scrim = self::card_scrim_strength();
        if ($scrim > 0.0) {
            if ($use_overlay && self::overlay_display_text(self::short_overlay_text($overlay_text)) !== '') {
                self::draw_vertical_scrim($canvas, $target_width, $target_height, self::overlay_scrim_peak($target_height, $position), (int) round($target_height * 0.32), $scrim);
            }
            if ($use_hint) {
                self::draw_vertical_scrim($canvas, $target_width, $target_height, $target_height, (int) round($target_height * 0.16), min(0.6, $scrim + 0.08));
            }
        }

        if ($use_overlay) {
            self::draw_social_overlay($canvas, $overlay_text, $target_width, $target_height, $variant, $position);
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

    /**
     * Smallest source size that avoids upscaling any local card variant.
     *
     * A single source cannot perfectly fit every aspect ratio, but a portrait
     * or square image at least this large will never be enlarged for any card.
     *
     * @return array{0: int, 1: int} Recommended [width, height] in pixels.
     */
    private static function recommended_source_size(): array
    {
        $width = 0;
        $height = 0;
        foreach (self::SOCIAL_IMAGE_VARIANTS as $config) {
            $width = max($width, (int) $config['width']);
            $height = max($height, (int) $config['height']);
        }

        return [$width, $height];
    }

    private static function overlay_word_count(string $text): int
    {
        $text = wp_strip_all_tags($text);
        $text = (string) preg_replace('~https?://\S+~i', '', $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8');
        $text = trim((string) preg_replace('/\s+/', ' ', $text));
        if ($text === '') {
            return 0;
        }

        return count(preg_split('/\s+/', $text) ?: []);
    }

    /**
     * Build pre-generation quality notices for the social image converter.
     *
     * Reflects the currently saved overlay text and source image. The notices
     * warn about overlay text that will be truncated or reads too short, and
     * about platform crops that discard a large share of the source or that
     * would upscale a low-resolution image.
     *
     * @return array{overlay: array<int, array{level: string, text: string}>, crop: array<int, array<string, mixed>>, source_missing: bool, source: array{width: int, height: int}}
     */
    private static function social_image_qa(int $post_id): array
    {
        $result = [
            'overlay' => [],
            'crop' => [],
            'source_missing' => false,
            'source' => ['width' => 0, 'height' => 0],
        ];

        $overlay_enabled = get_post_meta($post_id, '_dpj_social_local_overlay_enable', true) === '1';
        if ($overlay_enabled) {
            $overlay_source = trim((string) get_post_meta($post_id, '_dpj_social_local_overlay_text', true));
            if ($overlay_source === '') {
                $overlay_source = self::default_local_overlay_text($post_id);
            }
            $words = self::overlay_word_count($overlay_source);

            if ($words === 0) {
                $result['overlay'][] = [
                    'level' => 'warning',
                    'text' => __('The overlay is enabled but the overlay text is empty.', 'dr-purg-social-syndicator'),
                ];
            } elseif ($words > self::OVERLAY_WORD_MAX) {
                $result['overlay'][] = [
                    'level' => 'warning',
                    'text' => sprintf(
                        /* translators: 1: overlay word count, 2: maximum words kept. */
                        __('Overlay text is %1$d words; only the first %2$d are kept on the cards, so trim it to a tighter hook.', 'dr-purg-social-syndicator'),
                        $words,
                        self::OVERLAY_WORD_MAX
                    ),
                ];
            } elseif ($words < self::OVERLAY_WORD_MIN) {
                $result['overlay'][] = [
                    'level' => 'warning',
                    'text' => sprintf(
                        /* translators: %d: overlay word count. */
                        __('Overlay text is only %d words; a curiosity hook of 8 to 12 words reads better.', 'dr-purg-social-syndicator'),
                        $words
                    ),
                ];
            } elseif ($words < self::OVERLAY_WORD_IDEAL_MIN) {
                $result['overlay'][] = [
                    'level' => 'notice',
                    'text' => sprintf(
                        /* translators: %d: overlay word count. */
                        __('Overlay text is %d words; 8 to 12 words usually makes a stronger hook.', 'dr-purg-social-syndicator'),
                        $words
                    ),
                ];
            } else {
                $result['overlay'][] = [
                    'level' => 'ok',
                    'text' => sprintf(
                        /* translators: %d: overlay word count. */
                        __('Overlay text length looks good (%d words).', 'dr-purg-social-syndicator'),
                        $words
                    ),
                ];
            }
        }

        $source_id = self::social_image_source_id($post_id);
        if ($source_id <= 0) {
            $result['source_missing'] = true;
            return $result;
        }

        $source_path = (string) get_attached_file($source_id);
        $info = $source_path !== '' ? wp_getimagesize($source_path) : false;
        $source_width = is_array($info) ? (int) ($info[0] ?? 0) : 0;
        $source_height = is_array($info) ? (int) ($info[1] ?? 0) : 0;
        if ($source_width <= 0 || $source_height <= 0) {
            return $result;
        }

        $result['source'] = ['width' => $source_width, 'height' => $source_height];

        foreach (self::SOCIAL_IMAGE_VARIANTS as $config) {
            $result['crop'][] = self::crop_safety_row(
                (string) $config['label'],
                $source_width,
                $source_height,
                (int) $config['width'],
                (int) $config['height']
            );
        }

        return $result;
    }

    /**
     * Describe the center-crop and upscale outcome for one target size.
     *
     * @return array{label: string, target: string, axis: string, keep: float, scale: float, level: string}
     */
    private static function crop_safety_row(string $label, int $source_width, int $source_height, int $target_width, int $target_height): array
    {
        $source_ratio = $source_width / $source_height;
        $target_ratio = $target_width / $target_height;

        if ($source_ratio > $target_ratio) {
            $crop_width = max(1, (int) round($source_height * $target_ratio));
            $keep = $crop_width / $source_width;
            $scale = $target_width / $crop_width;
            $axis = __('width', 'dr-purg-social-syndicator');
        } else {
            $crop_height = max(1, (int) round($source_width / $target_ratio));
            $keep = $crop_height / $source_height;
            $scale = $target_height / $crop_height;
            $axis = __('height', 'dr-purg-social-syndicator');
        }

        $level = 'ok';
        if ($keep < self::CROP_KEEP_WARN || $scale > self::UPSCALE_WARN) {
            $level = 'warning';
        } elseif ($scale > 1.0) {
            $level = 'notice';
        }

        return [
            'label' => $label,
            'target' => $target_width . 'x' . $target_height,
            'axis' => $axis,
            'keep' => $keep,
            'scale' => $scale,
            'level' => $level,
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function overlay_positions(): array
    {
        return ['top', 'center', 'bottom'];
    }

    private static function sanitize_overlay_position(string $position): string
    {
        $position = sanitize_key($position);
        return in_array($position, self::overlay_positions(), true) ? $position : 'center';
    }

    private static function overlay_position(int $post_id): string
    {
        return self::sanitize_overlay_position((string) get_post_meta($post_id, '_dpj_social_local_overlay_pos', true));
    }

    private static function overlay_position_label(string $position): string
    {
        $labels = [
            'top' => __('Top', 'dr-purg-social-syndicator'),
            'center' => __('Center', 'dr-purg-social-syndicator'),
            'bottom' => __('Bottom', 'dr-purg-social-syndicator'),
        ];

        return $labels[$position] ?? $labels['center'];
    }

    /**
     * Vertical start (top y) of the overlay text block for the chosen position.
     * Center keeps the original behavior; top/bottom anchor to a margin, and
     * bottom leaves extra room so the headline clears the bottom-hint band.
     */
    private static function overlay_block_top(int $target_height, int $block_height, string $position): int
    {
        $margin = max(48, (int) round($target_height * 0.06));
        if ($position === 'top') {
            return $margin;
        }
        if ($position === 'bottom') {
            $bottom_margin = max($margin, (int) round($target_height * 0.10));
            return max($margin, $target_height - $block_height - $bottom_margin);
        }

        return (int) floor(($target_height - $block_height) / 2);
    }

    /**
     * Vertical center of the contrast scrim band for the chosen overlay position,
     * so the scrim tracks the text instead of always sitting in the middle.
     */
    private static function overlay_scrim_peak(int $target_height, string $position): int
    {
        if ($position === 'top') {
            return (int) round($target_height * 0.24);
        }
        if ($position === 'bottom') {
            return (int) round($target_height * 0.72);
        }

        return (int) round($target_height * 0.5);
    }

    /**
     * @return array<int, string>
     */
    private static function crop_focus_options(): array
    {
        return ['top', 'center', 'bottom'];
    }

    private static function sanitize_crop_focus(string $focus): string
    {
        $focus = sanitize_key($focus);
        return in_array($focus, self::crop_focus_options(), true) ? $focus : 'center';
    }

    private static function crop_focus(int $post_id): string
    {
        return self::sanitize_crop_focus((string) get_post_meta($post_id, '_dpj_social_local_crop_focus', true));
    }

    private static function crop_focus_label(string $focus): string
    {
        $labels = [
            'top' => __('Keep top', 'dr-purg-social-syndicator'),
            'center' => __('Keep center', 'dr-purg-social-syndicator'),
            'bottom' => __('Keep bottom', 'dr-purg-social-syndicator'),
        ];

        return $labels[$focus] ?? $labels['center'];
    }

    /**
     * Vertical anchor (0.0 top .. 1.0 bottom) for the cover crop. Used only when
     * the source is taller than the card and the height must be cropped — letting
     * the operator keep faces or subjects instead of always cropping centered.
     */
    private static function crop_focus_factor(string $focus): float
    {
        if ($focus === 'top') {
            return 0.0;
        }
        if ($focus === 'bottom') {
            return 1.0;
        }

        return 0.5;
    }

    /**
     * Peak darkness (0.0–0.6) of the text contrast scrim. SOCIAL_CARD_SCRIM is a
     * percent (0 disables; default 28). The gradient feathers to fully
     * transparent at the band edges, so this is the strongest point only.
     */
    private static function card_scrim_strength(): float
    {
        return self::env_int('SOCIAL_CARD_SCRIM', 28, 0, 60) / 100;
    }

    /**
     * Brand label drawn on the local social cards. SOCIAL_CARD_BRAND overrides it;
     * otherwise it follows the WordPress site name, so a cloned blog gets its own
     * brand on the cards without a code change. The renderer uppercases it.
     */
    private static function card_brand(): string
    {
        $brand = self::env('SOCIAL_CARD_BRAND', '');
        if (trim($brand) === '') {
            $brand = (string) get_bloginfo('name');
        }
        if (trim($brand) === '') {
            $brand = 'Dr Purg Jr.';
        }

        return $brand;
    }

    /**
     * Draw a vertical black gradient centered on $peak_y that fades to fully
     * transparent $reach pixels above and below it. Used as a soft readability
     * scrim behind centered overlay text and the bottom hint — no hard edges,
     * no solid panel. Requires alpha blending on the canvas (set by the caller).
     */
    private static function draw_vertical_scrim($canvas, int $target_width, int $target_height, int $peak_y, int $reach, float $peak_darkness): void
    {
        if ($reach <= 0 || $peak_darkness <= 0.0) {
            return;
        }

        $peak_darkness = min(0.85, $peak_darkness);
        $start = max(0, $peak_y - $reach);
        $end = min($target_height - 1, $peak_y + $reach);

        for ($y = $start; $y <= $end; $y++) {
            $distance = abs($y - $peak_y) / $reach; // 0 at the peak, 1 at the edges.
            $darkness = $peak_darkness * pow(1 - $distance, 1.6);
            if ($darkness <= 0.0) {
                continue;
            }

            $alpha = (int) round(127 * (1 - $darkness)); // GD: 0 opaque, 127 transparent.
            if ($alpha >= 127) {
                continue;
            }

            $color = imagecolorallocatealpha($canvas, 0, 0, 0, $alpha);
            if ($color === false) {
                continue;
            }
            imagefilledrectangle($canvas, 0, $y, $target_width, $y, $color);
        }
    }

    private static function copy_cover_image($canvas, $source, int $source_width, int $source_height, int $target_width, int $target_height, float $focus_y = 0.5): void
    {
        $source_ratio = $source_width / $source_height;
        $target_ratio = $target_width / $target_height;
        $focus_y = max(0.0, min(1.0, $focus_y));

        if ($source_ratio > $target_ratio) {
            // Source is wider than the card: full height kept, width cropped
            // centered. The vertical focus does not apply here.
            $crop_height = $source_height;
            $crop_width = max(1, min($source_width, (int) round($source_height * $target_ratio)));
            $source_x = (int) floor(($source_width - $crop_width) / 2);
            $source_y = 0;
        } else {
            // Source is taller than the card: height is cropped, so anchor the
            // crop with the chosen vertical focus to keep faces/subjects.
            $crop_width = $source_width;
            $crop_height = max(1, min($source_height, (int) round($source_width / $target_ratio)));
            $source_x = 0;
            $source_y = (int) round(($source_height - $crop_height) * $focus_y);
        }

        imagecopyresampled($canvas, $source, 0, 0, $source_x, $source_y, $target_width, $target_height, $crop_width, $crop_height);
    }

    private static function draw_social_overlay($canvas, string $text, int $target_width, int $target_height, string $variant, string $position = 'center'): void
    {
        $headline = self::overlay_display_text(self::short_overlay_text($text));
        if ($headline === '') {
            return;
        }

        $position = self::sanitize_overlay_position($position);
        $bold_font = self::font_path(true);
        $regular_font = self::font_path(false);
        if ($bold_font !== '' && function_exists('imagettftext') && function_exists('imagettfbbox')) {
            self::draw_ttf_social_text($canvas, $headline, $target_width, $target_height, $variant, $bold_font, $regular_font !== '' ? $regular_font : $bold_font, $position);
            return;
        }

        self::draw_fallback_social_text($canvas, $headline, $target_width, $target_height, $variant, $position);
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

    private static function draw_ttf_social_text($canvas, string $headline, int $target_width, int $target_height, string $variant, string $bold_font, string $regular_font, string $position = 'center'): void
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
        $brand_baseline = max($brand_size + 16, self::overlay_block_top($target_height, $block_height, $position) + $brand_size);

        self::draw_centered_ttf_text($canvas, self::overlay_display_text(self::card_brand()), $regular_font, $brand_size, $brand_baseline, $target_width, $sage !== false ? $sage : $white, $outline, $shadow, 2);

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

    private static function draw_fallback_social_text($canvas, string $headline, int $target_width, int $target_height, string $variant, string $position = 'center'): void
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
        $y = max(24, self::overlay_block_top($target_height, $block_height, $position));

        self::draw_centered_scaled_string($canvas, self::overlay_display_text(self::card_brand()), $font, $y, $brand_scale, $target_width, [213, 232, 224, 0], [0, 0, 0, 26], [92, 17, 48, 48], max(2, (int) round($brand_scale * 0.7)));

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

    private static function post_to_facebook(int $post_id, int $scheduled_time = 0)
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

        if ($scheduled_time > 0) {
            $now = time();
            if ($scheduled_time < $now + 600) {
                $error = __('Schedule the Facebook post at least 10 minutes in the future.', 'dr-purg-social-syndicator');
                update_post_meta($post_id, '_dpj_social_facebook_last_error', $error);
                update_post_meta($post_id, '_dpj_social_facebook_status', self::STATUS_FAILED);
                return new WP_Error('dpj_social_schedule_too_soon', $error);
            }
            if ($scheduled_time > $now + (75 * DAY_IN_SECONDS)) {
                $error = __('Facebook allows scheduling up to 75 days ahead.', 'dr-purg-social-syndicator');
                update_post_meta($post_id, '_dpj_social_facebook_last_error', $error);
                update_post_meta($post_id, '_dpj_social_facebook_status', self::STATUS_FAILED);
                return new WP_Error('dpj_social_schedule_too_far', $error);
            }
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

        $is_scheduled = $scheduled_time > 0;
        if ($image_url !== '') {
            $photo_body = [
                'url' => $image_url,
                'caption' => $message,
                'published' => $is_scheduled ? 'false' : 'true',
            ];
            if ($is_scheduled) {
                $photo_body['scheduled_publish_time'] = (string) $scheduled_time;
            }
            $result = self::facebook_request($base . '/photos', $photo_body, $settings);
        } else {
            $feed_body = ['message' => $message];
            if ($is_scheduled) {
                $feed_body['published'] = 'false';
                $feed_body['scheduled_publish_time'] = (string) $scheduled_time;
            }
            $result = self::facebook_request($base . '/feed', $feed_body, $settings);
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
        delete_post_meta($post_id, '_dpj_social_facebook_last_error');

        // A scheduled (unpublished) post cannot receive a comment yet, so the
        // link first comment is deferred — the operator posts it after the post
        // goes live via "Post first comment".
        if ($is_scheduled) {
            update_post_meta($post_id, '_dpj_social_facebook_status', self::STATUS_SCHEDULED);
            update_post_meta($post_id, '_dpj_social_facebook_scheduled_at', gmdate('c', $scheduled_time));
            delete_post_meta($post_id, '_dpj_social_facebook_posted_at');
            // Auto-post the link first comment a few minutes after the Page post
            // goes live (a scheduled/unpublished post can't be commented on yet).
            // Page only — never touches groups; idempotent; the manual "Post first
            // comment" button stays as a fallback if the cron misses.
            if (!wp_next_scheduled('dpj_social_post_scheduled_comment', [$post_id])) {
                wp_schedule_single_event($scheduled_time + (5 * MINUTE_IN_SECONDS), 'dpj_social_post_scheduled_comment', [$post_id]);
            }
            return $result;
        }

        update_post_meta($post_id, '_dpj_social_facebook_status', self::STATUS_POSTED);
        update_post_meta($post_id, '_dpj_social_facebook_posted_at', gmdate('c'));
        delete_post_meta($post_id, '_dpj_social_facebook_scheduled_at');

        $first_comment = self::apply_utm_to_text($first_comment, $post_id, 'facebook', self::selected_variant($post_id));
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

    /**
     * Post the reviewed first comment (with the tracked link) onto an existing
     * Facebook post. Used after a scheduled post goes live, or to retry a comment
     * that failed during immediate posting. Refuses if there is no post yet or a
     * comment was already recorded.
     *
     * @return array<string, mixed>|WP_Error
     */
    private static function post_facebook_comment(int $post_id)
    {
        $remote_id = trim((string) get_post_meta($post_id, '_dpj_social_facebook_remote_post_id', true));
        if ($remote_id === '') {
            $error = __('Post to or schedule Facebook first; there is no post to comment on yet.', 'dr-purg-social-syndicator');
            update_post_meta($post_id, '_dpj_social_facebook_last_error', $error);
            return new WP_Error('dpj_social_no_remote_post', $error);
        }

        if ((string) get_post_meta($post_id, '_dpj_social_facebook_comment_id', true) !== '') {
            $error = __('A first comment was already posted for this article.', 'dr-purg-social-syndicator');
            update_post_meta($post_id, '_dpj_social_facebook_last_error', $error);
            return new WP_Error('dpj_social_comment_exists', $error);
        }

        $settings = self::settings();
        $page_id = trim((string) $settings['facebook_page_id']);
        $token = trim((string) $settings['facebook_page_access_token']);
        if ($page_id === '' || $token === '') {
            $error = __('Facebook Page ID and Page Access Token are required in Social Settings.', 'dr-purg-social-syndicator');
            update_post_meta($post_id, '_dpj_social_facebook_last_error', $error);
            return new WP_Error('dpj_social_missing_credentials', $error);
        }

        $facebook_link = trim((string) get_post_meta($post_id, '_dpj_social_facebook_link', true));
        $first_comment = trim((string) get_post_meta($post_id, '_dpj_social_facebook_first_comment', true));
        if ($first_comment === '' && $facebook_link !== '') {
            $first_comment = self::default_facebook_first_comment($facebook_link);
        }
        if ($first_comment === '') {
            $error = __('There is no first comment text to post.', 'dr-purg-social-syndicator');
            update_post_meta($post_id, '_dpj_social_facebook_last_error', $error);
            return new WP_Error('dpj_social_empty_comment', $error);
        }

        $first_comment = self::apply_utm_to_text($first_comment, $post_id, 'facebook', self::selected_variant($post_id));
        $graph_version = self::clean_graph_version((string) $settings['facebook_graph_version']);
        $result = self::facebook_request(
            'https://graph.facebook.com/' . rawurlencode($graph_version) . '/' . rawurlencode($remote_id) . '/comments',
            ['message' => $first_comment],
            $settings
        );

        if (is_wp_error($result)) {
            update_post_meta($post_id, '_dpj_social_facebook_last_error', $result->get_error_message());
            return $result;
        }

        if (isset($result['id'])) {
            update_post_meta($post_id, '_dpj_social_facebook_comment_id', sanitize_text_field((string) $result['id']));
        }
        delete_post_meta($post_id, '_dpj_social_facebook_last_error');

        return $result;
    }

    /**
     * Convert a datetime-local value (site timezone) to a Unix timestamp.
     * Returns 0 when empty or unparseable.
     */
    private static function parse_schedule_timestamp(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        try {
            $dt = new DateTimeImmutable($value, wp_timezone());
            return $dt->getTimestamp();
        } catch (Exception $e) {
            return 0;
        }
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
            '_dpj_social_facebook_scheduled_at',
        ] as $key) {
            delete_post_meta($post_id, $key);
        }

        // Cancel any pending auto first-comment for a scheduled post.
        wp_clear_scheduled_hook('dpj_social_post_scheduled_comment', [$post_id]);

        update_post_meta($post_id, '_dpj_social_facebook_status', self::STATUS_DRAFT);
    }

    /**
     * Cron callback: once a scheduled Facebook PAGE post has gone live, post the
     * reviewed link first comment automatically. Page only — never targets a
     * group. Idempotent (post_facebook_comment skips if a comment already exists);
     * if it misses, the manual "Post first comment" button remains the fallback.
     */
    public static function run_scheduled_comment(int $post_id): void
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0 || get_post_status($post_id) === false) {
            return;
        }
        self::post_facebook_comment($post_id);
    }

    private static function pinterest_enabled(): bool
    {
        $settings = self::settings();
        return trim((string) $settings['pinterest_access_token']) !== '' && trim((string) $settings['pinterest_board_id']) !== '';
    }

    /**
     * Publish a Pin to a Pinterest board through the official API (v5). Needs a
     * Pinterest access token and a default board ID in Social Settings, plus a
     * selected Pinterest image. Reviewed-only: it publishes the fields the
     * operator has approved, never automatically.
     *
     * @return array<string, mixed>|WP_Error
     */
    private static function publish_pinterest(int $post_id)
    {
        $remote_id = trim((string) get_post_meta($post_id, '_dpj_social_pinterest_remote_id', true));
        if ($remote_id !== '') {
            $error = __('This article already has a Pinterest pin. Reset the Pinterest lock before publishing again.', 'dr-purg-social-syndicator');
            update_post_meta($post_id, '_dpj_social_pinterest_last_error', $error);
            return new WP_Error('dpj_social_pinterest_duplicate', $error);
        }

        $settings = self::settings();
        $token = trim((string) $settings['pinterest_access_token']);
        $board_id = trim((string) $settings['pinterest_board_id']);
        if ($token === '' || $board_id === '') {
            $error = __('Pinterest access token and board ID are required in Social Settings.', 'dr-purg-social-syndicator');
            update_post_meta($post_id, '_dpj_social_pinterest_last_error', $error);
            update_post_meta($post_id, '_dpj_social_pinterest_status', self::STATUS_FAILED);
            return new WP_Error('dpj_social_pinterest_missing_credentials', $error);
        }

        $media_id = (int) get_post_meta($post_id, '_dpj_social_pinterest_media_id', true);
        $image_url = $media_id > 0 ? (string) wp_get_attachment_image_url($media_id, 'full') : '';
        if ($image_url === '') {
            $error = __('Select a Pinterest image before publishing.', 'dr-purg-social-syndicator');
            update_post_meta($post_id, '_dpj_social_pinterest_last_error', $error);
            update_post_meta($post_id, '_dpj_social_pinterest_status', self::STATUS_FAILED);
            return new WP_Error('dpj_social_pinterest_no_image', $error);
        }

        $title = trim((string) get_post_meta($post_id, '_dpj_social_pinterest_title', true));
        if ($title === '') {
            $title = get_the_title($post_id);
        }
        $description = trim((string) get_post_meta($post_id, '_dpj_social_pinterest_description', true));
        $alt_text = trim((string) get_post_meta($post_id, '_dpj_social_pinterest_alt_text', true));
        // Preserve a custom destination the operator typed; only fall back to the
        // tracked permalink when no custom URL is set (do not clobber it, and do
        // not lose it when UTM is off).
        $custom_link = trim((string) get_post_meta($post_id, '_dpj_social_pinterest_url', true));
        $permalink = (string) get_permalink($post_id);
        $is_custom = $custom_link !== '' && untrailingslashit($custom_link) !== untrailingslashit($permalink);
        $link = $is_custom ? $custom_link : self::social_utm_url($post_id, 'pinterest');

        $body = [
            'board_id' => $board_id,
            'title' => self::clean_ai_text($title, 100),
            'description' => self::clean_ai_text($description, 800),
            'link' => $link,
            'media_source' => [
                'source_type' => 'image_url',
                'url' => $image_url,
            ],
        ];
        if ($alt_text !== '') {
            $body['alt_text'] = self::clean_ai_text($alt_text, 500);
        }

        $result = self::pinterest_request('https://api.pinterest.com/v5/pins', $body, $token);
        if (is_wp_error($result)) {
            update_post_meta($post_id, '_dpj_social_pinterest_last_error', $result->get_error_message());
            update_post_meta($post_id, '_dpj_social_pinterest_status', self::STATUS_FAILED);
            return $result;
        }

        $pin_id = isset($result['id']) ? sanitize_text_field((string) $result['id']) : '';
        update_post_meta($post_id, '_dpj_social_pinterest_remote_id', $pin_id);
        update_post_meta($post_id, '_dpj_social_pinterest_status', self::STATUS_POSTED);
        update_post_meta($post_id, '_dpj_social_pinterest_posted_at', gmdate('c'));
        delete_post_meta($post_id, '_dpj_social_pinterest_last_error');

        return $result;
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>|WP_Error
     */
    private static function pinterest_request(string $endpoint, array $body, string $token)
    {
        $response = wp_remote_post($endpoint, [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $raw = (string) wp_remote_retrieve_body($response);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return new WP_Error('dpj_social_pinterest_bad_response', __('Pinterest returned an unreadable response.', 'dr-purg-social-syndicator'));
        }

        if ($status < 200 || $status >= 300) {
            $message = (string) ($decoded['message'] ?? __('Pinterest API request failed.', 'dr-purg-social-syndicator'));
            return new WP_Error('dpj_social_pinterest_error', $message, $decoded);
        }

        return $decoded;
    }

    private static function reset_pinterest(int $post_id): void
    {
        foreach ([
            '_dpj_social_pinterest_remote_id',
            '_dpj_social_pinterest_last_error',
            '_dpj_social_pinterest_posted_at',
        ] as $key) {
            delete_post_meta($post_id, $key);
        }

        update_post_meta($post_id, '_dpj_social_pinterest_status', self::STATUS_DRAFT);
    }

    private static function ga4_property_id(): string
    {
        return (string) preg_replace('/[^0-9]/', '', self::env('GA4_PROPERTY_ID', ''));
    }

    /**
     * The client-side GA4 measurement id (G-XXXXXXXX) used by the gtag.js
     * COLLECTION tag — distinct from the numeric GA4_PROPERTY_ID used by the
     * Data API read side. Returns '' when unset or malformed, so presence of a
     * valid id is the on switch (off by default in source).
     */
    private static function ga4_measurement_id(): string
    {
        $id = strtoupper(self::env('GA4_MEASUREMENT_ID', ''));
        return preg_match('/^G-[A-Z0-9]{4,20}$/', $id) ? $id : '';
    }

    /**
     * Print the GA4 gtag.js tracking snippet in the site <head> on the front end
     * when GA4_MEASUREMENT_ID is a valid id. This is what records sessions into
     * the GA4 property; the Data API pull then reads them back. Keep to a single
     * Google tag per page — don't combine with Site Kit / another GA plugin.
     */
    public static function print_ga4_tag(): void
    {
        if (is_admin()) {
            return;
        }
        $id = self::ga4_measurement_id();
        if ($id === '') {
            return;
        }

        $src = esc_url('https://www.googletagmanager.com/gtag/js?id=' . rawurlencode($id));
        $config = esc_js($id);

        echo "\n<!-- Google tag (gtag.js) — dr-purg-social-syndicator -->\n";
        echo '<script async src="' . $src . '"></script>' . "\n";
        echo "<script>\n";
        echo "  window.dataLayer = window.dataLayer || [];\n";
        echo "  function gtag(){dataLayer.push(arguments);}\n";
        echo "  gtag('js', new Date());\n";
        echo "  gtag('config', '" . $config . "');\n";
        echo "</script>\n";
    }

    private static function ga4_lookback_days(): int
    {
        return self::env_int('GA4_LOOKBACK_DAYS', 28, 1, 365);
    }

    /**
     * Load and decode the GA4 service-account JSON from GA4_SA_JSON. The value may
     * be the raw JSON or a path to the key file.
     *
     * @return array<string, mixed>|null
     */
    private static function ga4_service_account(): ?array
    {
        $raw = self::env('GA4_SA_JSON', '');
        if ($raw === '') {
            return null;
        }
        if (strlen($raw) < 500 && @is_file($raw) && is_readable($raw)) {
            $contents = @file_get_contents($raw);
            if ($contents !== false) {
                $raw = $contents;
            }
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private static function ga4_enabled(): bool
    {
        return self::env_bool('GA4_ENABLE', false)
            && self::ga4_property_id() !== ''
            && self::ga4_service_account() !== null;
    }

    private static function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Exchange a service-account JWT for a short-lived GA4 read access token.
     *
     * @param array<string, mixed> $sa
     * @return string|WP_Error
     */
    private static function ga4_access_token(array $sa)
    {
        $client_email = (string) ($sa['client_email'] ?? '');
        $private_key = (string) ($sa['private_key'] ?? '');
        if ($client_email === '' || $private_key === '') {
            return new WP_Error('dpj_ga4_bad_sa', __('The GA4 service account JSON is missing client_email or private_key.', 'dr-purg-social-syndicator'));
        }
        if (!function_exists('openssl_sign')) {
            return new WP_Error('dpj_ga4_no_openssl', __('The PHP OpenSSL extension is required for GA4 authentication.', 'dr-purg-social-syndicator'));
        }

        $now = time();
        $header = self::base64url_encode((string) wp_json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = self::base64url_encode((string) wp_json_encode([
            'iss' => $client_email,
            'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));
        $signing_input = $header . '.' . $claims;
        $signature = '';
        if (!openssl_sign($signing_input, $signature, $private_key, OPENSSL_ALGO_SHA256)) {
            return new WP_Error('dpj_ga4_sign_failed', __('Could not sign the GA4 auth request. Check the service account private key.', 'dr-purg-social-syndicator'));
        }
        $jwt = $signing_input . '.' . self::base64url_encode($signature);

        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'timeout' => 20,
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ],
        ]);
        if (is_wp_error($response)) {
            return $response;
        }

        $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
        $token = is_array($decoded) ? (string) ($decoded['access_token'] ?? '') : '';
        if ($token === '') {
            $message = is_array($decoded) ? (string) ($decoded['error_description'] ?? $decoded['error'] ?? '') : '';
            return new WP_Error('dpj_ga4_token_failed', sprintf(
                /* translators: %s is an error message. */
                __('GA4 token request failed: %s', 'dr-purg-social-syndicator'),
                $message !== '' ? $message : __('unknown error', 'dr-purg-social-syndicator')
            ));
        }

        return $token;
    }

    /**
     * Run a GA4 sessions-by-campaign report for the last N days.
     *
     * @return array<string, int>|WP_Error Map of campaign name => sessions.
     */
    private static function ga4_pull_sessions(int $days)
    {
        $sa = self::ga4_service_account();
        if (!is_array($sa)) {
            return new WP_Error('dpj_ga4_no_sa', __('GA4 service account JSON is not configured or is invalid.', 'dr-purg-social-syndicator'));
        }
        $property = self::ga4_property_id();
        if ($property === '') {
            return new WP_Error('dpj_ga4_no_property', __('GA4_PROPERTY_ID is not set.', 'dr-purg-social-syndicator'));
        }

        $token = self::ga4_access_token($sa);
        if (is_wp_error($token)) {
            return $token;
        }

        $response = wp_remote_post('https://analyticsdata.googleapis.com/v1beta/properties/' . rawurlencode($property) . ':runReport', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'dateRanges' => [['startDate' => $days . 'daysAgo', 'endDate' => 'today']],
                'dimensions' => [['name' => 'sessionCampaignName'], ['name' => 'sessionManualAdContent'], ['name' => 'sessionManualTerm']],
                'metrics' => [['name' => 'sessions'], ['name' => 'screenPageViewsPerSession'], ['name' => 'engagementRate']],
                'limit' => 100000,
            ]),
        ]);
        if (is_wp_error($response)) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($decoded)) {
            return new WP_Error('dpj_ga4_bad_response', __('GA4 returned an unreadable response.', 'dr-purg-social-syndicator'));
        }
        if ($status < 200 || $status >= 300) {
            $message = is_array($decoded['error'] ?? null) ? (string) ($decoded['error']['message'] ?? '') : '';
            return new WP_Error('dpj_ga4_report_error', sprintf(
                /* translators: %s is an error message. */
                __('GA4 report failed: %s', 'dr-purg-social-syndicator'),
                $message !== '' ? $message : 'HTTP ' . $status
            ));
        }

        // Rows are at (campaign × utm_content × utm_term) grain. Aggregate to a
        // campaign total (session-weighted pages/session + engagement) plus a
        // per-hook-variant (utm_content) and per-group (utm_term) breakdown, so
        // /retro can crown a winning angle AND see which groups convert.
        $map = [];
        foreach (($decoded['rows'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $campaign = (string) ($row['dimensionValues'][0]['value'] ?? '');
            if ($campaign === '' || $campaign === '(not set)' || $campaign === '(direct)') {
                continue;
            }
            $variant = trim((string) ($row['dimensionValues'][1]['value'] ?? ''));
            $group = trim((string) ($row['dimensionValues'][2]['value'] ?? ''));
            $sessions = (int) ($row['metricValues'][0]['value'] ?? 0);
            $pps = (float) ($row['metricValues'][1]['value'] ?? 0);
            $engagement = (float) ($row['metricValues'][2]['value'] ?? 0);

            if (!isset($map[$campaign])) {
                $map[$campaign] = ['sessions' => 0, 'pps_weighted' => 0.0, 'eng_weighted' => 0.0, 'variants' => [], 'groups' => []];
            }
            $map[$campaign]['sessions'] += $sessions;
            $map[$campaign]['pps_weighted'] += $pps * $sessions;
            $map[$campaign]['eng_weighted'] += $engagement * $sessions;

            if ($variant !== '' && $variant !== '(not set)') {
                self::ga4_accumulate($map[$campaign]['variants'], $variant, $sessions, $pps, $engagement);
            }
            if ($group !== '' && $group !== '(not set)') {
                self::ga4_accumulate($map[$campaign]['groups'], $group, $sessions, $pps, $engagement);
            }
        }

        foreach ($map as &$data) {
            $total = (int) $data['sessions'];
            $data['pps'] = $total > 0 ? round($data['pps_weighted'] / $total, 2) : 0.0;
            $data['engagement'] = $total > 0 ? round($data['eng_weighted'] / $total, 4) : 0.0;
            unset($data['pps_weighted'], $data['eng_weighted']);
            $data['variants'] = self::ga4_finalize($data['variants']);
            $data['groups'] = self::ga4_finalize($data['groups']);
        }
        unset($data);

        return $map;
    }

    /**
     * Accumulate a session-weighted bucket (a hook variant or a group) while
     * reading GA4 rows. Pages/session and engagement are weighted by sessions so
     * the finalized average is correct across multiple rows.
     *
     * @param array<string, array<string, float|int>> $bucket
     */
    private static function ga4_accumulate(array &$bucket, string $key, int $sessions, float $pps, float $engagement): void
    {
        if (!isset($bucket[$key])) {
            $bucket[$key] = ['clicks' => 0, 'pps_weighted' => 0.0, 'eng_weighted' => 0.0];
        }
        $bucket[$key]['clicks'] += $sessions;
        $bucket[$key]['pps_weighted'] += $pps * $sessions;
        $bucket[$key]['eng_weighted'] += $engagement * $sessions;
    }

    /**
     * Finalize weighted buckets into {clicks, pps, engagement}.
     *
     * @param array<string, array<string, float|int>> $bucket
     * @return array<string, array<string, float|int>>
     */
    private static function ga4_finalize(array $bucket): array
    {
        $out = [];
        foreach ($bucket as $key => $entry) {
            $clicks = (int) $entry['clicks'];
            $out[$key] = [
                'clicks' => $clicks,
                'pps' => $clicks > 0 ? round($entry['pps_weighted'] / $clicks, 2) : 0.0,
                'engagement' => $clicks > 0 ? round($entry['eng_weighted'] / $clicks, 4) : 0.0,
            ];
        }

        return $out;
    }

    /**
     * Pull GA4 sessions by campaign and write them into the performance log as
     * clicks, matched to posts by slug (the post slug = its utm_campaign).
     */
    private static function handle_ga4_pull(): void
    {
        if (!self::can_manage()) {
            wp_die(esc_html__('You do not have permission to pull GA4 data.', 'dr-purg-social-syndicator'));
        }

        check_admin_referer('dpj_social_ga4_pull', 'dpj_social_ga4_nonce');

        $redirect = static function (string $notice, array $args = []) {
            wp_safe_redirect(add_query_arg(array_merge(['page' => self::PERF_SLUG, 'dpj_social_notice' => $notice], $args), admin_url('admin.php')));
            exit;
        };

        if (!self::ga4_enabled()) {
            update_option('dpj_social_ga4_error', __('GA4 is not configured. Set GA4_ENABLE=1, GA4_PROPERTY_ID, and GA4_SA_JSON.', 'dr-purg-social-syndicator'), false);
            $redirect('ga4_failed');
        }

        $days = isset($_POST['ga4_days']) ? absint($_POST['ga4_days']) : self::ga4_lookback_days();
        $days = max(1, min(365, $days));

        $map = self::ga4_pull_sessions($days);
        if (is_wp_error($map)) {
            update_option('dpj_social_ga4_error', $map->get_error_message(), false);
            $redirect('ga4_failed');
        }
        delete_option('dpj_social_ga4_error');

        $slug_to_id = [];
        foreach (get_posts([
            'post_type' => 'post',
            'post_status' => ['publish', 'future', 'draft'],
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]) as $pid) {
            $slug = sanitize_title((string) get_post_field('post_name', (int) $pid));
            if ($slug !== '') {
                $slug_to_id[$slug] = (int) $pid;
            }
        }

        $rows = 0;
        $updated = 0;
        $unmatched = 0;
        foreach ($map as $campaign => $data) {
            $rows++;
            $slug = sanitize_title((string) $campaign);
            if ($slug === '' || !isset($slug_to_id[$slug])) {
                $unmatched++;
                continue;
            }
            $post_id = $slug_to_id[$slug];
            update_post_meta($post_id, '_dpj_social_perf_clicks', (string) absint($data['sessions'] ?? 0));
            update_post_meta($post_id, '_dpj_social_perf_pps', number_format((float) ($data['pps'] ?? 0), 2, '.', ''));
            update_post_meta($post_id, '_dpj_social_perf_engagement', number_format((float) ($data['engagement'] ?? 0), 4, '.', ''));
            if (!empty($data['variants']) && is_array($data['variants'])) {
                update_post_meta($post_id, '_dpj_social_perf_variants', (string) wp_json_encode($data['variants']));
            }
            if (!empty($data['groups']) && is_array($data['groups'])) {
                update_post_meta($post_id, '_dpj_social_perf_groups', (string) wp_json_encode($data['groups']));
            }
            update_post_meta($post_id, '_dpj_social_perf_updated', gmdate('c'));
            $updated++;
        }

        $redirect('perf_imported', [
            'dpj_perf_updated' => $updated,
            'dpj_perf_rows' => $rows,
            'dpj_perf_unmatched' => $unmatched,
        ]);
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
