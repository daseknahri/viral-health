# Dr Purg Jr. Social Syndicator

The Social Syndicator plugin turns every article into a structured social package after first publish. It is intentionally manual-first: Facebook can post through the API, while Pinterest and Reddit stay as reviewed/copy-ready sections until dedicated API work is added.

## Workflow

1. Write and publish a post.
2. WordPress opens `Social Queue -> Social Editor` for that post.
3. Review the source title, intro, featured image, and canonical URL.
4. Click `Generate local social cards` to create platform-sized JPG copies from the source image.
5. Complete the Facebook, Pinterest, and Reddit sections.
6. Click `Post to Facebook` only after the Facebook preview is ready.
7. Use copy buttons and `Mark posted` for Pinterest or Reddit manual work.

Scheduled posts are not redirected when cron publishes them. They appear in `Social Queue` with a `Needs social` status.

## Facebook Page API setup

Configure credentials in `Social Queue -> Settings`:

```text
Facebook Page ID
Meta App ID
Meta App Secret
Long-lived Page Access Token
Graph API version
Pixazo API key
```

AI social drafts use environment variables instead of storing another API key in WordPress:

```text
SOCIAL_AI_ENABLE=1
SOCIAL_AI_PROVIDER=openrouter
SOCIAL_AI_API_KEY=your-openrouter-token
SOCIAL_AI_MODEL=inclusionai/ling-2.6-1t:free
```

The existing `AI_EXTRACTION_*` variables are also accepted as fallbacks, so the same OpenRouter setup can power author tools and social drafting.

To use Claude instead, set `SOCIAL_AI_PROVIDER=anthropic`, put a Claude API key in `SOCIAL_AI_API_KEY`, and set `SOCIAL_AI_MODEL` to a Claude model. The default for the Anthropic provider is `claude-opus-4-8`; for high-volume social drafting `claude-haiku-4-5` is the cheap choice and `claude-sonnet-4-6` is the mid tier. The Anthropic path uses the Messages API with structured outputs, so the model is constrained to return valid JSON for the reviewed fields, and it keeps the same review-first behavior — it never posts. The same provider switch (`AI_EXTRACTION_PROVIDER=anthropic`) is available for the author-tools `Auto fill` extraction.

The Page token must be allowed to publish to the Facebook Page. Use a long-lived Page token with `pages_manage_posts` and `pages_read_engagement`. First-comment posting may also require `pages_manage_engagement`. The plugin sends reviewed posts to the Page only; it does not post to personal profiles.

Facebook posts use this message shape:

```text
Hook

Summary
```

The article URL is kept out of the main Facebook post text. New social drafts fill the first comment with this default CTA:

```text
Read the full article here: Article URL
```

If a social image is selected, the plugin posts through the Page photos endpoint using the selected image URL and caption. If no image is selected, it falls back to the Page feed endpoint with the reviewed text only. The first comment is posted after the Page post is created when the token has comment permission.

## AI social draft assistant

The Social Editor includes an optional `AI Social Draft Assistant`. It sends controlled source material to OpenRouter: article title, excerpt, categories, tags, featured image alt text, canonical URL, and a capped amount of article body text.

The model must return strict JSON for these reviewed fields:

```text
Facebook hook
Facebook summary
Facebook first comment
Pinterest title, description, and alt text
Reddit title and body
Overlay text
Bottom hint text
```

The assistant never posts to Facebook, Pinterest, or Reddit. It only fills draft fields. The plugin strips article URLs from the Facebook summary, keeps the link in the first comment, validates JSON, limits field lengths, and preserves duplicate-posting locks. The bottom-hint checkbox stays manual; AI can draft the hint text, but the editor chooses whether to show it on the generated images.

## Link tracking (UTM)

Set `SOCIAL_UTM_ENABLE=1` to turn on click attribution. When it is on:

- The Social Editor shows a `Tracked links (UTM)` panel near the top with a UTM-tagged link per platform and a copy button. Use those links when you post manually so Analytics (GA4 / Site Kit) can attribute the clicks.
- When you post to Facebook through the API, the article link inside the first comment is automatically rewritten to its tracked version at post time. The stored first-comment text stays clean and readable; only the posted copy is tagged.

Each link carries `utm_source` (the platform: `facebook`, `pinterest`, `reddit`), `utm_medium` (configurable per platform — defaults `group` / `pin` / `social`), and `utm_campaign` (the post slug). `utm_content` is reserved for the upcoming hook-variant testing. Tagging is idempotent: a link that already has a `utm_source` is left alone, so re-posting never double-tags.

