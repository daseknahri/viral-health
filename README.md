# Dr Purg Jr. WordPress Blog

Dr Purg Jr. is a GitHub-driven WordPress health-facts blog for English-speaking, mobile-first readers in the United States. The repo contains the Docker Compose stack, custom theme, launch content, image plan, featured images, social syndication tools, ad controls, and WP-CLI bootstrap used by Coolify. For current operating status use `docs/project-status.md`; for future-session handoff use `docs/future-session-handoff.md`; for the article-to-social workflow use `docs/content-machine-extraction-map.md`.

## What This Repo Builds

- WordPress with MariaDB, deployed by Docker Compose with Dr Purg Jr.-specific images built from this repo.
- A reusable internal `kepoli` theme focused on reading, article discovery, internal links, newsletter capture, and ad-ready layouts.
- Production Apache settings for static asset caching, compression, and small security headers.
- A one-shot `wp-init` seed profile for first launch, plus a guarded self-seeding MU plugin for platforms that skip the profile service.
- A compact authoring plugin that keeps the WordPress admin in English and helps fill SEO, image, article, internal-link, and split-post fields.
- Google Site Kit installation for later Search Console, Analytics, or AdSense connection from WordPress admin.
- Env-gated monetization defaults: AdSense stays off, and instant/native ad providers stay controlled by environment variables.
- A documented clone path for future viral-content blogs that reuse the shared engine with a new identity, content pack, media, ad accounts, and social credentials.

## Content Status

- 20 original English launch posts: all health-fact articles built for Facebook/mobile readers.
- Site identity, public locale, admin locale, writer identity, identity asset basenames, and canonical slugs are stored in `content/site-profile.json`.
- 12 public pages: home, health facts, guides, about, author, contact, privacy, cookies, advertising/consent, editorial policy, terms, and health disclaimer.
- 20 matching featured-image files in `content/images/`.
- Image metadata and generation prompts are stored in `content/image-plan.json`.

## Coolify

1. Push this repo to GitHub.
2. In Coolify, create a Docker Compose application from the GitHub repo.
3. Use only `docker-compose.yml`.
4. Add the environment variables from `.env.example`.
5. Assign `https://health.ibnbatoutaweb.com` to the `wordpress` service on internal port `80`. The compose file also exposes `SERVICE_FQDN_WORDPRESS_80=https://health.ibnbatoutaweb.com` for Coolify's proxy routing. Do not add a production `ports:` mapping; Coolify can run many apps on internal port `80` because routing is domain-based.
6. Keep the `seed` profile disabled for normal deploys. WordPress self-seeds only on first launch, before real content exists.
7. After launch, keep `KEPOLI_FORCE_RESEED=0`. Set `KEPOLI_FORCE_RESEED=1` only for an intentional one-time repair, then turn it off again.
8. Enable GitHub auto-deploy on push.

The `CANONICAL_REDIRECT_HOSTS` value should include hostnames that may reach the app, such as `www.health.ibnbatoutaweb.com`. The MU plugin redirects those hosts to `SITE_URL` so readers and search engines see one canonical site.

If you need to manually reseed after launch, run:

```sh
docker compose --profile seed run --rm wp-init
```

## Monetag Notes

This clone is currently the controlled instant-monetization test site. Monetag is disabled by default and should be enabled only from Coolify:

```env
ADSENSE_ENABLE=0
MONETAG_ENABLE=0
MONETAG_INPAGE_PUSH_BASE64=
MONETAG_VIGNETTE_BASE64=
MONETAG_ONCLICK_BASE64=
MONETAG_PUSH_BASE64=
MONETAG_INPAGE_PUSH_MINUTES=0
MONETAG_VIGNETTE_MINUTES=0
MONETAG_ONCLICK_MINUTES=0
MONETAG_PUSH_MINUTES=0
MONETAG_POST_ONLY=1
MONETAG_INSTALL_CHECK=0
MONETAG_SW_JS_BASE64=
```

