# Dr Purg Jr. ad code inventory

This file is the source of truth for the current Adsterra and Monetag codes. Keep these codes controlled through Coolify environment variables, not pasted directly into WordPress, the theme editor, or random plugins.

For the step-by-step operating path, use `docs/ad-operations-manual.md`. This inventory answers "what code do we have?" The operations manual answers "where should we put it, when should we test it, and how do we pause it?"

## Recommended env now

Use this as the current controlled display stack.

```env
DISPLAY_ADS_ENABLE=1
DISPLAY_ADS_PROVIDER=adsterra
DISPLAY_AD_AFTER_INTRO_BASE64=PHNjcmlwdD4KICBhdE9wdGlvbnMgPSB7CiAgICAna2V5JyA6ICc1N2YyNWI0OGM1ZjQzNDcxMDFiNWQ3YTQ5NjZjNmZlZScsCiAgICAnZm9ybWF0JyA6ICdpZnJhbWUnLAogICAgJ2hlaWdodCcgOiAyNTAsCiAgICAnd2lkdGgnIDogMzAwLAogICAgJ3BhcmFtcycgOiB7fQogIH07Cjwvc2NyaXB0Pgo8c2NyaXB0IHNyYz0iaHR0cHM6Ly93d3cuaGlnaHBlcmZvcm1hbmNlZm9ybWF0LmNvbS81N2YyNWI0OGM1ZjQzNDcxMDFiNWQ3YTQ5NjZjNmZlZS9pbnZva2UuanMiPjwvc2NyaXB0Pg==
DISPLAY_AD_READING_OPTION_BASE64=PHNjcmlwdCBhc3luYz0iYXN5bmMiIGRhdGEtY2Zhc3luYz0iZmFsc2UiIHNyYz0iaHR0cHM6Ly9wbDI5NDMwMzQ1LnByb2ZpdGFibGVjcG1yYXRlbmV0d29yay5jb20vYTI0ZDk2NzRmZTUzYzk3NWRiYzE1MTJkMGRjN2U3MzcvaW52b2tlLmpzIj48L3NjcmlwdD4KPGRpdiBpZD0iY29udGFpbmVyLWEyNGQ5Njc0ZmU1M2M5NzVkYmMxNTEyZDBkYzdlNzM3Ij48L2Rpdj4=
DISPLAY_AD_MID_CONTENT_BASE64=PHNjcmlwdD4KICBhdE9wdGlvbnMgPSB7CiAgICAna2V5JyA6ICc4YzFjMzAwZDY1NzVkNzdiNmY1N2Y3MjU5NWNmNjM2YicsCiAgICAnZm9ybWF0JyA6ICdpZnJhbWUnLAogICAgJ2hlaWdodCcgOiA2MCwKICAgICd3aWR0aCcgOiA0NjgsCiAgICAncGFyYW1zJyA6IHt9CiAgfTsKPC9zY3JpcHQ+CjxzY3JpcHQgc3JjPSJodHRwczovL3d3dy5oaWdocGVyZm9ybWFuY2Vmb3JtYXQuY29tLzhjMWMzMDBkNjU3NWQ3N2I2ZjU3ZjcyNTk1Y2Y2MzZiL2ludm9rZS5qcyI+PC9zY3JpcHQ+
DISPLAY_AD_PART_CONTINUE_BASE64=
DISPLAY_AD_BELOW_CONTENT_BASE64=PHNjcmlwdD4KICBhdE9wdGlvbnMgPSB7CiAgICAna2V5JyA6ICc2ZTMzZDNmOTMwZTBmZGJkZjc5ZGE0NjZhODcyZjJhMycsCiAgICAnZm9ybWF0JyA6ICdpZnJhbWUnLAogICAgJ2hlaWdodCcgOiA1MCwKICAgICd3aWR0aCcgOiAzMjAsCiAgICAncGFyYW1zJyA6IHt9CiAgfTsKPC9zY3JpcHQ+CjxzY3JpcHQgc3JjPSJodHRwczovL3d3dy5oaWdocGVyZm9ybWFuY2Vmb3JtYXQuY29tLzZlMzNkM2Y5MzBlMGZkYmRmNzlkYTQ2NmE4NzJmMmEzL2ludm9rZS5qcyI+PC9zY3JpcHQ+
DISPLAY_AD_STICKY_BOTTOM_BASE64=
DISPLAY_AD_STICKY_BOTTOM_MIN_SECONDS=35
DISPLAY_AD_STICKY_BOTTOM_MIN_SCROLL=30
DISPLAY_AD_STICKY_BOTTOM_COOLDOWN_MINUTES=30
DISPLAY_AD_CARD_GRID_BASE64=
DISPLAY_AD_SIDEBAR_BASE64=
DISPLAY_AD_HEADER_BASE64=
```

