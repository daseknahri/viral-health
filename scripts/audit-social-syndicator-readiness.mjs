import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const failures = [];

const plugin = readFile('wp-content/plugins/dr-purg-social-syndicator/dr-purg-social-syndicator.php');
const adminJs = readFile('wp-content/plugins/dr-purg-social-syndicator/assets/admin.js');
const adminCss = readFile('wp-content/plugins/dr-purg-social-syndicator/assets/admin.css');
const docs = readFile('docs/social-syndicator.md');
const theme = readFile('wp-content/themes/kepoli/functions.php');
const autoseed = readFile('wp-content/mu-plugins/kepoli-autoseed.php');
const seedBootstrap = readFile('seed/bootstrap.php');
const wordpressEntrypoint = readFile('docker/wordpress/entrypoint.sh');
const wpCliEntrypoint = readFile('docker/wp-cli/entrypoint.sh');

checkPluginContract();
checkDeploymentContract();
checkDocs();

if (failures.length === 0) {
  console.log('Social Syndicator readiness OK.');
} else {
  console.log(`Social Syndicator readiness found ${failures.length} required fix${failures.length === 1 ? '' : 'es'}.`);
  console.log('\nRequired fixes:');
  for (const failure of failures) {
    console.log(`- ${failure}`);
  }
}

process.exit(failures.length > 0 ? 1 : 0);

function readFile(relativePath) {
  const absolutePath = path.join(root, relativePath);
  if (!fs.existsSync(absolutePath)) {
    failures.push(`Missing file: ${relativePath}`);
    return '';
  }

  return fs.readFileSync(absolutePath, 'utf8');
}

function requireIncludes(label, content, patterns) {
  for (const pattern of patterns) {
    if (!pattern.test(content)) {
      failures.push(`${label} is missing: ${pattern}`);
    }
  }
}

