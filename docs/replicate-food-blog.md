# Replicate This Viral Blog

Use this repo as the shared engine for another viral-content blog, health-facts blog, lifestyle blog, or food blog. The filename is historical because the engine started as a food-blog clone, but Dr Purg Jr. proves the same base can run a Facebook/mobile viral publication.

Before first deploy, change the public profile and content pack. Internal handles such as `kepoli_` can stay for now; what matters for readers, search engines, ad networks, and social platforms is that the new site has its own `content/site-profile.json`, pages, categories, posts, image plan, images, theme assets, ad accounts, and social credentials.

## What To Keep

- The Docker and Coolify deployment structure.
- The custom WordPress theme layout, performance work, structured data, ad gating, canonical redirects, and security hardening.
- The author tools plugin: automatic excerpts, meta descriptions, featured-image metadata, internal links, recipe fields, FAQ assist, heading cleanup, and post split controls.
- The native newsletter system that stores emails in WordPress admin.
- The env-gated monetization strategy: keep manual ad placements disabled in source defaults, then enable the new site's chosen ad stack from Coolify one placement at a time.
- The Social Syndicator workflow, if the new site will use Facebook/Pinterest/Reddit distribution.

## What To Change First

In the cloned repo, you can apply the mechanical identity changes with:

```powershell
node scripts/prepare-replica.mjs --brand "New Blog" --domain https://new-domain.com --site-email contact@new-domain.com --writer-name "Writer Name" --writer-email writer@example.com --project-slug new-blog --language en --recipes-slug recipes --guides-slug guides --write
```

Without `--write`, the command only shows the changes it would make.

Then clear the old launch posts and image plan in the clone:

```powershell
node scripts/reset-replica-content.mjs --write --delete-images
```

Leave out `--delete-images` if you want to remove the old images manually. The reset script writes a local `.replica-backups/` folder before deleting or clearing files.

Then generate fresh starter pages and categories:

```powershell
node scripts/generate-replica-shell.mjs --brand "New Blog" --domain https://new-domain.com --site-email contact@new-domain.com --writer-name "Writer Name" --writer-email writer@example.com --project-slug new-blog --language en --monetization ezoic --write
```

These commands create or rewrite `content/site-profile.json` too. The profile is the canonical source for brand name, public locale, admin locale, writer identity, optional Gravatar profile URL, identity asset basenames, and canonical page slugs. Admin stays English through `locales.admin=en_US` and `locales.force_admin=true`; public generated text follows `locales.public`.

These pages are a starter shell. Review them before launch, especially privacy, cookies, advertising, editorial, terms, disclaimer, and any niche-specific safety pages. The shell generator supports both Romanian and English starter sets.

Then review these values in the new repo and in the new Coolify environment:

```env
SITE_URL=https://new-domain.com
SITE_EMAIL=contact@new-domain.com
WRITER_EMAIL=writer@example.com
WP_LOCALE=en_US
WP_ADMIN_LOCALE=en_US
CANONICAL_REDIRECT_HOSTS=www.new-domain.com

WORDPRESS_DB_NAME=new_blog_name
WORDPRESS_DB_USER=new_blog_user
WORDPRESS_DB_PASSWORD=strong-password
MYSQL_ROOT_PASSWORD=strong-root-password

WP_ADMIN_USER=admin
WP_ADMIN_PASSWORD=strong-admin-password
WP_ADMIN_EMAIL=contact@new-domain.com

ADSENSE_CLIENT_ID=
ADSENSE_PUB_ID=
ADSENSE_ENABLE=0
ADSENSE_SLOT_HEADER=
ADSENSE_SLOT_AFTER_INTRO=
ADSENSE_SLOT_MID_CONTENT=
ADSENSE_SLOT_SIDEBAR=
ADSENSE_SLOT_BELOW_CONTENT=

GA_ENABLE=0
GA_MEASUREMENT_ID=
SEARCH_CONSOLE_VERIFICATION=

KEPOLI_DEPLOY_FINGERPRINT=0
```

If the new site will use AdSense after approval, fill `ADSENSE_CLIENT_ID` and `ADSENSE_PUB_ID` with that account's IDs. If the new site will use Ezoic first, those values can stay empty until you decide to activate AdSense later.

## Files To Rebrand

