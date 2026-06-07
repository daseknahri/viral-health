# Content experiments — the open door

`/draft-batch` reserves one **experiment slot** per batch and rotates through the families below (pick the next one not recently used; log which you used so a future `/retro` never re-tests the same one). Every experiment must still pass the full guardrail gate — calm, general health info, no medical claims. The goal: keep discovering what makes content excel instead of only repeating past winners.

## Rotation families (cycle through these)

1. **Named-trigger vs vague hook** — same topic, two `facebook_hook`s: one naming a concrete trigger + body-part + moment ("puffy hands the morning after salty food") vs an abstract-theme hook. *Metric: clicks by `utm_content`.* Tests whether specific curiosity beats broad.
2. **Numbered round-up body** — "3 ordinary reasons most people feel X" as the body structure vs standard prose. Counts everyday *factors/reasons* only — never a health statistic or percentage. *Metric: pages/session + dwell.*
3. **Micro "try it now" self-observation** — one harmless, **universal** sensation the reader can feel while reading ("notice how your breathing changes when you sit up straight"). Must stay collective/universal, never a symptom checklist. *Metric: pages/session + comments.*
4. **Myth-correction vs body-signal angle** — same cluster, drafted both ways (each a full hook+overlay+first-sentence contract). *Metric: clicks.* Tests which structural family wins a cluster.
5. **Discussion-tail vs open-loop summary** — `facebook_summary` ends on a calm GENERAL open question ("curious how many notice this in the morning vs at night", ≤260 chars, never personal-diagnostic) vs an open-loop cliffhanger. *Metric: comment count AND clicks* — a fast first-hour thread is what group reach rewards.

## Adopted writing upgrades (now baked into the prompt + `/draft-batch`)

- **Lede → nut-graf:** first sentence = the exact promised payoff; one line on "why it matters on a normal day" before the first `<h2>`.
- **Bold scan-layer:** one `<strong>` key phrase per section for skimmers (`wp_kses_post` keeps `<strong>`/`<em>`).
- **Named-trigger hook test:** concrete trigger + body-part/moment, not an abstract theme.
- **URL-less second-click cue:** a same-theme prose suggestion at the end of the body — no link/slug (the site adds links; the body stays URL-free).

## Image ideas to test (when the image step evolves)

- **Parameterized second hook:** rotate axes `{portrait | hands | object}` × gaze (down-at-body-part or off-frame toward the overlay, never camera-stare) × time-of-day lighting × one warm accent color; keep subject in the upper third, clean lower third for the burned overlay; keep the `no text/logos, undistorted hands/faces, 4:5, ≥1200×1500` tail.
- **Hands-as-hero / single-object cards** for sensation topics where a posed face is awkward — zero diagnosis-risk, crops cleanly into tall cards.

## Next structural move (when there are 2+ content skills)

Factor the `/draft-batch` guardrail gate into a shared **`/guardrail-audit`** skill that `/find-topics`, `/draft-batch`, and any future `/repurpose` or `/hook-lab` all call — so guardrails can't drift across skills as the suite grows. This is the highest-value structural step once more than one content skill exists (it now does), but it's optional until drift actually shows up.
