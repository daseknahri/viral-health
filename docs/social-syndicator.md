# Dr Purg Jr. Social Syndicator

The Social Syndicator plugin turns every article into a structured social package after first publish. It is intentionally manual-first: Facebook can post through the API, while Pinterest and Reddit stay as reviewed/copy-ready sections until dedicated API work is added.

## Workflow

1. Write and publish a post.
2. WordPress opens `Social Queue -> Social Editor` for that post.
3. Review the source title, intro, featured image, and canonical URL.
4. Click `Generate social images` to create platform-sized JPG copies from the source image.
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

The generated image uses the source image fitted inside the target canvas with a soft blurred background, so faces and text are less likely to be cropped. The Facebook and Pinterest variants are assigned to their platform media fields. The OG / Reddit variant is used by the theme's Open Graph image filters when the public post page is scraped.

## Safety

- Duplicate Facebook posting is blocked after a remote post ID exists.
- Use `Reset Facebook posting lock` only when you intentionally want to repost.
- Failed API responses are saved in the Facebook section.
- Generated social images are new attachments; the original featured image is not modified.
- Reddit remains manual-only to avoid account and subreddit spam risk.
- AI generation buttons are intentionally not part of v1; the structured fields are ready for later AI helpers.