- `content/site-profile.json`: replace brand name, tagline, description, public locale, writer identity, email, optional Gravatar profile URL, identity asset basenames, and canonical slugs. This is the first file to check.
- `.env.example`: replace domain, emails, database names, AdSense IDs, and canonical hosts. Keep `WP_LOCALE` equal to `content/site-profile.json` `locales.public`.
- `WP_ADMIN_LOCALE`: keep this as `en_US` so WordPress admin and beginner-publisher tools stay in English, even if a future public site uses another language.
- `docker-compose.yml`: replace default domain/email values and rename the image tags from `kepoli-wordpress` and `kepoli-wp-cli` to the new project name. This avoids image-name collisions if both blogs run on the same server.
- `README.md` and `docs/*.md`: replace project name, domain, old operational notes, ad code inventory, social credentials guidance, and niche-specific safety rules.
- `wp-content/themes/kepoli/style.css`: change the public theme header: theme name, URI, author, author URI, and description.
- `wp-content/themes/kepoli/assets/img/`: replace logo, social cover, homepage hero, icon, and writer photo. If you rename logo/icon/social-cover files, update `content/site-profile.json` `assets`.
- `wp-content/themes/kepoli/functions.php`: this should read public identity from `kepoli_site_profile`; avoid adding new brand-specific fallback copy here.
- `wp-content/themes/kepoli/header.php`, `footer.php`, `front-page.php`, `page-about-author.php`, `page-recipes.php`, and `page-guides.php`: keep these as layout templates. Public authenticity copy should come from `content/pages.json`; template labels can stay structural and locale-aware.
- `wp-content/mu-plugins/kepoli-adtech.php`: manifest name, short name, description, locale, email, and icon are profile-driven; only review this file for host/ads behavior.
- `wp-content/mu-plugins/kepoli-newsletter.php`: admin labels stay English and visible fallback source labels read the site profile; internal function names can stay.
- `seed/bootstrap.php`: imports `content/site-profile.json` into the `kepoli_site_profile` option and seeds title, tagline, page slugs, locale, and writer identity from that profile. A quick manual review is still wise, but new clones should not need direct seed-code identity edits.

The folder name `wp-content/themes/kepoli`, PHP function prefixes like `kepoli_`, CSS classes, and text domain can stay for the first clone. Renaming all internal handles is cosmetic and riskier than useful. Rebrand the visible text first.

If the new site switches language, run the mechanical clone scripts first, then do one calm public-copy pass through the theme and editor screens before launch. The scripts cover slugs, template filenames, env defaults, starter pages, and brand identity, but they do not promise a perfect translation of every UI sentence.

## Content Reset

Do not reuse the Dr Purg Jr. launch posts or images on the new site. For search, social platforms, ad networks, and reader trust, the new blog should look like a real independent publication, not a copy.

Replace these:

- `content/categories.json`: new categories and descriptions for the new niche.
- `content/site-profile.json`: brand, locale, writer, email, optional Gravatar profile URL, and canonical public slugs.
- `content/pages.json`: new Home, Recipes, Guides, About, Author, Contact, Privacy, Cookies, Advertising, Editorial Policy, Terms, and Disclaimer text.
- `content/posts.json`: new original posts, slugs, excerpts, tags, article data, related links, SEO titles, and meta descriptions.
- `content/image-plan.json`: new image prompts/metadata for the new posts.
- `content/images/*`: new featured images that match the new articles.

Keep the same JSON structure so the existing seed system and editor automation continue to work.

## Launch Checklist

- Create the new GitHub repo from this project, then update the files above before the first deployment.
- Do not copy `env.tmp`; it is local only.
- In Coolify, create a fresh project/app and use the new repo.
- Add both `new-domain.com` and `www.new-domain.com` in DNS and Coolify, then keep the canonical redirect host set to `www.new-domain.com`.
- Connect Site Kit fresh for the new domain if you plan to use Google services.
- Keep `ADSENSE_ENABLE=0` while the site is under review. That still applies even if you plan to start with Ezoic and add AdSense later.
- Publish enough original posts with real featured images before submitting to any ad network or sending traffic.

## Social And Ad Reset

For a new clone, create fresh accounts or zones instead of reusing Dr Purg Jr. credentials:

- Facebook Page, Meta App, Page token, and posting permissions.
- Pinterest boards and account workflow.
- Reddit account/subreddit notes.
- Pixazo key if the clone will test AI images.
- Monetag site and zones.
- Adsterra or other display/native zones.
- Histats project.
- Search Console property.

Keep all snippets in Coolify environment variables and keep source defaults disabled.

## Quick Checks Before Deployment

Run these in the new repo after replacing content:

```powershell
node scripts/audit-replica-readiness.mjs --min-posts 20
node scripts/verify-content.mjs
node scripts/image-status.mjs
node scripts/audit-rebrand.mjs
node scripts/audit-rebrand.mjs --old-brand Dr Purg Jr. --old-domain health.ibnbatoutaweb.com --old-email contact@health.ibnbatoutaweb.com
git diff --check
```

Run `node scripts/audit-adsense-readiness.mjs` only when the new site is actually preparing for AdSense.

If you want a manual second look, search for old identity leftovers too:

```powershell
Get-ChildItem -Recurse -File |
  Where-Object { $_.FullName -notmatch '\\.git\\' -and $_.Name -ne 'env.tmp' } |
  Select-String -Pattern 'Dr Purg Jr.','health.ibnbatoutaweb.com','contact@old-domain'
```

Public leftovers should be fixed. Internal code handles can wait unless you want a deeper white-label cleanup later.
