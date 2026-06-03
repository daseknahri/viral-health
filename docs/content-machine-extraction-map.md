# Dr Purg Jr. Content Machine Extraction Map

This document maps how Dr Purg Jr. turns an article idea into a WordPress post, social package, platform images, and controlled monetization surface. Use it as the working reference for future author tooling, AI helpers, social automation, and clone work.

## Current Flow

1. Plan the article outside WordPress.
   - Use a title, hook, short intro angle, category, tags, click reason, image idea, social hooks, and sources to check.
   - For health topics, sources are for claim checking, not for copying.
2. Draft outside WordPress.
   - Return a title plus clean plain-text article content.
   - Do not include HTML, fake sources, fake doctor authority, diagnosis language, treatment instructions, or ad instructions.
3. Paste into WordPress.
   - Posts use the classic editor through `wp-content/plugins/kepoli-author-tools/`.
   - Public content is English and admin UI is English.
4. Choose `Article`.
   - The underlying engine still has recipe-compatible fields, but Dr Purg Jr. is an article-first health-facts site.
5. Run `Auto fill`.
   - The author plugin fills excerpt, meta description, SEO title, category, tags, image metadata, related links, heading cleanup, and optional split support.
6. Publish.
   - On first publish, `wp-content/plugins/dr-purg-social-syndicator/` creates social drafts and opens the Social Editor for review.
7. Finish social packaging.
   - Review Facebook, Pinterest, and Reddit sections.
   - Generate local platform images from the featured image or use Pixazo test images when deliberately requested.
   - Facebook API posting is reviewed/manual. Pinterest and Reddit are structured manual sections in this version.
8. Track and monetize carefully.
   - Ads are controlled by Coolify environment variables only.
   - Start with one placement at a time and keep legal/policy/admin surfaces clean.

## Source Inputs

The content system uses:

- WordPress title.
- Main article body.
- Selected post kind, usually `Article`.
- Featured image ID and attachment metadata.
- Existing category and tags.
- Existing posts for related-link suggestions.
- `content/image-plan.json` for launch image prompts and metadata.
- `content/site-profile.json` for brand, writer, locale, asset basenames, and canonical slugs.
- Optional AI env variables for author extraction and social draft suggestions.

The social system adds:

- Canonical URL.
- Article excerpt or intro.
- Featured image URL and selected platform media IDs.
- Facebook hook, summary, first comment, and remote post state.
- Pinterest title, description, board, URL, media, and alt text.
- Reddit subreddit, title, body, link, rules notes, and preview media.
- Overlay and bottom-hint text for generated social cards.

## Main Author Metadata

The author-tools plugin stores post setup in existing engine keys:

- `_kepoli_post_kind`: article or recipe-compatible kind.
- `_kepoli_seo_title`: optional SEO title.
- `_kepoli_meta_description`: SEO and social description fallback.
- `_kepoli_related_article_slugs`: related article links.
- `_kepoli_related_recipe_slugs`: compatibility field.
- `_kepoli_related_slugs`: combined fallback.
- `_kepoli_auto_split_parts`: requested WordPress `nextpage` split count.
- `_kepoli_image_alt`: pending image alt text.
- `_kepoli_image_title`: pending image title.
- `_kepoli_image_caption`: pending image caption.
- `_kepoli_image_description`: pending image description.
- `_kepoli_image_plan_*`: seed image plan fields copied from `content/image-plan.json`.

It also stores small auto-generated flags such as `_kepoli_auto_excerpt`, `_kepoli_auto_meta_description`, `_kepoli_auto_related_slugs`, `_kepoli_auto_image_meta`, and `_kepoli_auto_seo_title`.

## Social Metadata

The Social Syndicator plugin stores platform fields as post meta:

