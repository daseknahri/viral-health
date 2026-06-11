# Dr Purg Jr — The Image Strategy

## 1. The principle

Generate one clean, text-free photograph; let the plugin paint the words. This is the only reliable path because the three jobs an image must do — be a beautiful editorial photo, carry a crisp legible headline, and be reused across A/B hook variants and three platform crops — are in direct conflict if you bake text into the AI render. AI generators turn requested words into mangled gibberish (a hard guardrail failure and an instant "AI slop" tell), they freeze one headline into pixels so you can't A/B `overlay_text` across `utm_content` variants, and they can't guarantee the white-on-photo contrast that survives JPEG compression at phone-thumbnail size. The plugin's overlay renderer already solves all three: it draws a vector-crisp ALL-CAPS hook with outline + burgundy-tinted shadow + a feathered scrim that reads at >=4.5:1 contrast, it's editable per variant, and it keeps the photo visible. So the AI's only job is to produce a gorgeous, undistorted, on-brand photo with a deliberate empty zone for the text — and to contain zero words, ever. Clean image + plugin overlay = embarrassment-free, reusable, legible. Every time.

## 2. The master-image spec

**The operator generates ONE thing per article: a 4:5 portrait "master" photo, at least 1500x1875 px (target ~2K).** Bumping the source above the plugin's stated 1200x1500 floor means nothing ever upscales — Facebook (1080), Pinterest (1000), and OG (1200) all downscale only.

