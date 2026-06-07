# Dr Purg Jr. — Facebook Click Playbook

How to maximize clicks from Facebook groups **without** breaking the health guardrails or risking the moderator seats. This is the strategy companion to `docs/operator-playbook.md` (the daily loop) and `docs/chatgpt-prompt-pack.md` (the prompts). It came out of a multi-lens analysis + a guardrail review; the corrected, safe version is below.

## The big reframe: the click is won at SIX gates, not one

A group reader passes six gates in ~2 seconds, and your result is capped by the **weakest** gate — not the average. You've mostly been optimizing two of them (topic + words). The real order a mobile reader experiences:

1. **Is this about ME?** → topic selection
2. **Does the IMAGE/overlay stop my muted scroll?** → the card is a *second hook*, read before the caption
3. **Do the first ~8 words open a loop before "See more"?** → hook + caption
4. **Is this a real member sharing, not a brand spamming?** → native caption + link-in-first-comment
5. **Right moment, right group, trust intact?** → timing, cadence, group rules
6. **Is the promise paid off above the fold so I read a 2nd page and click again next week?** → payoff + repeat reader

Two under-used truths: the **image is your real second hook**, and the **payoff gate** is what compounds (revenue = clicks × pages/session × RPM — the last two only move if you stop over-promising). Fix the gate that's actually leaking, and prove it with the UTM/Performance loop.

## Prioritized playbook (ranked by leverage, guardrail-corrected)

1. **Image + overlay = the silent second hook.** One relatable US adult, face in the **upper third**, an 8–12 word curiosity *fragment* overlay placed **off the face** (`crop focus = keep top`, `overlay position = bottom`). The overlay reveals **no answer** and implies **no personal diagnosis**. (Treat "image read before caption" as a working assumption — let clicks-by-variant confirm.)
2. **Hook + caption = the ad** (the link lives in the first comment). Front-load ONE specific open loop in the first ~8 words; full hook 60–90 chars; no answer leaked. **Cap the withhold:** the reader must still predict the *topic and calm tone* of the payoff — if the only reason to click is that you hid something, it's clickbait and gets downranked.
3. **Body-signal angle, strictly general.** Frame sensations as *"why **most people** feel X after Y"* — never *"is **YOUR** X a sign of…"*, never a named deficiency/condition/supplement as the withheld answer, never "should I be worried." If the loop only closes with a scary/medical claim, recast or drop it. **This is the safety rail on the whole curiosity engine.**
4. **Native-member caption + link in first comment.** Open line one as a real member would type ("most people don't realize…"); keep all URLs out of the caption; let the image's "LINK IN FIRST COMMENT" + a soft "full breakdown in the comments" carry the CTA. Reads native (not brand-spam mods remove); matches what the plugin already does. Posting to groups stays a **manual** action.
5. **Payoff + the second click (the real compounding lever).** Deliver the exact thing the hook promised in the **first sentence / first H2**, above the fold. End the **article body** with ONE genuinely related **URL-less prose suggestion** ("if this surprised you, look up what we found about cold hands") — a same-theme nudge, **no `<a>` link or slug** (the site adds internal links at publish; the article body stays URL-free), never a chum grid. Fast honest payoff is what makes a group click your name *again* and lifts pages/session.
6. **Group timing, cadence & rule-fit — as hypotheses, not rules.** Segment groups into topic buckets × trust tiers. Treat US-mobile windows (roughly early-morning and mid/late-evening ET) and "one link per group every few days, staggered over 3–5 days" as **starting defaults to validate against your own posted-log + Performance data** — not Facebook rules. The hard rule is qualitative: **follow each group's rules, post like a member, never same-hour blast.** A single violation can cost a moderator seat — your distribution moat.
7. **Safety canary, THEN the fair A/B (two separate jobs).** *Canary:* post your lead variant in one strong matching group, read the first 60–90 min of clicks + comment tone before widening — a safety/tone check, not an experiment. *A/B:* post the **same two genuinely different angles** across **comparable** groups so `utm_content` isolates the angle, not the group. Log the exact group name in the Cockpit every time.
8. **The measurement loop improves all the others.** Weekly: read clicks by `utm_content` (not by gut), and pair each winner with GA4 **pages/session** for that `utm_campaign`. Flag **"high clicks, low pages/session" as a broken payoff** (hook out-ran the article) and retire that hook pattern even if CTR looked good. Crown the winning angle family + topic cluster, seed next week toward it, retire the loser ~2 weeks, and always run ONE wildcard angle so you keep discovering.

## Topic "can't-resist" rubric (score before writing)

Score 12–15 candidates together, 0–3 each; write only those clearing the bar; always pick the **narrowest version that still has broad reach**.

- **Self-relevance / "that's me":** anchor to a body part, sensation, or daily moment they can self-check in 2 seconds. *Score 0 → auto-reject.*
- **Tag-a-friend:** can you name the exact friend they'd tag, or the "wait, is that why…?" they'd mutter? If not, it's too generic.
- **One specific open loop:** state the single question in one line ("why you wake around 3am"), not a theme ("sleep tips").
- **Zero prior knowledge:** a stranger cares with no chart, study, or named condition.
- **Broad reach:** applies to most US adults, not a niche/diagnosis. (Seasonal scores one lower unless the season is active.)
- **Mild surprise:** the honest takeaway is gently counter-intuitive, not common knowledge.
- **Honest + safe payoff GATE:** the loop closes calmly with general info — no diagnosis/cure/fear/invented stat. Kill any "is this a sign I have X?" on sight. *Score 0 → auto-reject.*

