<?php
/**
 * Plugin Name: Dr Purg Jr. Author Tools
 * Description: Simplifies the post editor with split tools, excerpt and SEO helpers, internal-link suggestions, and featured-image metadata.
 * Version: 1.10.3
 * Author: Site tools
 * Text Domain: kepoli-author-tools
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Food_Blog_Author_Tools
{
    private const VERSION = '1.10.3';
    private const AUTO_INTERNAL_LINKS_START = '<!-- kepoli-auto-internal-links:start -->';
    private const AUTO_INTERNAL_LINKS_END = '<!-- kepoli-auto-internal-links:end -->';
    private const AUTO_FAQ_START = '<!-- kepoli-auto-faq:start -->';
    private const AUTO_FAQ_END = '<!-- kepoli-auto-faq:end -->';
    private const TEMPLATE_PROMPTS = [
        'Scrie aici de ce merita pregatita reteta, cand se potriveste si ce rezultat trebuie sa obtina cititorul.',
        'Ingredient 1',
        'Ingredient 2',
        'Ingredient 3',
        'Descrie primul pas clar, cu temperatura, timp sau semne vizuale daca este nevoie.',
        'Continua cu pasii in ordinea fireasca.',
        'Incheie cu momentul in care preparatul este gata.',
        'Adauga ajustari, greseli de evitat si variante utile pentru ingrediente.',
        'Explica pastrarea la frigider, reincalzirea sau consumul in siguranta.',
        'Raspunde practic, cu intervale realiste.',
        'Prezinta subiectul si spune cititorului ce va invata din articol.',
        'Explica punctele importante in paragrafe scurte, cu exemple concrete.',
        'Leaga sfaturile de retete, ingrediente sau obiceiuri de gatit acasa.',
        'Adauga linkuri interne catre retete sau ghiduri apropiate.',
        'Write 2-3 sentences about the result, occasion, and texture.',
        'Add ingredients in a list, one per line.',
        'Add the steps in order, with time, temperature, and visual signs where useful.',
        'Note mistakes to avoid, adjustments, and useful variations.',
        'Explain storage, reheating, and safe consumption.',
        'Answer practically, with realistic time ranges.',
        'Introduce the topic and tell the reader what they will learn.',
        'Explain the important points in short paragraphs with concrete examples.',
        'Connect the advice to recipes, ingredients, or home cooking habits.',
        'Add internal links to nearby recipes or guides.',
    ];
    private const TEMPLATE_OUTLINE_LABELS = [
        'Pe scurt',
        'Ingrediente',
        'Mod de preparare',
        'Sfaturi pentru reusita',
        'Cum pastrezi',
        'Intrebari frecvente',
        'Pot pregati reteta in avans?',
        'Ideea principala',
        'Ce merita retinut',
        'Cum aplici in bucatarie',
        'Legaturi utile',
        'What to know first',
        'Ingredients',
        'Method',
        'Success notes',
        'Storage',
        'Frequently asked questions',
        'Can I prepare this recipe ahead?',
        'Main idea',
        'What to remember',
        'How to use it in the kitchen',
        'Useful links',
    ];
    private static $is_updating_post = false;

    private static function site_profile(): array
    {
        static $profile = null;

        if ($profile !== null) {
            return $profile;
        }

        $public_locale = (string) get_option('WPLANG');
        if ($public_locale === '') {
            $public_locale = 'en_US';
        }

        $default = [
            'brand' => [
                'name' => get_bloginfo('name') ?: 'Dr Purg Jr.',
                'site_email' => get_option('admin_email') ?: 'contact@example.com',
            ],
            'locales' => [
                'public' => $public_locale,
                'admin' => 'en_US',
                'force_admin' => true,
            ],
            'writer' => [
                'name' => '',
                'email' => '',
                'bio' => '',
            ],
            'slugs' => [],
        ];

        $stored = get_option('kepoli_site_profile');
        $profile = array_replace_recursive($default, is_array($stored) ? $stored : []);
        $profile['locales']['admin'] = 'en_US';
        $profile['locales']['force_admin'] = true;

        return $profile;
    }

    private static function profile_value(array $path, $default = '')
    {
        $value = self::site_profile();
        foreach ($path as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return $default;
            }

            $value = $value[$key];
        }

        return $value;
    }

    private static function public_locale(): string
    {
        $locale = trim((string) self::profile_value(['locales', 'public'], get_option('WPLANG') ?: 'en_US'));
        return $locale !== '' ? $locale : 'en_US';
    }

    private static function admin_locale(): string
    {
        $locale = trim((string) self::profile_value(['locales', 'admin'], 'en_US'));
        return $locale !== '' ? $locale : 'en_US';
    }

    private static function locale_is_english(string $locale): bool
    {
        return str_starts_with(strtolower($locale), 'en');
    }

    private static function public_is_english(): bool
    {
        return self::locale_is_english(self::public_locale());
    }

    private static function admin_is_english(): bool
    {
        return self::locale_is_english(self::admin_locale());
    }

    private static function is_english(): bool
    {
        return self::public_is_english();
    }

    private static function admin_ui_text(string $ro, string $en): string
    {
        return self::admin_is_english() ? $en : $ro;
    }

    private static function public_content_text(string $ro, string $en): string
    {
        return self::public_is_english() ? $en : $ro;
    }

    private static function ui_text(string $ro, string $en): string
    {
        return self::admin_ui_text($ro, $en);
    }

    private static function content_text(string $ro, string $en): string
    {
        return self::public_content_text($ro, $en);
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

    private static function ai_extraction_enabled(): bool
    {
        if (!self::env_bool('AI_EXTRACTION_ENABLE', false)) {
            return false;
        }

        return self::openrouter_api_key() !== '';
    }

    private static function openrouter_api_key(): string
    {
        return self::env('AI_EXTRACTION_API_KEY', self::env('OPENROUTER_API_KEY'));
    }

    private static function site_name(): string
    {
        $name = trim((string) self::profile_value(['brand', 'name'], ''));
        return $name !== '' ? $name : (get_bloginfo('name') ?: 'Dr Purg Jr.');
    }

    private static function profile_slug(string $key, string $fallback): string
    {
        $slug = sanitize_title((string) self::profile_value(['slugs', $key], ''));
        return $slug !== '' ? $slug : $fallback;
    }

    private static function article_category_slugs(): array
    {
        return array_values(array_unique(array_filter([
            self::profile_slug('guides', 'guides'),
            'guides',
            'articles',
            'guides',
        ])));
    }

    private static function clean_tag_name(string $tag): string
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

    private static function clean_tag_list(array $tags): array
    {
        $clean = [];
        $seen = [];
        foreach ($tags as $tag) {
            $tag = self::clean_tag_name((string) $tag);
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

        return array_slice($clean, 0, 8);
    }

    public static function init(): void
    {
        add_filter('use_block_editor_for_post_type', [self::class, 'use_classic_editor_for_posts'], 10, 2);
        add_filter('mce_external_plugins', [self::class, 'register_tinymce_plugin']);
        add_filter('mce_buttons', [self::class, 'register_tinymce_buttons']);
        add_action('wp_ajax_kepoli_author_tools_ai_extract', [self::class, 'ajax_ai_extract']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_assets']);
        add_action('add_meta_boxes_post', [self::class, 'add_publish_companion_box']);
        add_action('add_meta_boxes_post', [self::class, 'add_writer_guide_box']);
        add_action('add_meta_boxes_post', [self::class, 'add_post_setup_box']);
        add_action('save_post_post', [self::class, 'save_post_setup'], 10, 3);
        add_filter('manage_post_posts_columns', [self::class, 'add_post_list_columns']);
        add_action('manage_post_posts_custom_column', [self::class, 'render_post_list_column'], 10, 2);
        add_action('restrict_manage_posts', [self::class, 'render_post_kind_filter']);
        add_action('pre_get_posts', [self::class, 'filter_posts_by_kind']);
        add_filter('the_content', [self::class, 'remove_template_prompts_from_content'], 4);
        add_filter('get_the_excerpt', [self::class, 'remove_template_prompts_from_excerpt'], 12, 2);
    }

    public static function use_classic_editor_for_posts(bool $use_block_editor, string $post_type): bool
    {
        return $post_type === 'post' ? false : $use_block_editor;
    }

    public static function register_tinymce_plugin(array $plugins): array
    {
        if (self::is_post_editor_screen()) {
            $plugins['kepoli_author_tools'] = add_query_arg(
                'ver',
                self::VERSION,
                plugins_url('assets/editor-tools.js', __FILE__)
            );
        }

        return $plugins;
    }

    public static function register_tinymce_buttons(array $buttons): array
    {
        if (self::is_post_editor_screen()) {
            $buttons[] = 'separator';
            $buttons[] = 'kepoli_page_break';
            $buttons[] = 'kepoli_split_two';
            $buttons[] = 'kepoli_split_three';
        }

        return $buttons;
    }

    public static function enqueue_admin_assets(string $hook): void
    {
        if (!in_array($hook, ['post.php', 'post-new.php', 'edit.php'], true) || self::current_post_type() !== 'post') {
            return;
        }

        wp_enqueue_style(
            'kepoli-author-tools-admin',
            plugins_url('assets/admin.css', __FILE__),
            [],
            self::VERSION
        );

        if (in_array($hook, ['post.php', 'post-new.php'], true)) {
            wp_enqueue_script(
                'kepoli-author-tools-admin',
                plugins_url('assets/admin.js', __FILE__),
                ['quicktags'],
                self::VERSION,
                true
            );

            wp_localize_script('kepoli-author-tools-admin', 'kepoliAuthorTools', [
                'currentPostId' => self::current_post_id(),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'aiNonce' => wp_create_nonce('kepoli_author_tools_ai'),
                'aiExtractionEnabled' => self::ai_extraction_enabled(),
                'aiExtractionModel' => self::env('AI_EXTRACTION_MODEL', 'inclusionai/ling-2.6-1t:free'),
                'siteName' => self::site_name(),
                'isEnglish' => self::public_is_english(),
                'adminIsEnglish' => self::admin_is_english(),
                'publicIsEnglish' => self::public_is_english(),
                'relatedPosts' => self::related_posts_payload(self::current_post_id()),
                'categories' => self::category_payload(),
                'strings' => [
                    'checkReady' => self::ui_text('Setup aproape complet. Mai verifica naturaletea textului inainte de publicare.', 'Setup is almost complete. Review the text once before publishing.'),
                    'checkMissingPrefix' => self::ui_text('De completat inainte de publicare:', 'Complete before publishing:'),
                    'publishConfirmPrefix' => self::ui_text('Postarea mai are campuri lipsa:', 'The post still has missing fields:'),
                    'publishConfirmSuffix' => self::ui_text('Continui totusi publicarea?', 'Publish anyway?'),
                    'companionReady' => self::ui_text('Postarea arata bine pentru urmatorul pas. Fa doar o ultima lectura inainte de publicare.', 'The post looks ready for the next step. Give it one final read before publishing.'),
                    'companionReview' => self::ui_text('Mai sunt cateva lucruri de verificat inainte sa publici.', 'A few things still need review before publishing.'),
                    'companionStatusReady' => self::ui_text('Gata pentru o ultima lectura.', 'Ready for a final read.'),
                    'companionStatusSingle' => self::ui_text('Mai lipseste 1 lucru important.', '1 important item is still missing.'),
                    'companionStatusMultiple' => self::ui_text('Mai lipsesc %d lucruri importante.', '%d important items are still missing.'),
                    'companionNoCategory' => self::ui_text('Nicio sugestie clara inca', 'No clear suggestion yet'),
                    'companionNoTags' => self::ui_text('Fara taguri sugerate inca', 'No suggested tags yet'),
                    'defaultSlugHint' => self::ui_text('Slugul se va curata automat la salvare.', 'The slug will be cleaned automatically on save.'),
                ],
            ]);
        }
    }

    public static function ajax_ai_extract(): void
    {
        check_ajax_referer('kepoli_author_tools_ai', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => self::ui_text('Nu ai permisiunea pentru aceasta actiune.', 'You do not have permission to use this tool.')], 403);
        }

        if (!self::ai_extraction_enabled()) {
            wp_send_json_error(['message' => self::ui_text('Extractia AI nu este activata.', 'AI extraction is not enabled.')], 400);
        }

        $kind = isset($_POST['kind']) ? sanitize_key(wp_unslash((string) $_POST['kind'])) : 'recipe';
        if (!in_array($kind, ['recipe', 'article'], true)) {
            $kind = 'recipe';
        }

        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash((string) $_POST['title'])) : '';
        $html = isset($_POST['content_html']) ? (string) wp_unslash($_POST['content_html']) : '';
        $text = isset($_POST['content_text']) ? (string) wp_unslash($_POST['content_text']) : '';
        $source = self::ai_source_text($html !== '' ? $html : $text);

        if ($title === '' && strlen($source) < 80) {
            wp_send_json_error(['message' => self::ui_text('Adauga mai intai titlul si continutul retetei.', 'Add the recipe title and content first.')], 400);
        }

        $result = self::openrouter_extract_post($kind, $title, $source);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 502);
        }

        wp_send_json_success($result);
    }

    private static function ai_source_text(string $value): string
    {
        $value = (string) preg_replace('/<(br|\/p|\/li|\/h[1-6]|\/div|\/section)\b[^>]*>/i', "\n", $value);
        $value = wp_strip_all_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8');
        $value = (string) preg_replace("/[ \t]+/", ' ', $value);
        $value = (string) preg_replace("/\n{3,}/", "\n\n", $value);
        $value = trim($value);

        return mb_substr($value, 0, self::env_int('AI_EXTRACTION_MAX_CHARS', 9000, 1000, 20000));
    }

    private static function openrouter_extract_post(string $kind, string $title, string $source)
    {
        $model = self::env('AI_EXTRACTION_MODEL', 'inclusionai/ling-2.6-1t:free');
        $timeout = self::env_int('AI_EXTRACTION_TIMEOUT_SECONDS', 14, 4, 30);
        $public_locale = self::public_locale();
        $language = self::public_is_english() ? 'English' : 'Romanian';
        $schema = $kind === 'recipe'
            ? '{"servings":"4 servings","prepMinutes":15,"cookMinutes":30,"totalMinutes":45,"ingredients":["one clean ingredient per line"],"steps":["one clean cooking step per line"]}'
            : '{"summary":"short factual summary","metaDescription":"SEO description under 155 characters","tags":["short tag"]}';

        $prompt = "Extract structured fields from the post below. Return ONLY valid JSON, no markdown.\n"
            . "Public language: {$language} ({$public_locale}). Keep extracted text in the post language.\n"
            . "Post type: {$kind}.\n"
            . "Required JSON shape: {$schema}\n"
            . "Rules: do not invent ingredients, do not add HTML, keep steps in cooking order, use integer minutes, infer totalMinutes only from prep+cook or explicit total, keep arrays clean and deduplicated.\n\n"
            . "Title: {$title}\n\nContent:\n{$source}";

        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', [
            'timeout' => $timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . self::openrouter_api_key(),
                'Content-Type' => 'application/json',
                'HTTP-Referer' => home_url('/'),
                'X-Title' => self::site_name(),
            ],
            'body' => wp_json_encode([
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a strict food-blog extraction engine. You never write prose outside JSON.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.1,
                'max_tokens' => self::env_int('AI_EXTRACTION_MAX_TOKENS', 1400, 300, 3000),
                'response_format' => ['type' => 'json_object'],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        if ($status < 200 || $status >= 300) {
            return new WP_Error('openrouter_http_error', sprintf(self::ui_text('OpenRouter a raspuns cu eroarea HTTP %d.', 'OpenRouter returned HTTP error %d.'), $status));
        }

        $decoded = json_decode($body, true);
        $content = is_array($decoded) ? (string) ($decoded['choices'][0]['message']['content'] ?? '') : '';
        $payload = self::decode_ai_json_object($content);
        if (!is_array($payload)) {
            return new WP_Error('openrouter_bad_json', self::ui_text('Modelul AI nu a returnat JSON valid.', 'The AI model did not return valid JSON.'));
        }

        return $kind === 'recipe'
            ? ['recipe' => self::sanitize_ai_recipe_payload($payload)]
            : ['article' => self::sanitize_ai_article_payload($payload)];
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

    private static function sanitize_ai_recipe_payload(array $payload): array
    {
        $prep = self::positive_minutes($payload['prepMinutes'] ?? 0);
        $cook = self::positive_minutes($payload['cookMinutes'] ?? 0);
        $total = self::positive_minutes($payload['totalMinutes'] ?? 0);
        if ($total === 0 && ($prep > 0 || $cook > 0)) {
            $total = $prep + $cook;
        }

        return [
            'servings' => self::clean_ai_text($payload['servings'] ?? ''),
            'prepMinutes' => $prep > 0 ? (string) $prep : '',
            'cookMinutes' => $cook > 0 ? (string) $cook : '',
            'totalMinutes' => $total > 0 ? (string) $total : '',
            'ingredients' => self::clean_ai_list($payload['ingredients'] ?? [], 50),
            'steps' => self::clean_ai_list($payload['steps'] ?? [], 35),
        ];
    }

    private static function sanitize_ai_article_payload(array $payload): array
    {
        return [
            'summary' => self::clean_ai_text($payload['summary'] ?? ''),
            'metaDescription' => mb_substr(self::clean_ai_text($payload['metaDescription'] ?? ''), 0, 170),
            'tags' => self::clean_ai_list($payload['tags'] ?? [], 8),
        ];
    }

    private static function positive_minutes($value): int
    {
        if (is_string($value) && preg_match('/(\d{1,4})/', $value, $match)) {
            $value = $match[1];
        }

        $minutes = (int) $value;
        return $minutes > 0 && $minutes <= 1440 ? $minutes : 0;
    }

    private static function clean_ai_list($items, int $limit): array
    {
        if (is_string($items)) {
            $items = preg_split('/\r\n|\r|\n/', $items) ?: [];
        }
        if (!is_array($items)) {
            return [];
        }

        $clean = [];
        foreach ($items as $item) {
            $item = self::clean_ai_text($item);
            $item = (string) preg_replace('/^\s*(?:[-*]|\d+[.)])\s*/', '', $item);
            if ($item === '' || in_array($item, $clean, true)) {
                continue;
            }

            $clean[] = mb_substr($item, 0, 220);
            if (count($clean) >= $limit) {
                break;
            }
        }

        return $clean;
    }

    private static function clean_ai_text($value): string
    {
        if (is_array($value) || is_object($value)) {
            return '';
        }

        $value = wp_strip_all_tags((string) $value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8');
        $value = (string) preg_replace('/\s+/', ' ', $value);

        return trim(sanitize_text_field($value));
    }

    public static function add_publish_companion_box(): void
    {
        add_meta_box(
            'kepoli-publish-companion',
            self::ui_text('Asistent publicare', 'Publish helper'),
            [self::class, 'render_publish_companion_box'],
            'post',
            'side',
            'high'
        );
    }

    public static function render_publish_companion_box(): void
    {
        ?>
        <div class="kepoli-publish-companion" data-kepoli-publish-companion>
            <p class="kepoli-publish-companion__intro"><?php echo esc_html(self::ui_text('Cand esti aproape gata, foloseste acest buton pentru completarea automata finala.', 'When the post is almost ready, use this button for the final automatic setup.')); ?></p>
            <div class="kepoli-publish-companion__actions">
                <button type="button" class="button button-primary" data-kepoli-companion-complete><?php echo esc_html(self::ui_text('Pregateste pentru publicare', 'Prepare for publishing')); ?></button>
                <p class="kepoli-publish-companion__status" data-kepoli-companion-status></p>
            </div>
            <p class="kepoli-publish-companion__summary" data-kepoli-companion-summary></p>
            <details class="kepoli-publish-companion__details">
                <summary><?php echo esc_html(self::ui_text('Vezi detalii', 'View details')); ?></summary>
                <div class="kepoli-publish-companion__block">
                    <span class="kepoli-publish-companion__label"><?php echo esc_html(self::ui_text('Categoria sugerata', 'Suggested category')); ?></span>
                    <strong data-kepoli-companion-category><?php echo esc_html(self::ui_text('Se calculeaza...', 'Calculating...')); ?></strong>
                </div>
                <div class="kepoli-publish-companion__block">
                    <span class="kepoli-publish-companion__label"><?php echo esc_html(self::ui_text('Taguri sugerate', 'Suggested tags')); ?></span>
                    <p data-kepoli-companion-tags><?php echo esc_html(self::ui_text('Se calculeaza...', 'Calculating...')); ?></p>
                </div>
                <div class="kepoli-publish-companion__block">
                    <span class="kepoli-publish-companion__label"><?php echo esc_html(self::ui_text('Mai verifica', 'Review')); ?></span>
                    <ul class="kepoli-publish-companion__checks" data-kepoli-companion-checks></ul>
                </div>
            </details>
        </div>
        <?php
    }

    public static function add_writer_guide_box(): void
    {
        add_meta_box(
            'kepoli-author-guide',
            self::ui_text('Unelte de scriere', 'Writing tools'),
            [self::class, 'render_writer_guide_box'],
            'post',
            'side',
            'high'
        );
    }

    public static function render_writer_guide_box(): void
    {
        ?>
        <div class="kepoli-author-guide">
            <p class="kepoli-author-guide__intro"><strong><?php echo esc_html(self::ui_text('Porneste rapid cu o structura gata facuta.', 'Start quickly with a ready structure.')); ?></strong></p>
            <div class="kepoli-template-actions">
                <button type="button" class="button" data-kepoli-template="recipe"><?php echo esc_html(self::ui_text('Structura reteta', 'Recipe structure')); ?></button>
                <button type="button" class="button" data-kepoli-template="article"><?php echo esc_html(self::ui_text('Structura articol', 'Article structure')); ?></button>
            </div>
            <p class="kepoli-author-guide__note"><?php echo esc_html(self::ui_text('Pentru articole lungi, foloseste `Pauza`, `2 parti` sau `3 parti` din toolbar.', 'For long posts, use `Break`, `2 parts`, or `3 parts` in the toolbar.')); ?></p>
        </div>
        <?php
    }

    public static function add_post_setup_box(): void
    {
        add_meta_box(
            'kepoli-post-setup',
            self::ui_text('Setup postare', 'Post setup'),
            [self::class, 'render_post_setup_box'],
            'post',
            'normal',
            'high'
        );
    }

    public static function render_post_setup_box(WP_Post $post): void
    {
        $kind = get_post_meta($post->ID, '_kepoli_post_kind', true);
        $kind = in_array($kind, ['recipe', 'article'], true) ? $kind : 'recipe';
        $seo_title = (string) get_post_meta($post->ID, '_kepoli_seo_title', true);
        $excerpt = (string) $post->post_excerpt;
        $meta_description = (string) get_post_meta($post->ID, '_kepoli_meta_description', true);
        $related_recipes = self::array_meta_to_text($post->ID, '_kepoli_related_recipe_slugs');
        $related_articles = self::array_meta_to_text($post->ID, '_kepoli_related_article_slugs');
        $auto_split_raw = get_post_meta($post->ID, '_kepoli_auto_split_parts', true);
        $auto_split_parts = $auto_split_raw === '' ? -1 : (int) $auto_split_raw;
        $auto_split_parts = in_array($auto_split_parts, [-1, 0, 2, 3], true) ? $auto_split_parts : -1;
        $recipe = self::recipe_data($post->ID);
        $image_meta = self::featured_image_meta($post->ID);
        $has_image_meta = array_filter($image_meta, static function ($value): bool {
            return trim((string) $value) !== '';
        });
        $has_seo_details = trim($seo_title) !== '' || trim($meta_description) !== '' || trim($related_recipes) !== '' || trim($related_articles) !== '';

        wp_nonce_field('kepoli_author_tools_save', 'kepoli_author_tools_nonce');
        ?>
        <div class="kepoli-post-setup">
            <div class="kepoli-automation-actions kepoli-automation-actions--primary">
                <button type="button" class="button button-primary" data-kepoli-complete-setup><?php echo esc_html(self::ui_text('Completeaza automat', 'Auto fill')); ?></button>
                <span class="kepoli-automation-actions__status" data-kepoli-automation-status></span>
            </div>
            <p class="kepoli-automation-actions__note"><?php echo esc_html(self::ui_text('Acesta este butonul principal pentru lucru rapid. Site-ul incearca sa completeze campurile goale si iti lasa doar verificarea finala.', 'This is the main quick-work button. It fills empty fields where possible and leaves only the final review.')); ?></p>
            <details class="kepoli-automation-more">
                <summary><?php echo esc_html(self::ui_text('Mai multe unelte', 'More tools')); ?></summary>
                <div class="kepoli-automation-actions kepoli-automation-actions--secondary">
                    <button type="button" class="button" data-kepoli-suggest-category><?php echo esc_html(self::ui_text('Sugereaza categorie', 'Suggest category')); ?></button>
                    <button type="button" class="button" data-kepoli-suggest-tags><?php echo esc_html(self::ui_text('Sugereaza taguri', 'Suggest tags')); ?></button>
                    <button type="button" class="button" data-kepoli-extract-recipe><?php echo esc_html(self::ui_text('Extrage schema reteta', 'Extract recipe schema')); ?></button>
                    <button type="button" class="button" data-kepoli-generate-excerpt><?php echo esc_html(self::ui_text('Genereaza excerpt', 'Generate excerpt')); ?></button>
                    <button type="button" class="button" data-kepoli-generate-meta><?php echo esc_html(self::ui_text('Genereaza meta description', 'Generate meta description')); ?></button>
                    <button type="button" class="button" data-kepoli-suggest-related><?php echo esc_html(self::ui_text('Sugereaza linkuri interne', 'Suggest internal links')); ?></button>
                    <button type="button" class="button" data-kepoli-generate-image-meta><?php echo esc_html(self::ui_text('Genereaza meta imagine', 'Generate image metadata')); ?></button>
                </div>
                <p class="kepoli-automation-actions__note"><?php echo esc_html(self::ui_text('Pentru retete, Extrage schema reteta citeste ingredientele si pasii din continut daca folosesti structura pregatita.', 'For recipes, Extract recipe schema reads ingredients and steps from the post if you use the prepared structure.')); ?></p>
            </details>

            <fieldset class="kepoli-post-setup__group">
                <legend><?php echo esc_html(self::ui_text('Tip continut', 'Content type')); ?></legend>
                <label class="kepoli-choice">
                    <input type="radio" name="kepoli_post_kind" value="recipe" <?php checked($kind, 'recipe'); ?>>
                    <span><?php echo esc_html(self::ui_text('Reteta', 'Recipe')); ?></span>
                </label>
                <label class="kepoli-choice">
                    <input type="radio" name="kepoli_post_kind" value="article" <?php checked($kind, 'article'); ?>>
                    <span><?php echo esc_html(self::ui_text('Articol', 'Article')); ?></span>
                </label>
            </fieldset>

            <div class="kepoli-post-setup__grid kepoli-post-setup__grid--single">
                <label>
                    <span><?php esc_html_e('Excerpt', 'kepoli-author-tools'); ?></span>
                    <textarea name="kepoli_post_excerpt" rows="3" maxlength="260" placeholder="<?php echo esc_attr(self::ui_text('Rezumat scurt pentru carduri, arhive si intro.', 'Short summary for cards, archives, and the post intro.')); ?>"><?php echo esc_textarea($excerpt); ?></textarea>
                </label>
            </div>

            <div class="kepoli-post-setup__grid kepoli-post-setup__grid--single">
                <label>
                    <span><?php echo esc_html(self::ui_text('Impartire automata', 'Automatic split')); ?></span>
                    <select name="kepoli_auto_split_parts">
                        <option value="-1" <?php selected($auto_split_parts, -1); ?>><?php echo esc_html(self::ui_text('Smart: 2-3 parti pentru postari lungi', 'Smart: 2-3 parts for long posts')); ?></option>
                        <option value="0" <?php selected($auto_split_parts, 0); ?>><?php echo esc_html(self::ui_text('Fara impartire automata', 'No automatic split')); ?></option>
                        <option value="2" <?php selected($auto_split_parts, 2); ?>><?php echo esc_html(self::ui_text('2 parti la salvare', '2 parts on save')); ?></option>
                        <option value="3" <?php selected($auto_split_parts, 3); ?>><?php echo esc_html(self::ui_text('3 parti la salvare', '3 parts on save')); ?></option>
                    </select>
                    <small><?php echo esc_html(self::ui_text('Smart pastreaza postarile scurte pe o pagina, imparte postarile lungi in 2 parti si cele foarte lungi in 3. Pauzele manuale din editor raman prioritare.', 'Smart keeps short posts on one page, splits long posts into 2 parts, and very long posts into 3. Manual page breaks stay in control.')); ?></small>
                </label>
            </div>

            <details class="kepoli-setup-section kepoli-seo-fields" <?php echo $has_seo_details ? ' open' : ''; ?>>
                <summary><?php echo esc_html(self::ui_text('Detalii SEO si legaturi', 'SEO and links')); ?></summary>
                <p><?php echo esc_html(self::ui_text('Aceste campuri sunt optionale pentru lucru manual. Daca le lasi goale, site-ul incearca sa le completeze automat.', 'These fields are optional for manual work. If you leave them empty, the site will try to fill them automatically.')); ?></p>
                <div class="kepoli-post-setup__grid kepoli-post-setup__grid--single">
                    <label>
                        <span><?php echo esc_html(self::ui_text('SEO title optional', 'Optional SEO title')); ?></span>
                        <input type="text" name="kepoli_seo_title" value="<?php echo esc_attr($seo_title); ?>" placeholder="<?php echo esc_attr(self::ui_text('Daca ramane gol, se foloseste titlul postarii.', 'If empty, the post title will be used.')); ?>">
                    </label>
                </div>
                <div class="kepoli-post-setup__grid kepoli-post-setup__grid--single">
                    <label>
                        <span><?php esc_html_e('Meta description', 'kepoli-author-tools'); ?></span>
                        <textarea name="kepoli_meta_description" rows="3" maxlength="180" placeholder="<?php echo esc_attr(self::ui_text('Rezumat scurt pentru Google si distribuire sociala.', 'Short summary for Google and social sharing.')); ?>"><?php echo esc_textarea($meta_description); ?></textarea>
                    </label>
                </div>
                <div class="kepoli-post-setup__grid">
                    <label>
                        <span><?php echo esc_html(self::ui_text('Sluguri retete recomandate', 'Related recipe slugs')); ?></span>
                        <textarea name="kepoli_related_recipe_slugs" rows="3" placeholder="lemon-herb-chicken-tray-bake, creamy-tomato-spinach-pasta"><?php echo esc_textarea($related_recipes); ?></textarea>
                    </label>
                    <label>
                        <span><?php echo esc_html(self::ui_text('Sluguri articole recomandate', 'Related article slugs')); ?></span>
                        <textarea name="kepoli_related_article_slugs" rows="3" placeholder="how-to-plan-weeknight-dinners, pantry-staples-for-fast-meals"><?php echo esc_textarea($related_articles); ?></textarea>
                    </label>
                </div>
            </details>

            <details class="kepoli-setup-section kepoli-image-fields" <?php echo $has_image_meta ? ' open' : ''; ?>>
                <summary><?php echo esc_html(self::ui_text('Detalii imagine', 'Image details')); ?></summary>
                <p><?php echo esc_html(self::ui_text('Completeaza aceste campuri pentru imaginea reprezentativa. La salvare, site-ul le aplica pe featured image daca exista una selectata.', 'Fill these fields for the featured image. On save, the site applies them to the selected featured image.')); ?></p>
                <div class="kepoli-post-setup__grid">
                    <label>
                        <span><?php esc_html_e('Alt text', 'kepoli-author-tools'); ?></span>
                        <input type="text" name="kepoli_image_alt" value="<?php echo esc_attr($image_meta['alt']); ?>" placeholder="<?php echo esc_attr(self::ui_text('Descriere scurta si precisa a imaginii.', 'Short, accurate description of the image.')); ?>">
                    </label>
                    <label>
                        <span><?php esc_html_e('Image title', 'kepoli-author-tools'); ?></span>
                        <input type="text" name="kepoli_image_title" value="<?php echo esc_attr($image_meta['title']); ?>" placeholder="<?php echo esc_attr(self::ui_text('Titlu imagine in Media Library.', 'Image title in the Media Library.')); ?>">
                    </label>
                </div>
                <div class="kepoli-post-setup__grid">
                    <label>
                        <span><?php esc_html_e('Caption', 'kepoli-author-tools'); ?></span>
                        <input type="text" name="kepoli_image_caption" value="<?php echo esc_attr($image_meta['caption']); ?>" placeholder="<?php echo esc_attr(self::ui_text('Text optional afisat/subtitrare imagine.', 'Optional text shown as the image caption.')); ?>">
                    </label>
                    <label>
                        <span><?php esc_html_e('Description', 'kepoli-author-tools'); ?></span>
                        <textarea name="kepoli_image_description" rows="2" placeholder="<?php echo esc_attr(self::ui_text('Descriere interna pentru Media Library.', 'Internal description for the Media Library.')); ?>"><?php echo esc_textarea($image_meta['description']); ?></textarea>
                    </label>
                </div>
            </details>

            <details class="kepoli-setup-section kepoli-recipe-fields" data-kepoli-recipe-fields <?php echo $kind === 'recipe' ? ' open' : ''; ?>>
                <summary><?php echo esc_html(self::ui_text('Date reteta', 'Recipe data')); ?></summary>
                <p><?php echo esc_html(self::ui_text('Completeaza aceste campuri pentru retete noi. Ele alimenteaza schema Recipe folosita de Google.', 'Fill these fields for new recipes. They power the Recipe schema used by Google.')); ?></p>
                <div class="kepoli-post-setup__grid kepoli-post-setup__grid--thirds">
                    <label>
                        <span><?php echo esc_html(self::ui_text('Portii', 'Servings')); ?></span>
                        <input type="text" name="kepoli_recipe_servings" value="<?php echo esc_attr($recipe['servings']); ?>" placeholder="<?php echo esc_attr(self::ui_text('4 portii', '4 servings')); ?>">
                    </label>
                    <label>
                        <span><?php echo esc_html(self::ui_text('Pregatire minute', 'Prep minutes')); ?></span>
                        <input type="number" min="0" step="1" name="kepoli_recipe_prep_minutes" value="<?php echo esc_attr($recipe['prep_minutes']); ?>">
                    </label>
                    <label>
                        <span><?php echo esc_html(self::ui_text('Gatire minute', 'Cook minutes')); ?></span>
                        <input type="number" min="0" step="1" name="kepoli_recipe_cook_minutes" value="<?php echo esc_attr($recipe['cook_minutes']); ?>">
                    </label>
                    <label>
                        <span><?php echo esc_html(self::ui_text('Total minute', 'Total minutes')); ?></span>
                        <input type="number" min="0" step="1" name="kepoli_recipe_total_minutes" value="<?php echo esc_attr($recipe['total_minutes']); ?>">
                    </label>
                </div>
                <div class="kepoli-post-setup__grid">
                    <label>
                        <span><?php echo esc_html(self::ui_text('Ingrediente, cate unul pe linie', 'Ingredients, one per line')); ?></span>
                        <textarea name="kepoli_recipe_ingredients" rows="6"><?php echo esc_textarea(implode("\n", $recipe['ingredients'])); ?></textarea>
                    </label>
                    <label>
                        <span><?php echo esc_html(self::ui_text('Pasi, cate unul pe linie', 'Steps, one per line')); ?></span>
                        <textarea name="kepoli_recipe_steps" rows="6"><?php echo esc_textarea(implode("\n", $recipe['steps'])); ?></textarea>
                    </label>
                </div>
            </details>

            <details class="kepoli-editor-checklist" data-kepoli-editor-checklist>
                <summary class="kepoli-editor-checklist__toggle">
                    <span class="kepoli-editor-checklist__title"><?php echo esc_html(self::ui_text('Checklist editorial', 'Editorial checklist')); ?></span>
                    <span class="kepoli-editor-checklist__summary" data-kepoli-checklist-summary></span>
                </summary>
                <p class="kepoli-editor-checklist__intro"><?php echo esc_html(self::ui_text('Deschide lista doar daca vrei sa vezi exact ce mai lipseste.', 'Open the list only when you want to see exactly what is still missing.')); ?></p>
                <ul class="kepoli-editor-checklist__items">
                    <li data-kepoli-check="title"><?php echo esc_html(self::ui_text('Titlu clar', 'Clear title')); ?></li>
                    <li data-kepoli-check="content"><?php echo esc_html(self::ui_text('Continut suficient', 'Enough content')); ?></li>
                    <li data-kepoli-check="excerpt"><?php echo esc_html(self::ui_text('Excerpt completat', 'Excerpt filled')); ?></li>
                    <li data-kepoli-check="meta"><?php echo esc_html(self::ui_text('Meta description completata', 'Meta description filled')); ?></li>
                    <li data-kepoli-check="language"><?php echo esc_html(self::ui_text('Limba coerenta', 'Consistent language')); ?></li>
                    <li data-kepoli-check="slug"><?php echo esc_html(self::ui_text('Slug curat', 'Clean slug')); ?></li>
                    <li data-kepoli-check="featuredImage"><?php echo esc_html(self::ui_text('Imagine reprezentativa setata', 'Featured image set')); ?></li>
                    <li data-kepoli-check="imageAlt"><?php echo esc_html(self::ui_text('Alt text pentru imagine', 'Image alt text')); ?></li>
                    <li data-kepoli-check="related"><?php echo esc_html(self::ui_text('Linkuri interne pregatite', 'Internal links ready')); ?></li>
                    <li data-kepoli-check="recipe"><?php echo esc_html(self::ui_text('Schema reteta completata', 'Recipe schema filled')); ?></li>
                </ul>
            </details>
        </div>
        <?php
    }

    public static function save_post_setup(int $post_id, WP_Post $post, bool $update): void
    {
        unset($update);

        if (self::$is_updating_post) {
            return;
        }

        if (!isset($_POST['kepoli_author_tools_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['kepoli_author_tools_nonce'])), 'kepoli_author_tools_save')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if ($post->post_type !== 'post' || !current_user_can('edit_post', $post_id)) {
            return;
        }

        $kind = isset($_POST['kepoli_post_kind']) ? sanitize_key(wp_unslash((string) $_POST['kepoli_post_kind'])) : 'recipe';
        $kind = in_array($kind, ['recipe', 'article'], true) ? $kind : 'recipe';
        update_post_meta($post_id, '_kepoli_post_kind', $kind);
        $auto_split_parts = isset($_POST['kepoli_auto_split_parts']) ? (int) wp_unslash((string) $_POST['kepoli_auto_split_parts']) : -1;
        $auto_split_parts = in_array($auto_split_parts, [-1, 0, 2, 3], true) ? $auto_split_parts : -1;
        update_post_meta($post_id, '_kepoli_auto_split_parts', $auto_split_parts);
        self::maybe_clean_post_slug($post_id, $post);
        self::maybe_normalize_content_structure($post_id, $post);

        self::save_post_excerpt($post_id, $post);
        self::save_text_meta($post_id, '_kepoli_seo_title', 'kepoli_seo_title', 58);
        self::save_meta_description($post_id, $post);
        self::maybe_apply_suggested_category($post_id, $kind);
        self::maybe_clean_post_tags($post_id);

        $related_recipes = self::posted_slugs('kepoli_related_recipe_slugs');
        $related_articles = self::posted_slugs('kepoli_related_article_slugs');

        if (!$related_recipes && !$related_articles) {
            $suggested_related = self::suggest_related_slugs($post_id, $kind, $post);
            $related_recipes = $suggested_related['recipes'];
            $related_articles = $suggested_related['articles'];
        }

        update_post_meta($post_id, '_kepoli_related_recipe_slugs', $related_recipes);
        update_post_meta($post_id, '_kepoli_related_article_slugs', $related_articles);
        update_post_meta($post_id, '_kepoli_related_slugs', array_values(array_unique(array_merge($related_recipes, $related_articles))));

        if ($kind === 'recipe') {
            self::save_recipe_json($post_id, $post);
            self::maybe_remove_recipe_detail_duplicates($post_id, $post);
            self::maybe_add_recipe_faq($post_id, $post);
        } else {
            delete_post_meta($post_id, '_kepoli_recipe_json');
        }

        self::maybe_add_internal_links_to_content($post_id, $post, $kind, $related_recipes, $related_articles);
        self::maybe_apply_auto_split($post_id, $post, $auto_split_parts);
        self::save_featured_image_meta($post_id);
    }

    public static function add_post_list_columns(array $columns): array
    {
        $updated = [];

        foreach ($columns as $key => $label) {
            $updated[$key] = $label;

            if ($key === 'title') {
                $updated['kepoli_kind'] = self::ui_text('Tip continut', 'Content type');
                $updated['kepoli_readiness'] = __('Setup', 'kepoli-author-tools');
            }
        }

        return $updated;
    }

    public static function render_post_list_column(string $column, int $post_id): void
    {
        if ($column === 'kepoli_kind') {
            $kind = self::post_kind($post_id);
            $label = $kind === 'article' ? self::ui_text('Articol', 'Article') : self::ui_text('Reteta', 'Recipe');

            echo '<span class="kepoli-status-pill kepoli-status-pill--' . esc_attr($kind) . '">' . esc_html($label) . '</span>';
            return;
        }

        if ($column === 'kepoli_readiness') {
            $missing = self::post_missing_items($post_id);

            if (!$missing) {
                echo '<span class="kepoli-status-pill kepoli-status-pill--ready">' . esc_html(self::ui_text('Complet', 'Complete')) . '</span>';
                return;
            }

            echo '<span class="kepoli-status-pill kepoli-status-pill--needs">' . esc_html(self::ui_text('De completat', 'Needs work')) . '</span>';
            echo '<span class="kepoli-admin-note">' . esc_html(implode(', ', $missing)) . '</span>';
        }
    }

    public static function render_post_kind_filter(string $post_type): void
    {
        if ($post_type !== 'post') {
            return;
        }

        $selected = isset($_GET['kepoli_post_kind_filter']) ? sanitize_key(wp_unslash((string) $_GET['kepoli_post_kind_filter'])) : '';
        ?>
        <label class="screen-reader-text" for="kepoli-post-kind-filter"><?php echo esc_html(self::ui_text('Filtreaza dupa tip continut', 'Filter by content type')); ?></label>
        <select id="kepoli-post-kind-filter" name="kepoli_post_kind_filter">
            <option value=""><?php echo esc_html(self::ui_text('Toate tipurile', 'All types')); ?></option>
            <option value="recipe" <?php selected($selected, 'recipe'); ?>><?php echo esc_html(self::ui_text('Retete', 'Recipes')); ?></option>
            <option value="article" <?php selected($selected, 'article'); ?>><?php echo esc_html(self::ui_text('Articole', 'Articles')); ?></option>
        </select>
        <?php
    }

    public static function filter_posts_by_kind(WP_Query $query): void
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $post_type = $query->get('post_type');
        if ($post_type !== 'post' && $post_type !== '') {
            return;
        }

        $selected = isset($_GET['kepoli_post_kind_filter']) ? sanitize_key(wp_unslash((string) $_GET['kepoli_post_kind_filter'])) : '';
        if (!in_array($selected, ['recipe', 'article'], true)) {
            return;
        }

        $meta_query = (array) $query->get('meta_query');
        $meta_query[] = [
            'key' => '_kepoli_post_kind',
            'value' => $selected,
            'compare' => '=',
        ];

        $query->set('meta_query', $meta_query);
    }

    public static function remove_template_prompts_from_content(string $content): string
    {
        if (is_admin() || $content === '') {
            return $content;
        }

        foreach (self::TEMPLATE_PROMPTS as $prompt) {
            $quoted = preg_quote($prompt, '/');
            $content = (string) preg_replace('/<p>\s*' . $quoted . '\s*<\/p>/iu', '', $content);
            $content = (string) preg_replace('/<li>\s*' . $quoted . '\s*<\/li>/iu', '', $content);
        }

        $content = (string) preg_replace('/<ul>\s*<\/ul>/i', '', $content);
        $content = (string) preg_replace('/<ol>\s*<\/ol>/i', '', $content);

        return $content;
    }

    public static function remove_template_prompts_from_excerpt(string $excerpt, ?WP_Post $post = null): string
    {
        if ($excerpt === '') {
            return $excerpt;
        }

        $clean = self::remove_template_prompt_text($excerpt);

        if (($clean === '' || self::word_count($clean) < 8) && $post instanceof WP_Post) {
            $clean = self::sentence_limit((string) $post->post_content, 220, 95);
        }

        return self::word_count($clean) < 8 ? '' : $clean;
    }

    private static function is_post_editor_screen(): bool
    {
        if (!is_admin()) {
            return false;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && $screen->post_type) {
            return $screen->post_type === 'post';
        }

        return self::current_post_type() === 'post';
    }

    private static function current_post_type(): string
    {
        $post_type = '';

        if (isset($_GET['post_type'])) {
            $post_type = sanitize_key(wp_unslash((string) $_GET['post_type']));
        }

        if ($post_type === '' && isset($_GET['post'])) {
            $post = get_post((int) $_GET['post']);
            $post_type = $post ? $post->post_type : '';
        }

        if ($post_type === '' && isset($GLOBALS['typenow'])) {
            $post_type = sanitize_key((string) $GLOBALS['typenow']);
        }

        return $post_type !== '' ? $post_type : 'post';
    }

    private static function current_post_id(): int
    {
        if (isset($_GET['post'])) {
            return absint(wp_unslash((string) $_GET['post']));
        }

        return 0;
    }

    private static function related_posts_payload(int $current_post_id): array
    {
        $usage_counts = self::related_slug_usage_counts($current_post_id);
        $query = new WP_Query([
            'post_type' => 'post',
            'post_status' => ['publish', 'draft', 'pending', 'future'],
            'posts_per_page' => 120,
            'post__not_in' => $current_post_id ? [$current_post_id] : [],
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
        ]);

        $items = [];
        foreach ($query->posts as $post_id) {
            $post_id = (int) $post_id;
            $categories = wp_get_post_categories($post_id, ['fields' => 'names']);
            $tags = wp_get_post_tags($post_id, ['fields' => 'names']);

            $items[] = [
                'id' => $post_id,
                'slug' => get_post_field('post_name', $post_id),
                'title' => get_the_title($post_id),
                'kind' => self::post_kind($post_id),
                'excerpt' => wp_strip_all_tags(get_the_excerpt($post_id)),
                'categories' => is_array($categories) ? array_values($categories) : [],
                'tags' => is_array($tags) ? array_values($tags) : [],
                'linkUsage' => (int) ($usage_counts[(string) get_post_field('post_name', $post_id)] ?? 0),
            ];
        }

        return $items;
    }

    private static function related_slug_usage_counts(int $exclude_post_id = 0): array
    {
        $query = new WP_Query([
            'post_type' => 'post',
            'post_status' => ['publish', 'draft', 'pending', 'future'],
            'posts_per_page' => 250,
            'post__not_in' => $exclude_post_id ? [$exclude_post_id] : [],
            'fields' => 'ids',
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        $counts = [];

        foreach ($query->posts as $post_id) {
            $post_id = (int) $post_id;
            $slugs = get_post_meta($post_id, '_kepoli_related_slugs', true);
            $slugs = is_array($slugs) ? $slugs : [];

            foreach ($slugs as $slug) {
                $slug = sanitize_title((string) $slug);
                if ($slug === '') {
                    continue;
                }

                $counts[$slug] = (int) ($counts[$slug] ?? 0) + 1;
            }
        }

        return $counts;
    }

    private static function article_category_id(): int
    {
        foreach (self::article_category_slugs() as $slug) {
            $term = get_term_by('slug', $slug, 'category');
            if ($term instanceof WP_Term) {
                return (int) $term->term_id;
            }
        }

        $name = self::content_text('Articole', 'Guides');
        $slug = self::article_category_slugs()[0] ?? 'guides';
        $created = wp_insert_term($name, 'category', ['slug' => $slug]);

        if (is_wp_error($created)) {
            $fallback = get_term_by('slug', 'guides', 'category') ?: get_term_by('slug', 'articles', 'category');
            return $fallback instanceof WP_Term ? (int) $fallback->term_id : 0;
        }

        return (int) ($created['term_id'] ?? 0);
    }

    private static function is_article_category_term(WP_Term $term): bool
    {
        if (in_array((string) $term->slug, self::article_category_slugs(), true)) {
            return true;
        }

        $label = strtolower((string) $term->name . ' ' . (string) $term->description);
        return str_contains($label, 'article') || str_contains($label, 'guide') || str_contains($label, 'articol') || str_contains($label, 'ghid');
    }

    private static function posted_category_ids(): array
    {
        if (!isset($_POST['post_category'])) {
            return [];
        }

        $posted = wp_unslash($_POST['post_category']);
        if (!is_array($posted)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('absint', $posted))));
    }

    private static function maybe_apply_suggested_category(int $post_id, string $kind): void
    {
        $selected_ids = self::posted_category_ids();
        $valid_selected_ids = [];
        foreach ($selected_ids as $term_id) {
            $term = get_term($term_id, 'category');
            if ($term instanceof WP_Term && (int) $term->term_id !== 1) {
                $valid_selected_ids[] = (int) $term->term_id;
            }
        }

        if ($valid_selected_ids !== []) {
            return;
        }

        if ($kind === 'article') {
            $article_category_id = self::article_category_id();
            if ($article_category_id > 0) {
                wp_set_post_categories($post_id, [$article_category_id], false);
            }
            return;
        }

        $current_terms = get_the_category($post_id);
        foreach ($current_terms as $term) {
            if ($term instanceof WP_Term && !self::is_article_category_term($term) && (int) $term->term_id !== 1) {
                return;
            }
        }

        $suggested_category_id = self::suggest_recipe_category_id($post_id);
        if ($suggested_category_id > 0) {
            wp_set_post_categories($post_id, [$suggested_category_id], false);
        }
    }

    private static function suggest_recipe_category_id(int $post_id): int
    {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post) {
            return 0;
        }

        $terms = get_terms([
            'taxonomy' => 'category',
            'hide_empty' => false,
        ]);

        if (!is_array($terms)) {
            return 0;
        }

        $text = strtolower(self::plain_text($post->post_title . ' ' . $post->post_content));
        $keyword_map = [
            'quick-recipes' => ['quick', 'fast', 'easy', 'weeknight', 'one pan', 'one-pot', 'skillet', 'pasta', 'rice'],
            'main-dishes' => ['main', 'dinner', 'lunch', 'chicken', 'salmon', 'turkey', 'beef', 'burger', 'stew', 'pizza'],
            'desserts' => ['dessert', 'cake', 'cookie', 'cookies', 'crepe', 'pancake', 'chocolate', 'strawberry', 'sweet'],
            'seasonal-cooking' => ['seasonal', 'fresh', 'summer', 'winter', 'spring', 'autumn', 'herb', 'vegetable', 'salad'],
        ];
        $best_id = 0;
        $best_score = 0;

        foreach ($terms as $term) {
            if (!$term instanceof WP_Term || (int) $term->term_id === 1 || self::is_article_category_term($term)) {
                continue;
            }

            $score = 0;
            $keywords = $keyword_map[(string) $term->slug] ?? [];
            $keywords[] = (string) $term->slug;
            $keywords[] = (string) $term->name;

            foreach ($keywords as $keyword) {
                $keyword = strtolower(self::plain_text($keyword));
                if ($keyword !== '' && str_contains($text, $keyword)) {
                    $score += 4;
                }
            }

            if ($score > $best_score) {
                $best_score = $score;
                $best_id = (int) $term->term_id;
            }
        }

        return $best_score > 0 ? $best_id : 0;
    }

    private static function maybe_clean_post_tags(int $post_id): void
    {
        $posted_tags = [];
        if (isset($_POST['tax_input']) && is_array($_POST['tax_input'])) {
            $tax_input = wp_unslash($_POST['tax_input']);
            if (is_array($tax_input) && isset($tax_input['post_tag'])) {
                $raw = $tax_input['post_tag'];
                $posted_tags = is_array($raw) ? $raw : explode(',', (string) $raw);
            }
        }

        if (!$posted_tags) {
            $existing = wp_get_post_terms($post_id, 'post_tag', ['fields' => 'names']);
            $posted_tags = is_array($existing) ? $existing : [];
        }

        wp_set_post_terms($post_id, self::clean_tag_list($posted_tags), 'post_tag', false);
    }

    private static function category_payload(): array
    {
        $terms = get_terms([
            'taxonomy' => 'category',
            'hide_empty' => false,
        ]);

        if (!is_array($terms)) {
            return [];
        }

        $items = [];
        foreach ($terms as $term) {
            if (!$term instanceof WP_Term || (int) $term->term_id === 1) {
                continue;
            }

            $items[] = [
                'id' => (int) $term->term_id,
                'slug' => (string) $term->slug,
                'name' => (string) $term->name,
                'description' => (string) $term->description,
                'isArticle' => self::is_article_category_term($term),
            ];
        }

        return $items;
    }

    private static function featured_image_meta(int $post_id): array
    {
        $planned = self::planned_image_meta($post_id);
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if (!$thumbnail_id) {
            return $planned;
        }

        $attachment = get_post($thumbnail_id);

        return [
            'alt' => (string) get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true) ?: $planned['alt'],
            'title' => ($attachment ? $attachment->post_title : '') ?: $planned['title'],
            'caption' => ($attachment ? $attachment->post_excerpt : '') ?: $planned['caption'],
            'description' => ($attachment ? $attachment->post_content : '') ?: $planned['description'],
        ];
    }

    private static function planned_image_meta(int $post_id): array
    {
        return [
            'alt' => (string) get_post_meta($post_id, '_kepoli_image_plan_alt', true),
            'title' => (string) get_post_meta($post_id, '_kepoli_image_plan_title', true),
            'caption' => (string) get_post_meta($post_id, '_kepoli_image_plan_caption', true),
            'description' => (string) get_post_meta($post_id, '_kepoli_image_plan_description', true),
        ];
    }

    private static function save_featured_image_meta(int $post_id): void
    {
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if (!$thumbnail_id && isset($_POST['_thumbnail_id'])) {
            $posted_thumbnail_id = absint(wp_unslash((string) $_POST['_thumbnail_id']));
            $thumbnail_id = $posted_thumbnail_id > 0 ? $posted_thumbnail_id : 0;
        }

        if (!$thumbnail_id) {
            return;
        }

        $existing = self::attachment_image_meta($thumbnail_id);
        $generated = self::generated_image_meta($post_id);
        $alt = isset($_POST['kepoli_image_alt']) ? sanitize_text_field(wp_unslash((string) $_POST['kepoli_image_alt'])) : '';
        $title = isset($_POST['kepoli_image_title']) ? sanitize_text_field(wp_unslash((string) $_POST['kepoli_image_title'])) : '';
        $caption = isset($_POST['kepoli_image_caption']) ? sanitize_text_field(wp_unslash((string) $_POST['kepoli_image_caption'])) : '';
        $description = isset($_POST['kepoli_image_description']) ? sanitize_textarea_field(wp_unslash((string) $_POST['kepoli_image_description'])) : '';

        $alt = $alt !== '' ? $alt : ($existing['alt'] !== '' ? $existing['alt'] : $generated['alt']);
        $title = $title !== '' ? $title : ($existing['title'] !== '' ? $existing['title'] : $generated['title']);
        $caption = $caption !== '' ? $caption : ($existing['caption'] !== '' ? $existing['caption'] : $generated['caption']);
        $description = $description !== '' ? $description : ($existing['description'] !== '' ? $existing['description'] : $generated['description']);

        if ($alt !== '') {
            update_post_meta($thumbnail_id, '_wp_attachment_image_alt', self::limit_text($alt, 160));
        }

        $attachment_update = ['ID' => $thumbnail_id];
        if ($title !== '') {
            $attachment_update['post_title'] = self::limit_text($title, 90);
        }
        if ($caption !== '') {
            $attachment_update['post_excerpt'] = self::limit_text($caption, 180);
        }
        if ($description !== '') {
            $attachment_update['post_content'] = self::limit_text($description, 320);
        }

        if (count($attachment_update) > 1) {
            wp_update_post(wp_slash($attachment_update), true);
        }
    }

    private static function attachment_image_meta(int $attachment_id): array
    {
        $attachment = get_post($attachment_id);

        return [
            'alt' => (string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
            'title' => $attachment ? $attachment->post_title : '',
            'caption' => $attachment ? $attachment->post_excerpt : '',
            'description' => $attachment ? $attachment->post_content : '',
        ];
    }

    private static function generated_image_meta(int $post_id): array
    {
        $post = get_post($post_id);
        $title = $post ? trim((string) $post->post_title) : '';
        $title = $title !== '' ? $title : sprintf(self::content_text('Reteta %s', '%s recipe'), self::site_name());
        $kind = self::post_kind($post_id);
        $prefix = $kind === 'article'
            ? self::content_text('Imagine editoriala pentru', 'Editorial image for')
            : self::content_text('Fotografie culinara pentru', 'Food photo for');
        $published_on = sprintf(self::content_text('publicata pe %s.', 'published on %s.'), self::site_name());

        return [
            'alt' => self::sentence_limit($prefix . ' ' . $title . ', ' . $published_on, 150),
            'title' => self::limit_text($title, 90),
            'caption' => self::sentence_limit(sprintf(self::content_text('%1$s pe %2$s.', '%1$s on %2$s.'), $title, self::site_name()), 120),
            'description' => self::sentence_limit(sprintf(self::content_text('Imagine reprezentativa pentru %1$s, folosita in articolul culinar %2$s.', 'Representative image for %1$s, used in a %2$s food article.'), $title, self::site_name()), 220),
        ];
    }

    private static function post_kind(int $post_id): string
    {
        $kind = (string) get_post_meta($post_id, '_kepoli_post_kind', true);
        return in_array($kind, ['recipe', 'article'], true) ? $kind : 'recipe';
    }

    private static function post_missing_items(int $post_id): array
    {
        $missing = [];
        $kind = self::post_kind($post_id);
        $related_recipes = get_post_meta($post_id, '_kepoli_related_recipe_slugs', true);
        $related_articles = get_post_meta($post_id, '_kepoli_related_article_slugs', true);
        $related_count = (is_array($related_recipes) ? count($related_recipes) : 0) + (is_array($related_articles) ? count($related_articles) : 0);
        $post = get_post($post_id);
        $has_internal_links = $post instanceof WP_Post
            ? self::content_has_internal_links((string) $post->post_content, $post_id)
            : false;
        $language_consistent = $post instanceof WP_Post
            ? self::is_post_language_consistent($post_id, $post)
            : true;

        if ((string) get_post_meta($post_id, '_kepoli_meta_description', true) === '') {
            $missing[] = 'meta';
        }

        if (!has_excerpt($post_id)) {
            $missing[] = 'excerpt';
        }

        if (!$language_consistent) {
            $missing[] = 'language';
        }

        $thumbnail_id = get_post_thumbnail_id($post_id);
        if (!$thumbnail_id) {
            $missing[] = 'image';
        } elseif ((string) get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true) === '') {
            $missing[] = 'image meta';
        }

        if (!$has_internal_links && $related_count === 0) {
            $missing[] = 'internal links';
        }

        if ($kind === 'recipe') {
            $recipe = self::recipe_data($post_id);
            if (!$recipe['ingredients'] || !$recipe['steps'] || $recipe['servings'] === '') {
                $missing[] = 'recipe schema';
            }
        }

        return $missing;
    }

    private static function array_meta_to_text(int $post_id, string $key): string
    {
        $value = get_post_meta($post_id, $key, true);
        return is_array($value) ? implode(', ', array_map('sanitize_title', $value)) : '';
    }

    private static function recipe_data(int $post_id): array
    {
        $json = (string) get_post_meta($post_id, '_kepoli_recipe_json', true);
        $data = json_decode($json, true);
        $data = is_array($data) ? $data : [];

        return [
            'servings' => isset($data['servings']) ? (string) $data['servings'] : '',
            'prep_minutes' => self::iso_to_minutes((string) ($data['prep_iso'] ?? '')),
            'cook_minutes' => self::iso_to_minutes((string) ($data['cook_iso'] ?? '')),
            'total_minutes' => self::iso_to_minutes((string) ($data['total_iso'] ?? '')),
            'ingredients' => isset($data['ingredients']) && is_array($data['ingredients']) ? $data['ingredients'] : [],
            'steps' => isset($data['steps']) && is_array($data['steps']) ? $data['steps'] : [],
        ];
    }

    private static function recipe_servings_has_value(string $servings): bool
    {
        $servings = trim(sanitize_text_field($servings));
        if ($servings === '') {
            return false;
        }

        if (!preg_match('/\d+/', $servings, $matches)) {
            return false;
        }

        return isset($matches[0]) && (int) $matches[0] > 0;
    }

    private static function save_meta_description(int $post_id, WP_Post $post): void
    {
        $value = isset($_POST['kepoli_meta_description']) ? sanitize_textarea_field(wp_unslash((string) $_POST['kepoli_meta_description'])) : '';
        $value = self::remove_template_prompt_text($value);
        $value = $value !== '' ? $value : self::generate_meta_description($post);
        $value = self::limit_text(self::plain_text($value), 180);
        if (self::word_count($value) < 8) {
            $value = self::limit_text(self::plain_text(self::generate_meta_description($post)), 180);
        }

        if ($value === '') {
            delete_post_meta($post_id, '_kepoli_meta_description');
            return;
        }

        update_post_meta($post_id, '_kepoli_meta_description', $value);
    }

    private static function save_post_excerpt(int $post_id, WP_Post $post): void
    {
        $value = isset($_POST['kepoli_post_excerpt']) ? sanitize_textarea_field(wp_unslash((string) $_POST['kepoli_post_excerpt'])) : '';
        $value = self::remove_template_prompt_text($value);
        $value = $value !== '' ? $value : trim((string) $post->post_excerpt);
        $value = self::remove_template_prompt_text($value);
        $value = $value !== '' ? $value : self::generate_post_excerpt($post);
        $value = self::limit_text(self::plain_text($value), 260);
        if (self::word_count($value) < 8) {
            $value = self::limit_text(self::plain_text(self::generate_post_excerpt($post)), 260);
        }

        if ($value === '' || $value === (string) $post->post_excerpt) {
            return;
        }

        self::$is_updating_post = true;
        wp_update_post([
            'ID' => $post_id,
            'post_excerpt' => $value,
        ]);
        self::$is_updating_post = false;
        $post->post_excerpt = $value;
    }

    private static function maybe_clean_post_slug(int $post_id, WP_Post $post): void
    {
        $title = trim((string) $post->post_title);
        if ($title === '') {
            return;
        }

        $current_slug = (string) $post->post_name;
        $default_slug = sanitize_title($title);
        $clean_slug = self::clean_slug_from_title($title);

        if ($clean_slug === '' || $clean_slug === $current_slug) {
            return;
        }

        if ($current_slug !== '' && $current_slug !== $default_slug) {
            return;
        }

        self::$is_updating_post = true;
        wp_update_post([
            'ID' => $post_id,
            'post_name' => $clean_slug,
        ]);
        self::$is_updating_post = false;
        $post->post_name = $clean_slug;
    }

    private static function maybe_normalize_content_structure(int $post_id, WP_Post $post): void
    {
        $content = (string) $post->post_content;
        if ($content === '') {
            return;
        }

        $normalized = self::normalize_content_structure($content);
        if ($normalized === '' || $normalized === $content) {
            return;
        }

        self::$is_updating_post = true;
        wp_update_post([
            'ID' => $post_id,
            'post_content' => $normalized,
        ]);
        self::$is_updating_post = false;
        $post->post_content = $normalized;
    }

    private static function maybe_add_internal_links_to_content(int $post_id, WP_Post $post, string $kind, array $related_recipes, array $related_articles): void
    {
        $content = (string) $post->post_content;
        $clean_content = self::strip_auto_internal_links_block($content);
        $has_existing_links = self::content_has_internal_links($clean_content, $post_id);

        if ($has_existing_links) {
            if ($clean_content !== $content) {
                self::update_post_content($post_id, $clean_content);
            }
            return;
        }

        $suggested_posts = self::auto_internal_link_posts($kind, $related_recipes, $related_articles);
        if (!$suggested_posts) {
            if ($clean_content !== $content) {
                self::update_post_content($post_id, $clean_content);
            }
            return;
        }

        $updated_content = self::place_auto_internal_links_in_content($clean_content, $suggested_posts);

        if ($updated_content !== $content) {
            self::update_post_content($post_id, $updated_content);
        }
    }

    private static function maybe_add_recipe_faq(int $post_id, WP_Post $post): void
    {
        $content = (string) $post->post_content;
        $clean_content = self::strip_auto_faq_block($content);

        if (self::content_has_faq_section($clean_content)) {
            if ($clean_content !== $content) {
                self::update_post_content($post_id, $clean_content);
            }
            return;
        }

        $faq_block = self::build_recipe_faq_block($post_id, $clean_content);
        if ($faq_block === '') {
            if ($clean_content !== $content) {
                self::update_post_content($post_id, $clean_content);
            }
            return;
        }

        $updated_content = rtrim($clean_content) . "\n\n" . $faq_block;
        if ($updated_content !== $content) {
            self::update_post_content($post_id, $updated_content);
        }
    }

    private static function maybe_remove_recipe_detail_duplicates(int $post_id, WP_Post $post): void
    {
        $content = (string) $post->post_content;
        if ($content === '') {
            return;
        }

        $clean = self::remove_recipe_detail_duplicates_from_content($content);
        if ($clean === '' || $clean === $content) {
            return;
        }

        self::update_post_content($post_id, $clean);
        $post->post_content = $clean;
    }

    private static function remove_recipe_detail_duplicates_from_content(string $content): string
    {
        if ($content === '') {
            return '';
        }

        if (!preg_match('/<[^>]+>/', $content) || !class_exists('DOMDocument')) {
            return self::remove_recipe_detail_duplicates_from_lines($content);
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<div id="food-blog-recipe-detail-cleanup-root">' . $content . '</div>';

        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = $document->getElementById('food-blog-recipe-detail-cleanup-root');
        if (!$root) {
            return $content;
        }

        $nodes = [];
        foreach ($root->childNodes as $child) {
            $nodes[] = $child;
        }

        $removing_detail_section = false;

        foreach ($nodes as $node) {
            if ($node->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            /** @var DOMElement $node */
            $tag = strtolower((string) $node->nodeName);
            $plain = self::plain_text($document->saveHTML($node));
            $heading_like = preg_match('/^h[1-6]$/', $tag) || ($tag === 'p' && self::is_outline_heading($plain));

            if ($heading_like) {
                if (self::is_recipe_detail_heading($plain)) {
                    $removing_detail_section = true;
                    if ($node->parentNode) {
                        $node->parentNode->removeChild($node);
                    }
                    continue;
                }

                if ($removing_detail_section) {
                    $removing_detail_section = false;
                }
            }

            if ($removing_detail_section) {
                if ($node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
                continue;
            }

            if (in_array($tag, ['p', 'li'], true) && self::is_recipe_meta_line($plain) && $node->parentNode) {
                $node->parentNode->removeChild($node);
            }
        }

        $output = [];
        foreach ($root->childNodes as $child) {
            $html = trim((string) $document->saveHTML($child));
            if ($html !== '') {
                $output[] = $html;
            }
        }

        return trim(implode("\n", $output));
    }

    private static function remove_recipe_detail_duplicates_from_lines(string $content): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $output = [];
        $removing_detail_section = false;

        foreach ($lines as $line) {
            $plain = trim(self::plain_text((string) $line));

            if ($plain === '') {
                if (!$removing_detail_section) {
                    $output[] = $line;
                }
                continue;
            }

            if (self::is_outline_heading($plain)) {
                if (self::is_recipe_detail_heading($plain)) {
                    $removing_detail_section = true;
                    continue;
                }

                $removing_detail_section = false;
            }

            if ($removing_detail_section || self::is_recipe_meta_line($plain)) {
                continue;
            }

            $output[] = $line;
        }

        return trim((string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $output)));
    }

    private static function is_recipe_detail_heading(string $text): bool
    {
        return in_array(self::normalized_heading($text), [
            self::normalized_heading('Detalii despre reteta'),
            self::normalized_heading('Recipe details'),
            self::normalized_heading('Details'),
        ], true);
    }

    private static function is_recipe_meta_line(string $text): bool
    {
        $normalized = self::normalized_heading($text);
        return $normalized !== '' && (bool) preg_match('/^(?:timp|prep|preparation|cook|cooking|bake|baking|total|servings?|serves|makes|yield|portii|portie|persoane|nivel|difficulty)\b/u', $normalized);
    }

    private static function maybe_apply_auto_split(int $post_id, WP_Post $post, int $parts): void
    {
        if (!in_array($parts, [-1, 2, 3], true)) {
            return;
        }

        $content = (string) get_post_field('post_content', $post_id);
        if ($content === '') {
            $content = (string) $post->post_content;
        }

        if ($content === '' || preg_match('/<!--\s*nextpage\s*-->/i', $content)) {
            return;
        }

        if ($parts === -1) {
            $words = self::word_count($content);
            if ($words >= 1100) {
                $parts = 3;
            } elseif ($words >= 420) {
                $parts = 2;
            } else {
                return;
            }
        }

        $split = self::split_content_into_parts($content, $parts);
        if ($split === '' || $split === $content) {
            return;
        }

        self::update_post_content($post_id, $split);
        $post->post_content = $split;
    }

    private static function generate_post_excerpt(WP_Post $post): string
    {
        $source = self::remove_template_prompt_text(trim((string) $post->post_excerpt));

        if ($source === '') {
            $source = self::remove_template_prompt_text(trim((string) $post->post_content));
        }

        if ($source === '') {
            $source = trim((string) $post->post_title);
        }

        return self::sentence_limit($source, 220, 95);
    }

    private static function generate_meta_description(WP_Post $post): string
    {
        $source = self::remove_template_prompt_text(trim((string) $post->post_excerpt));

        if ($source === '') {
            $source = self::remove_template_prompt_text(trim((string) $post->post_content));
        }

        if ($source === '') {
            $source = trim((string) $post->post_title);
        }

        return self::sentence_limit($source, 155);
    }

    private static function clean_slug_from_title(string $title): string
    {
        $parts = preg_split('/\s+/', self::plain_text($title)) ?: [];
        $stopwords = array_flip([
            'si', 'sau', 'din', 'de', 'la', 'cu', 'pentru', 'despre', 'care', 'este', 'sunt',
            'the', 'and', 'with', 'from', 'into', 'your', 'this', 'that', 'history', 'fascinating',
            'what', 'when', 'where', 'how', 'why', 'guide', 'tips', 'best', 'more',
        ]);
        $kept = [];

        foreach ($parts as $part) {
            $normalized = remove_accents(function_exists('mb_strtolower') ? mb_strtolower($part, 'UTF-8') : strtolower($part));
            $normalized = preg_replace('/[^a-z0-9-]/', '', (string) $normalized);
            if ($normalized === '' || isset($stopwords[$normalized])) {
                continue;
            }

            $kept[] = $normalized;
            if (count($kept) >= 8) {
                break;
            }
        }

        $slug = sanitize_title(implode(' ', $kept));
        if ($slug === '') {
            $slug = sanitize_title($title);
        }

        return $slug;
    }

    private static function normalize_content_structure(string $content): string
    {
        $heading_index = 0;

        $content = (string) preg_replace_callback(
            '/<h([1-6])([^>]*)>(.*?)<\/h\1>/is',
            static function (array $matches) use (&$heading_index): string {
                $attributes = isset($matches[2]) ? (string) $matches[2] : '';
                $inner_html = isset($matches[3]) ? trim((string) $matches[3]) : '';
                $plain = trim(wp_strip_all_tags($inner_html));

                if ($plain === '') {
                    return '';
                }

                $target_level = $heading_index === 0 ? 2 : (((int) ($matches[1] ?? 2)) <= 2 ? 2 : 3);
                $heading_index++;

                return sprintf('<h%1$d%2$s>%3$s</h%1$d>', $target_level, $attributes, $inner_html);
            },
            $content
        );

        $content = (string) preg_replace('/<p>\s*<\/p>/i', '', $content);

        return trim($content);
    }

    private static function split_content_into_parts(string $content, int $parts): string
    {
        $blocks = self::expand_blocks_for_split(self::content_blocks($content), $content, $parts);
        if (count($blocks) < $parts) {
            return $content;
        }

        $preferred = self::preferred_block_break_indexes($blocks);
        $breaks = self::compute_split_breaks($blocks, $parts, $preferred);
        if (!$breaks) {
            return $content;
        }

        $output = [];
        foreach ($blocks as $index => $block) {
            if (in_array($index, $breaks, true)) {
                $output[] = '<!--nextpage-->';
            }
            $output[] = $block;
        }

        return trim(implode("\n\n", $output));
    }

    private static function content_blocks(string $content): array
    {
        $line_blocks = self::line_content_blocks($content);

        if (!class_exists('DOMDocument')) {
            return $line_blocks;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<div id="kepoli-split-root">' . str_replace('<!--nextpage-->', '', $content) . '</div>';

        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = $document->getElementById('kepoli-split-root');
        if (!$root) {
            return $line_blocks;
        }

        $blocks = [];
        foreach ($root->childNodes as $node) {
            if ($node->nodeType === XML_COMMENT_NODE) {
                continue;
            }

            if ($node->nodeType === XML_TEXT_NODE && trim((string) $node->textContent) === '') {
                continue;
            }

            $blocks[] = trim($document->saveHTML($node));
        }

        $blocks = array_values(array_filter($blocks));

        return count($blocks) > 1 ? $blocks : $line_blocks;
    }

    private static function line_content_blocks(string $content): array
    {
        $content = preg_replace('/<!--\s*nextpage\s*-->/i', '', trim($content));
        if (!is_string($content) || $content === '') {
            return [];
        }

        $blocks = preg_split('/\n{2,}/', $content) ?: [];
        $blocks = array_values(array_filter(array_map('trim', $blocks)));
        if (count($blocks) > 1) {
            return $blocks;
        }

        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines)));
        if (count($lines) > 1) {
            return $lines;
        }

        return $blocks;
    }

    private static function expand_blocks_for_split(array $blocks, string $content, int $parts): array
    {
        if (count($blocks) >= $parts) {
            return $blocks;
        }

        $structured_blocks = self::structure_preserving_split_blocks($blocks, $parts);
        if (count($structured_blocks) >= $parts) {
            return $structured_blocks;
        }

        if (!self::can_use_sentence_split_fallback($content)) {
            return $blocks;
        }

        $sentence_blocks = self::sentence_content_blocks($content, $parts);
        return count($sentence_blocks) > $parts ? $sentence_blocks : $blocks;
    }

    private static function can_use_sentence_split_fallback(string $content): bool
    {
        $clean = trim((string) preg_replace('/<!--\s*nextpage\s*-->/i', '', $content));
        if ($clean === '') {
            return false;
        }

        // Sentence chunks are plain text only. Formatted posts must keep their
        // existing paragraphs, headings, lists, and line breaks.
        if (preg_match('/<[^>]+>/', $clean)) {
            return false;
        }

        return !preg_match('/\r\n|\r|\n/', $clean);
    }

    private static function structure_preserving_split_blocks(array $blocks, int $parts): array
    {
        if (!$blocks) {
            return [];
        }

        $total_words = array_sum(array_map([self::class, 'block_word_count'], $blocks));
        $target_words = max(80, (int) ceil($total_words / max(1, $parts * 2)));
        $output = [];

        foreach ($blocks as $block) {
            $split = self::split_single_structured_block($block, $target_words);
            foreach ($split as $piece) {
                $piece = trim($piece);
                if ($piece !== '') {
                    $output[] = $piece;
                }
            }
        }

        return count($output) > count($blocks) ? $output : $blocks;
    }

    private static function split_single_structured_block(string $block, int $target_words): array
    {
        $trimmed = trim($block);
        if ($trimmed === '' || self::is_split_heading_block($trimmed)) {
            return [$block];
        }

        if (preg_match('/^<p\b([^>]*)>(.*)<\/p>$/is', $trimmed, $matches)) {
            $attributes = $matches[1] ?? '';
            $inner = trim((string) ($matches[2] ?? ''));
            $pieces = self::split_paragraph_inner_html($inner, $target_words);

            if (count($pieces) > 1) {
                return array_map(static function (string $piece) use ($attributes): string {
                    return '<p' . $attributes . '>' . $piece . '</p>';
                }, $pieces);
            }
        }

        return [$block];
    }

    private static function split_paragraph_inner_html(string $inner, int $target_words): array
    {
        $inner = trim($inner);
        if ($inner === '') {
            return [];
        }

        $br_parts = preg_split('/<br\s*\/?>/i', $inner) ?: [];
        $br_parts = array_values(array_filter(array_map('trim', $br_parts)));
        if (count($br_parts) > 1) {
            return $br_parts;
        }

        if (preg_match('/<[^>]+>/', $inner)) {
            return [$inner];
        }

        return self::sentence_chunks($inner, $target_words);
    }

    private static function sentence_chunks(string $plain, int $target_words): array
    {
        $sentences = preg_split('/(?<=[.!?])\s+/', trim($plain)) ?: [];
        $sentences = array_values(array_filter(array_map('trim', $sentences)));
        if (count($sentences) < 2) {
            return [$plain];
        }

        $chunks = [];
        $current = [];
        $current_words = 0;

        foreach ($sentences as $sentence) {
            $current[] = $sentence;
            $current_words += self::word_count($sentence);

            if ($current_words >= $target_words) {
                $chunks[] = implode(' ', $current);
                $current = [];
                $current_words = 0;
            }
        }

        if ($current) {
            $chunks[] = implode(' ', $current);
        }

        return count($chunks) > 1 ? $chunks : [$plain];
    }

    private static function sentence_content_blocks(string $content, int $parts): array
    {
        $plain = trim(self::plain_text((string) preg_replace('/<!--\s*nextpage\s*-->/i', ' ', $content)));
        if ($plain === '') {
            return [];
        }

        $sentences = preg_split('/(?<=[.!?])\s+/', $plain) ?: [];
        $sentences = array_values(array_filter(array_map('trim', $sentences)));
        if (count($sentences) <= $parts) {
            $sentences = self::word_chunk_blocks($plain, $parts * 3);
        }

        if (count($sentences) <= $parts) {
            return [];
        }

        $target_words = max(80, (int) ceil(self::word_count($plain) / max(1, $parts * 2)));
        $blocks = [];
        $current = [];
        $current_words = 0;

        foreach ($sentences as $sentence) {
            $sentence_words = self::word_count($sentence);
            $current[] = $sentence;
            $current_words += $sentence_words;

            if ($current_words >= $target_words) {
                $blocks[] = implode(' ', $current);
                $current = [];
                $current_words = 0;
            }
        }

        if ($current) {
            $blocks[] = implode(' ', $current);
        }

        return array_values(array_filter(array_map('trim', $blocks)));
    }

    private static function word_chunk_blocks(string $plain, int $target_chunks): array
    {
        $words = preg_split('/\s+/', trim($plain)) ?: [];
        $words = array_values(array_filter(array_map('trim', $words)));
        if (count($words) < 2) {
            return [];
        }

        $chunk_size = max(80, (int) ceil(count($words) / max(1, $target_chunks)));
        $chunks = array_chunk($words, $chunk_size);
        return array_map(static function (array $chunk): string {
            return implode(' ', $chunk);
        }, $chunks);
    }

    private static function preferred_block_break_indexes(array $blocks): array
    {
        $indexes = [];

        foreach ($blocks as $index => $block) {
            if ($index === 0) {
                continue;
            }

            if (preg_match('/^<h[23]\b/i', $block)) {
                $indexes[] = $index;
            }
        }

        return $indexes;
    }

    private static function is_split_heading_block(string $block): bool
    {
        if (preg_match('/^<h[23]\b/i', $block)) {
            return true;
        }

        $plain = trim(rtrim(self::plain_text($block), ':'));
        return $plain !== '' && self::is_outline_heading($plain);
    }

    private static function is_outline_heading(string $text): bool
    {
        $normalized = self::normalized_heading($text);
        if ($normalized === '') {
            return false;
        }

        foreach (self::TEMPLATE_OUTLINE_LABELS as $label) {
            if ($normalized === self::normalized_heading($label)) {
                return true;
            }
        }

        return self::is_recipe_detail_heading($text);
    }

    private static function block_word_count(string $block): int
    {
        return max(1, self::word_count($block));
    }

    private static function compute_split_breaks(array $blocks, int $parts, array $preferred): array
    {
        $total = count($blocks);
        if ($total < $parts) {
            return [];
        }

        if ($total === $parts) {
            return range(1, $parts - 1);
        }

        $weights = array_map([self::class, 'block_word_count'], $blocks);
        $total_words = array_sum($weights);
        if ($total_words <= 0) {
            return [];
        }

        $preferred_lookup = array_flip($preferred);
        $breaks = [];
        $used = [];
        $min_words_per_part = max(80, (int) floor(($total_words / $parts) * 0.35));

        for ($index = 1; $index < $parts; $index++) {
            $target_words = (int) round(($total_words * $index) / $parts);
            $chosen = 0;
            $best_score = PHP_INT_MAX;
            $running_words = 0;

            for ($candidate = 1; $candidate < $total; $candidate++) {
                $running_words += $weights[$candidate - 1];

                if (isset($used[$candidate])) {
                    continue;
                }

                $previous_break = $breaks ? max($breaks) : 0;
                if ($candidate <= $previous_break) {
                    continue;
                }

                $current_part_words = array_sum(array_slice($weights, $previous_break, $candidate - $previous_break));
                $remaining_words = array_sum(array_slice($weights, $candidate));
                $remaining_parts = $parts - $index;
                if ($current_part_words < $min_words_per_part || $remaining_words < ($min_words_per_part * $remaining_parts)) {
                    continue;
                }

                $score = abs($running_words - $target_words);
                if (isset($preferred_lookup[$candidate])) {
                    $score = max(0, $score - 30);
                }

                if ($score < $best_score) {
                    $best_score = $score;
                    $chosen = $candidate;
                }
            }

            if ($chosen <= 0) {
                $chosen = self::fallback_split_break($weights, $target_words, $breaks ? max($breaks) : 0, $parts - $index);
            }

            $used[$chosen] = true;
            $breaks[] = $chosen;
        }

        sort($breaks);
        return array_values(array_unique($breaks));
    }

    private static function fallback_split_break(array $weights, int $target_words, int $previous_break, int $remaining_parts): int
    {
        $total = count($weights);
        $min_candidate = max($previous_break + 1, 1);
        $max_candidate = max($min_candidate, $total - max(1, $remaining_parts));
        $running_words = 0;
        $chosen = $min_candidate;
        $best_score = PHP_INT_MAX;

        for ($candidate = 1; $candidate < $total; $candidate++) {
            $running_words += (int) ($weights[$candidate - 1] ?? 1);

            if ($candidate < $min_candidate || $candidate > $max_candidate) {
                continue;
            }

            $score = abs($running_words - $target_words);
            if ($score < $best_score) {
                $best_score = $score;
                $chosen = $candidate;
            }
        }

        return max($min_candidate, min($max_candidate, $chosen));
    }

    private static function build_recipe_faq_block(int $post_id, string $content): string
    {
        $recipe = self::recipe_data($post_id);
        $items = [];

        if ($recipe['servings'] !== '') {
            $items[] = [
                'question' => self::content_text('Cate portii ies din reteta?', 'How many servings does this recipe make?'),
                'answer' => sprintf(
                    self::content_text('Reteta este gandita pentru %s.', 'The recipe is designed for %s.'),
                    $recipe['servings']
                ),
            ];
        }

        $time_answer = self::recipe_time_faq_answer($recipe);
        if ($time_answer !== '') {
            $items[] = [
                'question' => self::content_text('Cat dureaza pregatirea?', 'How long does it take?'),
                'answer' => $time_answer,
            ];
        }

        $storage_answer = self::extract_storage_answer($content);
        if ($storage_answer !== '') {
            $items[] = [
                'question' => self::content_text('Cum se pastreaza?', 'How should it be stored?'),
                'answer' => $storage_answer,
            ];
        }

        if (count($items) < 2) {
            return '';
        }

        $html = [self::AUTO_FAQ_START, '<h2>' . esc_html(self::content_text('Intrebari frecvente', 'Frequently asked questions')) . '</h2>'];

        foreach (array_slice($items, 0, 3) as $item) {
            $html[] = '<h3>' . esc_html($item['question']) . '</h3>';
            $html[] = '<p>' . esc_html($item['answer']) . '</p>';
        }

        $html[] = self::AUTO_FAQ_END;

        return implode("\n", $html);
    }

    private static function recipe_time_faq_answer(array $recipe): string
    {
        $prep = (int) ($recipe['prep_minutes'] ?? 0);
        $cook = (int) ($recipe['cook_minutes'] ?? 0);
        $total = $prep + $cook;

        if ($prep > 0 && $cook > 0) {
            return sprintf(
                self::content_text('Ai nevoie de aproximativ %1$d minute pentru pregatire, %2$d minute pentru gatire si cam %3$d minute in total.', 'You need about %1$d minutes for prep, %2$d minutes for cooking, and about %3$d minutes in total.'),
                $prep,
                $cook,
                $total
            );
        }

        if ($total > 0) {
            return sprintf(
                self::content_text('Reteta cere aproximativ %d minute in total.', 'The recipe takes about %d minutes in total.'),
                $total
            );
        }

        return '';
    }

    private static function auto_internal_link_posts(string $kind, array $related_recipes, array $related_articles): array
    {
        $recipe_queue = array_values(array_unique(array_map('sanitize_title', $related_recipes)));
        $article_queue = array_values(array_unique(array_map('sanitize_title', $related_articles)));
        $recipe_posts = self::posts_from_slug_queue($recipe_queue, 2);
        $article_posts = self::posts_from_slug_queue($article_queue, 2);

        if ($kind === 'article' && $recipe_posts && $article_posts) {
            return [$recipe_posts[0], $article_posts[0]];
        }

        if ($kind === 'recipe' && $article_posts) {
            $posts = [$article_posts[0]];
            if ($recipe_posts) {
                $posts[] = $recipe_posts[0];
            } elseif (isset($article_posts[1])) {
                $posts[] = $article_posts[1];
            }

            return array_slice($posts, 0, 2);
        }

        return array_slice(array_merge($recipe_posts, $article_posts), 0, 2);
    }

    private static function suggest_related_slugs(int $post_id, string $kind, WP_Post $post): array
    {
        $source_category_slug = self::primary_category_slug($post_id);
        $usage_counts = self::related_slug_usage_counts($post_id);
        $source_words = self::keywords_from_text(implode(' ', [
            $post->post_title,
            $post->post_excerpt,
            $post->post_content,
            self::primary_category_name($post_id),
        ]));

        $query = new WP_Query([
            'post_type' => 'post',
            'post_status' => ['publish', 'draft', 'pending', 'future'],
            'posts_per_page' => 120,
            'post__not_in' => $post_id ? [$post_id] : [],
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
        ]);

        $candidates = [];
        foreach ($query->posts as $index => $candidate_id) {
            $candidate_id = (int) $candidate_id;
            $slug = (string) get_post_field('post_name', $candidate_id);

            if ($slug === '') {
                continue;
            }

            $candidates[] = [
                'index' => $index,
                'kind' => self::post_kind($candidate_id),
                'slug' => $slug,
                'score' => self::score_related_candidate(
                    $candidate_id,
                    $source_words,
                    $source_category_slug,
                    (int) ($usage_counts[$slug] ?? 0)
                ),
                'title' => (string) get_the_title($candidate_id),
            ];
        }

        usort($candidates, static function (array $a, array $b): int {
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }

            $title_compare = strcasecmp($a['title'], $b['title']);
            return $title_compare !== 0 ? $title_compare : ($a['index'] <=> $b['index']);
        });

        $recipe_limit = $kind === 'article' ? 5 : 3;
        $article_limit = $kind === 'article' ? 2 : 1;
        $recipes = [];
        $articles = [];

        foreach ($candidates as $candidate) {
            if ($candidate['kind'] === 'article') {
                if (count($articles) < $article_limit) {
                    $articles[] = $candidate['slug'];
                }
            } elseif (count($recipes) < $recipe_limit) {
                $recipes[] = $candidate['slug'];
            }

            if (count($recipes) >= $recipe_limit && count($articles) >= $article_limit) {
                break;
            }
        }

        return [
            'recipes' => $recipes,
            'articles' => $articles,
        ];
    }

    private static function score_related_candidate(int $post_id, array $source_words, string $source_category_slug = '', int $usage_count = 0): int
    {
        if (!$source_words) {
            return 0;
        }

        $candidate_words = self::keywords_from_text(self::related_candidate_text($post_id));
        $candidate_lookup = array_flip($candidate_words);
        $candidate_category_slug = self::primary_category_slug($post_id);
        $score = 0;

        foreach ($source_words as $word) {
            if (isset($candidate_lookup[$word])) {
                $score += 3;
                continue;
            }

            foreach ($candidate_words as $candidate_word) {
                if (strpos($candidate_word, $word) !== false || strpos($word, $candidate_word) !== false) {
                    $score += 1;
                    break;
                }
            }
        }

        if ($source_category_slug !== '') {
            if ($candidate_category_slug === $source_category_slug) {
                $score += 12;
            } elseif ($source_category_slug !== 'guides' && $candidate_category_slug !== '' && $candidate_category_slug !== 'guides') {
                $score -= 2;
            }
        }

        if ($usage_count > 0) {
            $score -= min(9, $usage_count * 2);
        }

        return $score;
    }

    private static function is_post_language_consistent(int $post_id, WP_Post $post): bool
    {
        $content_language = self::detect_language(implode(' ', [
            $post->post_title,
            $post->post_excerpt,
            $post->post_content,
        ]));

        if ($content_language === 'unknown') {
            return true;
        }

        $meta_language = self::detect_language((string) get_post_meta($post_id, '_kepoli_meta_description', true));
        $slug_language = self::detect_language(str_replace('-', ' ', (string) $post->post_name));

        if ($meta_language !== 'unknown' && $meta_language !== $content_language) {
            return false;
        }

        if ($slug_language !== 'unknown' && $slug_language !== $content_language) {
            return false;
        }

        return true;
    }

    private static function related_candidate_text(int $post_id): string
    {
        $categories = wp_get_post_categories($post_id, ['fields' => 'names']);
        $tags = wp_get_post_tags($post_id, ['fields' => 'names']);

        return implode(' ', [
            get_the_title($post_id),
            get_post_field('post_excerpt', $post_id),
            get_post_field('post_content', $post_id),
            is_array($categories) ? implode(' ', $categories) : '',
            is_array($tags) ? implode(' ', $tags) : '',
        ]);
    }

    private static function strip_auto_faq_block(string $content): string
    {
        if ($content === '') {
            return '';
        }

        $pattern = '/' . preg_quote(self::AUTO_FAQ_START, '/') . '.*?' . preg_quote(self::AUTO_FAQ_END, '/') . '\s*/is';
        $content = (string) preg_replace($pattern, '', $content);
        return rtrim($content);
    }

    private static function content_has_faq_section(string $content): bool
    {
        $content = self::strip_auto_faq_block($content);
        if ($content === '') {
            return false;
        }

        return (bool) preg_match('/<h[23][^>]*>\s*(?:Intrebari frecvente|Frequently asked questions|FAQs?)\s*<\/h[23]>/iu', $content);
    }

    private static function extract_storage_answer(string $content): string
    {
        if (!preg_match('/<h[23][^>]*>\s*(?:Cum pastrezi|Storage)\s*<\/h[23]>\s*(<p\b[^>]*>.*?<\/p>)/isu', $content, $matches)) {
            return '';
        }

        $text = self::sentence_limit((string) ($matches[1] ?? ''), 220, 60);
        return $text;
    }

    private static function strip_auto_internal_links_block(string $content): string
    {
        if ($content === '') {
            return '';
        }

        $pattern = '/' . preg_quote(self::AUTO_INTERNAL_LINKS_START, '/') . '.*?' . preg_quote(self::AUTO_INTERNAL_LINKS_END, '/') . '\s*/is';
        $content = (string) preg_replace($pattern, '', $content);
        return rtrim($content);
    }

    private static function content_has_internal_links(string $content, int $post_id = 0): bool
    {
        $content = self::strip_auto_internal_links_block($content);
        if ($content === '') {
            return false;
        }

        $host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
        $current_permalink = $post_id ? untrailingslashit((string) get_permalink($post_id)) : '';

        if (!preg_match_all('/<a\b[^>]*href=("|\')([^"\']+)\\1/i', $content, $matches, PREG_SET_ORDER)) {
            return false;
        }

        foreach ($matches as $match) {
            $href = html_entity_decode((string) ($match[2] ?? ''), ENT_QUOTES, get_bloginfo('charset'));
            $href = trim($href);

            if ($href === '' || strpos($href, '#') === 0 || stripos($href, 'mailto:') === 0 || stripos($href, 'tel:') === 0) {
                continue;
            }

            if (strpos($href, '/') === 0) {
                return true;
            }

            $href_host = (string) wp_parse_url($href, PHP_URL_HOST);
            if ($href_host === '' || !$host || !hash_equals(strtolower($host), strtolower($href_host))) {
                continue;
            }

            if ($current_permalink !== '' && untrailingslashit($href) === $current_permalink) {
                continue;
            }

            return true;
        }

        return false;
    }

    private static function build_auto_internal_links_block(array $posts): string
    {
        $anchors = [];
        $kinds = [];

        foreach ($posts as $post) {
            if (!$post instanceof WP_Post) {
                continue;
            }

            $kinds[] = self::post_kind($post->ID);
            $anchors[] = sprintf(
                '<a href="%1$s">%2$s</a>',
                esc_url(get_permalink($post)),
                esc_html(get_the_title($post))
            );
        }

        if (!$anchors) {
            return '';
        }

        $separator = self::content_text(' si ', ' and ');
        $links_text = count($anchors) === 1
            ? $anchors[0]
            : implode($separator, $anchors);
        $lead = self::auto_internal_links_lead($kinds, count($anchors));

        return self::AUTO_INTERNAL_LINKS_START
            . "\n"
            . '<p><strong>' . esc_html($lead) . '</strong> ' . $links_text . '.</p>'
            . "\n"
            . self::AUTO_INTERNAL_LINKS_END;
    }

    private static function auto_internal_links_lead(array $kinds, int $count): string
    {
        $kinds = array_values(array_unique(array_filter($kinds)));

        if ($count <= 0) {
            return self::content_text('Citeste si:', 'Read also:');
        }

        if ($kinds === ['recipe']) {
            return $count === 1
                ? self::content_text('Ca sa pui ideea in practica, vezi:', 'To put the idea into practice, see:')
                : self::content_text('Ca sa pui ideile in practica, vezi:', 'To put the ideas into practice, see:');
        }

        if ($kinds === ['article']) {
            return $count === 1
                ? self::content_text('Pentru context suplimentar, vezi:', 'For more context, see:')
                : self::content_text('Pentru context suplimentar, vezi si:', 'For more context, also see:');
        }

        return $count === 1
            ? self::content_text('Ca sa mergi mai departe, vezi:', 'To continue, see:')
            : self::content_text('Ca sa mergi mai departe, vezi si:', 'To continue, also see:');
    }

    private static function place_auto_internal_links_in_content(string $content, array $posts): string
    {
        $block = self::build_auto_internal_links_block($posts);
        if ($block === '') {
            return $content;
        }

        if (!preg_match_all('/<p\b[^>]*>.*?<\/p>/is', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $trimmed = rtrim($content);
            return $trimmed . ($trimmed === '' ? '' : "\n\n") . $block;
        }

        $best_index = -1;
        $best_score = 0;
        $post_keywords = [];

        foreach ($posts as $post) {
            if ($post instanceof WP_Post) {
                $post_keywords[] = self::keywords_from_text(self::related_candidate_text($post->ID));
            }
        }

        foreach ($matches[0] as $index => $match) {
            $paragraph_html = (string) $match[0];
            $paragraph_text = self::plain_text($paragraph_html);
            $paragraph_keywords = self::keywords_from_text($paragraph_text);
            $score = 0;

            foreach ($post_keywords as $keywords) {
                $score += self::keyword_overlap_score($paragraph_keywords, $keywords);
            }

            if (self::word_count($paragraph_text) >= 12) {
                $score += 1;
            }

            if ($score > $best_score) {
                $best_score = $score;
                $best_index = $index;
            }
        }

        if ($best_index < 0) {
            $best_index = max(0, count($matches[0]) - 1);
        }

        $selected = $matches[0][$best_index];
        $paragraph_html = (string) $selected[0];
        $offset = (int) $selected[1];
        $insert_at = $offset + strlen($paragraph_html);

        return substr($content, 0, $insert_at) . "\n\n" . $block . substr($content, $insert_at);
    }

    private static function keyword_overlap_score(array $left_words, array $right_words): int
    {
        if (!$left_words || !$right_words) {
            return 0;
        }

        $lookup = array_flip($right_words);
        $score = 0;

        foreach ($left_words as $word) {
            if (isset($lookup[$word])) {
                $score += 3;
                continue;
            }

            foreach ($right_words as $candidate) {
                if (strpos($candidate, $word) !== false || strpos($word, $candidate) !== false) {
                    $score += 1;
                    break;
                }
            }
        }

        return $score;
    }

    private static function detect_language(string $text): string
    {
        $plain = self::plain_text($text);
        if ($plain === '') {
            return 'unknown';
        }

        $normalized = function_exists('mb_strtolower') ? mb_strtolower($plain, 'UTF-8') : strtolower($plain);
        $romanian_markers = [' si ', ' din ', ' pentru ', ' cu ', ' este ', ' sunt ', ' reteta ', ' articol ', ' gatit ', ' ciocolata ', ' desert '];
        $english_markers = [' the ', ' and ', ' with ', ' from ', ' history ', ' guide ', ' recipe ', ' article ', ' chocolate ', ' sweet '];

        $romanian_score = preg_match('/[ăâîșşțţ]/u', $normalized) ? 4 : 0;
        $english_score = 0;

        foreach ($romanian_markers as $marker) {
            if (strpos(' ' . $normalized . ' ', $marker) !== false) {
                $romanian_score += 2;
            }
        }

        foreach ($english_markers as $marker) {
            if (strpos(' ' . $normalized . ' ', $marker) !== false) {
                $english_score += 2;
            }
        }

        if ($romanian_score === 0 && $english_score === 0) {
            return 'unknown';
        }

        if ($romanian_score >= $english_score + 2) {
            return 'ro';
        }

        if ($english_score >= $romanian_score + 2) {
            return 'en';
        }

        return 'unknown';
    }

    private static function posts_from_slug_queue(array $slugs, int $limit = 2): array
    {
        $posts = [];

        foreach ($slugs as $slug) {
            if ($slug === '') {
                continue;
            }

            $candidate = get_page_by_path($slug, OBJECT, 'post');
            if (!$candidate instanceof WP_Post || $candidate->post_status !== 'publish') {
                continue;
            }

            $posts[] = $candidate;
            if (count($posts) >= $limit) {
                break;
            }
        }

        return $posts;
    }

    private static function update_post_content(int $post_id, string $content): void
    {
        self::$is_updating_post = true;
        wp_update_post([
            'ID' => $post_id,
            'post_content' => $content,
        ]);
        self::$is_updating_post = false;
    }

    private static function save_text_meta(int $post_id, string $meta_key, string $field, int $max_length): void
    {
        $value = isset($_POST[$field]) ? sanitize_text_field(wp_unslash((string) $_POST[$field])) : '';
        $value = self::limit_text($value, $max_length);

        if ($value === '') {
            delete_post_meta($post_id, $meta_key);
            return;
        }

        update_post_meta($post_id, $meta_key, $value);
    }

    private static function keywords_from_text(string $text): array
    {
        $stopwords = [
            'aceasta', 'aceste', 'acest', 'acolo', 'acasa', 'adauga', 'aici', 'ale', 'are', 'asta',
            'care', 'cand', 'cele', 'celor', 'chiar', 'cum', 'daca', 'deja', 'despre', 'din', 'dupa',
            'este', 'fara', 'fiecare', 'foarte', 'intr', 'intre', 'mai', 'mult', 'pentru', 'peste',
            'poate', 'prin', 'reteta', 'recipes', 'romanesc', 'romaneasca', 'romanesti', 'sau', 'sunt',
            'toate', 'unui', 'unei', 'unde', 'kepoli', 'the', 'and', 'with', 'from',
        ];
        $stopword_lookup = array_flip($stopwords);
        $plain = self::plain_text($text);
        $plain = function_exists('mb_strtolower') ? mb_strtolower($plain, 'UTF-8') : strtolower($plain);
        $plain = remove_accents($plain);
        $plain = preg_replace('/[^a-z0-9\s-]/', ' ', $plain);
        $parts = preg_split('/\s+/', (string) $plain) ?: [];
        $words = [];

        foreach ($parts as $part) {
            $word = trim($part, "- \t\n\r\0\x0B");
            if (strlen($word) > 3 && !isset($stopword_lookup[$word])) {
                $words[] = $word;
            }
        }

        return array_values(array_unique($words));
    }

    private static function plain_text(string $text): string
    {
        $text = self::remove_template_prompt_text($text);
        $text = strip_shortcodes($text);
        $text = wp_strip_all_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, get_bloginfo('charset'));
        $text = preg_replace('/\s+/', ' ', $text);

        return trim((string) $text);
    }

    private static function normalized_heading(string $text): string
    {
        $text = self::plain_text($text);
        $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        $text = remove_accents($text);
        $text = preg_replace('/[^a-z0-9\s]/', ' ', (string) $text);

        return trim((string) preg_replace('/\s+/', ' ', (string) $text));
    }

    private static function remove_template_prompt_text(string $text): string
    {
        if ($text === '') {
            return '';
        }

        foreach (self::TEMPLATE_PROMPTS as $prompt) {
            $text = str_replace($prompt, '', $text);
        }

        foreach (self::TEMPLATE_OUTLINE_LABELS as $label) {
            $quoted = preg_quote($label, '/');
            $text = (string) preg_replace('/<h[23][^>]*>\s*' . $quoted . '\s*<\/h[23]>/iu', ' ', $text);
        }

        $labels = implode('|', array_map(static fn (string $label): string => preg_quote($label, '/'), self::TEMPLATE_OUTLINE_LABELS));
        $text = (string) preg_replace('/\b(?:' . $labels . ')\b(?=(?:\s+(?:' . $labels . ')\b)|\s*$)/iu', ' ', $text);

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    private static function word_count(string $text): int
    {
        $plain = self::plain_text($text);
        if ($plain === '') {
            return 0;
        }

        $words = preg_split('/\s+/', $plain) ?: [];
        return count(array_filter($words));
    }

    private static function posted_slugs(string $field): array
    {
        $value = isset($_POST[$field]) ? sanitize_textarea_field(wp_unslash((string) $_POST[$field])) : '';
        $parts = preg_split('/[\s,]+/', $value) ?: [];
        $slugs = [];

        foreach ($parts as $part) {
            $slug = sanitize_title($part);
            if ($slug !== '') {
                $slugs[] = $slug;
            }
        }

        return array_values(array_unique($slugs));
    }

    private static function limit_text(string $value, int $max_length): string
    {
        return function_exists('mb_substr') ? mb_substr($value, 0, $max_length) : substr($value, 0, $max_length);
    }

    private static function sentence_limit(string $value, int $max_length, int $min_length = 80): string
    {
        $value = self::plain_text($value);
        $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);

        if ($length <= $max_length) {
            return $value;
        }

        $slice = self::limit_text($value, $max_length + 1);
        $sentence_end = -1;

        foreach (['.', '!', '?'] as $mark) {
            $position = function_exists('mb_strrpos') ? mb_strrpos($slice, $mark, 0, 'UTF-8') : strrpos($slice, $mark);
            if ($position !== false) {
                $sentence_end = max($sentence_end, $position);
            }
        }

        $word_end = function_exists('mb_strrpos') ? mb_strrpos($slice, ' ', 0, 'UTF-8') : strrpos($slice, ' ');
        if ($sentence_end > $min_length) {
            $end = $sentence_end + 1;
        } elseif ($word_end !== false && $word_end > $min_length) {
            $end = $word_end;
        } else {
            $end = $max_length;
        }

        return rtrim(self::limit_text($slice, $end), " \t\n\r\0\x0B,;:") . '...';
    }

    private static function posted_lines(string $field): array
    {
        $value = isset($_POST[$field]) ? sanitize_textarea_field(wp_unslash((string) $_POST[$field])) : '';
        $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];
        $clean = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $clean[] = $line;
            }
        }

        return $clean;
    }

    private static function normalized_text_lines(array $lines): array
    {
        $clean = [];

        foreach ($lines as $line) {
            $value = trim(sanitize_text_field((string) $line));
            if ($value !== '') {
                $clean[] = $value;
            }
        }

        return $clean;
    }

    private static function stored_recipe_data(int $post_id): array
    {
        $data = self::recipe_data($post_id);

        return [
            'servings' => self::recipe_servings_has_value((string) ($data['servings'] ?? '')) ? sanitize_text_field((string) ($data['servings'] ?? '')) : '',
            'prep_minutes' => max(0, (int) ($data['prep_minutes'] ?? 0)),
            'cook_minutes' => max(0, (int) ($data['cook_minutes'] ?? 0)),
            'total_minutes' => max(0, (int) ($data['total_minutes'] ?? 0)),
            'ingredients' => self::normalized_text_lines(isset($data['ingredients']) && is_array($data['ingredients']) ? $data['ingredients'] : []),
            'steps' => self::normalized_text_lines(isset($data['steps']) && is_array($data['steps']) ? $data['steps'] : []),
        ];
    }

    private static function recipe_data_is_empty(array $data): bool
    {
        return ($data['servings'] ?? '') === ''
            && (int) ($data['prep_minutes'] ?? 0) === 0
            && (int) ($data['cook_minutes'] ?? 0) === 0
            && (int) ($data['total_minutes'] ?? 0) === 0
            && ($data['ingredients'] ?? []) === []
            && ($data['steps'] ?? []) === [];
    }

    private static function recipe_text_lines(string $content): array
    {
        $content = strip_shortcodes($content);
        $content = (string) preg_replace('/<br\s*\/?>/i', "\n", $content);
        $content = (string) preg_replace('/<li\b[^>]*>/i', "\n- ", $content);
        $content = (string) preg_replace('/<\/(?:p|div|li|h[1-6]|ul|ol|section|article|blockquote|tr)>/i', "\n", $content);
        $content = wp_strip_all_tags($content);
        $content = html_entity_decode($content, ENT_QUOTES, get_bloginfo('charset'));
        $content = str_replace("\r", "\n", $content);
        $content = preg_replace('/[ \t]+/', ' ', $content);
        $lines = preg_split('/\n+/', (string) $content) ?: [];

        return array_values(array_filter(array_map(static fn ($line): string => trim((string) $line), $lines)));
    }

    private static function recipe_line_text(string $line): string
    {
        $line = wp_strip_all_tags($line);
        $line = html_entity_decode($line, ENT_QUOTES, get_bloginfo('charset'));
        $line = (string) preg_replace('/^[\s>*\x{2022}\x{00b7}-]+/u', '', $line);
        $line = (string) preg_replace('/^\d{1,2}\s*[.)-]\s*/', '', $line);
        $line = (string) preg_replace('/^\([a-z0-9]+\)\s*/i', '', $line);

        return trim(sanitize_text_field($line));
    }

    private static function recipe_heading_key(string $line): string
    {
        $line = self::recipe_line_text($line);
        $line = remove_accents(strtolower($line));
        $line = preg_replace('/[^a-z0-9\s]/', ' ', (string) $line);

        return trim((string) preg_replace('/\s+/', ' ', (string) $line));
    }

    private static function canonical_recipe_heading(string $line): string
    {
        $heading = self::recipe_heading_key($line);

        if (preg_match('/^(ingredients?|ingredient list|ingredient checklist|what you need|ingrediente|lista ingrediente)$/', $heading)) {
            return 'ingredients';
        }

        if (preg_match('/^(method|instructions?|directions?|preparation|preparation method|steps?|cooking steps?|mod de preparare|preparare|pasi|instructiuni)$/', $heading)) {
            return 'steps';
        }

        if (preg_match('/^(recipe details?|details?|what to know first|serving ideas?|serving notes?|serving|how to serve|success notes?|tips?|storage|storage and reheating|variations?|common mistakes?|faq|frequently asked questions?|conclusion|notes?|nutrition|nutritional values?|pe scurt|detalii despre reteta|cum se serveste|cum servesti|sfaturi|cum pastrezi|variante|intrebari frecvente|concluzie)$/', $heading)) {
            return 'stop';
        }

        return '';
    }

    private static function recipe_line_looks_like_meta(string $line): bool
    {
        return (bool) preg_match('/^(prep|preparation|rest|cook|cooking|bake|baking|total|servings?|serves|makes|yield|difficulty|timp|portii|nivel)\b/i', self::recipe_line_text($line));
    }

    private static function recipe_section_items_from_content(string $content, string $section): array
    {
        $items = [];
        $active = false;

        foreach (self::recipe_text_lines($content) as $line) {
            $heading = self::canonical_recipe_heading($line);

            if ($heading === $section) {
                $active = true;
                continue;
            }

            if ($active && $heading !== '') {
                $active = false;
                continue;
            }

            if (!$active || self::recipe_line_looks_like_meta($line)) {
                continue;
            }

            $text = self::recipe_line_text($line);
            if ($text !== '') {
                $items[] = $text;
            }
        }

        return array_slice(array_values(array_unique($items)), 0, $section === 'ingredients' ? 40 : 30);
    }

    private static function recipe_duration_value_to_minutes(string $value): int
    {
        $value = self::recipe_heading_key($value);
        $hours = 0;
        $minutes = 0;

        if (preg_match('/(\d{1,2})\s*(?:h|hr|hrs|ora|ore|hour|hours)\b/i', $value, $matches)) {
            $hours = max(0, (int) ($matches[1] ?? 0));
        }

        if (preg_match('/(\d{1,3})\s*(?:m|min|mins|minute|minutes)\b/i', $value, $matches)) {
            $minutes = max(0, (int) ($matches[1] ?? 0));
        }

        if ($hours === 0 && $minutes === 0 && preg_match('/(\d{1,3})/', $value, $matches)) {
            return max(0, (int) ($matches[1] ?? 0));
        }

        return ($hours * 60) + $minutes;
    }

    private static function recipe_minutes_from_lines(array $lines, array $labels): int
    {
        $labels = array_values(array_unique(array_filter(array_map([self::class, 'recipe_heading_key'], $labels))));

        foreach ($lines as $line) {
            $normalized_line = self::recipe_heading_key((string) $line);
            foreach ($labels as $label) {
                if ($normalized_line === $label || !str_starts_with($normalized_line, $label . ' ')) {
                    continue;
                }

                $minutes = self::recipe_duration_value_to_minutes(substr($normalized_line, strlen($label)));
                if ($minutes > 0) {
                    return $minutes;
                }
            }
        }

        return 0;
    }

    private static function recipe_minutes_from_text(string $text, array $labels): int
    {
        $plain = implode(' ', self::recipe_text_lines($text));
        foreach ($labels as $label) {
            $quoted = preg_quote($label, '/');
            if (preg_match('/(?:^|\s)' . $quoted . '\s*:?\s*((?:\d{1,2}\s*(?:h|hr|hrs|hour|hours|ora|ore)\s*)?(?:\d{1,3}\s*(?:m|min|mins|minute|minutes))|(?:\d{1,3}))/i', $plain, $matches)) {
                $minutes = self::recipe_duration_value_to_minutes((string) ($matches[1] ?? ''));
                if ($minutes > 0) {
                    return $minutes;
                }
            }
        }

        return 0;
    }

    private static function recipe_servings_from_text(string $content): string
    {
        $plain = implode(' ', self::recipe_text_lines($content));
        if (!preg_match('/(?:servings?|serves|makes|yield|portii|pentru|aproximativ|cam)\s*:?\s*(\d{1,2}(?:\s*(?:servings?|portions?|people|persons|pieces?|slices?|portii|persoane))?)/i', $plain, $matches)) {
            return '';
        }

        $servings = sanitize_text_field((string) ($matches[1] ?? ''));
        return self::recipe_servings_has_value($servings) ? $servings : '';
    }

    private static function extract_recipe_data_from_content(string $content): array
    {
        $lines = self::recipe_text_lines($content);
        $prep_minutes = self::recipe_minutes_from_lines($lines, ['prep time', 'preparation time', 'prep', 'timp de pregatire', 'timp pregatire']);
        if ($prep_minutes === 0) {
            $prep_minutes = self::recipe_minutes_from_text($content, ['prep time', 'preparation time', 'prep', 'timp de pregatire', 'timp pregatire']);
        }

        $cook_minutes = self::recipe_minutes_from_lines($lines, ['cook time', 'cooking time', 'bake time', 'baking time', 'boil time', 'simmer time', 'timp de gatire', 'timp gatire', 'timp de coacere', 'timp de fierbere']);
        if ($cook_minutes === 0) {
            $cook_minutes = self::recipe_minutes_from_text($content, ['cook time', 'cooking time', 'bake time', 'baking time', 'boil time', 'simmer time', 'timp de gatire', 'timp gatire', 'timp de coacere', 'timp de fierbere']);
        }

        $total_minutes = self::recipe_minutes_from_lines($lines, ['total time', 'total', 'timp total']);
        if ($total_minutes === 0) {
            $total_minutes = self::recipe_minutes_from_text($content, ['total time', 'total', 'timp total']);
        }

        if ($prep_minutes > 0 && $cook_minutes === 0 && $total_minutes > $prep_minutes) {
            $cook_minutes = max(0, $total_minutes - $prep_minutes);
        } elseif ($cook_minutes > 0 && $prep_minutes === 0 && $total_minutes > $cook_minutes) {
            $prep_minutes = max(0, $total_minutes - $cook_minutes);
        }

        return [
            'servings' => self::recipe_servings_from_text($content),
            'prep_minutes' => $prep_minutes,
            'cook_minutes' => $cook_minutes,
            'total_minutes' => $total_minutes,
            'ingredients' => self::recipe_section_items_from_content($content, 'ingredients'),
            'steps' => self::recipe_section_items_from_content($content, 'steps'),
        ];
    }

    private static function save_recipe_json(int $post_id, WP_Post $post): void
    {
        $posted = [
            'ingredients' => self::posted_lines('kepoli_recipe_ingredients'),
            'steps' => self::posted_lines('kepoli_recipe_steps'),
            'servings' => self::recipe_servings_has_value(isset($_POST['kepoli_recipe_servings']) ? (string) wp_unslash((string) $_POST['kepoli_recipe_servings']) : '')
                ? sanitize_text_field(wp_unslash((string) $_POST['kepoli_recipe_servings']))
                : '',
            'prep_minutes' => isset($_POST['kepoli_recipe_prep_minutes']) ? absint(wp_unslash((string) $_POST['kepoli_recipe_prep_minutes'])) : 0,
            'cook_minutes' => isset($_POST['kepoli_recipe_cook_minutes']) ? absint(wp_unslash((string) $_POST['kepoli_recipe_cook_minutes'])) : 0,
            'total_minutes' => isset($_POST['kepoli_recipe_total_minutes']) ? absint(wp_unslash((string) $_POST['kepoli_recipe_total_minutes'])) : 0,
        ];
        $existing = self::stored_recipe_data($post_id);
        $extracted = self::extract_recipe_data_from_content((string) $post->post_content);
        $resolved = [
            'ingredients' => $posted['ingredients'] !== [] ? $posted['ingredients'] : (!empty($extracted['ingredients']) ? $extracted['ingredients'] : $existing['ingredients']),
            'steps' => $posted['steps'] !== [] ? $posted['steps'] : (!empty($extracted['steps']) ? $extracted['steps'] : $existing['steps']),
            'servings' => $posted['servings'] !== '' ? $posted['servings'] : (!empty($extracted['servings']) ? $extracted['servings'] : $existing['servings']),
            'prep_minutes' => $posted['prep_minutes'] > 0 ? $posted['prep_minutes'] : (!empty($extracted['prep_minutes']) ? (int) $extracted['prep_minutes'] : $existing['prep_minutes']),
            'cook_minutes' => $posted['cook_minutes'] > 0 ? $posted['cook_minutes'] : (isset($extracted['cook_minutes']) && (int) $extracted['cook_minutes'] > 0 ? (int) $extracted['cook_minutes'] : $existing['cook_minutes']),
            'total_minutes' => $posted['total_minutes'] > 0 ? $posted['total_minutes'] : (!empty($extracted['total_minutes']) ? (int) $extracted['total_minutes'] : $existing['total_minutes']),
        ];

        $resolved = [
            'ingredients' => self::normalized_text_lines($resolved['ingredients'] ?? []),
            'steps' => self::normalized_text_lines($resolved['steps'] ?? []),
            'servings' => self::recipe_servings_has_value((string) ($resolved['servings'] ?? '')) ? sanitize_text_field((string) ($resolved['servings'] ?? '')) : '',
            'prep_minutes' => max(0, (int) ($resolved['prep_minutes'] ?? 0)),
            'cook_minutes' => max(0, (int) ($resolved['cook_minutes'] ?? 0)),
            'total_minutes' => max(0, (int) ($resolved['total_minutes'] ?? 0)),
        ];

        if ($resolved['total_minutes'] <= 0 && ($resolved['prep_minutes'] > 0 || $resolved['cook_minutes'] > 0)) {
            $resolved['total_minutes'] = $resolved['prep_minutes'] + $resolved['cook_minutes'];
        }

        if (self::recipe_data_is_empty($resolved)) {
            delete_post_meta($post_id, '_kepoli_recipe_json');
            return;
        }

        update_post_meta($post_id, '_kepoli_recipe_json', wp_json_encode([
            'category' => self::primary_category_name($post_id),
            'servings' => $resolved['servings'],
            'prep_iso' => self::minutes_to_iso($resolved['prep_minutes']),
            'cook_iso' => self::minutes_to_iso($resolved['cook_minutes']),
            'total_iso' => self::minutes_to_iso($resolved['total_minutes']),
            'ingredients' => $resolved['ingredients'],
            'steps' => $resolved['steps'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private static function primary_category_name(int $post_id): string
    {
        $categories = get_the_category($post_id);
        return !empty($categories) ? $categories[0]->name : self::content_text('Retete', 'Recipes');
    }

    private static function primary_category_slug(int $post_id): string
    {
        $categories = get_the_category($post_id);
        return !empty($categories) && isset($categories[0]->slug) ? (string) $categories[0]->slug : '';
    }

    private static function minutes_to_iso(int $minutes): string
    {
        $minutes = max(0, $minutes);
        if ($minutes === 0) {
            return 'PT0M';
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;
        $duration = 'PT';

        if ($hours > 0) {
            $duration .= $hours . 'H';
        }

        if ($remaining > 0) {
            $duration .= $remaining . 'M';
        }

        return $duration;
    }

    private static function iso_to_minutes(string $duration): int
    {
        if (!preg_match('/^PT(?:(\d+)H)?(?:(\d+)M)?$/', $duration, $matches)) {
            return 0;
        }

        $hours = isset($matches[1]) && $matches[1] !== '' ? (int) $matches[1] : 0;
        $minutes = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : 0;

        return ($hours * 60) + $minutes;
    }
}

Food_Blog_Author_Tools::init();
