# Go-Live Setup — Social Syndicator

Everything built into the Social Syndicator is **off by default** and turns on with environment variables (in Coolify) or tokens (in `Social Queue → Settings`). This is the one checklist to make each part work. Do only the parts you want; nothing here is required for the blog to run.

Secrets live in Coolify env or WordPress settings — never commit them. The matching example keys are in `.env.example`.

## 0. Deploy first (always)

1. Push/pull `main` and redeploy in Coolify.
2. Confirm the plugin still activates (Plugins screen has no fatal error). `node scripts/preflight-launch.mjs` runs `php -l` on every file before you deploy, so a parse error is caught locally first — set `PHP_BIN` to a php binary to enable that step.
3. Open `Social Queue` in wp-admin. You should see submenus: Social Queue, Cockpit, Calendar, Performance, Settings.

## 1. AI (drafts, hook variants, calendar ideas)

Coolify env:

```env
SOCIAL_AI_ENABLE=1
SOCIAL_AI_PROVIDER=anthropic        # or openrouter
SOCIAL_AI_API_KEY=sk-ant-...        # the matching provider key
SOCIAL_AI_MODEL=claude-opus-4-8     # or claude-haiku-4-5 for cheap volume
SOCIAL_AI_HOOK_VARIANTS=4
SOCIAL_AI_CALENDAR_IDEAS=8
```

Test: Social Editor → `Generate AI social draft` and `Generate hook variants`; Calendar → `Generate ideas`. Every output is a draft you review.

## 2. Link tracking (UTM) — needed for the measurement loop

```env
SOCIAL_UTM_ENABLE=1
SOCIAL_UTM_FACEBOOK_MEDIUM=group
SOCIAL_UTM_PINTEREST_MEDIUM=pin
SOCIAL_UTM_REDDIT_MEDIUM=social
```

Test: Social Editor shows the `Tracked links` panel; posted Facebook first comments carry `utm_*`. Turn this on **before** you rely on Performance numbers, so clicks attribute by `utm_campaign` (= post slug).

## 3. Local social cards (no setup, optional tuning)

Works with no config. Optional env:

```env
SOCIAL_CARD_SCRIM=28          # contrast scrim strength (0 disables)
SOCIAL_CARD_BRAND=            # blank = use the WP site name
```

Per-post controls in the converter: overlay text + position (top/center/bottom), crop focus (keep top/center/bottom), and `Preview card` to see it before generating. Test: `Preview card`, then `Generate local social cards`. (Needs the PHP **GD** extension, which standard WordPress hosts have.)

## 4. Facebook Page posting + scheduling

In `Social Queue → Settings`:
- Facebook Page ID, Meta App ID, Meta App Secret, Long-lived Page Access Token, Graph API version.
- The Page token needs scopes: `pages_manage_posts`, `pages_read_engagement`, and `pages_manage_engagement` (for first comments).

Test (use a throwaway/test time first):
- `Post to Facebook` publishes now and adds the tracked first comment.
- `Schedule Facebook post` — pick a time 10 min–75 days out; status becomes `Scheduled`. **After it goes live**, open the post and click `Post first comment` to add the link (Facebook does not allow commenting on an unpublished post).
- Group posting stays manual via the **Cockpit** (copy caption/comment/link, paste, then `Mark posted`).

## 5. Pinterest API publishing (optional)

In `Social Queue → Settings`:
- **Pinterest access token** (API v5, scopes `boards:read`, `pins:read`, `pins:write`).
- **Pinterest board ID** (the default board to publish to).

Pinterest requires app approval for production API access. Until both fields are set, Pinterest stays copy-and-paste manual. Test: Pinterest section → `Publish to Pinterest` (needs a selected Pinterest image).

## 6. Performance log → which hooks win

Three ways to fill it, cheapest first:

- **Manual**: Social Editor → Performance section (clicks, RPM, revenue, notes).
- **CSV import**: Performance page → `Import from CSV`. Upload a GA4 export (or any sheet) with a campaign/slug column plus clicks/sessions and optionally RPM/revenue. Matched by slug = `utm_campaign`.
- **GA4 auto-pull** (optional):

```env
GA4_ENABLE=1
GA4_PROPERTY_ID=123456789     # numeric, GA4 Admin -> Property Settings
GA4_SA_JSON=...               # service-account JSON (raw) or a path to the key file
GA4_LOOKBACK_DAYS=28
```

GA4 setup: create a Google Cloud **service account** → download its JSON key → enable the **Google Analytics Data API** in that project → add the service-account email as a **Viewer** on the GA4 property. Then Performance → `Pull from GA4` writes sessions-by-campaign as clicks. (Revenue/RPM still come from CSV or manual entry.)

## Verification status (important)

Everything is parse-clean (`php -l`, 24 files) and the image/card rendering and the GA4 JWT signing were verified by actually running them. The pieces that call **external services** — Facebook Graph API, Pinterest API, the GA4 Data API, and the editor's live-preview AJAX — cannot be exercised without live credentials/WordPress, so give each a quick click-test after deploy and report any error message; the failures are contained (they never break the rest of the plugin).

## Quick map: feature → where to turn it on

| Feature | Turn on in | Key(s) |
|---|---|---|
| AI drafts / hooks / ideas | Coolify env | `SOCIAL_AI_ENABLE` (+provider, key, model) |
| UTM tracking | Coolify env | `SOCIAL_UTM_ENABLE` |
| Card scrim / brand | Coolify env | `SOCIAL_CARD_SCRIM`, `SOCIAL_CARD_BRAND` |
| FB post + schedule | Settings page | Page ID + token (+ scopes) |
| Pinterest publish | Settings page | access token + board ID |
| Performance CSV | Performance page | (upload, no key) |
| GA4 auto-pull | Coolify env | `GA4_ENABLE`, `GA4_PROPERTY_ID`, `GA4_SA_JSON` |