function checkPluginContract() {
  requireIncludes('plugin header and hooks', plugin, [
    /Plugin Name:\s*Dr Purg Jr\. Social Syndicator/,
    /add_action\('admin_menu'/,
    /add_action\('transition_post_status'/,
    /add_filter\('redirect_post_location'/,
    /add_filter\('post_row_actions'/,
    /register_activation_hook/,
  ]);

  requireIncludes('admin page contract', plugin, [
    /Social Queue/,
    /Social Editor/,
    /Social Settings/,
    /add_menu_page/,
    /add_submenu_page/,
    /wp_enqueue_media/,
    /data-dpj-media-select/,
  ]);

  requireIncludes('publish workflow contract', plugin, [
    /\$new_status\s*!==\s*'publish'/,
    /\$old_status\s*===\s*'publish'/,
    /\$old_status\s*===\s*'future'/,
    /ensure_social_package/,
    /_dpj_social_package_created/,
    /_dpj_social_redirect_after_publish/,
    /admin\.php\?page=/,
    /REDIRECT_TRANSIENT_PREFIX/,
  ]);

  requireIncludes('Facebook field contract', plugin, [
    /_dpj_social_facebook_hook/,
    /_dpj_social_facebook_summary/,
    /_dpj_social_facebook_media_id/,
    /_dpj_social_facebook_first_comment/,
    /_dpj_social_facebook_remote_post_id/,
    /_dpj_social_facebook_last_error/,
    /_dpj_social_facebook_posted_at/,
  ]);

  requireIncludes('manual platform field contract', plugin, [
    /_dpj_social_pinterest_title/,
    /_dpj_social_pinterest_description/,
    /_dpj_social_pinterest_board/,
    /_dpj_social_pinterest_alt_text/,
    /_dpj_social_reddit_subreddit/,
    /_dpj_social_reddit_media_id/,
    /_dpj_social_reddit_rules_notes/,
    /mark_pinterest_posted/,
    /mark_reddit_posted/,
    /publish_pinterest/,
    /pinterest_request/,
    /api\.pinterest\.com\/v5\/pins/,
    /pinterest_access_token/,
    /pinterest_board_id/,
    /_dpj_social_pinterest_remote_id/,
  ]);

  requireIncludes('Facebook API contract', plugin, [
    /facebook_page_id/,
    /facebook_app_id/,
    /facebook_app_secret/,
    /facebook_page_access_token/,
    /facebook_graph_version/,
    /pixazo_api_key/,
    /PIXAZO_SDXL_FREE_ENDPOINT/,
    /pages_manage_posts/,
    /pages_read_engagement/,
    /graph\.facebook\.com/,
    /\/photos/,
    /\/feed/,
    /\/comments/,
    /default_facebook_first_comment/,
    /strip_article_url_from_text/,
    /appsecret_proof/,
    /wp_remote_post/,
    /Reset Facebook posting lock/,
    /scheduled_publish_time/,
    /post_facebook_comment/,
    /parse_schedule_timestamp/,
    /_dpj_social_facebook_scheduled_at/,
    /Schedule Facebook post/,
  ]);

  requireIncludes('AI social draft contract', plugin, [
    /AI Social Draft Assistant/,
    /generate_ai_social_draft/,
    /request_ai_social_draft/,
    /apply_ai_social_draft/,
    /sanitize_ai_social_payload/,
    /decode_ai_json_object/,
    /SOCIAL_AI_ENABLE/,
    /AI_EXTRACTION_ENABLE/,
    /SOCIAL_AI_API_KEY/,
    /AI_EXTRACTION_API_KEY/,
    /openrouter\.ai\/api\/v1\/chat\/completions/,
    /response_format/,
    /json_object/,
    /_dpj_social_ai_last_error/,
    /_dpj_social_ai_last_run/,
    /_dpj_social_ai_model/,
  ]);

  requireIncludes('social image converter contract', plugin, [
    /SOCIAL_IMAGE_VARIANTS/,
    /PIXAZO_PLATFORM_VARIANTS/,
    /generate_pixazo_images_for_post/,
    /request_pixazo_image_url/,
    /Ocp-Apim-Subscription-Key/,
    /media_handle_sideload/,
    /AI Image Generator/,
    /generate_social_images_for_post/,
    /render_social_image_jpeg/,
    /copy_cover_image/,
    /draw_social_overlay/,
    /draw_bottom_hint/,
    /draw_vertical_scrim/,
    /card_scrim_strength/,
    /overlay_position/,
    /overlay_block_top/,
    /_dpj_social_local_overlay_pos/,
    /crop_focus_factor/,
    /_dpj_social_local_crop_focus/,
    /card_brand/,
    /SOCIAL_CARD_BRAND/,
    /ajax_card_preview/,
    /wp_ajax_dpj_social_card_preview/,
    /wp_send_json_success/,
    /hint_has_down_pointer/,
    /strip_hint_pointer_symbols/,
    /draw_down_pointer_icon/,
    /overlay_display_text/,
    /draw_centered_ttf_text/,
    /draw_centered_ttf_hint_text/,
    /draw_scaled_string/,
    /draw_centered_scaled_string/,
    /draw_centered_scaled_hint_string/,
    /font_path/,
    /_dpj_social_local_overlay_text/,
    /_dpj_social_local_overlay_enable/,
    /_dpj_social_local_hint_text/,
    /_dpj_social_local_hint_enable/,
    /_dpj_social_generated_facebook_media_id/,
    /_dpj_social_generated_pinterest_media_id/,
    /reddit_media_id/,
    /Copy image URL/,
    /_dpj_social_og_media_id/,
    /wp_insert_attachment/,
    /wp_generate_attachment_metadata/,
    /imagejpeg/,
    /Generate local social cards/,
  ]);

  requireIncludes('social image QA contract', plugin, [
    /social_image_qa/,
    /crop_safety_row/,
    /overlay_word_count/,
    /render_social_image_qa/,
    /OVERLAY_WORD_MAX/,
    /CROP_KEEP_WARN/,
    /UPSCALE_WARN/,
    /Image QA checks/,
    /recommended_source_size/,
    /data-dpj-overlay-counter/,
  ]);

  requireIncludes('AI provider contract', plugin, [
    /social_ai_provider/,
    /openrouter_complete/,
    /anthropic_complete/,
    /api\.anthropic\.com\/v1\/messages/,
    /x-api-key/,
    /anthropic-version/,
    /output_config/,
    /cache_control/,
    /ai_social_schema/,
  ]);

  requireIncludes('UTM tracking contract', plugin, [
    /social_utm_enabled/,
    /social_utm_url/,
    /apply_utm_to_text/,
    /utm_source/,
    /utm_campaign/,
    /SOCIAL_UTM_ENABLE/,
    /Tracked links/,
    /render_tracked_links_section/,
  ]);

  requireIncludes('performance log contract', plugin, [
    /render_performance_page/,
    /render_performance_section/,
    /save_performance_fields/,
    /_dpj_social_perf_clicks/,
    /_dpj_social_perf_revenue/,
    /PERF_SLUG/,
    /Social Performance/,
    /handle_performance_import/,
    /dpj_social_perf_action/,
    /Import from CSV/,
    /handle_ga4_pull/,
    /ga4_access_token/,
    /analyticsdata\.googleapis\.com/,
    /oauth2\.googleapis\.com\/token/,
    /GA4_PROPERTY_ID/,
    /sessionCampaignName/,
  ]);

  requireIncludes('hook variants contract', plugin, [
    /generate_hook_variants/,
    /apply_hook_variant/,
    /render_hook_variants_section/,
    /ai_hook_variants_schema/,
    /_dpj_social_hook_variants/,
    /selected_variant/,
    /SOCIAL_AI_HOOK_VARIANTS/,
    /utm_content|social_utm_url/,
  ]);

  requireIncludes('posting cockpit contract', plugin, [
    /render_cockpit_page/,
    /handle_cockpit_post/,
    /add_posting_log_entry/,
    /_dpj_social_posting_log/,
    /COCKPIT_SLUG/,
    /dpj_cockpit_action/,
    /Posting Cockpit/,
  ]);

  requireIncludes('content calendar contract', plugin, [
    /render_calendar_page/,
    /handle_calendar_post/,
    /generate_calendar_ideas/,
    /ai_calendar_schema/,
    /add_calendar_idea/,
    /update_calendar_idea/,
    /delete_calendar_idea/,
    /CALENDAR_SLUG/,
    /CALENDAR_OPTION/,
    /dpj_calendar_action/,
    /SOCIAL_AI_CALENDAR_IDEAS/,
    /Content Calendar/,
  ]);

  requireIncludes('admin assets', adminJs + '\n' + adminCss, [
    /wp\.media/,
    /navigator\.clipboard/,
    /data-dpj-copy/,
    /data-dpj-overlay-counter/,
    /updateOverlayCounter/,
    /data-dpj-card-preview/,
    /\.dpj-generated-grid/,
    /\.dpj-social-card/,
    /\.dpj-social-qa/,
    /\.dpj-overlay-counter/,
    /\.dpj-tracked-link/,
    /\.dpj-status--posted/,
    /\.dpj-status--failed/,
  ]);

  requireIncludes('theme social image filter contract', theme, [
    /apply_filters\('kepoli_social_image_url'/,
    /apply_filters\('kepoli_social_image_alt'/,
    /apply_filters\('kepoli_social_image_dimensions'/,
  ]);
}

function checkDeploymentContract() {
  const deployment = `${wordpressEntrypoint}\n${wpCliEntrypoint}\n${autoseed}\n${seedBootstrap}`;
  requireIncludes('deployment plugin copy and activation', deployment, [
    /rm -rf \/var\/www\/html\/wp-content\/plugins\/dr-purg-social-syndicator/,
    /cp -a \/opt\/kepoli\/wp-content\/plugins\/dr-purg-social-syndicator \/var\/www\/html\/wp-content\/plugins\/dr-purg-social-syndicator/,
    /wp-content\/plugins\/dr-purg-social-syndicator/,
    /kepoli_autoseed_activate_plugin\('dr-purg-social-syndicator\/dr-purg-social-syndicator\.php'\)/,
    /kepoli_seed_activate_plugin\('dr-purg-social-syndicator\/dr-purg-social-syndicator\.php'\)/,
  ]);
}

function checkDocs() {
  requireIncludes('docs/social-syndicator.md', docs, [
    /Social Syndicator plugin/,
    /Facebook Page API/,
    /AI social draft assistant/,
    /SOCIAL_AI_ENABLE/,
    /strict JSON/,
    /Long-lived Page Access Token/,
    /Post to Facebook/,
    /Social image converter/,
    /Pixazo image testing/,
    /Generate local social cards/,
    /Overlay text/,
    /poster-style overlay/,
    /link-in-comment posts/,
    /emoji-font support/,
    /no external API/,
    /819x1024/,
    /683x1024/,
    /1024x538/,
    /1080x1350/,
    /1000x1500/,
    /1200x630/,
    /Pinterest/,
    /Reddit/,
    /Duplicate Facebook posting is blocked/,
  ]);
}
