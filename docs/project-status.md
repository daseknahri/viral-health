# Project Status

This is the short handoff note for Dr Purg Jr. For a future-session continuation guide, read `docs/future-session-handoff.md`.

## Current Role

This repo is now the English, Facebook/mobile-first health-facts site for `health.ibnbatoutaweb.com`. It is built from the shared engine, but the public identity, pages, categories, article pack, and images are all Dr Purg Jr. specific.

The repo can still be replicated for another viral-content blog. Use `docs/replicate-food-blog.md` for the clone workflow, then replace all Dr Purg Jr. identity, content, images, ad codes, and social credentials.

## Production Defaults

```env
ADSENSE_ENABLE=0
KT_AD_MODE=baseline
KT_PRELANDER_ENABLE=0
KEPOLI_AUTOSEED_ENABLE=1
KEPOLI_FORCE_RESEED=0
DISPLAY_ADS_ENABLE=0
MONETAG_ENABLE=0
MONETAG_POST_ONLY=1
HISTATS_ENABLE=0
HISTATS_EXCLUDE_ADMINS=1
```

Keep AdSense IDs blank. Add instant/native ad snippets only as base64 Coolify variables, one slot at a time. Keep `MONETAG_ONCLICK_BASE64`, `MONETAG_VIGNETTE_BASE64`, `MONETAG_PUSH_BASE64`, `DISPLAY_AD_HEADER_BASE64`, `DISPLAY_AD_CARD_GRID_BASE64`, `DISPLAY_AD_SIDEBAR_BASE64`, and `DISPLAY_AD_STICKY_BOTTOM_BASE64` empty unless running a deliberate short test.

## Content Workflow

- Admin stays English.
- Public content stays English.
- Use the external AI prompt to generate only title and clean plain-text article content.
- In WordPress, choose `Article`, then use `Auto fill`.
- For long posts, use `Auto split` or `2 parts` / `3 parts`.
- Smart split is tuned for monetization tests: `420+` words becomes 2 parts and `1100+` words becomes 3 parts when the split improves reading flow.
- After first publish, finish the post in `Social Queue` / `Social Editor`.
- Generate platform images locally first; use Pixazo only as a deliberate test.

## Ad Workflow

- Control ads only from Coolify env.
- Do not paste ad code into WordPress widgets, posts, plugins, or theme editor.
- Add one ad slot at a time and wait at least 48 hours or enough Facebook clicks before judging.
- If redirects or bad ads appear, pause Monetag first, then reading-option/below-content display slots.
- Health content must not promise cures, diagnoses, treatment outcomes, or personal medical guidance.

## Deployment Rules

- Normal redeploys must not reseed content.
- Keep `KEPOLI_FORCE_RESEED=0` unless intentionally repairing seed data.
- If a repair needs reseed, set `KEPOLI_FORCE_RESEED=1` temporarily, run the repair, then immediately set it back to `0`.

## Checks Before Push

```powershell
node scripts\preflight-launch.mjs
git diff --check
```

For live deploy checks, temporarily set `KEPOLI_DEPLOY_FINGERPRINT=1`, redeploy, run `node scripts\preflight-launch.mjs --live https://health.ibnbatoutaweb.com`, then turn the fingerprint off.

## Key Docs

- `docs/future-session-handoff.md`: complete continuation guide for future sessions.
- `docs/growth-engine-roadmap.md`: phased build plan for content, distribution, Claude AI, and monetization (with the no-fraud / no-ToS-evasion guardrails). All five phases shipped.
- `docs/operator-playbook.md`: the daily/weekly operating loop (Calendar → write → package → post → log → measure) plus the curiosity-hook writing principles for clicks.
- `docs/content-machine-extraction-map.md`: article, image, social, and ad workflow map.
- `docs/ai-content-growth-strategy.md`: future AI, content, Facebook, SEO, and monetization direction.
- `docs/site-brief-dr-purg-jr.md`: launch brief, audience, content guardrails, and ad strategy for this clone.
- `docs/social-syndicator.md`: Facebook/Pinterest/Reddit social package workflow.
- `docs/ad-operations-manual.md`: daily ad operations and pause order.
- `docs/ad-code-inventory.md`: provider snippets and base64 values.
- `docs/ads-optimization-playbook.md`: testing strategy and stop rules.
- `docs/author-workflow.md`: posting and auto-fill workflow.
- `docs/coolify.md`: deployment and seed safety rules.
- `docs/replicate-food-blog.md`: historical filename, now the clone guide for future blogs from this engine.