```text
SOCIAL_UTM_ENABLE=1
SOCIAL_UTM_FACEBOOK_MEDIUM=group
SOCIAL_UTM_PINTEREST_MEDIUM=pin
SOCIAL_UTM_REDDIT_MEDIUM=social
```

When the flag is off, links are the plain canonical URL and nothing is rewritten, so the default behavior is unchanged.

## Posting cockpit

`Social Queue -> Cockpit` is the daily driver for manual posting. It lists every packaged, non-skipped post as a card with everything needed to post in seconds:

- the **caption** (hook + summary) and the **first comment** (with the tracked link applied) in copy-ready boxes;
- a **tracked link** field and the assigned **image**;
- copy buttons for each, plus an `Open editor` link;
- a **posting log**: type a group or destination name and click `Mark posted` to record where and when you posted. The log keeps the most recent 50 entries per post.

The cockpit deliberately does not post for you. You paste into Facebook (or another platform) yourself; the cockpit only removes the preparation work and keeps a record. This keeps the workflow inside platform rules — no automation posts to groups on your behalf.

## AI hook variants

The Social Editor's `AI hook variants` section generates several distinct Facebook hook angles for one article so you can A/B test them. Click `Generate hook variants` (needs the AI provider enabled, same as the draft assistant). The model returns `SOCIAL_AI_HOOK_VARIANTS` options (default 4, range 2–8), each with:

- a short **angle** label (curiosity gap, surprising number, common mistake, body signal, myth correction, …);
- a **hook** (under ~95 characters);
- an **overlay** version (5–9 words) for the image card;
- a `utm_content` tag (`v1`, `v2`, …).

`Apply to Facebook` copies a variant's hook into the Facebook hook field and its overlay into the overlay text, and marks it as the selected variant. When `SOCIAL_UTM_ENABLE=1`, the selected variant's `utm_content` tag is then carried into the Facebook first-comment link at post time, and each variant shows a copyable tracked link. That makes the `Performance` table able to compare which angle earned the clicks. All variants are drafts for review — nothing is posted automatically.

## Performance log

`Social Queue -> Performance` is the "which hooks won" view. It lists every post that has been posted to a platform or has recorded results, with its hook, UTM campaign (the post slug), Facebook posted date, clicks, RPM, and revenue, plus a totals line.

Record results per post in the Social Editor's `Performance` section:

- **Clicks** — read from Analytics, filtered by the post's `utm_campaign` (its slug). Pair this with `SOCIAL_UTM_ENABLE=1` so the links are tagged.
- **RPM (USD / 1k)** and **Revenue (USD)** — enter from your ad network once finalized.
- **Notes** — anything worth remembering about the hook, image, or groups used.

The section also shows the post's current hook, UTM campaign, and Facebook posted date for context. Nothing here is auto-pulled from ad networks yet; it is a manual, reviewed log so hook decisions are driven by real numbers instead of guesses.

## Content calendar

`Social Queue -> Calendar` plans the pipeline that feeds everything else. It is a lightweight idea backlog plus an AI brainstormer:

- **Brainstorm ideas with AI**: type an optional theme or seed (for example `sleep`, `gut health`, `winter`) and click `Generate ideas`. Claude returns `SOCIAL_AI_CALENDAR_IDEAS` curiosity-led, guardrail-safe topic ideas (default 8, range 3–20), each with a working title, an angle label, a hook seed, and a one-line rationale. To avoid repeats, the recent post titles are sent with the request so the model proposes genuinely new topics. Needs the AI provider enabled, same as the draft assistant.
- **Add an idea manually**: capture a title, angle, planned date, and hook seed without the AI.
- **Planned ideas**: each idea is a card you can schedule (a planned date), move through a status (`Idea → Planned → Drafted → Published`), annotate with notes, or delete. Cards sort by date (dated first, then undated) so the next thing to write is at the top.

Ideas are stored in a plugin option (`dpj_social_calendar`), separate from posts — they exist before an article does. Everything here is a reviewed draft: the calendar proposes and tracks topics, but writing and publishing stay manual. When an idea becomes an article, publish it as usual and the rest of the Social Syndicator workflow takes over.

## Social image converter

The converter never edits the original featured image. It creates separate JPG attachments in the Media Library and assigns them back to the social package:

```text
Facebook photo: 1080x1350
Pinterest pin: 1000x1500
OG / Reddit preview: 1200x630
```

