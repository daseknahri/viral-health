# Dr Purg Jr. — The Content-Machine Roadmap

*From a blog with a distribution channel into a one-topic-many-renditions media engine. Founder/CTO decisions, not options. Every line inside the guardrails.*

> **This is the EXPANSION path, governed by [operating-doctrine.md](operating-doctrine.md) — it does NOT override it.** The doctrine (v1.0, 2026-06-10) deliberately CUT video, Google Discover, Reddit/Quora, and the cron, and capped the live operation at ~8 articles/month on TWO channels (FB Groups + Pinterest) because the single human review pass is the bottleneck. Nothing here re-opens a cut channel until that two-channel base is *boringly stable* and the numbers force a specific addition. Read sections 2–6 as a menu to pull from **in order, one surface at a time** — not a mandate to run everything. If a month exceeds the doctrine's time cap, **cut a surface, never add hours.**

---

## 0. Read this first (the verify-before-you-build line)

Two claims in the research are load-bearing and fast-moving. Confirm both at the top of any session that re-tools on them; do **not** build on the year-old repo note or a marketing blog:

1. **Pinterest pin format + link-out behavior.** Keep the repo's original call: **static link pins are the DRIVE default.** Verified against live 2026 Pinterest behavior — **Idea / multi-page pins still cannot carry an outbound link** (they're brand/engagement only). So multi-page pins, if used at all, are an **ON-PLATFORM** surface judged on saves/follows, **never a DRIVE experiment**, and only after the static-pin base is boringly stable. (Re-check live Pinterest creator docs at session start; the format rules move — but do not act on the "multi-slide out-clicks static / now links out" claim; that is false for outbound links.)
2. **The June 15, 2026 Anthropic billing split** (verified in the research): interactive Claude Code stays on Max (free factory); `claude -p` / Agent SDK / GH Actions now draw a **separate metered credit** ($100 Max5x / $200 Max20x, API rates, no rollover). This is *good* — it makes the cron a known cost, not a guess — but it means the cron must be capped and model-routed (Haiku for mining, Opus only for the final draft + gate).

Everything below assumes both are checked at session start.

---

## 1. The reframe

**Old model:** write an article → blast its link everywhere → hope for a click.
**New model:** every topic is a **pillar**; each platform gets a **native rendition** engineered for how that platform's users actually consume — and each rendition has *one* declared job.

The job is not a vibe. It is forced by 2026 link suppression (external-link reach penalties up to ~60% in-feed). So we split every surface into exactly one of two jobs, and we measure it accordingly:

- **DRIVE-TO-SITE** (a click *is* the product → the literal `clicks` term of the revenue formula): **FB Groups** (link in first comment), **Pinterest** (link pins), **Google Discover**, **Reddit/Quora** (citation links). Judged on **pages/session per `utm_campaign`**.
- **ON-PLATFORM** (consumption + follow *is* the product → brand + in-app search, *not* a click): **vertical video** (TikTok / IG Reels / YouTube Shorts / FB Reels), **X/threads**, **carousels**. Judged **only** on follower/group growth + brand-search lift. **Never on views.**

> Revenue today = `clicks × pages/session × (RPM/1000)`. DRIVE surfaces feed `clicks`. ON-PLATFORM surfaces feed the *top of that funnel* (brand, in-app search presence, follower base that later clicks) and the **owned audience** that survives any platform turning hostile. Trying to make an ON-PLATFORM surface click is fighting the algorithm and wastes the scarce review pass.

