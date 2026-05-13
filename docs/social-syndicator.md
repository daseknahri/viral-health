# Dr Purg Jr. Social Syndicator

The Social Syndicator plugin turns every article into a structured social package after first publish. It is intentionally manual-first: Facebook can post through the API, while Pinterest and Reddit stay as reviewed/copy-ready sections until dedicated API work is added.

## Workflow

1. Write and publish a post.
2. WordPress opens `Social Queue -> Social Editor` for that post.
3. Review the source title, intro, featured image, and canonical URL.
4. Complete the Facebook, Pinterest, and Reddit sections.
5. Click `Post to Facebook` only after the Facebook preview is ready.
6. Use copy buttons and `Mark posted` for Pinterest or Reddit manual work.

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

## Safety

- Duplicate Facebook posting is blocked after a remote post ID exists.
- Use `Reset Facebook posting lock` only when you intentionally want to repost.
- Failed API responses are saved in the Facebook section.
- Reddit remains manual-only to avoid account and subreddit spam risk.
- AI generation buttons are intentionally not part of v1; the structured fields are ready for later AI helpers.
