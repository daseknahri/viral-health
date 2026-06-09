# Dr Purg Jr. — Build Plan (the engineering roadmap)

How we turn `docs/growth-strategy.md` into a working production suite. Strategy = *what/why*; this = *what we build, in order*. Every phase keeps the non-negotiables (general health info only, real clicks only, manual group posting, review-first, secrets in Coolify env). Companion: `docs/growth-strategy.md`, `docs/operator-playbook.md`, `docs/social-syndicator.md`.

## Phase 0 — Contract + gate extension ✅ DONE (plugin v0.7.4)
The plumbing that lets one article fan across channels as DRAFT fields.
- **Importer** stores four optional bundle fields — `discover_image_prompt`, `pin_set`, `reel_script`, `community_answer` — via a recursive `sanitize_structured_field` helper (tags + URLs stripped, JSON-encoded meta). Additive; can't break the existing import or the public page.
- **Canonical gate** (`/guardrail-audit`) section D covers each new field with real sub-checks: Discover title honesty (`seo_title` ≠ curiosity hook), pin_set ≤5 distinct + URL-free, reel_script general-only + CTA-not-a-URL + no self-diagnosis, community_answer general-only + clinician note + URL-free.
- **Master Bundle Prompt + `/draft-batch` + `/draft-month`** emit the pack; the **readiness audit** guards the new contract.
- Verified: php -l clean, readiness OK, adversarial review (PHP correctness + cross-surface consistency) clean.

## Phase 1 — UI suite (the production console)
Make the plugin admin a per-channel review-and-post cockpit, surfacing the Phase-0 fields.
- A **Channel Cockpit** per post: render `pin_set` (copy each pin + open Pinterest), `reel_script` (copy script/voiceover), `community_answer` (copy Reddit/Quora), `discover_image_prompt` (copy for image gen) — each with a copy button and a "mark posted" log, like the existing FB-group Cockpit.
- **Escaping note (carried from the Phase-0 review):** these meta are currently write-only; when the Cockpit renders them, escape on output (`esc_html`/`esc_textarea`).
- Per-channel UTM on the copy links (extend the existing `social_utm_url`).
- Calendar view that shows which channels a post has been pushed to.

## Phase 2 — Claude video / voiceover pipeline
The "content people watch," figured out by trial and error.
- Claude writes `reel_script` (hook + beats + **voiceover** + group-follow CTA); Higgsfield renders the vertical clip (GPT Image 2 still → Seedance image-to-video), Virality Predictor (`brain_activity`) pre-scores hook/retention so only high-completion clips ship.
- Iterate to a repeatable faceless-explainer format + a voiceover style. Review-first; **AI-content label on every upload**; manual upload.
- Output feeds FB Reels (Tier 2 funnel) → repurposed to TikTok/IG/Shorts (Tier 3, repurpose-only, judged on brand-search lift not views).

## Phase 3 — Consistency cadence (the monthly factory)
One focused Claude Code session → a month of cross-channel creative, drip-published, human-reviewed.
- `/retro` (weekly) → `/find-topics` → `/draft-month` (the pack) → Higgsfield render → review → publish/queue/post by hand. Measure with GA4 (pages/session per `utm_campaign`) + the per-group/per-variant splits. Pin Pinterest at ~30–60 *reviewed* pins/month (no bulk automation).

## Phase 4 — Arabic clone (new market)
Soft-translate/localize the US "consumable" health-facts content for the Arab world as a **separate blog** (own repo, `CLAUDE.md`, commands, brand, credentials — per `docs/replicate-food-blog.md`). Same engine + guardrails; translation must preserve general-info-only framing (no medical advice), localize idioms/examples, and keep its own rubric. Claude as translator + localizer, review-first.

---

*Sequence rationale:* Phase 0 unblocks everything (done). Phase 1 makes the pack usable without a spreadsheet. Phase 2 unlocks the highest-reach format. Phase 3 makes it sustainable for a solo operator. Phase 4 multiplies the whole engine into a new market once the first blog is proven. Don't start a later phase before the earlier one is stable.
