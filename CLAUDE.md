# Dr Purg Jr. — project brain

This file loads automatically every Claude Code session in this repo. It's this blog's memory: who it is, the rules, the engine, and how to work here. Each cloned blog has its own copy — edit freely per blog.

## What this is
**Dr Purg Jr.** — an English-language, US-audience, mobile-first **health-FACTS blog**. Revenue model:

```
Monthly revenue = Facebook-group link clicks × pages per session × (RPM / 1000)
```

Traffic comes from the operator posting article links in **Facebook health groups they moderate** (link in the first comment) → clicks to the site → display-ad revenue. The whole operation optimizes those three terms, legitimately.

## NON-NEGOTIABLE guardrails (never cross these)
- **Health content:** general information only. No diagnosis, cure, treatment, prevention, dosage, personal medical advice, fear-mongering, miracle claims, or invented statistics. Body-signal framing stays GENERAL ("why most people feel X"), never personal ("is YOUR X a sign of…").
- **No ad fraud / invalid traffic:** real human clicks only. No proxy/bot traffic to ad networks.
- **No Facebook ToS evasion:** no bots/automation posting to groups, no fake engagement, no scraping Facebook or gated sites. **Posting to groups is always a manual human action.** Web *search* + reading *public* pages is fine.
- **AI is review-first:** AI drafts/proposes; a human reviews before anything publishes or posts. Automated write-backs are DRAFT-ONLY.

## The system (three surfaces)
- **WordPress + the `dr-purg-social-syndicator` plugin** = system of record (the "New from Claude" bundle importer, local card images, Cockpit for manual posting, Calendar, Performance + GA4 pull). Also `kepoli-author-tools` (article SEO/auto-fill).
- **Claude Code (this repo, on Max)** = the operator console — slash commands in `.claude/commands/`. Free, interactive, human-triggered.
- **claude.ai "Dr Purg Jr. Editor" Project** = manual flagship authoring (returns a content bundle JSON).

## The content loop
```
/find-topics → paste ideas into Calendar → /draft-batch → paste bundle into New from Claude
   → review + Gemini featured image (use the bundle's image_prompt) → publish
   → Cockpit (manual post to FB groups) → Performance/GA4 → re-weight next /find-topics
```
- **`/find-topics [seed]`** — discovers ranked, rubric-scored topics from public demand (Reddit/YouTube/PAA/Trends) now, and from first-party FB-group questions + Search Console once `docs/signals/fb-questions-*.md` exist. Emits idea JSON to paste into the Calendar.
- **`/draft-batch [topic]`** — writes 2–3 full content bundles (guardrail-gated, one experiment per batch) as paste-ready JSON for New from Claude.
- **`/draft-month [topic list]`** — the efficient monthly batch: many bundles in one run, canon loaded once, same gate (cap 25).
- **`/guardrail-audit [draft.json]`** — the **canonical** health + ToS + contract gate; other content commands defer to it so guardrails never drift.
- **`/retro`** — weekly learning loop: reads Performance/GA4, crowns winning angles/topics, judges the experiments, proposes next-week seeds (Opus, proposals only).

## Where the thinking lives (read these for detail)
- **`docs/operating-doctrine.md` — START HERE: what we want and the easiest way to run it (FB Groups + Pinterest only; bank a month in one ~90-min session; Claude drafts, human approves + posts).**
- `docs/growth-strategy.md` — the research-backed channel strategy (what/why); `docs/build-plan.md` — the engineering roadmap (phases).
- `docs/go-live-setup.md` — turn each feature on (env, tokens, tests).
- `docs/social-syndicator.md` — the plugin reference.
- `docs/fb-click-playbook.md` — the click strategy + **canonical topic rubric** (the single source of truth for scoring; keep `/find-topics`, `/draft-batch`, and the Project aligned to it).
- `docs/chatgpt-prompt-pack.md` — the Master Bundle Prompt (the JSON contract) + the Project "editor identity" setup.
- `docs/image-strategy.md` — the featured-image/thumbnail strategy: one clean 4:5 master + plugin overlay, the upgraded image_prompt/discover_image_prompt formulas, tool (Nano Banana), and the reject checklist.
- `docs/topic-discovery.md`, `docs/topic-bank.md`, `docs/content-experiments.md` — finding/queuing topics + the experiment rotation.
- `docs/claude-code-engine.md`, `docs/claude-leverage-roadmap.md` — how Claude runs the engine (Max-first; API only for deferred unattended cron).
- `docs/project-status.md` — index of all docs.

## Working in this repo
- **No PHP runtime by default.** Verify PHP with `php -l` via a portable build (download to `%TEMP%/php-lint` if absent); `node scripts/preflight-launch.mjs` runs `php -l` on all files + every readiness audit. Always run `node scripts/audit-social-syndicator-readiness.mjs` after touching the plugin.
- **Secrets** (API keys, tokens) live only in **Coolify env** — never in `wp_options` or committed files. `*_ENABLE` flags stay `0` in source defaults.
- **Commit messages** end with the Co-Authored-By line; branch off `main` only when asked to commit.
- `.claude/commands/` and `.claude/skills/` are version-controlled; the rest of `.claude/` is local harness state.

## Per-blog / cloning
This engine is cloneable for other blogs (`docs/replicate-food-blog.md`). Each clone is its own repo with its **own `CLAUDE.md`, `.claude/commands/`, docs, brand, rubric, and credentials** — so `cd <blog-repo> && claude` gives you a session that already knows *that* blog. Modify any blog's brain/commands independently; nothing here is shared across blogs unless you package it as a plugin.
