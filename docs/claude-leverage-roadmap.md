# Leveraging Claude — the developer way

> **Superseded by `docs/claude-code-engine.md`** — the Max-first design (human-triggered Claude Code skills run the factory free on your subscription; the paid API below becomes the *deferred* unattended lane). Read that first; this doc keeps the fuller capability research and cost model.

How to use Claude's full surface (not just chat) to hit the goal: more Facebook-group clicks × pages/session × RPM. Researched against the actual stack and trimmed by a feasibility/cost/guardrail critic. Companion to `docs/fb-click-playbook.md` and `docs/go-live-setup.md`.

## The reframe

> Stop treating Claude as the writer you chat with; treat the **Claude API as a background factory wired into the plugin you already built.** Writing quality is already solved (Opus on your Max plan). Your real bottlenecks are **throughput** (one post at a time = few shots on goal) and a **broken feedback loop** (results never feed the next post). The plugin already has every receiving socket — `apply_imported_bundle`, structured-output schemas, prompt caching, the GA4 pull, the Cockpit, the Performance log. The unlock is plumbing an `ANTHROPIC_API_KEY` Node job into those sockets so Claude **drafts overnight in bulk and grades yesterday's results** — turning you from author into reviewer.

## What Claude can do here (capability → your operation)

- **Content at scale** — Messages API structured outputs (the bundle JSON), prompt caching (~90% cheaper after the first call), long 1M context (ground every draft in your style guide + last 50 titles + winning patterns), model tiers (Opus for the article, Haiku for variants).
- **Vision** — send the actual rendered card pixels and have Claude judge legibility, face-crop, scrim, and pick the best of N images — upgrading the geometry-only QA panel to "is the hook actually readable?"
- **Automation / agents** — a small zero-dep Node job calls the API and writes **drafts** into WordPress via **WP-CLI in your existing Docker container** (no new auth); runs nightly via Coolify cron after the GA4 pull. (Agent SDK + a REST route is possible but unnecessary now.)
- **The learning loop** — Claude reads the Performance log + GA4 (clicks **and** pages/session), finds winning hooks/angles/topics, flags broken-payoff posts, and feeds proven angles back into the hook + calendar prompts. This is what makes content **compound** instead of plateau.
- **Distribution-prep intelligence** — topic→group matching, native per-group captions, rule-compliance lint. Prep only; **posting to groups stays your manual click** (ToS).

## Recommended next build (lowest-regret, highest-leverage)

**The Overnight Bundle Factory** — with the GA4 fix shipped the same day as its prerequisite.

It attacks your single biggest constraint (throughput) and converts you from author to reviewer, reusing 100% of existing sockets (`apply_imported_bundle`, the schema, the wp-cli container, the New-from-Claude review flow). Shape:
1. `wp dpj import-bundle <file.json>` — a WP-CLI command that calls the existing **draft-only** `apply_imported_bundle()`.
2. `scripts/generate-bundles.mjs` (zero-dep, like your audit scripts): read Planned calendar ideas + recent titles, call the API with a cached system prefix (style + guardrails), validate against the bundle schema, drop validated drafts into a folder.
3. Nightly Coolify cron runs it after the GA4 pull → you wake to a few reviewable drafts. **Nothing publishes; nothing posts to Facebook.**

## The critic-trimmed plan (do this, stop, evaluate)

**Phase 0 — today, free, ~½ day (the prerequisite):** extend `ga4_pull_sessions()` to also capture **pages/session** + engagement rate + `sessionManualAdContent` (your `utm_content` variants) into a new `_dpj_social_perf_pps` meta key, shown in Performance. Without pages/session you're optimizing only the *first* click and ignoring half the revenue equation.

**Phase 1 — ~1–2 days, pennies (prove the lane):** add `ANTHROPIC_API_KEY` in Coolify env (separate from Max, never in WP), the draft-only `wp dpj import-bundle` command, and a **single-call** `generate-bundles.mjs` that drafts **2–3 posts/night**. Bolt on the **hook-payoff + medical-leak pre-screen as a HARD flag** immediately (not a later phase) — review pressure is the only real guardrail risk.

**Then stop and evaluate.** Only continue if throughput is actually the bottleneck:
- **Later (Opus-only):** the weekly **retro analyst** — but only once you have enough attributed sessions for "winners" to be real, not noise (set a minimum-sessions threshold). Define "won" as clicks **AND** pages/session **AND** payoff — never clicks alone (that path leads to fear-bait).
- **Maybe:** vision card-QA; topic-cluster sibling articles (lifts pages/session).
- **Skip until justified:** the **Batches API** (async polling complexity for single-digit-cents savings at your volume), **mixed Opus/Haiku batches**, a full **group-registry CRM**, and the **Agent-SDK/REST runner** (WP-CLI already does the write-back).

## Free vs paid

- **Keep free:** flagship article authoring in the claude.ai Project (Max plan — can't be called programmatically anyway); all Claude Code development in this repo; the in-editor free-model fallback; WP-CLI write-back; GA4 pulls.
- **Pay API only** for the automated lanes: the overnight factory, variant fan-out, the Opus retro analyst, vision/guardrail checks. **Rough cost with caching: ~$0.05–0.15 per fully-packaged article → ~$2–5/month** at ~30 articles, + ~$1/mo for the weekly Opus retro. Single-digit dollars, far below the click revenue it unlocks. **Never run the retro analyst on the free model.**

## Guardrails baked into the design

- The write-back is **draft-only** by construction (`apply_imported_bundle` writes meta; the import path hard-codes `post_status=draft`). The WP-CLI command must **never** accept a publish/status argument.
- **Cap nightly output low (2–3)** so morning review stays real — unreviewed medical content is only as safe as the review.
- The **medical-leak / hook-payoff check is a hard pre-screen**, not optional.
- The **retro loop runs on Opus**, defines "won" as clicks + pages/session + payoff, and needs a **minimum-sessions threshold** before treating a variant as proven (small samples = noise).
- `ANTHROPIC_API_KEY` lives **only in Coolify env**, never in `wp_options`.
- **Posting to Facebook groups stays a manual human action.** No automation crosses that line.
