# Ads optimization playbook for Dr Purg Jr.

This site is the controlled instant-monetization test. The goal is not to show the maximum number of ads on day one. The goal is to find the best revenue per 1,000 Facebook clicks while keeping the site believable, readable, and safe for future cleanup.

Use `docs/ad-operations-manual.md` for the exact add/remove/test workflow. Use this playbook for strategy and decision-making.

## Current baseline

Use this as the default stack while the site is young:

```env
ADSENSE_ENABLE=0
KT_AD_MODE=baseline
KT_PRELANDER_ENABLE=0
KT_ACTION_AD_MIN_SECONDS=45
KT_ACTION_AD_MIN_SCROLL=35
DISPLAY_ADS_ENABLE=1
DISPLAY_ADS_PROVIDER=adsterra
DISPLAY_AD_AFTER_INTRO_BASE64=PASTE_300X250_OR_NATIVE_BASE64
DISPLAY_AD_MID_CONTENT_BASE64=PASTE_300X250_OR_468X60_BASE64
DISPLAY_AD_PART_CONTINUE_BASE64=PASTE_320X50_OR_300X250_BASE64
DISPLAY_AD_READING_OPTION_BASE64=
DISPLAY_AD_BELOW_CONTENT_BASE64=
DISPLAY_AD_STICKY_BOTTOM_BASE64=
DISPLAY_AD_STICKY_BOTTOM_MIN_SECONDS=35
DISPLAY_AD_STICKY_BOTTOM_MIN_SCROLL=30
DISPLAY_AD_STICKY_BOTTOM_COOLDOWN_MINUTES=30
DISPLAY_AD_CARD_GRID_BASE64=
DISPLAY_AD_SIDEBAR_BASE64=
MONETAG_ENABLE=1
MONETAG_POST_ONLY=1
MONETAG_INSTALL_CHECK=0
MONETAG_INPAGE_PUSH_BASE64=
MONETAG_VIGNETTE_BASE64=
MONETAG_ONCLICK_BASE64=
MONETAG_PUSH_BASE64=
MONETAG_INPAGE_PUSH_MINUTES=0
MONETAG_VIGNETTE_MINUTES=0
MONETAG_ONCLICK_MINUTES=60
MONETAG_PUSH_MINUTES=0
```

Recommended visible display stack:

- Keep `DISPLAY_AD_AFTER_INTRO_BASE64` active.
- Keep `DISPLAY_AD_MID_CONTENT_BASE64` active.
- Keep `DISPLAY_AD_PART_CONTINUE_BASE64` active for posts split into 2-3 parts. It appears before the simple part navigation.
- Keep `DISPLAY_AD_BELOW_CONTENT_BASE64` empty at first.
- Test `DISPLAY_AD_READING_OPTION_BASE64` next if you want a native ad that appears like sponsored reading suggestions, clearly labeled as an ad.
- Keep `DISPLAY_AD_PART_CONTINUE_BASE64` active if the ad is light and clearly labeled.
- Test `DISPLAY_AD_STICKY_BOTTOM_BASE64` only after baseline display is stable. Use a `320x50` unit, and keep the default 35-second / 30% scroll gate.
- Keep `DISPLAY_AD_CARD_GRID_BASE64` empty for now; listing grids should stay fast, clean, and stable on mobile.
- Keep `DISPLAY_AD_SIDEBAR_BASE64` empty at first.
- Add `below_content` only if RPM is weak and mobile readability stays good.

Recommended Monetag stack:

- Keep Monetag page-load formats off in `baseline`. The theme blocks In-Page Push and Vignette in baseline even if a code is accidentally left in Coolify.
- Keep OnClick/Popunder off until after the first payout.
- Keep Push Notifications off unless there is a clear long-term reason to build subscribers.

## Mode switch

Use `KT_AD_MODE` as the simple operating mode:

- `baseline`: default. Display/native ads only. No Monetag page-load formats.
- `medium`: optional In-Page Push test plus action-triggered OnClick. OnClick requires at least `KT_ACTION_AD_MIN_SECONDS` seconds on page and `KT_ACTION_AD_MIN_SCROLL` percent scroll before firing.
- `aggressive`: small traffic tests only. Never use it as the whole-site default.

Use `KT_PRELANDER_ENABLE=1` only when testing Facebook comment funnels. The pre-lander route is `/prelander/{post-slug}/`, keeps UTM parameters, and should point to the real recipe page with one honest CTA.

## Quality controls

Ask every ad network to block these categories where possible:

- Adult and sexual intent.
- Dating.
- Casino, gambling, and betting.
- Fake download, system warning, antivirus, cleaner, or software-update creatives.
- Misleading health, miracle cure, or fear-based creatives.
- Deceptive subscription or sweepstakes creatives.

