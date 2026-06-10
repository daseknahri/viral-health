# Dr Purg Jr. — Operating Doctrine
*"Produce good content, the easiest way." The one page we live by. v1.0 — 2026-06-10*

## 1. The goal, in one line
**Ship a small number of genuinely good health-facts articles every month, banked in a single sitting, and distribute them through exactly two channels by hand — so the only recurring work is paste, publish, post.**

The good-vs-easy tension is resolved by deciding *where* each one is bought:
- **"Good" is bought in code, once.** The raised `/guardrail-audit` craft gate (hook-as-contract, sections-that-teach, escalation beat, contested-science honesty, a named second-click cue that lifts pages/session) is the quality floor. It is identical on an inspired week and an exhausted one. We never lower the bar; on a bad week we review *fewer* articles, never *lower-bar* ones.
- **"Easy" is bought by subtraction.** We cut channels, asset-types, and volume — never the gate. Easy is defined as *fewest reviewed surfaces per shipped click*, because the operator's single review pass is the real bottleneck, not Claude's drafting minute.

They stop fighting at the review pass: a small reviewed surface keeps that one human glance an honest quality gate instead of decaying into "approve all."

## 2. Operating principles
- **Two channels only: Facebook Groups (home) + Pinterest (compounding hedge).** FB Groups is the only proven payer; Pinterest is the one cold channel that also sends deep multi-page readers and compounds. Add a third channel only when these two are boringly stable *and* the numbers force a specific one — never on a hunch.
- **Batch once a month, in one calendar-locked session.** All drafting and all featured images happen in a single ~2h block (first Sunday). The other ~29 days run zero Claude sessions and zero writing.
- **Keep a one-month buffer; never draft under deadline.** You always post *last* month's banked, gate-passed work. A skipped session costs the buffer, not the live chain — giving ~4 weeks of warning before anything breaks.
- **Claude drafts and scores; the human approves and posts.** AI is review-first by guardrail. The human touches each article at exactly two irreducible moments: one review pass, and the manual FB-group / Pinterest posts. Everything else is generated, gated, and pre-staged.
- **Fewer, better beats more, thinner.** Keep 8 articles/month (cap topic triage at 8–12 candidates, not 20). Pages/session and RPM both reward a destination people actually read.
- **The cut rule: if a month needs more than ~2.5h, cut a layer — never add hours.** Quantity flexes; the gate and the chain do not.

## 3. The loop (with honest effort budgets)

**MONTHLY — one calendar-locked session, ~2h total, $0 marginal (Max).** *Claude owns the generation; the human owns the review.*
- `/retro` (skip month 1) — reads Performance + the month's GA4 numbers, crowns the winning hook angle + topic cluster, returns one seed. *Claude. ~10 min incl. re-verifying the 3 platform facts (link-in-first-comment, Pinterest queue limit, AI-label flow).*
- `/find-topics <seed>` — ranked, rubric-scored candidates; operator keeps the best **8–12**. *Human triage. ~10 min.*
- `/draft-month` on those topics — canon loaded once, 8 full bundles, each passed through `/guardrail-audit`. *Claude. ~15 min run.*
- **REVIEW PASS — the irreducible quality moment.** Read each gate-passed bundle once for taste and honest payoff; accept/reject. *Human. ~25 min (~3 min × 8).*
- **IMAGES, batched now.** Copy each `image_prompt` → one 4:5 featured still (Higgsfield/Gemini). *Human-triggered, Claude renders. ~30 min for 8.*
- `git commit docs/signals` — **the month is banked.** *~2 min.*

**WEEKLY — the heartbeat, ~30 min, NO Claude, NO repo.**
- Two posting pushes (~12 min each, see per-post). A genuinely bad week drops to **one** push and still keeps the chain.
- Numbers: Performance → enter RPM/revenue, note pages/session per `utm_campaign`. *~5 min.* (See §6 — fold this into the monthly session if weekly toil creeps.)

**PER-POST — ~12 min on a posting day, NO Claude.** *All human; copy-button-fed by the Cockpit.*
- Paste bundle → New from Claude → create draft → set the pre-made featured image → Publish → generate local social cards. *~4 min.*
- Cockpit: copy caption + first-comment tracked link + card → **manually post to one canary FB group** (link in first comment) → Mark posted. Widen to more groups, staggered, over the next 1–3 days (~2 min each). *~5 min.*
- Publish/queue **~3 pins** for that article (native scheduler where allowed, hand-publish the rest). *~3 min.*