Use this Monetag first test. Keep push, popunder, and vignette disabled until tracking is working and we intentionally test them.

```env
KT_AD_MODE=medium
MONETAG_ENABLE=1
MONETAG_POST_ONLY=1
MONETAG_INSTALL_CHECK=0
MONETAG_INPAGE_PUSH_BASE64=PHNjcmlwdD4oZnVuY3Rpb24ocyl7cy5kYXRhc2V0LnpvbmU9JzEwOTk4NTIzJyxzLnNyYz0naHR0cHM6Ly9uYXA1ay5jb20vdGFnLm1pbi5qcyd9KShbZG9jdW1lbnQuZG9jdW1lbnRFbGVtZW50LCBkb2N1bWVudC5ib2R5XS5maWx0ZXIoQm9vbGVhbikucG9wKCkuYXBwZW5kQ2hpbGQoZG9jdW1lbnQuY3JlYXRlRWxlbWVudCgnc2NyaXB0JykpKTwvc2NyaXB0Pg==
MONETAG_INPAGE_PUSH_MINUTES=0
MONETAG_VIGNETTE_BASE64=
MONETAG_VIGNETTE_MINUTES=0
MONETAG_ONCLICK_BASE64=
MONETAG_ONCLICK_MINUTES=60
MONETAG_PUSH_BASE64=
MONETAG_PUSH_MINUTES=0
MONETAG_SW_JS_BASE64=
```

## Future test env values

Only add these when testing a specific strategy.

```env
MONETAG_ONCLICK_BASE64=
MONETAG_VIGNETTE_BASE64=
MONETAG_PUSH_BASE64=
MONETAG_SW_JS_BASE64=
```

Direct link, not an env value yet:

```text
https://omg10.com/4/9911453
```

Use the direct link only as a real optional CTA later, such as a clearly labeled "More recipe ideas" experiment. Do not use it as fake navigation.

## Placement map

Recommended current mapping:

- `DISPLAY_AD_AFTER_INTRO_BASE64`: Adsterra `300x250`.
- `DISPLAY_AD_READING_OPTION_BASE64`: Adsterra `Native Banner`.
- `DISPLAY_AD_MID_CONTENT_BASE64`: Adsterra `468x60`.
- `DISPLAY_AD_PART_CONTINUE_BASE64`: reserved; posts are not paginated yet.
- `DISPLAY_AD_BELOW_CONTENT_BASE64`: Adsterra `320x50`.
- `DISPLAY_AD_STICKY_BOTTOM_BASE64`: Adsterra `320x50`, mobile-only delayed sticky test.
- `MONETAG_INPAGE_PUSH_BASE64`: Monetag `In-Page Push`.

Reserve these:

- `DISPLAY_AD_SIDEBAR_BASE64`: Adsterra `160x600`, desktop-only style test later.
- `DISPLAY_AD_HEADER_BASE64`: Adsterra `728x90`, desktop-only style test later.
- `MONETAG_ONCLICK_BASE64`: only in `KT_AD_MODE=medium` or `aggressive` after tracking works.
- `MONETAG_VIGNETTE_BASE64`: aggressive test only.
- `MONETAG_PUSH_BASE64` plus `MONETAG_SW_JS_BASE64`: push subscription test only, not baseline.