When Monetag is enabled, the theme renders configured individual snippets or one Multitag only on public single posts and never for logged-in admins, search, 404, feeds, static pages, or legal/policy pages. The homepage remains clean unless `MONETAG_INSTALL_CHECK=1` is temporarily enabled for Monetag's checker. The MU plugin serves Monetag's optional HTTPS service worker at `/sw.js` from `MONETAG_SW_JS_BASE64` or the bundled `content/monetag/sw.js` file. Legacy direct script env vars are intentionally removed; use the base64 per-format variables only. Use `docs/monetag-readiness.md` for the exact dashboard setup, traffic ramp, and acceptance checks.

## Display Ad Notes

Dr Purg Jr. also has an env-gated native/display layer for instant providers such as Adsterra. It stays disabled by default:

```env
DISPLAY_ADS_ENABLE=0
DISPLAY_ADS_PROVIDER=adsterra
DISPLAY_AD_AFTER_INTRO_BASE64=
DISPLAY_AD_MID_CONTENT_BASE64=
DISPLAY_AD_BELOW_CONTENT_BASE64=
DISPLAY_AD_STICKY_BOTTOM_BASE64=
DISPLAY_AD_STICKY_BOTTOM_MIN_SECONDS=35
DISPLAY_AD_STICKY_BOTTOM_MIN_SCROLL=30
DISPLAY_AD_STICKY_BOTTOM_COOLDOWN_MINUTES=30
DISPLAY_AD_SIDEBAR_BASE64=
```

Provider snippets must be base64-encoded before being added to Coolify. The theme renders configured display slots only on public single posts, with automatic after-intro and mid-content placements. The optional sticky bottom slot is mobile-only and delayed by time plus scroll gates. See `docs/display-ads-readiness.md`.

## Ezoic Notes

Ezoic support remains available for a later test. Keep direct AdSense placements disabled during any third-party network review:

```env
ADSENSE_ENABLE=0
GA_ENABLE=0
```

After approval, configure Ezoic from the dashboard or through the integration method Ezoic recommends for the account. Confirm the final `ads.txt` setup from Ezoic before enabling live monetization.

For Ezoic Ads.txt Manager, set one of these in Coolify after Ezoic gives the value:

```env
EZOIC_ADSTXT_ACCOUNT_ID=19390
# or
EZOIC_ADSTXT_REDIRECT_URL=https://srv.adstxtmanager.com/19390/health.ibnbatoutaweb.com
```

## Newsletter

The newsletter signup is a small native WordPress form on the front page and the About Dr Purg Jr. page. Signups are stored in WordPress admin under `Newsletter`, where they can be reviewed or exported as CSV.

## Author Writing

The `kepoli-author-tools` plugin keeps the writing workflow simple for beginner publishers. It can auto-fill excerpt, meta description, related links, featured-image metadata, category suggestions, tags, FAQ blocks, and post splits. See `docs/author-workflow.md` for the exact writing flow.

Optional OpenRouter AI repair can be enabled later for messy extraction workflows. Keep `AI_EXTRACTION_ENABLE=0` unless `AI_EXTRACTION_API_KEY` is set in Coolify; when enabled, the plugin still parses locally first and only asks AI to repair incomplete structured fields.

## Checks

Run these before pushing a launch change:

```sh
node scripts/preflight-launch.mjs
```

To include the live site too:

```sh
node scripts/preflight-launch.mjs --live https://health.ibnbatoutaweb.com
```

## Media

The current repo includes SVG logo assets and generated starter featured images. If you add exact bitmap brand assets later, place them at:

- `wp-content/themes/kepoli/assets/img/dr-purg-jr-wordmark.png`
- `wp-content/themes/kepoli/assets/img/dr-purg-jr-icon.png`
- `wp-content/themes/kepoli/assets/img/writer-photo.jpg`

The theme automatically prefers those filenames when present.

For future clones, `content/site-profile.json` can point `assets.wordmark`, `assets.icon`, and `assets.social_cover` to different basenames.