**What Claude owns:** `/retro`, `/find-topics`, `/draft-month`, the `/guardrail-audit` gate, all article + FB + Pinterest copy, the pin-angle variants, the featured stills, and the per-group/per-variant UTM links.
**Irreducible human steps:** the one monthly review pass; clicking Publish; the manual, staggered FB-group post; manual pin publishing; entering RPM/pages-session. None of these is ever automated — the guardrails reserve them for a person.

## 4. What we deliberately DON'T do
- **Facebook Reels — CUT.** It's a group-*growth* funnel, not a click channel, and the render + virality-score + manual-upload + AI-label + hand-attribution lane is the heaviest in the strategy. Not until the two-channel base is stable and group growth is the *proven* bottleneck.
- **Google Discover — CUT from the action list.** Needs an E-E-A-T author page, cluster planning, GSC wiring for a months-out, unproven payoff. We keep `seo_title` honest (real topic, never the curiosity hook) but spend zero hours acting on Discover.
- **Reddit / Quora — CUT.** A 3–4 week daily warm-up is the opposite of easy and carries the highest personal-advice drift risk.
- **TikTok / IG / Shorts — CUT.** Link-suppressed by design; only ever a repurpose off a Reel we aren't making.
- **The `wp dpj import-bundle --drip` CLI — DO NOT BUILD.** Verified: there is **no `WP_CLI::add_command` anywhere in the plugin**. It's a solo-dev build *month* with no content shipped, to automate the part that was already cheap (publishing) while leaving the irreducible manual FB post untouched. We get ~80% of its benefit for $0 via WordPress's native scheduler (§6).
- **Volume above ~8–12 articles/month, hook-variant sprawl (keep 2–3 max), and Pinterest bulk (cap ~3 pins/article, ~24–30/mo).** Volume past the review bar is slop that gets down-ranked and turns review into rubber-stamping.

Ease beats all of these *for now* because every one multiplies the per-article human steps — upload, label, warm-up, attribute, review — without touching the same article's quality or the single revenue formula.

## 5. The one metric that matters
**Pages per session, read per `utm_campaign`** — it is the literal middle term of `revenue = clicks × pages/session × (RPM/1000)`, the one number that proves an article was good enough to earn a second page, and the only signal `/retro` needs to crown next month's winner.

How little it takes to read it: clicks-by-campaign are already tool-read in the Cockpit via the UTM links; pages/session is hand-noted from the GA4 UI. The read-API auto-pull is blocked, so this stays manual — but the loop only *consumes* the number monthly, so reading it monthly (during the batch session) is enough.

## 6. The next concrete step (highest-leverage, drawn from the critique)
**Reconcile the importer-vs-gate contract, then turn on native drip-scheduling — no new code, both done in the monthly session.**

1. **Fix the live divergence first (build-order must).** The gate requires all four pack fields (`guardrail-audit.md` C/D: "a missing field is a FIX") but the importer treats them as optional (`dr-purg-social-syndicator.php:830`, `?? null`). Make them agree: **mark the pack optional in the gate + Master Bundle Prompt** so the two-channel loop is coherent and we generate only the `pin_set` we'll actually use. Run `node scripts/audit-social-syndicator-readiness.mjs` after.
2. **Then schedule the banked drafts to future dates in WordPress's native scheduler** (`post_status=future`) so the site shows public motion on weeks you touch nothing. This is Design 2's drip benefit without its CLI build — and it keeps every guardrail: drafts stay human-approved, and the FB-group post stays a staggered, per-day manual action that is *never* batch-scheduled.
3. **Shift GA4 number-reading from weekly to monthly** (the loop only consumes it monthly), keeping the weekly touch to tool-read clicks-by-campaign. Separately, keep pushing to unblock the GA4 service account — it's the single highest-value piece of plumbing for an unattended learning loop.

The guardrail to defend hardest, exactly where ease is most automated: the manual, canaried Facebook-group post. The plugin may auto-comment on scheduled *Page* posts — it must **never** touch *groups*. That one automated step would torch the moderator seats and the ad account.

*The thing that most determines whether we're still doing this in month 6: keeping the reviewed surface small (two channels, one image, ~8 articles) so the single human review pass stays an honest gate. Fewer surfaces, reviewed honestly, beats more surfaces rubber-stamped — that is where good and easy stop fighting.*

---

*Provenance: synthesized 2026-06-09 by a 5-agent design panel (subtract / automate / sustain designs -> adversarial hidden-effort critique -> doctrine). Companion to docs/growth-strategy.md (what/why) and docs/build-plan.md (engineering). The operator lives by this; revisit when a channel is added or the loop changes.*
