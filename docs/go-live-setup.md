# Go-Live Setup — Dr Purg Jr. (operator checklist)

Everything **you** do to make the full content engine run. Follow top to bottom. Most of the loop uses no paid services — secrets live only in **Coolify env**, never in `wp_options` or committed files, and every `*_ENABLE` flag defaults to `0`. Companion to `docs/operator-playbook.md` (the rhythm) and `docs/social-syndicator.md` (the plugin reference).

## The 4 surfaces
| Where | What you do there |
|---|---|
| **Claude Code** (VS Code panel, in this repo) | run `/find-topics`, `/draft-month`, `/draft-batch`, `/retro`, `/guardrail-audit` |
| **Browser → your WordPress** | New from Claude (paste bundle), publish, cards, Cockpit (post), Performance |
| **Browser → Gemini** | paste each bundle's `image_prompt`, get the featured image |
| **Browser → claude.ai Project** | optional one-off flagship authoring |

---

## STEP 0 — Deploy & confirm (required)
1. Coolify → **redeploy** latest `main`.
2. **Plugins** → *Dr Purg Jr. Social Syndicator* shows the current version, no activation error.
3. **Social Queue** submenus exist: Cockpit · Calendar · New from Claude · Performance · Settings.

## STEP 1 — Turn on tracking (required for measurement)
Coolify → Environment:
```
SOCIAL_UTM_ENABLE=1
SOCIAL_UTM_FACEBOOK_MEDIUM=group
SOCIAL_UTM_PINTEREST_MEDIUM=pin
SOCIAL_UTM_REDDIT_MEDIUM=social
```
Redeploy. This is what makes per-hook-variant (`utm_content`) and per-group (`utm_term=g_<group>`) attribution work.

## STEP 2 — GA4 measurement loop
Two distinct IDs — don't mix them: the **`G-XXXX` measurement id** *collects* visits on the site (client-side tag); the **numeric property id** is what the Data API *reads*.

**2a — Collection tag (so GA4 records visits).** The plugin injects the `gtag.js` tag itself — no extra plugin, no Site Kit. Coolify → Environment:
```
GA4_MEASUREMENT_ID=G-XXXXXXXXXX
```
Redeploy, then in GA4 → *Configure a Google tag* click **Test installation** to confirm it fires. (Use only this OR Site Kit — never two Google tags per page.)

**2b — Read API (pull the numbers back into WordPress).** Google (one-time): create a **service account** → download its **JSON key** → enable the **Google Analytics Data API** in that project → add the service-account **email as a Viewer** on the GA4 property (uncheck "notify by email" first, or it rejects the service account) → copy the **numeric property id** (Admin → Property Settings — a number, not `G-XXXX`). Coolify → Environment:
```
GA4_ENABLE=1
GA4_PROPERTY_ID=123456789
GA4_SA_JSON=<the whole service-account JSON, or a path to the key file>
GA4_LOOKBACK_DAYS=28
```
Redeploy. The pull fills clicks, **pages/session**, engagement, and the per-variant + per-group breakdowns. (RPM/revenue stay manual — they're not in this report.)

## STEP 3 — Optional extra channels (`Social Queue → Settings`)
Your core loop posts to groups manually and needs none of these:
- **Facebook Page** (to post/schedule to your Page): Page ID + long-lived token with `pages_manage_posts`, `pages_read_engagement`, `pages_manage_engagement`.
- **Pinterest**: access token (`boards:read`, `pins:read`, `pins:write`) + default board ID.

## STEP 4 — Claude
- **Claude Code** — already set; the `/` commands live in `.claude/commands/`. Just keep this project folder open.
- **(Optional)** claude.ai **Project "Dr Purg Jr. Editor"** for one-off flagship pieces — paste the Master Bundle Prompt from `docs/chatgpt-prompt-pack.md` as its instructions.
- **(Optional)** in-plugin free AI fallback: `SOCIAL_AI_ENABLE=1`, `SOCIAL_AI_PROVIDER=openrouter`, a free OpenRouter key + `SOCIAL_AI_MODEL` (a current `:free` model). Optional card knobs: `SOCIAL_CARD_SCRIM`, `SOCIAL_CARD_BRAND`.

---

## The running rhythm
**Once a month — one Claude Code session:**
```
/retro                → winning-cluster seed (skip month 1)
/find-topics <seed>   → keep the best 12–20 topics
/draft-month          → drafts them all → docs/signals/draft-*.json
  → make the Gemini images (copy each bundle's image_prompt)
  → git commit docs/signals
```
**Each posting day (~10 min, no Claude):** copy a bundle → **New from Claude** → set image → **Publish** → **Generate local social cards** → **Cockpit**: type the group (the **Group link** auto-tags `utm_term`) → copy it → paste to the group → **Mark posted**.

**Each week (~5 min):** **Performance → Pull from GA4** → enter RPM/revenue → watch **Rev/1k-clicks**, pages/session, and the winning angle/group.

---

## STEP 5 — Live-verify once (only you can; report any error)
- New from Claude creates a clean draft · Generate cards · Preview card
- (if set) Post/Schedule to FB Page → the link **first comment auto-posts ~5 min** after a scheduled post goes live
- (if set) Publish to Pinterest
- Performance → Pull from GA4 fills clicks + pages/session

## Minimum to start earning today
**Deploy + `SOCIAL_UTM_ENABLE=1` + GA4**, then `/find-topics` → `/draft-month` → publish → **Cockpit-post to your groups** → weekly GA4 pull. Facebook-Page and Pinterest are optional bonus channels.

## Guardrails (never crossed)
General health info only · real human clicks only (no IVT) · **posting to groups is always a manual human action** (no automation/scraping/fake engagement) · AI is review-first / drafts-only · secrets in Coolify env only.