Ad quality varies by GEO, device, browser, and advertiser inventory. A bad Morocco preview does not always mean the same ad will show to the Facebook audience, but it is still a warning signal. If one format repeatedly shows low-quality creatives, pause that format before pausing the whole stack.

## Test method

Change one thing at a time and leave it running long enough to measure. A clean test needs at least 48 hours or 2,000-5,000 Facebook clicks, whichever comes later.

Track this for every test:

```text
Date range:
Traffic source:
Post URLs:
Facebook clicks:
Pageviews:
Pages/session:
Mobile share:
Adsterra revenue:
Monetag revenue:
Finalized paid revenue:
Revenue per 1,000 Facebook clicks:
Complaints or bad ad examples:
Facebook reach change:
Decision:
```

Main KPI:

```text
Revenue per 1,000 Facebook clicks = finalized paid revenue / Facebook clicks * 1000
```

Do not optimize only for dashboard CPM. A format can show high CPM and still reduce real earnings if it hurts Facebook reach, causes users to leave, or gets rejected before payout.

## Experiment ladder

Use this order. Do not jump to the bottom early.

1. Baseline: Adsterra after-intro + mid-content, optional below-content display, no Monetag page-load formats.
2. Cleaner mode: if users report bad creatives, pause below-content first, then keep only after-intro + mid-content display.
3. Pre-lander test: set `KT_PRELANDER_ENABLE=1` and send Facebook links to `/prelander/{post-slug}/`.
4. Medium mode: set `KT_AD_MODE=medium` and add `MONETAG_ONCLICK_BASE64` only after adding a stable article intent hook, such as a tested same-page CTA that does not pretend to be navigation. Keep the default 45-second and 35% scroll guard.
5. In-Page Push test: set `KT_AD_MODE=medium` and use `MONETAG_INPAGE_PUSH_BASE64` only if baseline RPM is too weak and user experience stays acceptable.
6. Multi-part posts: use the editor's smart split or manual `2 parts` / `3 parts`; the part_continue ad can appear before the simple navigation.
7. More display: add `reading_option` first, then `sticky_bottom` if mobile still feels clean. Do not re-add `card_grid` unless we run a controlled test.
8. Vignette test: only try Vignette in `aggressive` after traffic is stable, with a 10-15 minute cooldown, and stop immediately if it traps users or redirects on close.
9. Aggressive test: use `KT_AD_MODE=aggressive` only on short controlled traffic windows.
10. Direct/SmartLink test: only use as a real related-reading link, never as fake navigation or a fake button.

## Coolify controls

To pause Monetag instantly:

```env
MONETAG_ENABLE=0
```

To return to the safest mode:

```env
KT_AD_MODE=baseline
KT_PRELANDER_ENABLE=0
KT_ACTION_AD_MIN_SECONDS=45
KT_ACTION_AD_MIN_SCROLL=35
MONETAG_INPAGE_PUSH_BASE64=
MONETAG_VIGNETTE_BASE64=
MONETAG_ONCLICK_BASE64=
MONETAG_PUSH_BASE64=
```

## Posting workflow for the current monetization setup

Use this workflow before we add aggressive ads:

1. Create the post from the external AI title and clean content.
2. Select `Recipe` or `Article`.
3. Leave `Automatic split` on `Smart: 2-3 parts for long posts`.
4. Click `Auto fill` or `Prepare for publishing`.
5. Preview on mobile. Long posts should show a labeled ad, then simple `Part 1`, `Part 2`, and sometimes `Part 3` navigation, not a big continue box.
6. Publish only if the first page still has enough useful content before the continue panel.

To test same-domain Facebook pre-landers:

```env
KT_PRELANDER_ENABLE=1
```

Then use this URL shape:

```text
https://health.ibnbatoutaweb.com/prelander/post-slug/?utm_source=facebook&utm_medium=social&utm_campaign=dr_purg_monetag_test&utm_content=hook_name
```

To pause only Vignette:

```env
MONETAG_VIGNETTE_BASE64=
```

To test Vignette later:

```env
KT_AD_MODE=medium
MONETAG_VIGNETTE_MINUTES=15
```

To pause all display/native ads:

```env
DISPLAY_ADS_ENABLE=0
```

To make the page cleaner without disabling display entirely:

```env
DISPLAY_AD_BELOW_CONTENT_BASE64=
DISPLAY_AD_STICKY_BOTTOM_BASE64=
DISPLAY_AD_SIDEBAR_BASE64=
```

## Stop rules

Stop or reduce the current test if any of these happen:

- Facebook reach drops sharply after posting links.
- Users report forced redirects, adult ads, fake warnings, or scam-like creatives.
- Ads cover recipe content or make the page hard to scroll.
- The dashboard shows traffic-quality, policy, or payment warnings.
- Dashboard revenue is high but finalized revenue is cut heavily.

When in doubt, protect the domain. A slightly lower RPM on a clean site is better than a short spike that kills the traffic source or makes the domain hard to reuse.
