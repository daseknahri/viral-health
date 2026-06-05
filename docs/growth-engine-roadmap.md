# Dr Purg Jr. Growth Engine Roadmap

This is the working plan for turning Dr Purg Jr. from "publish and hope" into a measured, Claude-assisted content + distribution + monetization operation. It is the companion build plan to `docs/content-machine-extraction-map.md` (which maps the current flow) and `docs/ai-content-growth-strategy.md` (direction).

## Guardrails (non-negotiable)

These constrain every phase. They protect the asset (Page, domain, ad accounts, moderator seats) that the whole operation depends on.

- **No fake traffic.** No proxy/residential-IP traffic, bots, or click generation against AdSense/Ezoic/any ad network. This is invalid traffic (ad fraud); it gets the domain and identity permanently blacklisted. Revenue comes from real humans only.
- **No platform-ToS evasion.** No browser bots or extensions that mass-post to Facebook groups by getting around Facebook's anti-automation limits. Automate *preparation*, keep *publishing* human. Posting from the queue must be a deliberate human action.
- **Health-content rules** stay as documented: no diagnosis, cure, treatment, prevention, or personal-medical-advice claims; curiosity hooks pay off with calm context.
- **Ads stay env-gated** and added one slot at a time (see the ad docs). AI never enables ads or posts anything.

## The model we optimize

```
Monthly revenue = FB link clicks × pages per session × (RPM / 1000)
```

Every phase moves one of those three terms, legitimately. Claude is used to raise the top of the funnel (better hooks → more clicks) and to remove production toil — never to fake demand.

## How Claude is used in the content system

The content system already has a provider-gated AI layer (`SOCIAL_AI_*` for social drafts, `AI_EXTRACTION_*` for article fields), historically pointed at OpenRouter. Phase 1 adds **Anthropic / Claude** as a first-class provider for both, using:

- The Messages API via `wp_remote_post` (no SDK in WordPress).
- **Structured outputs** (`output_config.format` JSON schema) so every reviewed field comes back as valid JSON — no brittle parsing, no prefill hacks.
- **Prompt-cacheable** request shape (`cache_control` on the static system block) so future multi-variant calls (Phase 2) reuse the article prefix cheaply.
- Model is env-selectable: default `claude-opus-4-8` for quality; set `claude-haiku-4-5` or `claude-sonnet-4-6` for cheaper high-volume drafting.

Everything Claude produces remains **review-first**: it fills draft fields only; a human approves before anything posts.

## Phases

### Phase 1 — Claude provider in the content system  ✅ (this is the current build)

- Add `anthropic` as a provider for both `SOCIAL_AI_*` (social drafts) and `AI_EXTRACTION_*` (article field extraction).
- Structured-output JSON schemas for the social package and for article/recipe extraction.
- Docs + `.env.example` + readiness-audit contract updated.
- **Outcome:** the Social Editor's "AI Social Draft Assistant" and the author "Auto fill" can run on Claude with guaranteed-valid output.

### Phase 2 — Measurement loop: UTM + performance log  ✅

- ✅ **UTM tracking** (`SOCIAL_UTM_ENABLE`): a "Tracked links" panel with per-platform UTM links + copy buttons, and auto-tagging of the Facebook first-comment link at post time (`utm_source`=platform, `utm_medium`=group/pin/social, `utm_campaign`=post slug, `utm_content` reserved for hook variants). Idempotent; off by default.
- ✅ **Performance log**: a per-post `Performance` section in the Social Editor (clicks / RPM / revenue / notes, with hook + UTM campaign + FB posted date shown for context) and a `Social Queue -> Performance` overview that lists posted/measured posts with a totals line — the "which hooks won" view.
- **Outcome:** the loop is closed — GA4 attributes clicks by UTM campaign, and the performance log ties each hook to clicks and revenue. (Matches the handoff's "Known Safe Next Work.") Manual entry for now; an automated GA4/ad-network pull is a later enhancement.

### Phase 3 — AI hook-variant generator  ✅

- ✅ `AI hook variants` section: one AI call returns N distinct hook angles (`SOCIAL_AI_HOOK_VARIANTS`, default 4), each with an overlay version and a `v1..vN` `utm_content` tag. `Apply to Facebook` sets the hook + overlay and marks the selected variant; the selected tag flows into the posted Facebook first-comment link, and each variant shows a copyable tracked link.
- **Outcome:** systematic hook testing instead of one-shot copy — the variants feed Phase 2's tracking, so the Performance table can compare angles. (One call returns all variants, which is cheaper than N separate calls; prompt caching stays available for future per-variant expansion.)

### Phase 4 — Posting cockpit (ToS-safe automation)  ✅

- ✅ `Social Queue -> Cockpit`: a card per packaged post with the caption, the tracked first comment, the tracked link, and the image — all copy-ready — plus a "Mark posted to [group] at [time]" log (keeps the last 50 entries). Publishing stays a manual human action; only the prep is automated.
- ⏳ **Optional later:** official scheduling where allowed (Meta Business Suite for the Page; Pinterest API; the existing newsletter as an owned channel).
- **Outcome:** posting becomes a few-second paste per destination; preparation is automated, publishing stays human and inside platform rules.

### Phase 5 — Content calendar + AI ideation  ✅

- ✅ `Social Queue -> Calendar`: an idea backlog plus an AI brainstormer. `Generate ideas` (optional theme/seed) asks Claude for `SOCIAL_AI_CALENDAR_IDEAS` curiosity-led, guardrail-safe topics (title + angle + hook seed + rationale), sending recent post titles so it does not repeat covered topics. Ideas can also be added manually. Each idea is scheduled with a date and moved through `Idea → Planned → Drafted → Published`, with notes and delete. Ideas live in a plugin option (`dpj_social_calendar`), separate from posts, since they exist before an article does.
- **Outcome:** a steady, planned pipeline feeding the whole funnel — ideation is no longer ad-hoc, and topics are reviewed before any writing starts. Reviewed-only: the calendar proposes and tracks; writing and publishing stay human.

## Monetization ladder (parallel, not a code phase)

1. Real volume + clean GA4 (Site Kit already installed).
2. Ezoic first (low entry bar, auto-optimizes) → AdSense kept squeaky clean.
3. Mediavine/Raptive later at 50k–100k+ sessions for the higher RPMs.
4. RPM levers that are legit: `Auto split` (more pages/session — already shipped), placement testing via the display-ad layer, page speed, dwell time.

## Validation (every phase)

```powershell
node scripts\preflight-launch.mjs
node scripts\audit-social-syndicator-readiness.mjs
git diff --check
```

Secrets (API keys) live only in Coolify env — never commit them. `SOCIAL_AI_ENABLE` / `AI_EXTRACTION_ENABLE` stay `0` in source defaults.