The local converter is the preferred daily workflow because it uses no external API, no image-generation quota, and no prompt token budget. It makes a full-bleed crop from the featured/source image, then can add a centered poster-style overlay from the `Overlay text` field. The overlay uses large text with outline and shadow instead of a solid background panel, so the image stays visible. Keep that overlay short, usually a curiosity hook of 8 to 12 words.

Click `Preview card` to render a Facebook-sized preview from the current image and the overlay text, position, and hint you have on screen — *before* generating and without saving anything. It uses the same renderer as the real cards, so what you see is what you get; use it to check crop, overlay length, and position quickly instead of generating and re-checking.

The `Overlay position` select places the hook at the **top**, **center** (default), or **bottom** of the card. Use it to move the text off a face or a busy area of the photo. The contrast scrim follows the chosen position, and the bottom hint (when enabled) always stays pinned to the very bottom, so a bottom-positioned hook leaves room for it.

To keep the hook readable on bright or busy photos, a soft contrast scrim is drawn behind the overlay text (and behind the bottom hint). It is a feathered dark gradient centered on the text, not a solid bar, so the photo still shows through. Its strength is set by `SOCIAL_CARD_SCRIM` (a percent, 0–60; default 28). Set `SOCIAL_CARD_SCRIM=0` to turn it off entirely, or lower it for a subtler effect. The scrim only appears when the overlay or hint is enabled; plain cards with no text are untouched. Regenerate the cards after changing the value to see the new look.

When the Facebook link is planned for the first comment, enable `Add bottom hint for link-in-comment posts`. This adds a small outlined footer such as `LINK IN FIRST COMMENT` without covering the image with a solid bar. If you type a down-pointer emoji in the hint, the image renderer replaces it with a drawn arrow so the result does not depend on server emoji-font support.

The Facebook and Pinterest variants are assigned to their platform media fields. The OG / Reddit variant is assigned to the Reddit media field and is used by the theme's Open Graph image filters when the public post page is scraped.

## Image QA checks

The converter shows quality checks before you generate cards, so overlay text and crops are reviewed first:

- A live word counter under the `Overlay text` field flags text that is empty, too short for a hook, or longer than the 12-word limit that the cards keep. The ideal range is 8 to 12 words.
- An `Image QA checks` panel reflects the last saved overlay text and the current source image. It repeats the overlay length verdict and adds a per-variant crop report.
- The crop report lists the source resolution and, for each platform size, how much of the source the centered crop keeps and whether the source must be upscaled. A crop that keeps less than 60% of one side, or an upscale beyond about 15%, is flagged as a warning so you can pick a better source image or accept the trade-off deliberately.
- The panel also states the best source size: a portrait or square image at least `1200x1500` px. That is the largest width and height any local card needs, so a source at least that big never has to be upscaled. Wide landscape photos (for example a `1408x768` AI image) fit the `1200x630` OG / Reddit card well but crop heavily and upscale badly into the tall `1080x1350` Facebook and `1000x1500` Pinterest cards, which is exactly what the warnings catch.

The QA panel never blocks generation; it surfaces risk so the editor can fix overlay length or swap a low-resolution or awkwardly-proportioned source before posting. Save the post to refresh the saved-state checks.

The Reddit media field is mostly a workflow aid. For link posts, Reddit normally pulls the preview image from the article's Open Graph tags. The field gives you a visible reference and a copyable image URL for manual image-post workflows.

## Pixazo image testing

The Social Editor includes an optional `AI Image Generator` section for Pixazo SDXL v1.0 Free. Add the Pixazo API key in `Social Queue -> Settings`, then review or rewrite the prompt on each post before clicking `Generate Pixazo images`.

Pixazo generation creates three new Media Library attachments and assigns them to the platform fields:

```text
Facebook AI image: 819x1024
Pinterest AI image: 683x1024
OG / Reddit AI image: 1024x538
```

These dimensions keep the correct platform aspect ratios while staying inside the free SDXL test resolution range. Nothing is posted automatically; the generated images are drafts for review.

## Safety

- Duplicate Facebook posting is blocked after a remote post ID exists.
- Use `Reset Facebook posting lock` only when you intentionally want to repost.
- Failed API responses are saved in the Facebook section.
- Generated social images are new attachments; the original featured image is not modified.
- Local social cards are generated from reviewed source imagery and optional overlay text; disable the overlay checkbox when the image already contains important text.
- Pixazo images are generated only after a user clicks the button, and API errors stay inside the editor.
- AI social drafts are generated only after a user clicks the button, and every generated field must be reviewed before posting.
- Reddit remains manual-only to avoid account and subreddit spam risk.
- Pixazo is image-generation only.
