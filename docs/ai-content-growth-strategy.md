# Dr Purg Jr. AI Content Growth Strategy

This document captures the AI, content, traffic, and monetization strategy for Dr Purg Jr. and for future viral-content clones created from this repo.

The goal is to use AI as an editorial and optimization assistant, not as uncontrolled auto-publishing. The site should become easier to operate, better at turning outside drafts into careful health-facts articles, and more intentional about Facebook traffic, social packaging, image quality, and revenue tests.

## Current Site Goal

Dr Purg Jr. is the English, Facebook/mobile-first health-facts site for readers in the United States.

Primary goals:

- Build useful, curiosity-led health explainers that mobile Facebook readers want to open.
- Increase finalized revenue per 1,000 Facebook clicks without wrecking user trust.
- Use Monetag, Adsterra, Histats, and social packaging in a controlled way.
- Keep health content careful, general, and non-diagnostic.
- Treat SEO as useful, but not the first traffic channel.

Best content types:

- Body-signal explainers.
- Food label and habit myth checks.
- Sleep, energy, stress, hydration, posture, and walking-after-meals context.
- Gentle warning-sign articles that tell readers when to seek professional care.
- Practical everyday checks that create curiosity and then give a real payoff.

Avoid:

- Cure claims.
- Diagnosis language.
- Treatment instructions.
- Fake doctor authority.
- Fear-only health clickbait.
- Articles that exist only to force ad impressions.

## Core Principle

AI can help write faster than a human, but the system should still behave like an editor.

Good AI use:

- Build topic tables.
- Draft article outlines and plain-text drafts.
- Suggest excerpts, tags, categories, related links, image alt text, and social hooks.
- Generate reviewed social fields for Facebook, Pinterest, and Reddit.
- Suggest short overlay text for social images.
- Score whether a post is health-safe, Facebook-friendly, and monetization-safe.

Bad AI use:

- Auto-publishing unreviewed posts.
- Making unsupported medical claims.
- Creating fake sources, fake doctors, fake patient stories, or fake urgency.
- Rewriting health articles without a manual accuracy review.
- Enabling ads or posting to social platforms without review.

## Current AI Layer

Implemented:

- Deterministic author-tools extraction.
- Optional OpenRouter repair for incomplete structured extraction.
- Optional Social AI draft assistant through Social Syndicator.
- Optional Pixazo image generation from the Social Editor.
- Local social image conversion from the featured image.

Not implemented by design:

- Blind auto-publish.
- Full-content generation inside WordPress.
- Automatic Facebook posting without Social Editor review.
- Automatic Pinterest or Reddit API posting.
- Automatic ad strategy changes.

Detailed flow: `docs/content-machine-extraction-map.md`.

## Recommended Content Planning Table

Use this table before drafting each article:

```text
Article title:
Main hook:
Short intro angle:
Category:
Tags:
Why people would click:
Featured image idea:
Facebook post hook:
Pinterest pin title:
Sources to check:
```

Then generate a clean article draft from the approved row.

## Outside-AI Draft Contract

Required output:

- Title.
- Content only.
- Clear section headings.
- Short mobile-friendly paragraphs.
- No HTML.
- No markdown tables.
- No fake sources.
- No exaggerated medical claims.
- No diagnosis, cure, treatment, or supplement instructions.
- A calm professional-care reminder when symptoms are severe, sudden, persistent, worsening, or personal.

The article body should pay off the hook. The headline can create curiosity, but the content must lower panic and add context.

## Social AI Contract

Social AI should fill editable fields only.

It may suggest:

- Facebook hook.
- Facebook summary without the URL.
- Facebook first comment with CTA and URL.
- Pinterest title.
- Pinterest description.
- Pinterest alt text.
- Reddit title.
- Reddit body.
- Overlay text.
- Bottom hint text.

It must not:

- Post to Facebook.
- Add URLs into the Facebook summary.
- Turn Reddit into spam.
- Invent claims not present in the article.
- Enable the bottom hint checkbox automatically.
- Generate medical advice.

## Image Strategy

Images are critical for clicks.

Preferred workflow:

1. Create or upload a strong featured image.
2. Use the local social converter to generate platform-sized images.
3. Add short centered overlay text when it helps curiosity.
4. Add `LINK IN FIRST COMMENT` only when the Facebook link is actually in the first comment.
5. Use Pixazo only when a featured image is weak or a platform-specific image test is intentional.

Avoid:

- Tiny text.
- Thick dark panels that hide the image.
- Fake medical charts or fake authority cues.
- Overloaded text.
- Panic visuals.
- Images that imply a diagnosis or guaranteed outcome.

## Facebook Traffic Strategy

Use warm curiosity instead of hard clickbait.

Good hook patterns:

- `Most people miss this small body clue`
- `The everyday habit your body may notice first`
- `A common label trick that makes snacks look healthier`
- `Why this harmless-looking routine can change how sleep feels`

Bad hook patterns:

- `Doctors hate this`
- `This one trick cures...`
- `You will die if...`
- Fake urgency, fake tragedy, or fake certainty.

Every Facebook link should use UTM parameters:

```text
?utm_source=facebook&utm_medium=social&utm_campaign=dr_purg_growth&utm_content=post_slug_or_hook
```

## Monetization Strategy

Default source state:

- AdSense off.
- GA off.
- Monetag off.
- Display ads off.
- Histats off until configured.

Live tests can enable providers from Coolify, but the source defaults stay conservative.

Test ladder:

1. Histats tracking.
2. Clean display/native slots.
3. Monetag In-Page Push.
4. Sticky bottom only after mobile layout looks stable.
5. Vignette or OnClick only as deliberate short tests after enough traffic and payout confidence.

Main KPI:

```text
Revenue per 1,000 Facebook clicks = finalized paid revenue / Facebook clicks * 1000
```

Supporting KPIs:

- Facebook reach stability.
- Mobile engagement.
- Pages per session.
- Time on page.
- Split-next click rate.
- Related-post click rate.
- Complaint risk.
- Finalized revenue versus dashboard estimate.

## Practical Roadmap

### Phase 1: Manual Excellence

- Draft outside WordPress.
- Publish manually.
- Use Auto fill.
- Use Social Editor.
- Use local image converter.
- Track performance manually.

### Phase 2: Reviewed AI Assist

- Use Social AI for field suggestions.
- Add stricter validation for hook length, summary style, and claim safety.
- Add a simple performance log for topic, hook, source, clicks, RPM, and notes.

### Phase 3: Image And Hook Testing

- Test image overlays by format.
- Compare featured-image-derived cards versus Pixazo cards.
- Track which hook/image pairs get clicks without increasing complaints.

### Phase 4: Clone-Ready System

- Keep docs and scripts ready for another viral-content site.
- Replace all brand, ad, social, image, and content assets per clone.
- Run replica and preflight checks before traffic.

## Future Build Candidates

- AI hook quality score.
- Health-claim safety score.
- Social image crop and overlay checker.
- Performance log template.
- UTM helper.
- Facebook caption variant generator.
- Pinterest-safe title generator.
- Reddit-safe manual post helper.
- Monthly topic calendar generator.
