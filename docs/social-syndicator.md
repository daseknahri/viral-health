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

## Social image converter

The converter never edits the original featured image. It creates separate JPG attachments in the Media Library and assigns them back to the social package:

```text
Facebook photo: 1080x1350
Pinterest pin: 1000x1500
OG / Reddit preview: 1200x630
```

The local converter is the preferred daily workflow because it uses no external API, no image-generation quota, and no prompt token budget. It makes a full-bleed crop from the featured/source image, then can add a centered poster-style overlay from the `Overlay text` field. The overlay uses large text with outline and shadow instead of a solid background panel, so the image stays visible. Keep that overlay short, usually a curiosity hook of 8 to 12 words.

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