**The discipline that does not change:** atomize **WIDE in drafting** (Claude does it for ~$0 on Max), stay **NARROW in review + posting** (the human is the bottleneck; that's the moat against slop). The "1 idea → 40-50 pieces" number is a *team* metric and a vanity trap for a solo op. We extend the atomization graph; we do **not** abandon the few-reviewed-surfaces rule.

**The third, missing job — OWNED.** Add **email/newsletter**. Social is rented land; the whole business currently sits on Facebook moderator seats — a single platform risk no guardrail can cover. A newsletter is a near-free text derivative that converts borrowed reach into an algorithm-proof, deplatform-proof asset. This is the highest-leverage *new* surface in the whole roadmap — higher than any video.

---

## 2. The platform map (ruthlessly prioritized)

Existing bundle fields already in the contract: `article`, `seo_title`, `meta_description`, `pin_set[]`, `reel_script`, `community_answer`, `discover_image_prompt`, FB `hook`/`first_comment`/variants, overlay text. The map below shows what *already seeds* each surface and the two **new** cheap text fields we add.

| Surface | Native shape | Job | Seeded by | Cost to make | Verdict |
|---|---|---|---|---|---|
| **Blog + Google Discover** | Long-form facts article + hero | DRIVE | `article`, `seo_title`, `discover_image_prompt` | Cheap (text) | **Keep — the destination** |
| **FB Groups** | Value post + link in 1st comment | DRIVE | `facebook_hook`, `first_comment` | Cheap | **Keep — proven base** |
| **Pinterest** | Static link pin (multi-slide = §0 experiment) | DRIVE | `pin_set[]` | Cheap copy / moderate image | **Keep — proven base** |
| **Reddit / Quora** | General-only citation answer | DRIVE | `community_answer` | Cheap | **Keep — already gated** |
| **Email / newsletter** | Repurposed facts blurb + link | **OWNED** | **NEW `newsletter_blurb`** | Cheap (text, ~$0) | **ADD FIRST — the brand moat** |
| **X / threads** | Curiosity-loop thread, zero-click | ON-PLATFORM | **NEW `x_thread`** | Cheap (text, ~$0) | **ADD FIRST — cheap brand** |
| **Vertical video** (TikTok + IG Reels + YT Shorts + FB Reels) | One 12-20s captioned clip | ON-PLATFORM (brand + in-app search) | `reel_script` (+ keyword discipline) | **Expensive** (render once) | **Phase 2 — render once, upload 4x** |
| **YouTube long-form** | Faceless 6-10 min explainer | DRIVE *and* monetizable destination | NEW pillar field | **Most expensive** | **Phase 3 bet** |

**Two non-obvious calls:**

- **Vertical video is ONE atom, rendered once, hand-uploaded to all four surfaces.** Never re-cut per platform — that's where solo ops drown. TikTok/IG are **in-app search engines** for health questions (~49% of US, ~65% of Gen Z search in-app; 70%+ completion is the rank signal). So the win isn't virality, it's **durable evergreen search inventory** for "why does my body do X." Bake keyword discipline into `reel_script`: keyword **spoken in first 3s**, on-screen OCR text, keyword-first caption. Pre-screen every clip with the **Higgsfield Virality Predictor** (`brain_activity`) and only upload high-completion ones.
- **YouTube long-form inverts the blog's logic** (Shorts RPM ~$0.03-0.15 vs long-form $1-15+; a 20-30x gap) and is itself a monetizable destination *and* the richest pillar to atomize from. But it's the most expensive derivative to do well — a deliberate Phase-3 bet, not a Phase-1 reflex.

**What we do NOT do now:** light up six channels at once; chase the team-cadence atomization number; build long-form video before the cheap text derivatives are boringly stable.

---

## 3. The legitimacy line (the bright, decidable rule)

> **Viral content is used ONLY as a demand SIGNAL (proof a topic/format resonates) and an abstract FORMAT TEMPLATE (proof a structure retains). We create ORIGINAL content from re-researched facts in our own voice. We NEVER translate, repost, or "spin" a specific existing post — in any language. Facts are free; expression is owned.**

**Why this is the *only* line, not a cautious preference:**

- **Translation is a derivative work** requiring the rights-holder's permission (17 U.S.C. §101, Copyright Office Circular 14). "Translate it well / change a few words" does **not** cure it — faithful translation is the textbook infringing derivative. There is no "right way" to translate-and-repost someone's post. The right way is to **not translate the post at all.**
- **Berne (180+ members incl. the US and Arab states)** makes the source protected automatically, internationally, with the **translation right explicitly exclusive**. "It's a foreign post, nobody here will notice" is a detection bet, not a defense.
- **Fair use is a trap post-*Warhol* (2023):** "new meaning/message" is *not* enough, and **same commercial purpose** (both pieces drive engagement in the same category for ad revenue) cuts factor one against you. A solo op cannot afford to be the test case. **Design to never invoke it.**
- **"Substantially similar" is infringement without verbatim copying.** An AI told to "rewrite this viral post" tracks the source's selection/sequence/phrasing too closely. So we generate from the **re-researched facts + an abstract archetype**, *never* from the source text in context.
- **Platform ToS is a second, parallel kill-switch** — independent of copyright. Meta's 2026 Original-Content rules and YouTube's reused-content policy **fingerprint across platforms and demonetize** translated reposts, AI-voiceover summaries, and minor-edit reuploads. So even ignoring the law, translate-and-repost **wouldn't even monetize.** Reading *public* pages for signal is fine; scraping gated/FB content or storing verbatim text to spin from is not.

**The decidable test:** *Would this survive if the original author saw it?* Original-from-signal survives. Translated/spun does not.

**Make it code, not prose.** Add an **originality + provenance** sub-check to `/guardrail-audit` (the canonical gate everything defers to). It FAILS any bundle where: (a) a claimed fact isn't independently re-verified against *our own* cited general-health sources (not the viral post); (b) any passage is traceable to a single observed source; (c) for the Arabic clone, the field is a near-verbatim translation of a sourced post. And define a **signal-only research schema** in `find-topics`: capture `{topic, claimed_fact, format_archetype, hook_pattern (abstracted, NOT quoted), engagement_magnitude, reference_url}` — and **never** store verbatim post text as a generation input.

---

## 4. The Arabic / MENA play

**The opportunity is half-true — split it honestly.** The *audience* side is genuinely under-served: a documented quality/trust gap in credible Arabic health content (JMIR Infodemiology 2026; YouTube launched "YouTube Health" in the UAE in MSA to close exactly this gap). The *supply* side is **not** empty — short-form is crowded. So our position is precise: **the credible, faceless Arabic health-FACTS explainer.** "Low competition" is defensible for *trustworthy explainers*, not for entertainment short-form.

**The execution instinct ("translate viral posts the right way") is the riskiest idea in the brief — hard-redirect it** to §3's rule. It's infringement *and* now commercially dead under 2026 originality rules. The compliant path is identical to the English engine: mine **public** Arabic demand + formats as signal → generate **100% original** Arabic content through a localized gate.

**Localize, don't translate — the platform mix is different and must drive format:**
- **YouTube dominates MENA** (~89% of social users) and rewards depth → make **faceless YouTube the brand anchor**, then atomize to TikTok/Reels/**Snapchat**.
- **Snapchat is the Gulf's hidden lever** (~87% Saudi reach; Gen Z brand-discovery engine; almost no US-engine analog). A simple Spotlight/Story re-cut is a real edge.
- **Egypt is FB-first** (~45M FB users, FB-monetization-eligible) → the existing FB-group + Reels engine **ports almost wholesale to Egypt**. **Pinterest is weak in MENA** — do not assume that leg transfers.
- **Dialect is a real fork:** **Egyptian (Cairene) dialect for voiceover/hooks** (pan-Arab lingua franca, relatable), **MSA for any on-screen factual claim** (authority/credibility). Encode this per-format, don't pick one globally.

**Monetization reality (model it honestly, up front):** YouTube CPM UAE $5-20 / Saudi $4-18 / **Egypt $0.50-2**; FB Reels RPM ~$0.02-0.20. So: **Egypt = audience engine** (huge, low payout), **Gulf = monetization layer** (smaller, high payout). Treat the Arabic thread primarily as **on-platform audience/brand** — the operator's actual vision — with monetization weighted to the Gulf and patience for rising Saudi CPMs (Saudi ad spend +16.8% in 2026 → ~$8B by 2029). Do **not** clone the US display-ad economics and expect them to hold.

**Heavier review, not lighter.** 2026 research: Arabic LLM safety/accuracy lags English, unsafe content slips through in low-resource translation, dialectal medical phrasing is easy to get subtly wrong, *and* cultural/religious fit (family framing, Ramadan/halal context, gender/body-topic sensitivity) is non-optional. So the Arabic gate needs an **Arabic-fluent human reviewer** step — budget for it; the English gate alone is insufficient. Use the strongest Arabic-native models for drafting (Falcon-H1-Arabic, ALLaM, Fanar) but never ship un-reviewed Arabic health claims.

**Whether/when to spin it:** A **separate repo** with its own `CLAUDE.md`, Arabic rubric, Arabic banned-phrase gate, and the §3 no-translate sub-check **written in before a single piece ships**. **This is Phase 4 — last**, and only after the US engine's new surfaces are stable. Validate cheap first: bank ~10 original Arabic explainers (Egyptian voiceover, MSA on-screen claims), publish manually to **one** YouTube + **one** Egyptian FB surface, measure watch-time/retention + Egypt-vs-Gulf RPM split over 4-6 weeks before scaling. **Get a one-time local-jurisdiction legal sanity check** on the clean-room workflow before launch — cheap relative to one infringement claim.

---

## 5. The engineering (smallest concrete extensions)

The verified repo facts shape everything: the data model is **flat and post-centric** (`apply_imported_bundle()` writes one draft post; renditions hang off it as `_dpj_social_*` meta; **no** `WP_CLI` command; the **only** scheduled event is the FB-Page first-comment auto-poster — never groups; the importer **hard-codes `post_status=draft`** as the sole automated write path). That last fact is the cleanest guardrail surface possible: **automation can physically only produce a draft.** Adding any rendition is a three-place change today: importer allow-list + Master Bundle Prompt + gate allowed-keys. That coupling is the friction worth engineering away.

**Phase A — per-rendition STATUS + LOG (this month, ~half a day). Highest leverage.**
Add one structured meta `_dpj_social_rendition_state = { pinterest:{status, posted_log[]}, reel:{...}, reddit:{...}, discover:{...}, fb_group:{...}, newsletter:{...}, x:{...} }`, `status ∈ {draft|approved|posted|skipped}`, reusing the existing FB-Cockpit log pattern. Render it as a **per-channel checklist** in the Cockpit/Calendar. This delivers "one topic → many *gated* renditions" with **zero new tables** — review-first becomes auditable **per channel**, not per article. **Escape on output** (`esc_html`/`esc_textarea`) — the structured fields are currently write-only. Verify with `node scripts/audit-social-syndicator-readiness.mjs` + `php -l`.

**Phase B — collapse the three-place coupling into ONE renditions manifest.**
A single `dpj-renditions.json`: each type = `{meta_key, max_len, shape: string|list|map, gate_subcheck_id, channels[], job: drive|on_platform|owned}`. The importer **loops the manifest** instead of hard-coding fields; the Master Bundle Prompt and the gate's allowed-keys are generated from / validated against the **same** manifest. Then a new native platform format is a **one-place config change** — this is the structural unlock behind "engineered natively per platform." The manifest references gate sub-check **IDs**; it never duplicates gate logic. **Keep ONE canonical `/guardrail-audit`.**

**Phase C — read-only, draft-only per-platform EXPORTER.**
An admin-ajax / WP-REST `GET /dpj/rendition/{post}/{channel}` returning the **gated** rendition in each platform's ingest shape: `pin_set` as CSV, `reel_script` as caption/SRT + shotlist, `community_answer` as plain text, `newsletter_blurb` as email-ready text. **Export ≠ post** — a human still uploads, so this satisfies the on-platform-consumption goal without touching the manual-posting guardrail. Any "upload-ready" video package **carries the AI-label reminder flag** so disclosure can't be forgotten. (Disclosure carve-out: AI-*drafted* captions/scripts the human posts are exempt; only AI-*synthesized realistic media* must be labeled — don't be over-cautious.)

**Phase D — the capped Coolify cron agent (only after A-C are stable AND throughput genuinely can't keep up).**
A **draft-only, post-never** series running the genuinely-automatable left side overnight:
1. **Mine PUBLIC demand** (Reddit/YouTube/PAA/Trends via WebSearch + public WebFetch — **no login, no gated/FB fetch, no verbatim-text storage**), score against the canonical rubric. → **Haiku 4.5** ($1/$5 per Mtok).
2. **Draft bundles** with the **same** Master Bundle Prompt. → **Opus** (quality is load-bearing).
3. **Run the SAME `/guardrail-audit`** programmatically and **DROP failures** — never stage them. → **Opus**.
4. **POST passing bundles to the existing draft importer** (`post_status=draft` — the only write path).
5. **Write a `docs/signals/` summary + push a notification** so the monthly review queue is pre-filled.

**Where the human stays — and three guardrails locked in CODE, not prose:**
1. The cron's **only** write path is the draft importer; its **credential/role physically cannot publish or post** to any platform (not a convention — a capability).
2. **ONE gate.** The cron *invokes* the canonical `/guardrail-audit`; it does not reimplement it. A drifted second gate is worse than no cron.
3. **Public-read only.** An explicit block on logged-in/FB/gated fetches and on storing source text as a generation input.

Run it in a **Coolify cron container against `ANTHROPIC_API_KEY` in Coolify env** — *not* the harness session schedulers (in-memory, 7-day expiry) or a Max-OAuth cloud routine (fails the June-15 metering split and risks the subscription). **Cap at the source** (the topic-triage number, so volume never outruns the single human review pass) **and at the budget** (the fixed Max agent credit). The cron's value is removing prep toil, **not raising volume.**

**Deferred (premature now):** a dedicated renditions DB table (build only when one topic provably needs renditions with *divergent publish dates / post types*); a `WP-CLI import-bundle` command (doctrine says don't — the native scheduler covers drip, and it doesn't exist); any always-on (non-monthly) cron.

---

## 6. The phased plan + the EASY FIRST WIN

**THE EASY FIRST WIN (do this week — highest leverage, lowest effort, zero new review cost):**
**Add two cheap text derivatives to the bundle: `newsletter_blurb` and `x_thread`.**
- Both are **~$0 Claude-drafted text** on Max, both pass the **same** gate, both land via the existing three-place discipline (importer allow-list + Master Bundle Prompt + gate sub-check — soon Phase-B's one-place manifest).
- `newsletter_blurb` builds the **OWNED, deplatform-proof** audience — the single biggest structural protection against the FB-moderator-seat platform risk.
- `x_thread` is a **zero-click ON-PLATFORM brand** surface that thrives on the same curiosity-payoff structure as the FB hook.
- They advance "real audience / brand" **more durably and far more cheaply than any video**, and they cost essentially zero extra review. This is the smartest first move because it proves the "one topic → more native renditions" thesis with the lowest-risk outputs in the catalog.

**Then, in order, each shipping only when the prior is *boringly stable*:**

- **Phase 1 (now):** Keep FB Groups + Pinterest as the proven DRIVE base. **Add `newsletter_blurb` + `x_thread`.** Ship **Engineering Phase A** (per-rendition status/log + per-channel Cockpit checklist). Verify the §0 Pinterest pin-format question; run multi-slide as a *single* experiment if live docs confirm link-out.
- **Phase 2:** **Engineering Phase B** (renditions manifest) + **Phase C** (exporter). Then **render ONE vertical video per pillar** from `reel_script` — hand-upload to all four vertical surfaces as ON-PLATFORM brand + in-app search, **Virality-Predictor-screened first**, AI-labeled, anti-slop axes rotated (portrait/hands/object × gaze × time-of-day × accent). One render, four uploads, **zero per-platform re-edit.**
- **Phase 3:** Faceless **long-form YouTube** as a monetizable pillar in its own right (and the richest atomization source). Only after Phase 2's single-video lane is routine. Consider **Engineering Phase D** (capped cron) here *only if* throughput genuinely can't keep up.
- **Phase 4:** The **Arabic clone** — separate repo, native original content, Egypt-first FB/YouTube, then YouTube long-form anchor, then Gulf Snapchat. The no-translate sub-check and Arabic-fluent reviewer in the gate **before any piece ships**; legal sanity check before launch; 10-piece validation before scaling.

**The principle threaded through all of it — human touch from a smart process:** Claude atomizes wide and cheap and stages drafts; the **human owns exactly two moments** — the one monthly review pass and all manual, staggered posting/uploading. Measurement stays split by job (DRIVE → pages/session per `utm_campaign`; ON-PLATFORM → follower/brand-search lift; never scale a channel on view count), fed into `/retro` so the engine never over-invests in a link-suppressed surface as if it were a traffic channel. We scale the **cheap text/image derivatives aggressively** and treat **video as a single render repurposed four ways** — never six channels at once, never a bot posting, never someone else's expression in any language.

---

## 7. Corrections from the adversarial review (apply before building)

Two HIGH fixes are already folded in above: the Pinterest **static-pins-are-the-DRIVE-default** correction (§0) and the **doctrine-governance note** (top). The rest:

- **Honest per-surface human-minute budget — stage ONE at a time.** New text fields (`newsletter_blurb`, `x_thread`) are NOT "zero review": each is another field to read in the review pass and another manual send/post. The newsletter also needs a one-time compliant ESP + unsubscribe + sender identity (CAN-SPAM) — not free. So add the **newsletter alone first**, let it get boringly stable, *then* `x_thread`. Re-assert the doctrine's rule: if the monthly session exceeds its time cap, cut a surface, don't add hours.
- **Cron cost, stated honestly.** Post-June-15, `claude -p` / Agent SDK / GH Actions draw a SEPARATE metered credit at API rates, no rollover, HARD STOP when exhausted — leave overflow billing OFF so it fails safe. Route models (Haiku to mine, Opus only for the final draft+gate) and set a token ceiling. The cron stays DEFERRED until the base provably can't keep up; its value is removing prep toil, not raising volume — on a cost-conscious solo budget the cheapest correct answer may remain "no cron, keep the Max-interactive monthly batch."
- **The Arabic clone gets its OWN native gate.** Do NOT machine-translate the English `/guardrail-audit`. Author a fresh in-language canonical gate (banned phrases, MSA-for-claims / dialect-for-hooks, cultural / Ramadan / halal / gender-body framing) with the no-translate originality sub-check + an Arabic-fluent human reviewer written in BEFORE the first piece ships — in its own repo, its own single source of truth.

---

Relevant files for the engineering work: `c:\Users\user\OneDrive\Documents\viral-health\wp-content\plugins\dr-purg-social-syndicator\dr-purg-social-syndicator.php` (importer/manifest/status work), `c:\Users\user\OneDrive\Documents\viral-health\.claude\commands\guardrail-audit.md` (originality+provenance sub-check), `c:\Users\user\OneDrive\Documents\viral-health\.claude\commands\find-topics.md` (signal-only research schema), `c:\Users\user\OneDrive\Documents\viral-health\docs\chatgpt-prompt-pack.md` (Master Bundle Prompt: add `newsletter_blurb`, `x_thread`), and `c:\Users\user\OneDrive\Documents\viral-health\docs\build-plan.md` (phase sequencing). Suggested new doc: `c:\Users\user\OneDrive\Documents\viral-health\docs\atomization-map.md`.

---

*Provenance: synthesized 2026-06-13 by a 6-agent workflow (multi-platform engine + MENA landscape + legitimacy/IP + automation architecture -> roadmap -> adversarial IP/ToS/feasibility review). Companion to docs/operating-doctrine.md, growth-strategy.md, build-plan.md. The legitimacy line (signal/template -> ORIGINAL, never translate-and-repost) is non-negotiable.*
