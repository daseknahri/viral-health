# Dr Purg Jr. Operator Playbook

This is the day-to-day operating guide for running Dr Purg Jr. now that the growth engine is built (`docs/growth-engine-roadmap.md`). It ties the tools together into a repeatable routine and captures the hook-writing knowledge that turns Facebook-group reach into clicks. It assumes the strategy in `docs/ai-content-growth-strategy.md` and the tool reference in `docs/social-syndicator.md`.

The whole operation optimizes one line:

```
Monthly revenue = FB link clicks × pages per session × (RPM / 1000)
```

Your daily work moves the first term (more clicks) and the loop below makes each future post move it a little more, legitimately.

## The loop, end to end

```
Calendar  →  Write  →  Package (AI draft + hooks + images)  →  Post manually  →  Log  →  Measure  →  back to Calendar
```

Each arrow is a real admin screen. Nothing posts itself — you stay the publisher at the one step that matters (sharing to groups), and the tools remove the preparation around it.

## Daily routine (~20–30 min, excluding writing)

1. **Pick from the Calendar.** `Social Queue → Calendar`. Work the top cards (dated first). If the backlog is thin, click `Generate ideas` (optionally with a seed like "sleep" or "gut health") and triage what comes back — keep the genuinely curious ones, delete the rest, set a date on the keepers.
2. **Write the article.** `Posts → Add New`, then follow `docs/author-workflow.md` (Auto fill, split, checklist). Publish when it reads well. On publish you land in the Social Editor automatically.
3. **Package it.** In the Social Editor:
   - `Generate AI social draft` to fill the platform fields, then **read every field** — it is a draft, not a decision.
   - `Generate hook variants` and `Apply` the angle you believe in (see the hook principles below). This sets the Facebook hook + image overlay and tags the link `v1..vN` so you can tell later which angle won.
   - `Generate local social cards` for the platform-sized images. Watch the Image QA panel: keep overlay text 8–12 words, and prefer a portrait/square source ≥ `1200x1500` so nothing crops or upscales badly.
4. **Post manually.** `Social Queue → Cockpit`. Copy the caption and first comment, grab the image, paste into your Facebook group(s). Use the tracked link so clicks attribute in Analytics.
5. **Log it.** On the same Cockpit card, type the group name and `Mark posted`. This is your record of where/when, and the only honest way to compare groups later.

## Weekly routine (~30 min)

1. **Read the scoreboard.** `Social Queue → Performance`. Pull clicks from Analytics (GA4 / Site Kit) filtered by each post's `utm_campaign` (its slug), and enter RPM + finalized revenue. The totals line is your week.
2. **Find the winning angles.** Compare hooks and `utm_content` variants. Which angle type earned clicks? Which groups converted (from the posting log)?
3. **Feed it back.** In the Calendar, lean next week's `Generate ideas` seed toward the winning themes, and write more in the angle families that worked. This is the compounding part — the loop teaches you what your audience opens.

## What makes a health-curiosity hook click

The whole top of the funnel is the hook. These are the patterns that earn the open without breaking trust or the guardrails.

**Angle families that work** (the hook variant generator rotates through these — learn them so you can judge its output):

- **Curiosity gap** — name a surprising outcome, withhold the mechanism. *"Why your hands go cold before the rest of you does."*
- **Surprising number** — a concrete, checkable figure. *"Most people miss 15g of fiber a day. Here's where it hides."*
- **Common mistake** — a thing the reader probably does. *"The breakfast habit that leaves you hungrier by 10am."*
- **Body signal** — read your own body. *"What morning mouth dryness can be quietly telling you."*
- **Myth correction** — overturn a belief calmly. *"You don't actually need 8 glasses of water. Here's the real signal."*

**Make it click (do):**
- Lead with the reader's body or daily habit — relatable beats clever.
- Keep it under ~95 characters so it survives on mobile.
- Pay the hook off in the article calmly; curiosity that gets a flat payoff burns trust and future reach.
- Use one idea per hook. Two ideas is zero hooks.

**Kill the click (don't):**
- No diagnosis, cure, treatment, prevention, or "doctors hate this" claims — these are the guardrail and also what gets posts removed from groups.
- No fake urgency ("you MUST stop NOW"), no fear-mongering, no invented numbers.
- No clickbait the article can't honor. The promise and the payoff must match.

**Test, don't guess.** That is the entire point of hook variants + UTM + the Performance log: ship 2–4 angles over time on similar topics, read which `utm_content` earned the clicks, and let real numbers — not taste — pick your house style.

## Guardrails (the asset you are protecting)

These are non-negotiable because they protect the Page, domain, ad accounts, and moderator seats the whole operation depends on (see `docs/growth-engine-roadmap.md` and the project memory):

- **Real traffic only.** No proxy/residential-IP traffic, bots, or click generation against any ad network. That is invalid traffic and it permanently blacklists the domain.
- **Publishing stays human.** No bots or extensions that mass-post to Facebook groups around its anti-automation limits. The tools prepare; you post.
- **Health content stays careful.** General information only; curiosity hooks pay off with calm context.
- **AI is review-first.** Every AI field — drafts, hook variants, calendar ideas — is a draft you approve. AI never posts and never enables ads.

## Where each tool lives

| Step | Screen | Reference |
|---|---|---|
| Plan topics | `Social Queue → Calendar` | `docs/social-syndicator.md` → Content calendar |
| Write | `Posts → Add New` | `docs/author-workflow.md` |
| Draft + hooks + images | `Social Queue → Social Editor` | `docs/social-syndicator.md` |
| Post + log | `Social Queue → Cockpit` | `docs/social-syndicator.md` → Posting cockpit |
| Measure | `Social Queue → Performance` | `docs/social-syndicator.md` → Performance log |
| Monetization | (parallel) | `docs/ads-optimization-playbook.md`, `docs/ad-operations-manual.md` |