## Writing-style rules that earn clicks honestly

- **Hook → article CONTRACT:** the Facebook hook, the image overlay, and the article's first sentence make the *same* promise. If the article can't pay it off in its first screen, change the hook.
- **Pay off above the fold, literally:** sentence one (and an early H2) names the exact thing promised — the number, the "one thing", the fact.
- **Curiosity gap, honestly:** name subject + payoff category, withhold the specific, cap the withhold (reader predicts topic + tone). Never leak the answer into hook/overlay; the body states it plainly.
- **Mobile length:** curiosity in the first ~8 words (before "See more"); full hook 60–90 chars; overlay 5–9 words (max 12); caption ≤ ~3 short lines.
- **Voice:** second person, present tense, "you/your body", warm, ~7th-grade level; short paragraphs, scannable H2s.
- **Summary ends on an open loop** naming what the reader still doesn't know — not a fake cliffhanger.
- **Numbers only if real** and in the article; specific/odd over round; never invent/round/change units.
- **Caption opens as a member, not a brand;** no URL and no word "link" in the caption (it lives in the image hint + first comment).
- **Engineer the second click:** end the article with ONE specific related, same-theme **prose suggestion** (no link/slug — the site adds internal links; the body stays URL-free), not a slideshow.
- **Banned everywhere:** the answer leaking; "amazing / top 10 / you won't believe / doctors hate this / one weird trick"; ALL CAPS or "!"; fake urgency ("now/today/breaking/before it's gone"); fear / "silent killer"; any diagnosis, cure, treatment, prevention, dosage, product-push, or invented stat. Prefer "may be linked to", "what it could mean", "general signs".

## Weekly routine (the loop that compounds)

- **Mon (~10 min):** Performance → enter clicks (GA4 by `utm_campaign`), RPM, revenue; also note **pages/session** per campaign (the ignored multiplier).
- **Mon (~5 min):** crown the winning **angle family** (by `utm_content`) and topic cluster — top 2 / bottom 2.
- **Mon (~5 min):** flag **high-clicks/low-pages-session** posts as broken payoff; kill that hook pattern; note the fix.
- **All week:** ship a **fair test** — 2 genuinely different angles to comparable groups; canary your lead variant first in your best group, then widen over 3–5 days; log the group each time.
- **Fri/Sun (~5 min):** feed winners back into the Calendar's idea generator and the variant angle; retire losers ~2 weeks; refresh a top evergreen as a **pinned post** in an A-tier group (a pin earns clicks all week without re-triggering cadence).

## Prompt upgrade — append to your "Dr Purg Jr. Editor" instructions

Add this block to the project instructions (it keeps the single-JSON-object contract and all existing keys):

```text
TOPIC SCORING GATE (run silently before writing; never print scores): rate the topic 0-3 on six tests — (1) self-relevance: a typical US adult thinks "that's my body/daily life"; (2) one specific answerable open loop, not a vague theme; (3) mild surprise; (4) zero prior knowledge needed to care; (5) broad reach across most adults, not a niche/named condition; (6) honest + safe payoff: the loop closes calmly with general info, no diagnosis/cure/fear. If any test is 0 or the total is under 13, do NOT write it as given — write a sharper, guardrail-safe RECAST of the same subject (keep the JSON shape; never return prose). Anchor topics to a body part, sensation, or daily moment the reader can self-check (hands, feet, eyes, sleep, first hour awake, afternoon dip, right after eating). Reject anything needing a chart, study, expert nuance, named/rare condition, or a "should I be worried" angle.

HOOK = THE PRODUCT, AND A CONTRACT: write facebook_hook, overlay_text, and the FIRST sentence of content_html as one matched set making the same promise. Pay it off in the first sentence of content_html (the number, the "one thing", the surprising fact) so a phone reader sees it above the fold. facebook_hook uses the curiosity gap: name the subject + payoff category, withhold the specific. CAP THE WITHHOLD: the reader must still predict the TOPIC and the calm, general tone of the payoff — if the only reason to click is that you hid something, the angle is clickbait; fix it. Never leak the answer into facebook_hook or overlay_text, but content_html states it plainly and early.

BODY-SIGNAL RULE (safety rail on the curiosity engine): when a hook uses a sensation or body part, frame it GENERALLY — "why most people feel X after Y" — never personally ("is YOUR X a sign of…"), never a named deficiency/condition/supplement as the withheld answer, never "should I be worried." If the loop only closes with a scary or medical claim, recast to the general version or drop it.

STYLE LOCKS: front-load curiosity in the first ~8 words of facebook_hook (full hook 60-90 chars); second person, present tense, "you/your body"; warm, calm, ~7th-grade level. End facebook_summary on an open loop naming what the reader still doesn't know (not a fake cliffhanger). overlay_text = the hook tightened to 8-12 words, a standalone fragment that works muted and reveals no answer. Numbers only if real and present in content_html; prefer specific/odd over round; never invent, round, or change units. Banned: "amazing / top 10 / you won't believe / doctors hate this / one weird trick", ALL CAPS, "!", fake urgency ("now / today / breaking / before it's gone"), fear / "silent killer".

SECOND-CLICK: end content_html with ONE sentence pointing to a genuinely related Dr Purg Jr. topic ("if this surprised you, here's what we found about …") as a same-theme internal-link cue — written inside content_html (not as a separate field), because only the article body renders on the page.
```
