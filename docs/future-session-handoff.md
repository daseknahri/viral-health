# Future Session Handoff

Use this when a future Codex session opens the `viral-health` repo and needs to continue the Dr Purg Jr. work without mixing it with the separate food site or another clone.

## Repo Identity

- Local path: `C:\Users\user\OneDrive\Documents\viral-health`
- Remote: `https://github.com/daseknahri/viral-health.git`
- Production domain: `https://health.ibnbatoutaweb.com`
- Brand: `Dr Purg Jr.`
- Public language: English, `en_US`
- Admin language: English, `en_US`
- Audience country: United States
- Niche: shocking health facts, body-signal explainers, food and habit myths, sleep and energy context

The current open `New project` workspace may be the separate food-site repo. Dr Purg Jr. work belongs here.

## Current Stack

- WordPress plus MariaDB through Docker Compose.
- Custom theme folder: `wp-content/themes/kepoli/`.
- Author plugin: `wp-content/plugins/kepoli-author-tools/`.
- Social plugin: `wp-content/plugins/dr-purg-social-syndicator/`.
- MU plugins:
  - `kepoli-autoseed.php`: first-launch seed protection.
  - `kepoli-adtech.php`: canonical redirects, ad verification, `ads.txt`, `sw.js`, and `security.txt`.
  - `kepoli-newsletter.php`: native newsletter signup storage.
- Seed and content:
  - `seed/bootstrap.php`
  - `seed/version.php`
  - `content/site-profile.json`
  - `content/pages.json`
  - `content/categories.json`
  - `content/posts.json`
  - `content/image-plan.json`
  - `content/images/`

Internal names such as `kepoli_` are legacy engine handles. Do not rename them casually.

## Operating Defaults

Source defaults stay conservative:

```env
ADSENSE_ENABLE=0
GA_ENABLE=0
DISPLAY_ADS_ENABLE=0
MONETAG_ENABLE=0
HISTATS_ENABLE=0
HISTATS_EXCLUDE_ADMINS=1
KEPOLI_AUTOSEED_ENABLE=1
KEPOLI_FORCE_RESEED=0
AI_EXTRACTION_ENABLE=0
SOCIAL_AI_ENABLE=0
```

Live Coolify can enable controlled ad or AI tests, but those values should remain documented in the relevant ad or social docs. Do not commit secrets.

## Daily Publishing Workflow

1. Create a health-facts article draft outside WordPress.
2. Paste title and plain article content into `Posts` > `Add New`.
3. Choose `Article`.
4. Run `Auto fill`.
5. Review title, excerpt, meta description, category, tags, related links, image metadata, and health disclaimers.
6. Publish.
7. WordPress redirects to `Social Queue` / `Social Editor`.
8. Generate local social cards from the featured image or deliberately test Pixazo.
9. Review Facebook, Pinterest, and Reddit fields.
10. Post to Facebook only after preview review. Keep Pinterest and Reddit manual unless API work is intentionally added later.

The full extraction path is documented in `docs/content-machine-extraction-map.md`.

## Social And Image Workflow

- Social Syndicator is manual-first.
- Facebook Page API posting is available from the Social Editor when credentials are configured.
- Facebook summaries should not include the article URL.
- The default Facebook first comment carries the CTA and article URL.
- Local image conversion is the normal path for Facebook, Pinterest, and OG/Reddit images.
- Pixazo is optional testing only and requires a key in Social Settings.
- Reddit media is mainly a preview/reference field because Reddit link posts normally pull Open Graph images.

Docs:

- `docs/social-syndicator.md`
- `docs/image-generation.md`

## Ads And Revenue Workflow

The site is built for controlled instant/native ad testing, not blind aggressive monetization.

Rules:

- Ads are controlled only through Coolify env variables.
- Provider snippets must be base64 encoded.
- Add one placement at a time.
- Watch mobile layout, Facebook reach, redirects, complaint risk, and finalized revenue.
- Keep legal, policy, contact, search, 404, feed, and admin views clean.
- If bad ads appear, pause the most intrusive format first.

Docs:

- `docs/ad-code-inventory.md`
- `docs/ad-operations-manual.md`
- `docs/ads-optimization-playbook.md`
- `docs/monetag-readiness.md`
- `docs/display-ads-readiness.md`
- `docs/histats-readiness.md`

## Deployment Rules

- Coolify should use only `docker-compose.yml`.
- Domain routes to service `wordpress`, internal port `80`.
- Do not publish host port `80` in production.
- Keep `KEPOLI_FORCE_RESEED=0` on normal redeploys.
- Do not run the seed profile after real content exists unless doing an intentional repair.
- If a repair requires reseeding, set `KEPOLI_FORCE_RESEED=1` temporarily, run the repair, then immediately set it back to `0`.

Docs:

- `docs/coolify.md`
- `.env.example`

## Replication Path

This repo can be replicated whenever a new viral-content blog is needed.

Use `docs/replicate-food-blog.md` as the clone guide. The filename is historical; the current guide should be read as a generic replication path for this reusable WordPress engine.

For a new clone:

1. Create a new GitHub repo.
2. Run `scripts/prepare-replica.mjs` with the new brand, domain, writer, email, project slug, language, and slugs.
3. Run `scripts/reset-replica-content.mjs --write --delete-images`.
4. Run `scripts/generate-replica-shell.mjs --write`.
5. Replace pages, categories, posts, images, image plan, ad codes, social credentials, and site-specific docs.
6. Run replica and preflight checks.

Do not reuse Dr Purg Jr. launch posts, images, ad codes, Facebook credentials, Pixazo settings, or social page credentials in a new site.

## Validation Commands

Before push:

```powershell
node scripts\preflight-launch.mjs
git diff --check
```

Focused checks:

```powershell
node scripts\audit-rebrand.mjs
node scripts\verify-content.mjs
node scripts\image-status.mjs
node scripts\audit-social-syndicator-readiness.mjs
node scripts\audit-ad-ops-readiness.mjs
```

Live deploy check:

```powershell
node scripts\preflight-launch.mjs --live https://health.ibnbatoutaweb.com
```

Run the live check only after temporarily setting `KEPOLI_DEPLOY_FINGERPRINT=1`; turn it off again afterward.

## Known Safe Next Work

- Keep improving social field generation as reviewed suggestions.
- Add a performance log for Facebook hook, post URL, UTM, clicks, RPM, finalized revenue, and notes.
- Add stronger social image QA around overlay text length and crop safety.
- Add a monthly topic calendar for viral health-facts posts.
- Harden clone scripts and docs whenever a new sibling site is launched.

## Do Not Do By Accident

- Do not edit the separate food-site repo when working on Dr Purg Jr.
- Do not commit Coolify secrets or API keys.
- Do not enable aggressive ads in source defaults.
- Do not add health claims that imply diagnosis, cure, treatment, prevention, or personal medical advice.
- Do not bypass the Social Editor review step.
- Do not set `KEPOLI_FORCE_RESEED=1` permanently.
