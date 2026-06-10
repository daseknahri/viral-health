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

*Provenance: synthesized 2026-06-10 by a 6-agent workflow (4 research lenses — thumbnail mechanics, AI prompt-craft, brand/safety, the live plugin pipeline — → strategy → adversarial mobile/AI-tell skeptic, approved with the fixes above). Companion to docs/social-syndicator.md (the card converter) and the Master Bundle Prompt in docs/chatgpt-prompt-pack.md (the image_prompt / discover_image_prompt fields this upgrades).*
