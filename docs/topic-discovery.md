# Topic discovery — the harmonic engine (Max plan)

Finding the right topic is the biggest upstream revenue lever. This is how to do it on the Max plan, wired into the system you already built (rubric → Calendar → `/draft-batch` → Performance loop). Companion to `docs/fb-click-playbook.md`, `docs/topic-bank.md`, and `docs/claude-code-engine.md`.

## The reframe

> Topic discovery isn't a tool you buy — it's a **human-triggered Claude Code skill that fuses signals you already own and scores them through the existing rubric before they reach the Calendar.** Your two unfair, un-copyable edges: **moderator visibility into the exact questions your audience asks**, and **first-party Search Console demand**. No competitor has either, and both are free. The job is to harness them, not bolt on a keyword tool — and to **augment the calendar idea generator you already shipped**, not rebuild it.

## Discovery sources, ranked by signal quality

1. **FB-group questions, read manually as a moderator** — *the moat.* Demand observed at the exact point of distribution, in the audience's own words; auto-clears the two hardest rubric gates (self-relevance + tag-a-friend). Highest signal, lowest volume, **uniquely yours.**
2. **Your own Performance/GA4 loop** (clicks by `utm_campaign` + pages/session) — the only signal proven *in dollars*; the posterior that grades every other source. Dominant **once a cluster has ~5 posts / ~300 sessions**; noise before that (so it carries little weight for the first months).
3. **Google Search Console queries** — first-party proven demand; **position 8–20 / high-impression-low-CTR** rows are topics you nearly rank for and under-serve (FB clicks + free organic clicks). Quantified, exportable. (May be sparse on a young site — a bonus when it fires.)
4. **Public web demand via WebSearch** — People Also Ask, Reddit/Quora phrasings, "why do I…" autocomplete. One step removed from your audience → use to **corroborate/expand 1–3, not lead.**
5. **Trends & seasonality via WebSearch** — a perishable **WHEN** multiplier, not a WHAT generator (flip the rubric's seasonal penalty when the season is live).

## The moat habit (the single most important thing)

**Weekly, ~10 min, as a mod:** skim the last ~7 days in your groups and paste **10–20 recurring member questions verbatim** (with rough frequency + which group) into `docs/signals/fb-questions-YYYY-MM-DD.md`. Manual reading only — **never scrape Facebook.** Keep captures to anonymized *questions*, not member names/quotes. This is your un-copyable signal; if you skip it, discovery quietly degrades to weak public-only data.

## The `/find-topics` skill (thin, augments the existing generator)

A human-triggered Claude Code skill (`.claude/skills/find-topics/`, `disable-model-invocation: true`, optional seed arg e.g. `/find-topics sleep`). It does **not** replace the in-plugin "Generate ideas" button — it feeds it the signals that button can't see, and fixes its dedup gap. Four steps in one interactive run (free on Max):

1. **Gather** — read this week's `docs/signals/` captures (FB questions, GSC CSV, GA4-winners note) + WebSearch PAA/forum/autocomplete around the seed (public pages only; no looping Suggest/SERP endpoints).
2. **Dedup** — against published + drafted + planned titles **AND** the current Calendar ideas **AND** `docs/topic-bank.md` (the in-plugin generator only sees the last ~25 titles — the skill must add the rest).
3. **Score** — apply the **canonical rubric from `docs/fb-click-playbook.md`** verbatim (the *same* text `/draft-batch` uses, so discovery and drafting never drift; keep only ≥13; self-relevance and honest-safe-payoff are auto-reject gates).
4. **Rank + output** — emit ranked survivors as the Calendar's exact idea shape `{title, angle, hook, notes}` with **score + source signal in `notes`** (e.g. "asked ~8× in [group]; GSC 1.4k impr / 1.1% CTR / pos 7; score 16"). **Output JSON you paste into the existing Calendar UI** ("Add an idea"). No new infrastructure.

## The harmonic loop

```
DISCOVER (/find-topics, $0 on Max — fuses the 5 signals)
   → SCORE (canonical 6-factor rubric, same text everywhere)
   → CALENDAR (paste survivors as 'idea'; you triage Idea→Planned)
   → DRAFT (/draft-batch turns top planned ideas into draft bundles)
   → POST (manual to FB groups via Cockpit; utm_campaign=slug, utm_content=v1..vN)
   → MEASURE (Performance + GA4: winning angle families + topic clusters)
   → RE-WEIGHT (the winning cluster becomes next week's /find-topics seed;
               retire losers ~2 weeks; always keep ONE wildcard)
```
The harmonic part: **MEASURE changes the weighting of DISCOVER each cycle.**

## Weekly routine (~30 min total)

- **Mon ~10 min — capture the moat:** paste recurring FB-group questions into `docs/signals/`.
- **Mon ~10 min — capture demand + results:** export GSC queries (position 8–20 / low-CTR); run the GA4 pull, note the winning cluster + top/bottom `utm_content` angles.
- **Mid-week ~15 min — discover:** run `/find-topics [winning-cluster]`; review the ranked proposals; approve keepers into the Calendar (Idea→Planned, date them).
- **All week — draft + post:** `/draft-batch` → manual post (canary then widen) → log each group.

## Honest constraints (from the critic)

- **Augment, don't rebuild:** the Calendar idea generator already ships; `/find-topics`'s real value is (a) the first-party captures the in-WP generator can't see and (b) the dedup fix. Don't reinvent ideation.
- **Manual paste first; defer the CLI:** hand-pasting ~5–10 ideas/week into the existing Calendar UI is the 80/20. A `wp dpj add-calendar-idea` command + docker `wp` runner is real but **premature** — build it only when pasting actually hurts.
- **Pin the rubric count:** the playbook lists tag-a-friend as a sub-test of self-relevance — treat it as **one canonical rubric** used identically by `/find-topics`, `/draft-batch`, and the Project so scoring never drifts.
- **Pages/session isn't pulled yet:** the GA4 pull currently fetches **clicks only**; pages/session is manual entry. So "winners = clicks AND pages/session" is partly hand-maintained until that metric is added (a later, optional enhancement).
- **ToS:** manual reading of your own groups + WebSearch of public pages only. Never scrape Facebook or gated sites, never loop Suggest/SERP endpoints, never auto-post. Review-first: every idea is a proposal you approve.

## First step (corrected — cheapest, highest-signal)

Not a new WP-CLI command. **Start the weekly `docs/signals/fb-questions-*.md` capture this week, and build a thin `/find-topics` skill that reads those captures + GSC + topic-bank/calendar, scores on the canonical rubric, and emits ranked idea JSON to paste into the Calendar UI you already have.** Defer the write-socket and the CLI until manual paste hurts; treat the existing Phase 5 generator as the thing you're feeding better signals.