**What's in frame:**
- **ONE relatable everyday US adult**, medium close-up, eye-level, calm/curious/relieved expression — the single strongest stop-scroll device. No second person, no background figure (that's where face-drift artifacts breed).
- **The face + subject live in the center vertical band**, horizontally centered, occupying ~40-55% of the frame, with headroom above and breathing room below. This center-safe composition is the linchpin: it's the only thing that survives all three crops (proven below).
- **One reserved negative-space zone** — the lower third — kept soft, evenly lit, and low-detail (blurred room, plain wall, bedding, a countertop). This is where the overlay + the "LINK IN FIRST COMMENT" hint land.
- **One chromatic accent per image** — sage-green OR muted-burgundy, never both fighting — as the color pattern-interrupt. Subject lit brighter than the background so they pop.
- **Hands hidden from trouble**: relaxed at sides, one hand, a natural mug/glass grip, or out of frame. Never spread/counting fingers, never cropped at the wrist.

**How the ONE master crops to the three cards (this is the load-bearing math):**

| Card | Size / ratio | What the plugin does to a 4:5 master | Survives if… |
|---|---|---|---|
| **Facebook feed** | 1080x1350 (4:5) | Exact ratio — downscale only, 100% kept | Always. Set `overlay_pos = bottom`. |
| **Pinterest pin** | 1000x1500 (2:3) | Master is *wider* than 2:3 → center-crops the SIDES (~83% width kept) | Keep subject + accents off the outer ~10% left/right. |
| **OG / Reddit** | 1200x630 (1.91:1) | Master is *taller* → center-crops HEIGHT, keeps only the middle ~42% band (head & feet discarded) | **Only** if the subject is in the center band. This is why center-safe composition is non-negotiable. |

The OG crop is the brutal constraint: a 4:5 master keeps only ~42% of its height for the link card, and `crop_focus` is the *only* knob that bites here (it has no effect on FB's exact ratio or Pinterest's width-crop). A subject composed high or low in the portrait gets decapitated on the Reddit/link preview. **Compose center, and the same master serves all three.**

**The blog hero:** the same 4:5 master IS the WordPress featured/hero image — center-safe composition also survives the theme's wide hero banner crop. No separate hero render.

**Do you still need the separate 16:9 `discover_image_prompt`?** Yes — but only as the Google Discover/Search card, and it must NEVER be fed into the local social converter (a wide image crops and upscales catastrophically into the tall FB/Pinterest cards — the inverse failure the QA panel flags). Keep two renders: the 4:5 master (FB + Pinterest + OG + hero) and the 16:9 Discover hero. They share the identical brand canon block; only orientation and the negative-space clause differ.

## 3. The tool + settings

**Default generator (now): Gemini's Nano Banana — FREE, via Google AI Studio (aistudio.google.com), NOT the consumer Gemini app.** "Nano Banana" *is* Google's image model: Nano Banana = **Gemini 2.5 Flash Image**; **Nano Banana Pro = Gemini 3 Pro Image** (2K/4K, best hands — use it for the featured/hero master). It wins on exactly the things that betray AI — real subsurface skin scattering, individual hair strands in window light, natural undistorted hands, real-camera mood — and photoreal humans are the whole game for a relatable health-lifestyle blog. It supports the two ratios we need natively (**4:5** and **16:9**) and Gemini's killer feature: **conversational EDITING** — fix a hand, empty the lower third, remove a stray mark, warm the light, all in follow-up turns (see §9).

**Use Google AI Studio, NOT the Gemini app.** Both are free, but the consumer app stamps a visible "Gemini sparkle" watermark on free-tier images (off-brand on a blog hero). **AI Studio outputs carry only the invisible SynthID (no visible watermark) and allow ~500 images/day** — set aspect ratio in its controls and generate at the highest resolution offered so the plugin never upscales (target ≥1500×1875 for the 4:5 master; Nano Banana Pro's 2K clears this).

**The paid upgrade, for serious future work: Higgsfield** (Nano Banana 2/Pro, GPT Image 2, a trained Soul-ID recurring "host" face, Seedance video, the Virality Predictor). Reach for it when you want a recognizable recurring face, batch automation, or video. For the blog's still images today, free Gemini/AI Studio is the *same model* and is enough.

All of these embed SynthID — don't try to launder it; label honestly per §6.

## 4. The upgraded `image_prompt` formula (portrait 4:5)

Replace the current `image_prompt` instruction in the Master Bundle Prompt (`docs/chatgpt-prompt-pack.md` line 336) with this fill-in-the-blank template. One sentence of subject, then the locked canon tokens — short prompts beat token-stuffing on 2026 models.

```
Calm, realistic editorial lifestyle photograph, natural documentary feel, candid not posed, of [ONE relatable everyday US adult, ~30s–50s] [SIMPLE SAFE ACTION tied to the topic — hands relaxed, holding a plain mug, or out of frame], with a calm, gently curious / mildly relieved expression, looking softly toward the window light. Subject and face centered horizontally in the middle of the frame, occupying about 40–55% of the image, with headroom above; the lower third is soft, evenly lit, low-detail empty space (blurred room, plain wall, bedding, or countertop) reserved as clean negative space for a text overlay; nothing important in the top or bottom fifth. Soft warm natural daylight from a window, gentle and reassuring, no harsh flash, no clinical light. Cozy modern US home, uncluttered, with a subtle [sage-green OR muted-burgundy] accent and warm neutral tones. 50mm, f/2.0, shallow depth of field, realistic skin texture with visible pores and faint expression lines, slight natural film grain, no posed glamour, no stock-photo look. Portrait orientation, 4:5, high resolution. No text, no words, no letters, no logos, no watermark, no signage, no numbers. One person only. Natural, relaxed, undistorted hands and faces; correct number of fingers; visible skin texture, no plastic/wax skin; no extra or merged fingers; no clinical, hospital, lab-coat, medical-procedure, or before/after imagery; no charts or diagrams; calm, non-alarming expression.
```

Fill-in slots: the adult, the safe topic action, and the single accent color (pick one). Everything else is the locked canon — paste verbatim every time.

**Plugin pairing for this composition:** `crop_focus = top` (saves the head if the master ever runs tall), `overlay_pos = bottom` (hook + link hint land on the clean lower third, never on the face), bottom hint = `LINK IN FIRST COMMENT`. Always click **Preview card** as the phone-thumbnail legibility gate before generating.

## 5. The upgraded `discover_image_prompt` formula (16:9 hero)

Same canon, landscape geometry, off-center negative space for a wider crop. Replaces line 347.

```
Calm, realistic editorial lifestyle photograph, natural documentary feel, candid not posed, of [ONE relatable everyday US adult, ~30s–50s] [SIMPLE SAFE ACTION tied to the topic — hands relaxed, holding a plain mug, or out of frame], with a calm, gently curious / mildly relieved expression. Subject placed slightly off-center toward one side, kept within the center two-thirds horizontally and vertically (nothing important in the top or bottom 12%, which gets cropped); the opposite side is soft, evenly lit, low-detail open space. Soft warm natural daylight from a window, gentle and reassuring, no harsh flash, no clinical light. Cozy modern US home, uncluttered, with a subtle [sage-green OR muted-burgundy] accent and warm neutral tones. 50mm, f/2.0, shallow depth of field, realistic skin texture with visible pores and faint expression lines, slight natural film grain, no posed glamour, no stock-photo look. Landscape orientation, 16:9, high resolution. No text, no words, no letters, no logos, no watermark, no signage, no numbers. One person only. Natural, relaxed, undistorted hands and faces; correct number of fingers; visible skin texture, no plastic/wax skin; no extra or merged fingers; no clinical, hospital, lab-coat, medical-procedure, or before/after imagery; no charts or diagrams; calm, non-alarming expression.
```

## 6. Brand consistency

Make the feed look like one brand by reusing a fixed **CANON IMAGE BLOCK** verbatim in every prompt (store it in `docs/chatgpt-prompt-pack.md` so `/draft-batch`, `/draft-month`, and the claude.ai Project all emit identical tokens). The fixed style anchors:

1. **Style anchor:** "Calm, realistic editorial lifestyle photograph, natural documentary feel, candid not posed." (A single named anchor does more than ten loose adjectives.)
2. **Lighting:** soft warm natural daylight from a window — never clinical.
3. **Set + palette lock:** cozy modern US home, uncluttered, subtle sage-green + muted-burgundy accents, warm neutral tones — one accent dominant per image.
4. **Lens/look:** 50mm, f/2.0, shallow depth of field, realistic skin texture, slight film grain, no stock-photo look.
5. **Framing motif:** ONE relatable adult, medium close-up, eye-level, center-safe, lower-third negative space.

Same five anchors on every article turns the feed from "eight unrelated stock people" into "one recognizable, trusted explainer."

**Is a Soul ID recurring face worth it?** Not at launch. Topics naturally vary the person, and the style + palette lock already delivers brand cohesion. **Skip Soul ID for now**; rely on the canon block, and optionally feed Nano Banana Pro 3-6 brand-look reference images per render (front + 3/4 angles) for ~93% look-consistency with zero training. **Adopt Soul ID only as a deliberate Phase-2 experiment** if you decide you want a recognizable recurring "host" face — train once on 20+ varied photos, store the `reference_id`, generate via `text2image_soul_v2 --soul-id <id>` with the same canon block. Honesty caveat if you do: keep the host in ordinary home clothes — never a lab coat, scrubs, or stethoscope — so it never implies a fake medical authority.

**Honest AI labeling (not laundering):** Gemini/Nano Banana embed SynthID + C2PA and Pinterest runs visual classifiers, so detection is effectively guaranteed and stripping it is both futile and against the brand's honesty guardrail. Use Meta's built-in "AI info" tag when the upload flow prompts (low risk for non-deceptive lifestyle imagery), expect Pinterest's "modified with AI" label and compete on craft, and keep alt text honest.

## 7. The reject checklist — any one = regenerate

Before any image becomes the source, zoom to 100% and reject (regen is cheap, ~20-30s on Nano Banana) if **any** of these fire:

- [ ] **Mangled hand/face** — extra/merged/missing fingers, warped eyes/teeth, melted jewelry, wrist cropped mid-joint, or a "plastic/wax" skin look.
- [ ] **Any baked text** — letters, words, gibberish signage, logos, labels, numbers, UI. All text is the plugin overlay, full stop.
- [ ] **Subject NOT in the center band** — head or critical content sits high/low and would be decapitated by the OG 1200x630 center-crop (or pushed off-edge by the Pinterest side-crop).
- [ ] **No clean negative-space zone** — the lower third is busy/cluttered, so the overlay would land on a face or detail and force a heavy scrim.
- [ ] **Low thumbnail contrast** — subject doesn't pop off the background; fails the **Preview card** phone-size legibility test (overlay unreadable on the small preview).
- [ ] **Clinical / scary / off-guardrail** — hospital, lab coat, medical procedure, before/after, charts/diagrams, or an alarmed/distressed/pained expression.
- [ ] **Off-brand palette** — both accents fighting, cold/blue clinical light, or generic stock-photo flatness instead of the warm daylight canon.
- [ ] **More than one person** — a second or blurred background figure (the worst source of face-drift artifacts).
- [ ] **Too small** — 4:5 master under 1200x1500 (target >=1500x1875), which trips the plugin's upscale QA.

---

**Net change to act on today:** swap two prompt instructions in `docs/chatgpt-prompt-pack.md` (lines 336 and 347) with the §4 and §5 templates, make Nano Banana the default generator, generate the 4:5 master center-safe at >=1500x1875, set `crop_focus = top` + `overlay_pos = bottom`, optionally raise `SOCIAL_CARD_SCRIM` from 28 to ~34-38 for these bright daylight scenes (test in Preview card first), and run the §7 checklist before every card ships. No plugin code change is required — the one master, three cards, overlay, crop, and scrim machinery already exists.

Relevant files: `c:\Users\user\OneDrive\Documents\viral-health\docs\chatgpt-prompt-pack.md` (lines 336, 347 — the prompt contract to edit), `c:\Users\user\OneDrive\Documents\viral-health\docs\social-syndicator.md` (lines 179-216 — converter/QA reference), `c:\Users\user\OneDrive\Documents\viral-health\wp-content\plugins\dr-purg-social-syndicator\dr-purg-social-syndicator.php` (card sizes + renderer).

## 8. Hardening fixes (from the adversarial review — apply these)

The strategy passed review against the actual plugin code; fold these in:

1. **The OG/Reddit 1200×630 card is a separate render, not the same settings.** A bottom-positioned 3-line hook + the "LINK IN FIRST COMMENT" hint collide and double-scrim into a dark band on the short card, and an overflowing hook gets silently ellipsis-truncated. Render the **OG card with the overlay centered and the bottom hint OFF** (the hint is a Facebook-comment instruction, meaningless on a link/Reddit preview); keep bottom-overlay + hint only on the 1080×1350 FB card. Always click **Preview card on the OG size**, not just FB. *(Until the plugin auto-centers the OG overlay, fix #2 is the mitigation — short hooks fit.)*
2. **overlay_text = 5–8 words, hard max 9** (was max 12). A 12-word ALL-CAPS hook overflows the OG card and truncates mid-thought, killing the curiosity loop. Verify on the OG preview that it fits in ≤2 lines without an ellipsis.
3. **Best-of-N, not regen-on-fail.** Generate 2–3 candidates per master and pick the cleanest; make **hands hidden / out of frame the DEFAULT** (eliminates the highest-probability AI tell outright). The negative-prompt tail is belt-and-suspenders, not the primary control. Optional: a quick vision pass (count fingers / any baked text / >1 face) before an image becomes the source.
4. **AI disclosure is a checklist line, not memory.** Apply the platform AI-content label where supported (Meta "AI info"; Pinterest auto-labels). **Alt text describes a generic person — never names or credentials them** ("a person at home", never "our expert/doctor"). If a Soul-ID recurring host is ever used (Phase 2), it is only ever "a person at home", never a named author or clinician — the no-fake-authority guardrail holds.
5. **Keep the two prompts clearly labeled PORTRAIT-MASTER (4:5) vs DISCOVER-ONLY (16:9)**, and never feed the 16:9 image into the local card converter (it crops/upscales badly into the tall FB/Pinterest cards). Also verify once what the live WordPress theme crops the 4:5 hero to.

## 9. The Gemini method — the free default, step by step

Free, repeatable, watermark-free, and the *same model* the strategy recommends. Per article, ~1–2 min:

1. **Open Google AI Studio** (aistudio.google.com) → image generation → model **Nano Banana Pro** (Gemini 3 Pro Image) for the featured/hero, or **Nano Banana** (2.5 Flash Image) for speed. *Use AI Studio, NOT the Gemini app — the app stamps a visible sparkle watermark; AI Studio doesn't.*
2. **Set aspect ratio = 4:5** (do the Discover hero separately at **16:9**) and the highest resolution offered.
3. **Paste the draft's `image_prompt` verbatim** → generate. **Best-of-N:** regenerate 2–3 and keep the cleanest.
4. **Fix tells with Gemini's edit loop instead of rerolling** (its superpower) — reply on the chosen image: *"keep everything the same but make the lower third emptier and softer for text"* · *"fix the left hand to a natural relaxed shape with five fingers"* · *"remove any text or marks"* · *"warm the daylight a touch."* Iterate until it clears the §7 reject checklist.
5. **Download → set as the WordPress featured image.** The plugin builds the FB/Pinterest/OG cards (OG = centered overlay, no hint, per §8).
6. **Repeat with `discover_image_prompt` at 16:9** for the Discover hero.
7. **Disclosure:** SynthID is embedded (honest); apply the platform AI-content label on upload; keep alt text generic — never crediting the person.

Free daily limits are plenty for a monthly batch (AI Studio ~500/day). Want a recurring brand "host" face or video later? That's when Higgsfield (§3) earns its cost.

---

# Dr Purg Jr — The Content-to-Thumbnail Method (Scroll-Stopper Mode)

*This is the GROWTH-PHASE image method. It sits alongside §4's calm-editorial canon (now the "brand mode"). Use scroll-stopper mode now, while the brand is unknown and every post has to earn the click cold. See §two-mode note at the end for when to switch back.*

## Why this exists (the 2026 evidence)

The single best 4:5 master is no longer a generic calm person looking at window light — it's the **one relatable "caught moment" from the article itself**, made visible. The research is unambiguous for 2026:

- A face showing a genuine, mid-arousal emotion (surprise, amusement, mild relief) gets ~**20% higher CTR**, because humans recognize a face/emotion in ~13ms — before they can read a single word. ([thumbnailtest](https://thumbnailtest.com/guides/psychology-of-youtube-thumbnails/), [futuramo](https://futuramo.com/blog/effective-youtube-video-thumbnail-psychology-best-practices/))
- The psychology has shifted from **"shock me" to "relate to me."** Authentic, candid micro-expressions now beat both staged drama and hyper-polished AI; real-person faces tested **47% higher CTR** than generic stock. The 2026 trend is literally called "Proof of Human." ([bananathumbnail trends](https://blog.bananathumbnail.com/2026-thumbnail-trends/), [bananathumbnail psychology](https://blog.bananathumbnail.com/thumbnail-psychology-4/))
- Winners use **one dominant subject, 2–3 colors, a single legible "what's happening here"** — legible at the 120px mobile preview where the decision is actually made. ([nexlev](https://www.nexlev.io/youtube-thumbnail-types), [thumbnailmaker](https://thumbnailmaker.ai/best-youtube-thumbnail-styles))
- The **curiosity gap** is the engine: show a person clearly *reacting* to something the viewer can't yet explain, and the unanswered "wait — why?" is the click. The article is the answer. ([graphaize](https://graphaize.com/psychology-of-thumbnails-how-to-stop-the-scroll-and-get-the-click/))
- Vertical 4:5 (1080×1350) owns up to **45% more mobile screen** and lifts CTR 12–22% — which is exactly our master ratio, so none of the pipeline changes. ([adstellar](https://www.adstellar.ai/blog/best-image-size-for-facebook))

**The reframe:** we are not illustrating the topic, we are casting the reader as the person in the photo at the exact second the article's question occurs to them. "That's so me" → click.

## Step 1 — Mine the article for the ONE caught moment

Don't picture the *subject* (a stomach, sleep, hiccups). Picture the **everyday scene where a real person would suddenly wonder about it**, and the **micro-reaction on their face**. Ask the content these four questions, in order:

1. **The trigger scene:** "In what ordinary, specific US-everyday place/situation does a normal person actually *notice* this body thing happen?" (Library, work meeting, car at a red light, kitchen at 2am, on the couch mid-show, in line at the store, brushing teeth.) Specific beats generic — a *library* out-stops a blank "room."
2. **The honest reaction:** "What's the small, real, *non-alarmed* face they'd make in that second?" Pick from our safe emotion palette: pleasant surprise, amused embarrassment, gentle curiosity, quiet relief, a little 'huh, weird.' Name ONE. (The exemplar = surprised-amused-embarrassed half-smile.)
3. **The visible curiosity gap:** "What is the person clearly *reacting to* that the viewer can see but can't yet explain — so they need the article?" (Glancing down at their own belly; pausing mid-yawn; touching their own cheek; looking at the clock.) The gesture points at the mystery; the article is the answer.
4. **The relatability test:** "Will a scrolling stranger think *'wait, that's literally me'* in under a second?" If the moment is universal and slightly funny/human, keep it. If it needs a caption to make sense, it's wrong — go back to Q1.

Output of Step 1 = one line: **[everyday scene] + [one safe reaction] + [the body-gesture that makes the question visible] + [one brand-accent color object].**
*Example: "young adult at a quiet library table, surprised-amused half-smile, glancing down at own belly, muted-burgundy cardigan as the anchor."*

## Step 2 — The reusable SCROLL-STOPPER PROMPT FORMULA

Fill the five brackets from Step 1; paste the rest verbatim. This matches the exemplar's energy (punchy, high-contrast, candid, mid-moment) while staying inside our 4:5 overlay pipeline and guardrails.

```
Candid, photoreal editorial photograph with a punchy, vivid, high-contrast look, of [ONE relatable everyday US adult, ~20s–50s] in [SPECIFIC EVERYDAY SCENE from the article — e.g. a quiet shared work table, a car at a red light, a cozy couch mid-show], caught mid-moment [THE BODY-GESTURE THAT MAKES THE QUESTION VISIBLE — e.g. glancing down toward their own stomach, pausing mid-yawn, lightly touching their own cheek], with a genuine [ONE SAFE REACTION: surprised, amused, slightly embarrassed half-smile / gently curious 'huh, weird' look / quietly relieved soft smile] — the universal '[THE TINY RELATABLE THOUGHT — e.g. wait, was that my stomach?]' moment. The expressive, reacting face is the clear focal point, caught mid-reaction, not posed. Subject centered horizontally in the middle band of the frame, filling about 50–60% of the image, with headroom above; the lower third kept soft, evenly lit, and low-detail as clean negative space for a text overlay; nothing important in the top or bottom fifth, nothing critical in the outer tenth left or right. Bright, crisp natural daylight with strong but flattering contrast so the subject pops off a softly blurred background; one bold pop of [muted-burgundy OR sage-green] in the scene as the single color anchor; warm, modern, relatable US setting, uncluttered. 35mm, f/2.2, shallow depth of field, crisp realistic skin texture with visible pores and faint expression lines, lively and candid, energetic but not chaotic, no stiff stock-photo look. Portrait orientation, 4:5, high resolution. No text, no words, no letters, no logos, no watermark, no signage, no numbers. One person only. Natural, relaxed, undistorted hands and faces, correct number of fingers; hands relaxed or out of frame; no clinical, hospital, lab-coat, medical, or before/after imagery; no charts; a genuine, warm, slightly funny, non-alarmed human expression.
```

**The five fill-in slots:** the adult · the specific scene · the body-gesture · the one safe reaction (+ its tiny relatable thought) · the one accent color. Everything else is locked canon — paste it identically every time so the feed still reads as one brand.

**The non-negotiable tail (every render):** center-safe subject (survives all three crops) · clean soft lower-third (overlay lands on space, never the face) · one brand accent (burgundy *or* sage, never both) · the full no-text / no-tell / no-clinical clause.

**Pipeline pairing (unchanged):** `crop_focus = top`, `overlay_pos = bottom`, FB bottom hint `LINK IN FIRST COMMENT`; OG/Reddit card = overlay centered, hint OFF (§8). Best-of-N: generate 2–3, keep the cleanest, fix tells with Gemini's edit loop. Run the §7 reject checklist before it ships, plus the two scroll-stopper additions below.

## Step 3 — The bright line: scroll-stopper vs clickbait / fear / clinical

Stop at "wait, that's me," never at "something is wrong with me." The line is decidable:

| KEEP (scroll-stop, honest) | KILL (clickbait / fear / clinical) |
|---|---|
| Pleasant surprise, amusement, mild embarrassment, gentle curiosity, quiet relief | Pain, fear, alarm, distress, shock, crying, panic, grimace |
| Reacting to a normal, universal body moment ("was that my stomach?") | Reacting to a symptom as a threat ("is this serious?!") |
| One bold color pop + strong-but-*flattering* contrast | Garish saturation, red-alert/danger coding, blaring arrows |
| Everyday home/work/car/store scene | Hospital, lab coat, exam room, pills, charts, before/after body shots |
| Pointing-gesture at a curiosity the article answers | Pointing at a body part as if diagnosing the viewer |
| "That's so me" recognition | "That could be YOU and it's bad" exaggeration |

**The two-second gut check:** *Would this make a calm person smile and lean in — or make an anxious person's stomach drop?* If it's the second one, it fails the brand even if it would out-click. Curiosity is allowed to be high-energy; the emotion is never allowed to be negative. Two extra reject lines for this mode, on top of §7:

- [ ] **Reaction reads as distress, not delight** — any frown/wince/wide-eyed alarm → regenerate to a lighter beat.
- [ ] **Implies a personal diagnosis** — the gesture/scene frames the body thing as *your problem* rather than a shared "huh, interesting" → reframe to general/relatable.

## The two-mode note (which mode, when)

We keep **both** prompt formulas and choose per phase — same pipeline, same 4:5 master, same overlay, same guardrails; only the energy differs.

- **Scroll-Stopper Mode (NOW — growth phase, this section's formula).** Brand is unknown; every post earns the click cold in a crowded feed. Mine the article for the *caught reactive moment*, punchy high-contrast, 35mm f/2.2, face at 50–60%, one bold color pop, a tiny visible story. This is what the operator loved and what the 2026 evidence rewards while you're a stranger in the feed.
- **Calm-Editorial / Brand Mode (LATER — §4 formula).** Once "Dr Purg Jr" is a recognized name people already trust and seek out, dial back to the calm, gently-curious editorial canon (50mm f/2.0, softer warm window light, ~40–55% subject). At that point recognition does the stopping work and the calmer, more authoritative look protects long-term trust.

**Default today = Scroll-Stopper.** Switch a given topic to Brand Mode only when (a) the brand is established, or (b) a topic is too sensitive for an amused beat and a calm, respectful frame serves it better. Both modes obey the identical no-text / no-clinical / one-person / center-safe / clean-lower-third rules — only the *feeling* on the face and the *contrast* on the photo change.

---

**Sources:** [thumbnailtest — Psychology of YouTube Thumbnails 2026](https://thumbnailtest.com/guides/psychology-of-youtube-thumbnails/) · [futuramo — Thumbnail Psychology & Best Practice](https://futuramo.com/blog/effective-youtube-video-thumbnail-psychology-best-practices/) · [bananathumbnail — 2026 Thumbnail Trends ("Proof of Human")](https://blog.bananathumbnail.com/2026-thumbnail-trends/) · [bananathumbnail — 9 Viral Thumbnail Psychology Secrets](https://blog.bananathumbnail.com/thumbnail-psychology-4/) · [nexlev — Thumbnail Types That Boost Views 2026](https://www.nexlev.io/youtube-thumbnail-types) · [thumbnailmaker.ai — Best Thumbnail Styles for High CTR 2026](https://thumbnailmaker.ai/best-youtube-thumbnail-styles) · [graphaize — Psychology of Thumbnails: Stop the Scroll](https://graphaize.com/psychology-of-thumbnails-how-to-stop-the-scroll-and-get-the-click/) · [adstellar — Best Image Size for Facebook 2026](https://www.adstellar.ai/blog/best-image-size-for-facebook) · [madpin — Trending Pinterest Pin Styles 2026](https://madpinmedia.com/trending-design-styles-for-pinterest-pins/)

*Target file for this section: `c:\Users\user\OneDrive\Documents\viral-health\docs\image-strategy.md` (insert as a new "Scroll-Stopper Mode" section; it complements §4's calm canon, which becomes the "brand mode" referenced above). It also serves directly as the brief for `/draft-batch`, `/draft-month`, and the claude.ai Editor Project when they emit the `image_prompt` field.*


---

*Provenance: synthesized 2026-06-10 by a 6-agent workflow (4 research lenses — thumbnail mechanics, AI prompt-craft, brand/safety, the live plugin pipeline — → strategy → adversarial mobile/AI-tell skeptic, approved with the fixes above). Companion to docs/social-syndicator.md (the card converter) and the Master Bundle Prompt in docs/chatgpt-prompt-pack.md (the image_prompt / discover_image_prompt fields this upgrades).*
