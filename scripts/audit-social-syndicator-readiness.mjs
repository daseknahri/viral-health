import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const failures = [];

const plugin = readFile('wp-content/plugins/dr-purg-social-syndicator/dr-purg-social-syndicator.php');
const adminJs = readFile('wp-content/plugins/dr-purg-social-syndicator/assets/admin.js');
const adminCss = readFile('wp-content/plugins/dr-purg-social-syndicator/assets/admin.css');
const docs = readFile('docs/social-syndicator.md');
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
    /_dpj_social_reddit_rules_notes/,
    /mark_pinterest_posted/,
    /mark_reddit_posted/,
  ]);

  requireIncludes('Facebook API contract', plugin, [
    /facebook_page_id/,
    /facebook_app_id/,
    /facebook_app_secret/,
    /facebook_page_access_token/,
    /facebook_graph_version/,
    /pages_manage_posts/,
    /pages_read_engagement/,
    /graph\.facebook\.com/,
    /\/photos/,
    /\/feed/,
    /\/comments/,
    /appsecret_proof/,
    /wp_remote_post/,
    /Reset Facebook posting lock/,
  ]);

  requireIncludes('admin assets', adminJs + '\n' + adminCss, [
    /wp\.media/,
    /navigator\.clipboard/,
    /data-dpj-copy/,
    /\.dpj-social-card/,
    /\.dpj-status--posted/,
    /\.dpj-status--failed/,
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
    /Long-lived Page Access Token/,
    /Post to Facebook/,
    /Pinterest/,
    /Reddit/,
    /Duplicate Facebook posting is blocked/,
  ]);
}