## Raw Adsterra codes

### Native Banner

```html
<script async="async" data-cfasync="false" src="https://pl29430345.profitablecpmratenetwork.com/a24d9674fe53c975dbc1512d0dc7e737/invoke.js"></script>
<div id="container-a24d9674fe53c975dbc1512d0dc7e737"></div>
```

### Banner 320x50

```html
<script>
  atOptions = {
    'key' : '6e33d3f930e0fdbdf79da466a872f2a3',
    'format' : 'iframe',
    'height' : 50,
    'width' : 320,
    'params' : {}
  };
</script>
<script src="https://www.highperformanceformat.com/6e33d3f930e0fdbdf79da466a872f2a3/invoke.js"></script>
```

### Banner 160x300

```html
<script>
  atOptions = {
    'key' : '042dac5bc1c6cd3341251a23ac78519c',
    'format' : 'iframe',
    'height' : 300,
    'width' : 160,
    'params' : {}
  };
</script>
<script src="https://www.highperformanceformat.com/042dac5bc1c6cd3341251a23ac78519c/invoke.js"></script>
```

### Banner 468x60

```html
<script>
  atOptions = {
    'key' : '8c1c300d6575d77b6f57f72595cf636b',
    'format' : 'iframe',
    'height' : 60,
    'width' : 468,
    'params' : {}
  };
</script>
<script src="https://www.highperformanceformat.com/8c1c300d6575d77b6f57f72595cf636b/invoke.js"></script>
```

### Banner 300x250

```html
<script>
  atOptions = {
    'key' : '57f25b48c5f4347101b5d7a4966c6fee',
    'format' : 'iframe',
    'height' : 250,
    'width' : 300,
    'params' : {}
  };
</script>
<script src="https://www.highperformanceformat.com/57f25b48c5f4347101b5d7a4966c6fee/invoke.js"></script>
```

### Banner 160x600

```html
<script>
  atOptions = {
    'key' : 'da98a57e5b28442c5f088aedd90bd726',
    'format' : 'iframe',
    'height' : 600,
    'width' : 160,
    'params' : {}
  };
</script>
<script src="https://www.highperformanceformat.com/da98a57e5b28442c5f088aedd90bd726/invoke.js"></script>
```

### Banner 728x90

```html
<script>
  atOptions = {
    'key' : '58bfe9cee4d316b04256c8bb3f2dba84',
    'format' : 'iframe',
    'height' : 90,
    'width' : 728,
    'params' : {}
  };
</script>
<script src="https://www.highperformanceformat.com/58bfe9cee4d316b04256c8bb3f2dba84/invoke.js"></script>
```

## Raw Monetag codes

### In-Page Push

```html
<script>(function(s){s.dataset.zone='10998523',s.src='https://nap5k.com/tag.min.js'})([document.documentElement, document.body].filter(Boolean).pop().appendChild(document.createElement('script')))</script>
```

### OnClick Popunder

No current Dr Purg Jr. code. Keep `MONETAG_ONCLICK_BASE64` empty until we intentionally create and test a dedicated zone.

### Vignette Banner

No current Dr Purg Jr. code. Keep `MONETAG_VIGNETTE_BASE64` empty until we intentionally create and test a dedicated zone.

### Push Notifications

No current Dr Purg Jr. code. Keep `MONETAG_PUSH_BASE64` empty until we intentionally create and test a dedicated zone.

Push service worker code:

```js
// Add Monetag's Dr Purg Jr. service worker code here only when testing Push Notifications.
```

The theme/MU plugin serves the service worker at:

```text
https://health.ibnbatoutaweb.com/sw.js
```

To enable push later, set both:

```env
MONETAG_PUSH_BASE64=
MONETAG_SW_JS_BASE64=
```

Keep push disabled until we intentionally test it, because push subscriptions can feel aggressive and may hurt user trust.