- `_dpj_social_facebook_hook`
- `_dpj_social_facebook_summary`
- `_dpj_social_facebook_link`
- `_dpj_social_facebook_first_comment`
- `_dpj_social_facebook_media_id`
- `_dpj_social_facebook_status`
- `_dpj_social_facebook_remote_post_id`
- `_dpj_social_facebook_remote_photo_id`
- `_dpj_social_facebook_comment_id`
- `_dpj_social_facebook_last_error`
- `_dpj_social_pinterest_title`
- `_dpj_social_pinterest_description`
- `_dpj_social_pinterest_board`
- `_dpj_social_pinterest_url`
- `_dpj_social_pinterest_alt_text`
- `_dpj_social_pinterest_media_id`
- `_dpj_social_pinterest_status`
- `_dpj_social_reddit_title`
- `_dpj_social_reddit_body`
- `_dpj_social_reddit_link`
- `_dpj_social_reddit_subreddit`
- `_dpj_social_reddit_rules_notes`
- `_dpj_social_reddit_media_id`
- `_dpj_social_reddit_status`
- `_dpj_social_local_overlay_text`
- `_dpj_social_local_overlay_enable`
- `_dpj_social_local_hint_text`
- `_dpj_social_local_hint_enable`
- `_dpj_social_og_media_id`
- `_dpj_social_skip`
- `_dpj_social_do_not_repost`

Generated image attachments also receive provider, variant, source, and size metadata so future cleanup can distinguish original featured images from social derivatives.

## Image Flow

Use the featured image as the source unless a post needs custom platform art.

Local social card sizes:

- Facebook photo: `1080x1350`
- Pinterest pin: `1000x1500`
- OG / Reddit preview: `1200x630`

Rules:

- The original featured image is never modified.
- Local generated cards become separate Media Library attachments.
- Overlay text should be short, usually 8 to 12 words.
- Bottom hint text can say `LINK IN FIRST COMMENT`; the renderer draws the arrow symbol instead of relying on server emoji fonts.
- Pixazo is optional and manual. It is for testing fresh platform images, not for automatic posting.

## AI Boundaries

Current AI use is optional and reviewed.

Author extraction:

- `AI_EXTRACTION_ENABLE=1` enables OpenRouter fallback for messy structured extraction.
- Keep it off by default unless an API key is configured.

Social drafts:

- `SOCIAL_AI_ENABLE=1` enables reviewed social field suggestions.
- It can fall back to `AI_EXTRACTION_*` variables when social-specific values are empty.
- The assistant must return strict JSON.
- It must not post, publish, or enable ads.
- Facebook summary should not include the article URL; the first comment carries the CTA link.

Future AI should remain button-driven and review-first.

## Health And Trust Guardrails

Dr Purg Jr. can use curiosity-led packaging, but the content must stay careful.

- Do not diagnose readers.
- Do not promise cures, prevention, treatment outcomes, or fast fixes.
- Do not imply a personal clinician relationship.
- Do not recommend medications, supplements, tests, or treatment changes as general instructions.
- Do not use fake medical credentials or fake source quotes.
- Encourage professional care for severe, sudden, persistent, worsening, or personal symptoms.
- Keep headlines clickable but make the article body calm and useful.

## Ads And Analytics Boundaries

Ads are env-controlled only.

- Never paste provider code into posts, widgets, the theme editor, or random plugins.
- Keep AdSense disabled unless the strategy changes.
- Add Monetag and Adsterra snippets as base64 Coolify variables only.
- Test one slot at a time.
- Keep homepage, search, 404, feeds, legal pages, policy pages, contact pages, and logged-in admin views clean.
- If redirects or bad ads appear, pause the most intrusive format first.

## Validation

Run before committing or deploying:

```powershell
node scripts\preflight-launch.mjs
git diff --check
```

Useful focused checks:

```powershell
node scripts\verify-content.mjs
node scripts\image-status.mjs
node scripts\audit-social-syndicator-readiness.mjs
node scripts\audit-ad-ops-readiness.mjs
node scripts\audit-monetag-readiness.mjs
node scripts\audit-display-ads-readiness.mjs
```

For live deploy checks, temporarily enable the deploy fingerprint and run:

```powershell
node scripts\preflight-launch.mjs --live https://health.ibnbatoutaweb.com
```

Turn `KEPOLI_DEPLOY_FINGERPRINT=0` again after the check.

## Replication Note

This repo can still be replicated for another viral-content blog. The internal engine keeps historical `kepoli_` handles and recipe-compatible fields, but a new site must receive its own:

- domain and repo;
- `content/site-profile.json`;
- public pages;
- categories;
- original post pack;
- image plan and images;
- theme identity assets;
- ad-network accounts and snippets;
- social platform credentials.

Start from `docs/replicate-food-blog.md`, but treat it as a generic clone guide for this viral engine, not a command to keep the new site food-only.
