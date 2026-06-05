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

### Phase 3 — AI hook-variant generator

- Extend the Claude social drafter to return **N hook/overlay variants** per post (prompt-cached on the shared article prefix, so variants are cheap).
- Each variant carries a `utm_content` tag, feeding Phase 2's tracking → real A/B data.
- **Outcome:** systematic hook testing instead of one-shot copy.

### Phase 4 — Posting cockpit (ToS-safe automation)

- A WP-admin "today's queue": each row shows the exact caption, first-comment text, image, the UTM link, one-click copy buttons, and a "Mark posted to [group] at [time]" logger.
- Official scheduling where allowed (Meta Business Suite for the Page; Pinterest API; the existing newsletter as an owned channel).
- **Outcome:** posting becomes a 5-second paste per group; preparation is automated, publishing stays human.

### Phase 5 — Content calendar + AI ideation

- A monthly viral-health topic calendar (handoff item), with Claude proposing curiosity-led, guardrail-safe angles for review.
- **Outcome:** a steady, planned pipeline feeding the whole funnel.

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
