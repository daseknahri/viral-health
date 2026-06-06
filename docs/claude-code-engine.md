# The Max-first Claude engine (recommended)

This supersedes the API-first framing in `docs/claude-leverage-roadmap.md`. Same goal (more FB-group clicks → revenue), but built to lean on the **Max subscription you already pay for** and use the paid API only where it's genuinely required.

## The reframe

> **Make a human-triggered Claude Code skill the content factory (free on Max). Keep the claude.ai Project as the manual flagship-authoring surface. Reserve the paid API only for genuinely unattended overnight cron you can't review live.**

You don't run the factory by paying the API — you run it by **typing `/draft-batch` in your terminal**. Interactive Claude Code stays on the subscription; the API is only for the one thing the subscription isn't for: hands-off automation.

## The capability + limit facts that decide the architecture

*(High-confidence items are the architecture's foundation; the metering specifics shift over time — verify in your own Anthropic account/billing before relying on exact numbers.)*

- ✅ **Interactive Claude Code (terminal) stays on Max** — included, in-policy, **$0 marginal**. This is where the whole factory runs.
- ⚠️ **Headless `claude -p` / Agent SDK is metered separately now** (reported: a separate monthly credit at API rates, then PAYG) — i.e. **"free overnight automation on Max" is not a thing.** Unattended runs are genuinely paid → use a real API key, honestly. *(Verify current terms.)*
- ✅ **Claude Projects = interactive/manual only** — no API, no scheduling, no scripting. It's your authoring brain, never an automation component.
- ✅ **Sanctioned automation path = the API.** Don't wire a cron/scheduler to `claude -p` on your Max login to dodge metering — that's both against guidance and self-defeating.
- ✅ **Subscription assumes ordinary individual use** — heavy back-to-back batch runs can hit Max limits; don't make a business-critical, always-on pipeline depend on Max OAuth.

**Conclusion:** the factory = **human-invoked Claude Code skills** (you trigger, you review). The API is a *deferred, optional* lane you likely never need at review-first, low volume.

## Division of labor

- **Claude Code (interactive, Max, $0):** builds and runs *everything* — the `.claude/skills/` suite (`/draft-batch`, `/retro`), the new draft-only WP-CLI command, the guardrail gate, the GA4 metric extension. All drafting, analysis, validation, and draft write-back happen here.
- **Claude Project (claude.ai, manual):** the "Dr Purg Jr. Editor" — voice/guardrail instructions + knowledge base, for **one-off flagship pieces**. Bundle JSON crosses to the plugin by human copy/paste. *Pick one canonical source for the prompt/guardrail text (the skill) so the Project and skill don't drift.*
- **API (paid, deferred):** ONLY a future unattended Coolify cron (`generate-bundles.mjs` + `ANTHROPIC_API_KEY` in Coolify env), same draft-only gate, capped low. Build only if interactive throughput genuinely can't keep up.

## ⚠️ Two corrections the critic caught (must fix in the build)

1. **The draft-only guarantee is NOT in `apply_imported_bundle()`.** That function only writes meta/taxonomy onto an *already-created* post. The `post_status=draft` lives in `handle_import_post()`'s `wp_insert_post`. → The new `wp dpj import-bundle` command **must do its own `wp_insert_post([... 'post_status' => 'draft'])`** and **must never accept a publish/status argument.** Cleanest fix: extract a shared `import_bundle_to_draft($bundle): int` (does the draft insert + calls `apply_imported_bundle`) and have **both** the HTTP importer and the WP-CLI command call it.
2. **There is no `wp-cli` docker service.** `docker-compose.yml` has `wp-init` (image `dr-purg-jr-wp-cli:latest`) gated behind `profiles:[seed]` as a one-shot seeder. → Add a dedicated unprofiled `wp` runner service (or invoke via the seed profile) so `wp dpj import-bundle` actually runs.

## The trimmed plan (build smallest-compounding-first)

**Phase 1 — the only thing to build first:** a draft-only `wp dpj import-bundle <file.json>` WP-CLI command (own `wp_insert_post` draft, no status arg, via the shared `import_bundle_to_draft`), and **verify it end-to-end against a live WordPress+DB** through a real wp runner service. *A proven, un-publishable draft socket is the smallest compounding step — not the learning loop.*

**Then, once the safe write path is verified:**
- **Phase 2:** the hard **guardrail pre-screen** (medical-leak + hook-payoff) that rejects and never writes a failing bundle.
- **Phase 3:** the **`/draft-batch` skill** (`disable-model-invocation: true`, scoped allowed-tools, injects calendar ideas + recent titles + winners, drafts 2–3 bundles, validates schema, runs the gate, writes drafts). Your daily author→reviewer flip, free on Max.

**Defer until you actually feel a limit:** the GA4 pages/session metric extension, the `/retro` learning loop (Opus-only, min-sessions threshold, "won" = clicks AND pages/session AND payoff), variant-fan-out subagents, a read-only MCP server, a standalone `generate-bundles.mjs`, and the PAYG cron. These are real but premature now.

## Limits to accept (the honest constraints of the Max path)

- **Not unattended:** user-invoked skills run only when you type them — the factory can't fire from cron by itself. Hands-off overnight = the paid API lane.
- **Keep volume low (2–3 drafts/run):** your morning review is the only thing between AI and unreviewed medical claims going live.
- **Don't make it business-critical on Max OAuth:** heavy/always-on use hits subscription limits; that's the API's job.
- **The Project can't be wired in:** it stays a manual surface.
- **Write-back is still unverified against a live stack** — Phase 1's live test is a real gate before trusting automated draft writes.

## Guardrails (unchanged, by construction)
Drafts only (never publish from automation); manual FB-group posting only; medical-leak check is a hard gate; `/retro` runs on Opus with a real sample threshold; `ANTHROPIC_API_KEY` (if ever used) in Coolify env only. None of this crosses the no-fraud / no-ToS-evasion line.
