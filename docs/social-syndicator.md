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

## Social image converter

The converter never edits the original featured image. It creates separate JPG attachments in the Media Library and assigns them back to the social package:

```text
Facebook photo: 1080x1350
Pinterest pin: 1000x1500
OG / Reddit preview: 1200x630
```

The local converter is the preferred daily workflow because it uses no external API, no image-generation quota, and no prompt token budget. It makes a full-bleed crop from the featured/source image, then can add a centered poster-style overlay from the `Overlay text` field. The overlay uses large text with outline and shadow instead of a solid background panel, so the image stays visible. Keep that overlay short, usually a curiosity hook of 8 to 12 words.

The Facebook and Pinterest variants are assigned to their platform media fields. The OG / Reddit variant is assigned to the Reddit media field and is used by the theme's Open Graph image filters when the public post page is scraped.

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
- Reddit remains manual-only to avoid account and subreddit spam risk.
- Text-generation helpers are still future work; Pixazo is image-generation only.
