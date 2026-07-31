# LIS Directory — Detailed Changelog

All notable changes to the LIS Directory plugin. Format inspired by [Keep a Changelog](https://keepachangelog.com/).
For each version: user-facing changes, files touched, data model deltas, technical decisions, and known limitations.

This file is the authoritative project history.

---

## [0.13.1] — 2026-07-31 — Fix: theme's default table borders bled into hours table

### Found by

Client screenshot of the live Business Hours box — a full grid of borders
around every cell, not the clean bordered-card-with-row-dividers look the
CSS intended. `assets/css/listings.css` never explicitly reset `border` on
the table/td elements, so the active theme's own default `<table>` styling
(Astra applies borders to content tables site-wide) won out.

### Fixed

- **`assets/css/listings.css`**: `.lis-listing-hours-table-display td`
  now explicitly sets `border: none`, with a single `border-bottom` added
  back only on non-last rows for a subtle divider instead of a full grid.

---

## [0.13.0] — 2026-07-31 — Listings: gallery, price, hours, features, video

### Context

Client shared a reference screenshot (a polished listing page: photo
gallery, rating badge, price, category, open/closed status, description,
features checklist, business hours sidebar, appointment booking, video)
and asked to build toward it. Scoped down deliberately: reviews/ratings
need a real submission+moderation data model, and appointment booking
needs a real slots/reservation system — both are separate features to
design properly, not fields to bolt on here. Everything else in the
reference is now built.

### Added

- **`includes/listings-hours.php`**: new — per-day open/close time meta
  (`_lis_listing_hours_{day}_open/close`), `lis_directory_is_listing_open_now()`
  (uses `current_time()`, which resolves against Settings > General's site
  timezone rather than a hardcoded one — LIS Events hardcoded Pacific for a
  documented reason that doesn't apply here), and
  `lis_directory_get_listing_week_hours()`. A listing with no hours set at
  all returns `null` (unknown) from the open-now check, not `false` — the
  template omits the badge entirely rather than confidently showing
  "Closed" for a listing that just never configured hours.
- **`includes/listings-meta.php`**: added Price (free text — "$150.00" or
  "$$" both work), Video URL (YouTube/Vimeo, converted to an embeddable URL
  via `lis_directory_video_embed_url()`, empty string on no match so
  nothing embeds rather than embedding something wrong), and a multi-image
  Gallery field (`_lis_listing_gallery_ids`, comma-separated attachment
  IDs — chose a single delimited string over `single: false` postmeta
  rows for simplicity, matching the same pattern already used for the
  contact fields).
- **`includes/listings-cpt.php`**: new `lis_listing_feature` taxonomy
  (non-hierarchical/tag-style — "Air Conditioning", "Free WiFi" — reuses
  WordPress's own tag-input admin UI rather than a custom checkbox field).
- **`assets/js/admin.js`**: gallery picker — `wp.media` in multi-select
  mode, same interaction pattern as the existing single-logo picker
  (select → thumbnail preview → individual remove buttons), extended
  rather than duplicated.
- **`templates/single-listing.php`**: rebuilt to match the reference's
  structure — gallery strip, header row (title + price + category badge +
  open/closed badge), two-column body (description/features/video in the
  main column, business hours table in the sidebar).
- **`templates/archive-listing.php`**: cards now show the same
  category/price/open-status badges, and use the first gallery image as
  the thumbnail when one exists (falling back to the featured image).
- **`assets/css/listings.css`**: full rewrite — card hover states, pill
  badges for category/price/open-status, gallery grid, two-column
  responsive layout (stacks under 720px), hours table styling, plus the
  admin-side gallery-thumbnail styles.

### Deliberately not built

Reviews/ratings and appointment booking, both present in the reference
image — flagged explicitly rather than silently omitted. Each needs its
own data model and submission flow (review moderation queue; booking
slots/availability/conflict handling), not a field addition to this
pass.

---

## [0.12.0] — 2026-07-31 — Framework for a self-hosted Directorist replacement

### Context

Direct request: same motivation and approach as LIS Events (built to
eventually replace EventON) — "paying for something that doesn't look
great, wish I had more control." Rather than a new plugin, this is built
into LIS Directory itself, growing its scope from "Preferred Vendor
Program overlay" to also being the general listings base layer it
currently sits on top of Directorist for. Framework only, explicitly:
CPT + taxonomy + minimal fields + minimal templates. No migration of
Directorist's ~230 categories/listings yet, no feature parity attempt
(no hours, gallery, map, reviews, claim-listing, booking) — those are
real design decisions for later passes, not gaps to silently patch.

### Added

- **`includes/listings-cpt.php`**: `lis_listing` CPT (`/listings/` archive)
  and `lis_listing_category` taxonomy, registered the same way as LIS
  Events' `includes/cpt.php` — same structure, same labels pattern, for
  consistency across both plugins.
- **`includes/listings-meta.php`**: "Listing Details" meta box — address,
  phone, website, email. `show_in_rest: true` (unlike this plugin's
  existing `lis_preferred_vendor` meta, which is REST-disabled) since
  there's no XSS-sensitive file upload involved here.
- **`includes/listings-template.php`**: routes `single_template` /
  `archive_template` / `taxonomy_template` to this plugin's own templates —
  same theme-override-first pattern as LIS Events' `includes/template.php`
  (`locate_template()` checked first, so a theme can still win).
- **`templates/single-listing.php`**, **`templates/archive-listing.php`**,
  **`assets/css/listings.css`**: basic but real templates — `get_header()`/
  `get_footer()` (inherits the theme's own site chrome), a card grid for
  archives, contact-info block on single pages.
- **`lis-directory.php`**: added a one-time-per-version rewrite-rule flush
  (`init` hook comparing a stored `lis_directory_flushed_version` option
  against `LIS_DIRECTORY_VERSION`) — the new CPT's rewrite rules
  (`/listings/`, `/listing-category/...`) don't exist until something
  flushes them, and `register_activation_hook` only fires on fresh
  install/reactivate, not on an in-place version update via the
  auto-updater. Plugin header `Description` updated to reflect the
  expanded scope.

---

## [0.11.0] — 2026-07-31 — Auto-place vendor cards on Directorist pages

### Context

Ticker/card placement was flagged open since v0.5.2. Client wanted the
preferred vendor card to actually show up where people are browsing —
Directorist's own category and single-listing pages, not just this
plugin's own shortcode pages. Directorist's category tree (~230
hierarchical terms, e.g. "Travel & Hospitality > Hotels & Lodging") has no
slug overlap with this plugin's flat `lis_vendor_category` terms (e.g.
"Lodging") — confirmed by pulling the real term list from
`edit-tags.php?taxonomy=at_biz_dir-category`, not assumed. That's the
decoupling trade-off already noted in readme.txt, not a bug. So automatic
matching wasn't possible; an explicit mapping was needed.

### Added

- **`includes/directorist-integration.php`**: new file, hooks into
  Directorist's own front-end templates (hook names and exact `do_action()`
  signatures confirmed against the real Directorist 8.9.2 source, not
  guessed from docs — `directorist_before_grid_listings_loop`,
  `directorist_before_list_listings_loop`, `directorist_before_map_listings_loop`
  fire with no arguments; `directorist_single_listing_after_title` fires
  with only the listing ID).
  - New term meta `_lis_pv_directorist_category_id` on `lis_vendor_category`
    terms — a dropdown (via core `wp_dropdown_categories()`, so Directorist's
    hierarchy renders indented) on the category add/edit screens, admin-set
    per vendor category.
  - `lis_directory_get_vendor_category_for_directorist_term()` — reverse
    lookup, Directorist term ID → mapped `lis_vendor_category` term.
  - On a Directorist category archive: resolves the current category the
    same way Directorist's own `category_archive()` does (`is_tax()` +
    `get_queried_object()` on a real taxonomy archive, falling back to
    `$_GET['category']` / `get_query_var('atbdp_category')` for the
    shortcode-rendered case) — matters because `get_queried_object()` alone
    is wrong when the archive is rendered via shortcode on an arbitrary
    page rather than a real `/at_biz_dir-category/{slug}/` URL.
  - On a single listing page: `get_the_terms( $listing_id, 'at_biz_dir-category' )`,
    renders one card for the first mapped category found (a listing with
    multiple mapped categories doesn't get duplicate cards).
  - Either case renders via `do_shortcode( '[lis_preferred_vendor_card
    category="..."]' )` — reuses the existing shortcode's own "no active
    vendor yet" / "bad category" handling rather than duplicating it.

### Known limitation

Directorist's list-view template fires `directorist_after_grid_listings_loop`
instead of a (non-existent) `directorist_after_list_listings_loop` — a
copy-paste bug in Directorist itself. Not routed around here since this
integration only uses the "before" hooks; noted in the file's own docblock
in case a future "after" placement is ever wanted.

---

## [0.10.0] — 2026-07-30 — Single logo upload, CSS-recolored; WooCommerce fix

### Context

Two loose ends flagged after the auto-update work: the "Logo — Black &
White" field was collected and editable but never rendered by any
shortcode, and the LIS Partner product still allowed a one-time purchase
that would bypass the subscription lifecycle the vendor auto-expire logic
depends on. Client confirmed: derive black/white from the one logo via CSS
instead of a second upload, and turn off one-time purchases.

### Changed: one logo, recolored via CSS instead of two uploads

- **`includes/submission.php`**: removed the "Logo — Black & White" field,
  its validation, and its upload handling entirely. The remaining logo
  upload (`lis_pv_logo_color`, meta key `_lis_pv_logo_color_id` — left
  unrenamed to avoid a data migration for the one real vendor already using
  it) is now **PNG-only** (was PNG or JPG), enforced both in the `accept`
  attribute and server-side (`wp_check_filetype_and_ext` whitelist,
  `upload_mimes` filter). PNG-only is deliberate, not incidental: the
  recolor effect only looks right against a transparent background, which
  JPEG can't have.
- **`includes/meta.php`**: removed the `_lis_pv_logo_bw_id` meta
  registration, its admin meta-box row, and its save handling. Existing
  `_lis_pv_logo_bw_id` postmeta (none exists on any real vendor) is simply
  orphaned, not migrated — nothing ever read it.
- **`includes/shortcodes.php`**: `[lis_preferred_vendor_ticker style="logos"]`
  gained a `tone` attribute (`color` default, `black`, `white`).
  `lis_directory_render_vendor_logo_html()` now takes `$tone` and adds a
  `lis-pv-ticker-logo--black`/`--white` modifier class.
- **`assets/css/vendor-ticker-logos.css`**: new `.lis-pv-ticker-logo--black`
  (`filter: brightness(0)`) and `--white`
  (`filter: brightness(0) invert(1)`) rules. `brightness(0)` turns every
  non-transparent pixel solid black regardless of its original color;
  stacking `invert(1)` after it flips that to solid white. Verified in the
  browser against the real "LIS Partners" lockup PNG (which does have real
  alpha transparency, confirmed via a PIL alpha-channel sample) before
  committing — screenshotted all three tones side by side, white checked
  against a dark background specifically since that's the case that would
  otherwise be invisible if the filter didn't work.

### Fixed: WooCommerce one-time purchase bypass

- Disabled "Customers can buy this product without subscribing" on the LIS
  Partner product (WCSATT setting, `data-allow-one-off`). A one-time
  purchase would let someone acquire an Active vendor slot that never
  entered the subscription lifecycle `includes/woocommerce.php` hooks into
  for auto-expiring a vendor on cancellation — the slot would just never
  expire. No plugin code changed for this; it's a WooCommerce product
  setting, not plugin behavior.
- Caught and immediately corrected a mistake while making this change: the
  product's own "Publish"/"Update" submit button briefly published it
  live (it's meant to stay Draft — a $1/mo placeholder, not a real
  purchasable listing yet) as a side effect of saving the WCSATT toggle.
  Reverted the post status back to Draft in the same session before ending
  the turn; confirmed via `post-status-display` text reading "Draft" again
  and `data-allow-one-off` reading "no".

---

## [0.9.0] — 2026-07-29 — GitHub-based auto-update

### Context

Requested directly: wire up one-click updates the same way LIS Events
already works, instead of continuing to hand-deliver zips for every fix.

### Added

- **`lib/plugin-update-checker/`**: the same bundled copy of
  YahnisElsts/plugin-update-checker (MIT) used by LIS Events, copied in
  unmodified.
- **`includes/updater.php`**: new — same structure as LIS Events'
  `includes/updater.php`, adapted to `LIS_DIRECTORY_*` naming. Points at
  `https://github.com/luccagrillo1/lis-directory/`, authenticates via an
  optional `LIS_DIRECTORY_UPDATE_TOKEN` constant (only defined if set — the
  plugin runs identically without it, just with no update banner), and is
  required from `lis-directory.php` at plugin-load time rather than from a
  hook (constructing it later means some of PUC's own hook registrations,
  e.g. the periodic check and the "Check for updates" link, never wire up —
  same lesson already documented in LIS Events' updater.php).

### Requires (one-time, done outside this repo)

A fine-grained GitHub personal access token scoped to just this repo,
**Contents: read-only**, added to `wp-config.php` as
`LIS_DIRECTORY_UPDATE_TOKEN`. LIS Events uses a separately-scoped token for
its own repo — per that plugin's own documented practice this should be a
new token scoped only to `lis-directory`, not a reuse of the LIS Events one.

### Not yet done

No GitHub Actions lint workflow or top-level README.md yet (LIS Events has
both) — out of scope for this change, which was specifically about the
update mechanism.

---

## [0.8.5] — 2026-07-29 — Fix: the 0.8.3 margin fix was silently losing the cascade

### Found by

Client reported the heading-to-card gap looked unchanged despite 0.8.3/0.8.4
being installed. Checked computed styles directly on the live page again
(`getComputedStyle` on `.lis-pv-heading`) instead of assuming the CSS in the
file was taking effect: `margin-bottom` was still `19.6px`, not the `8px`
set in 0.8.3's `vendor-heading.css`. The plugin's own `<style>` tag with the
override was confirmed present and loaded on the page — the rule was there,
it just wasn't winning.

### Root cause

The theme has its own rule for headings inside post content, keyed off a
class + type selector like `.entry-content h2` — specificity (0,1,1). This
plugin's override used a bare class selector, `.lis-pv-heading` —
specificity (0,1,0). Lower specificity loses regardless of source order, so
the theme's ~20px default margin was winning every time, silently, with no
error or warning.

### Fixed

- **`assets/css/vendor-heading.css`**: added `!important` to
  `.lis-pv-heading`'s `margin` declaration. Verified live by patching the
  page's actual `<style id="lis-pv-heading-style">` tag in the browser and
  re-checking `getComputedStyle` before shipping — confirmed `8px` this
  time, then took a screenshot showing the visibly tighter gap.

---

## [0.8.4] — 2026-07-29 — Revert: lockup image back to the exact original file

### Found by

Direct correction from the client: the request was only ever to reduce the
space between the heading and the card/ticker underneath it (0.8.3) — never
to touch the spacing inside the pre-built logo file itself. 0.8.1 and 0.8.3
both edited the image's internal icon/text gap based on a misreading of
"less gap" that never should have touched the asset at all.

### Fixed

- **`assets/img/lis-partners-lockup.png`**: replaced with a direct,
  unmodified copy of the original supplied file
  (`specialty/lis-logomark-black-v1-LISPARTNERS.png`) — verified identical
  via `shasum` (matching SHA-1 on both files) rather than eyeballing it, so
  there's no ambiguity left about whether this copy is untouched.
- No CSS or PHP changed in this version — only the asset file.

---

## [0.8.3] — 2026-07-29 — Fix: gap was between the heading and the card, not inside it

### Found by

Follow-up on the "less gap" feedback that led to 0.8.1 — that fix was for
the wrong gap. The client meant the space between the "LIS Partners"
heading and the card/ticker sitting below it, not the space inside the
lockup image between the icon and the word "Partners". Checked the live
page's computed styles directly (`getComputedStyle` on `.lis-pv-heading`)
rather than guessing again: `margin-bottom: 19.6px`, coming entirely from
the theme's default `<h2>` spacing, since this plugin had never set its own
margin on the heading.

### Fixed

- **`assets/css/vendor-heading.css`**: `.lis-pv-heading` now sets
  `margin: 0 0 8px 0`, overriding the theme's default heading margin
  instead of inheriting it.
- Verified by injecting the same CSS live on the preview page and
  screenshotting before committing the change, same as prior fixes.

### Fixed: icon/text gap overcorrected in 0.8.1

Same message that reported the heading-to-card margin also flagged that the
logomark and "Partners" now read as too crowded — 0.8.1's trim (484px down
to 150px) went further than intended.

- **`assets/img/lis-partners-lockup.png`**: regenerated again from the
  original, untouched source asset (not from the already-trimmed 0.8.1
  file, to avoid compounding rounding/quality loss across re-encodes),
  this time trimming the gap to ~280px instead of 150px.

---

## [0.8.2] — 2026-07-29 — Fix: stale cache kept serving the old lockup image

### Found by

After installing 0.8.1, the live preview page still showed the wide-gap
version of the lockup. Checked wp-admin directly — the plugin's active
version was already 0.8.1, so the PHP/markup was current. Checked for a
page-caching plugin (LiteSpeed Cache is installed on this site but
confirmed inactive), ruling that out — this was a plain browser/host
static-asset cache: the `<img src>` URL is identical across versions (same
filename, `lis-partners-lockup.png`), so nothing signaled that the file
behind that URL had changed, and it kept serving the previously cached
bytes.

### Fixed

- **`includes/shortcodes.php`**: the lockup `<img src>` now appends
  `?ver=<?php echo LIS_DIRECTORY_VERSION; ?>`. Every version bump changes
  the URL, so a stale cached copy of the old image can never be served
  again by mistake — each version gets its own cache entry.

---

## [0.8.1] — 2026-07-29 — Fix: gap between icon and "Partners" too wide

### Found by

Live feedback right after installing 0.8.0 — the whitespace baked into the
supplied lockup PNG between the logomark and "Partners" was wider than it
should read at heading size.

### Fixed

- **`assets/img/lis-partners-lockup.png`**: regenerated from the original
  supplied asset — found the whitespace column run between the icon and
  text (columns 1143–1627 of 5065px wide, ~484px), and cut it down to
  ~150px by trimming equally from both sides of that gap and re-compositing
  the two halves, rather than attempting to fake a tighter gap with CSS
  over a flattened image (not possible — the gap is baked into the pixels).
  No other part of the image (icon shape, letterforms, inter-letter spacing
  within "Partners") was touched.

---

## [0.8.0] — 2026-07-29 — Heading uses the pre-built "LIS Partners" lockup

### Context

Two rounds of alignment fixes (0.7.1, 0.7.2, 0.7.3) tried to compose the
logomark icon and "Partners" text into a matching lockup with CSS, and still
didn't look right — the client pointed out a ready-made asset already
existed in the supplied brand export
(`specialty/lis-logomark-black-v1-LISPARTNERS.png`): the full "LIS Partners"
lockup, already designed, kerned, and aligned as one image. Using it outright
replaces three versions of CSS guesswork with the actual designed asset.

### Changed

- **`assets/img/lis-partners-lockup.png`**: new — copied directly from the
  brand export, unmodified.
- **`includes/shortcodes.php`**: `lis_directory_render_heading_shortcode()`
  now renders a single `<img>` (alt text "LIS Partners") inside the heading
  tag, instead of an icon `<img>` plus a "Partners" text node.
- **`assets/css/vendor-heading.css`**: collapsed to just sizing the one
  image (`height: 28px`) — no more flex layout, gap, or icon-vs-text
  alignment rules, since there's nothing left to align.
- `lis-logomark-green.svg` (the standalone icon asset added in 0.7.0) is no
  longer used by this shortcode but left in place — unused-asset cleanup
  wasn't asked for and the file may still be useful elsewhere.

### Not yet verified

Not yet checked against the live site — next step is installing this build
and confirming the lockup image renders at a reasonable size and legibility
next to the card/ticker.

---

## [0.7.3] — 2026-07-29 — Fix: heading icon still misaligned after 0.7.2

### Found by

0.7.2's center-align + translateY compensation wasn't enough — the icon
still read as sitting low/disconnected from "Partners" once installed.

### Fixed

- **`assets/css/vendor-heading.css`**: dropped the center-align +
  translateY hack. `.lis-pv-heading` now uses `align-items: flex-start`
  (icon top and text top share the same edge, avoiding the need to guess
  where the icon's internal "baseline" is at all) with a tighter
  `height: 0.8em` and `gap: 0.15em` on the icon so the lockup reads as one
  connected mark instead of two floating pieces.
- Chosen by rendering seven side-by-side candidates (varying align, icon
  height, translateY, and gap) directly in the browser against the live
  page's own logomark asset, rather than reasoning about it abstractly.

---

## [0.7.2] — 2026-07-29 — Fix: heading icon misaligned, heading too large next to a card

### Found by

Live feedback after installing v0.7.1: the logomark icon sat visibly lower
than the "Partners" text baseline, and the whole heading (still sized off
the theme's `<h2>`) dwarfed the compact card sitting right underneath it.

### Fixed

- **`assets/css/vendor-heading.css`**: the logomark SVG has more visual
  weight low in its bounding box (the "S" swoop dips below the "L"/"i"
  baseline, with headroom above), so centering its box against the text's
  line box (`align-items: center`) left it reading low. Added
  `transform: translateY(-14%)` on `.lis-pv-heading-icon` and trimmed its
  height to `0.95em` to compensate. Also gave `.lis-pv-heading` a fixed
  `font-size: 1.15rem` instead of inheriting the theme's `<h2>` size, so it
  reads as a small label sitting above the card/ticker rather than a
  full-width section title competing with it for attention.
- Confirmed by cloning the live `.lis-pv-heading` node into an isolated
  full-viewport overlay in the browser to test icon alignment in isolation,
  then testing the final font-size against the real card in place on the
  preview page, before committing the values to the CSS file.

---

## [0.7.1] — 2026-07-28 — Fix: heading text used theme's serif font, clashed with brand

### Found by

Live check of the preview page right after installing v0.7.0: the theme's
`<h2>` uses a light serif face, so "Partners" rendered in that serif style
next to the bold sans-serif LIS logomark icon — it didn't read as one brand
mark, and the actual "Lis" logotype (`lis-alt-green-v1.png` in the supplied
brand export) is a clean geometric sans-serif, not a serif.

### Fixed

- **`assets/css/vendor-heading.css`**: `.lis-pv-heading` now sets its own
  `font-family` (system sans-serif stack) and `font-weight: 700` instead of
  purely inheriting the theme's heading font. The element is still a real
  `<h2>`/`<h3>`/etc. for semantic structure, sizing (`1em`-relative icon),
  and spacing — only the typeface is overridden, not the tag choice from
  v0.7.0's original design.

---

## [0.7.0] — 2026-07-28 — "LIS Partners" branded heading shortcode

### Context

With Talus Rock Retreat live and both a card and a logo-only ticker to place
it in, the remaining gap was a title for those components — the brief asked
for "LIS Partners" as the heading, built from the LIS logomark plus the word
"Partners", using brand assets supplied directly (a logo export folder
containing both a "logomark" icon-only SVG and a separate "wordmark" script
SVG reading "Living in Sandpoint"). The logomark was the correct asset for
this — the wordmark is a full cursive rendering of the site name, not "LIS,"
and would have made the shortcode's own "Partners" text redundant or
mismatched in style.

### Added

`[lis_preferred_vendor_heading]` — renders the LIS logomark icon followed by
the text "Partners," meant to sit above a `[lis_preferred_vendor_card]` or
`[lis_preferred_vendor_ticker]` wherever they're placed on a page.

- **`includes/shortcodes.php`**: new `lis_directory_render_heading_shortcode()`
  and `lis_directory_get_heading_styles_once()` (same "return, don't echo"
  pattern as every other style-loader in this file, per the v0.5.2 lesson).
  Takes a `tag` attribute (`h1`/`h2`/`h3`/`h4`/`div`, default `h2`) via
  `shortcode_atts()`, validated against a whitelist rather than trusted
  directly.
- **`assets/img/lis-logomark-green.svg`**: new — copied from the supplied
  brand export (`svg/lis-logomark-green-v1.svg`), the icon-only mark, not the
  full wordmark.
- **`assets/css/vendor-heading.css`**: new, small — flexbox row for the icon
  + text, `height: 1em` on the icon so it scales with whatever font-size the
  surrounding heading tag ends up at (this plugin doesn't set its own heading
  font-size; it renders a real `<h2>` etc. so the active theme's own heading
  styles apply, and the icon needs to track that, not a hardcoded pixel size).

### Technical decision: real heading tag, not a styled `<div>`

Renders as an actual `<h1>`–`<h4>` (or `div` if explicitly requested) so it
inherits the theme's existing heading typography, color, and spacing instead
of the plugin guessing fonts to match. The icon is nested inside the heading
tag (not a sibling before it) specifically so its `height: 1em` sizing
resolves against the heading's own computed font-size — this makes the icon
automatically scale correctly across whatever breakpoints the theme applies
to its headings, without this plugin needing to know or duplicate those
breakpoints. `alt="LIS"` on the icon combined with the following text node
lets a screen reader announce it as "LIS Partners."

### Not yet verified

Not yet checked against the live site — next step is installing this build
and confirming the icon renders at a reasonable size next to the theme's
actual `<h2>` styling, and that the whole line reads correctly as "LIS
Partners."

---

## [0.6.0] — 2026-07-28 — Directorist-listing requirement, logo-only ticker

### Context

First real vendor (Talus Rock Retreat) went live in the new "Lodging" category
this session, which surfaced three follow-up requests: (1) a vendor should
have to already be listed on the Local Directory before becoming a Preferred
Vendor, (2) a logos-only ticker display as an alternative to the card ticker,
and (3) the WooCommerce setup docs needed correcting (see v0.5.2-era testing
notes — this site uses WCSATT's "Subscriptions" tab on a Variable product,
not the classic "Variable subscription" product type the readme originally
described).

### Added: Directorist-listing requirement

A vendor must have a real, published listing on Directorist (`at_biz_dir`)
before they can be set Active. Reuses the existing `_lis_pv_link_url` field
rather than adding a second URL field — it already meant "their Directorist
listing or external site," and the one real vendor submitted so far (Talus
Rock Retreat) already satisfied this by coincidence, since its Link URL was
already set to its real Directorist listing.

- **`includes/enforcement.php`**: new `lis_directory_is_valid_directorist_url()`
  — `url_to_postid()` to resolve the URL, then confirms the resulting post is
  `at_biz_dir` and `publish`. New `lis_directory_can_activate_vendor()` runs
  both activation requirements (category lock + this one) and flags whichever
  fails first, so the meta box save and the list-table Approve action share
  one source of truth instead of two separate copies of "block + explain why."
  `lis_directory_flag_active_conflict()` was replaced by a more general
  `lis_directory_flag_activation_blocked( $vendor_id, $reason, $data )` (reasons:
  `category_conflict` or `not_on_directory`), and `lis_directory_conflict_notice()`
  now branches its message on `$reason`.
- **`includes/meta.php`** / **`includes/admin.php`**: both the meta box save
  and the Approve row action now call `lis_directory_can_activate_vendor()`
  instead of only checking the category conflict. Field relabeled "Link URL"
  → "Directorist Listing URL" with an updated description.
- **`includes/submission.php`**: new required field on the front-end form,
  "Your Local Directory Listing URL" — validated server-side the same way
  (submission is rejected with a clear error if missing or invalid, not just
  discouraged in copy), then stored into `_lis_pv_link_url` on creation.

### Technical decision: enforced at both submission AND activation

Submission-time validation stops a bad link at the door; activation-time
validation (via the shared `lis_directory_can_activate_vendor()`) re-checks
in case the linked listing was unpublished or deleted after a vendor
submitted but before an admin approved them. Same "solid server-side
enforcement, not just UI prevention" standard the brief originally set for
the category lock — this is the second rule under that standard now, and it
was deliberately built to share the same flag/notice mechanism rather than
becoming a second, parallel pattern.

### Added: logo-only ticker option

`[lis_preferred_vendor_ticker style="logos"]` — alongside the original
(now-default) `style="cards"`. Logos-only mode shows just each vendor's color
logo, larger, no name or tagline; a vendor with no logo uploaded is silently
skipped (not shown as an empty slot) rather than breaking the layout.

- **`includes/shortcodes.php`**: the ticker shortcode now takes a `style` attr
  via `shortcode_atts()`. New `lis_directory_render_vendor_logo_html()`
  (parallel to `lis_directory_render_vendor_card_html()`, returns `''` for a
  vendor with no logo so `array_filter()` can drop it) and
  `lis_directory_get_ticker_logo_styles_once()` (same "return, don't echo"
  pattern as the other style-loader functions, v0.5.2's lesson applied
  directly to the new code rather than repeating the mistake).
- **`assets/css/vendor-ticker-logos.css`**: new, small — only the logo-item
  sizing rules. The base scroll/loop mechanics in `vendor-ticker.css` are
  shared by both styles unchanged.
- Existing `[lis_preferred_vendor_ticker]` calls with no `style` attribute are
  unaffected — `cards` is the default, matching current behavior exactly.

### Fixed: readme.txt's WooCommerce setup section

Rewrote to describe the actual click path discovered while setting up the
real "LIS Partner" product: a Variable product's **Subscriptions** tab
("Create custom subscription plans" → "Add subscription plan"), powered by
WooCommerce Subscribe All The Things (WCSATT) — not a dedicated "Variable
subscription" product type, which this WooCommerce Subscriptions install
doesn't expose. No plugin code changed for this — `includes/woocommerce.php`
already reads the same underlying `_subscription_price` / `_subscription_period`
meta regardless of which admin UI wrote them.

### Not yet verified

Fix/features haven't been re-tested against the live site yet — next step is
reinstalling and confirming: (1) the existing Talus Rock Retreat vendor still
activates cleanly (it already has a valid Directorist URL, so this should be
a no-op), (2) a vendor with an invalid/missing Directorist URL is correctly
blocked from Active with the new notice, and (3) `style="logos"` renders
correctly on the live preview page.

---

## [0.5.2] — 2026-07-27 — Fix: shortcode CSS corrupted non-page render contexts

### Found by

Continued live testing on production, step 7. The block editor's iframed
content canvas wasn't reliably accepting simulated keystrokes for automated
testing, so I created a test page via a direct `fetch()` call to the
WordPress REST API (`POST /wp/v2/pages`) instead, using the page's own
`wpApiSettings.nonce` — a practical workaround for testing, and it happened
to surface a real bug the normal editor UI never would have hit.

### The bug

The REST API response for creating the test page (content: both shortcodes)
came back as `<style id="lis-pv-card-style">...</style>{"id":123,...}` instead
of clean JSON — `response.json()` failed with "Unexpected token '<'". Same
thing on a subsequent GET request for the page list.

### Root cause

`lis_directory_print_card_styles_once()` and `_print_ticker_styles_once()`
(v0.3.0) called `echo` directly, **outside** the `ob_start()` buffer that the
rest of each shortcode's markup is built in. On a normal front-end page
render, `the_content` output streams straight to the browser, so a stray
direct `echo` mid-filter just lands in the page's HTML somewhere near the
shortcode — harmless in practice, which is exactly why this shipped
undetected through v0.3.0–0.5.1. But `apply_filters( 'the_content', ... )` is
also how the REST API builds its `content.rendered` field (and how
admin-ajax previews, feeds, and excerpts work) — anywhere the *return value*
of `the_content` is captured as a string and serialized elsewhere, a direct
echo happening during that filter corrupts the output stream instead of
landing in it. The v0.3.0 changelog entry already reasoned carefully about
*why* the styles couldn't be `wp_enqueue_style()`'d (shortcodes run after
`wp_head()` in most themes) — but missed that "echo instead" has this second,
non-obvious failure mode outside full template rendering.

### Fix (`includes/shortcodes.php`)

Renamed both functions (`lis_directory_get_card_styles_once()` /
`lis_directory_get_ticker_styles_once()`) to **return** the `<style>` string
instead of echoing it, and changed every call site to prepend that returned
string onto the function's own `ob_get_clean()` result — so the styles are
now part of the shortcode's single returned string, never a side-channel
echo. Behavior on a normal page is unchanged (styles still appear once,
inline, ahead of the first card); the fix only matters for non-template
contexts.

### Not yet verified

Fix hasn't been re-tested against the live site yet — next step is
reinstalling and repeating the same REST `fetch()` call to confirm the
response is now valid JSON.

---

## [0.5.1] — 2026-07-27 — Fix: unset status meta on a blocked new-vendor save

### Found by

Live testing on production (livinginsandpoint.com) — build order step 7. First
real exercise of this plugin against actual WordPress, and it caught a real
bug within the first few minutes of testing.

### The bug

Created a brand-new vendor, checked its category, and — to test the v0.2.0
category-lock enforcement — set its Status straight to `Active` on first
Publish, expecting the conflict block to leave it as `Pending` (an existing
active vendor already held that category). The conflict block worked
correctly: it did NOT set status to `active`, and it did show the "Status not
changed to Active" admin notice with a working link to the conflicting
vendor.

But afterward, the vendor showed up in the list table with a blank-looking
row and — critically — **no Approve/Reject row actions**, even though the
Status column displayed "Pending". Digging in: `get_post_meta( $post_id,
'_lis_pv_status', true )` was actually returning `''` (empty string), not
`'pending'`. The Status *column* (`includes/admin.php`) has always defaulted
an empty value to display "Pending" — a display-only fallback — which masked
the fact that the meta key had never actually been written. The Approve/
Reject row actions and the Pending status-filter's `meta_query` both match on
the literal value `'pending'`, and neither matches "meta key doesn't exist,"
so both silently failed to find this vendor.

### Root cause

`lis_directory_save_meta_box()`'s conflict branch (v0.2.0) was written for
the case of *editing an existing vendor*: "block the change, leave the
existing status untouched." That's correct when a status value already
exists. But for a **brand-new post**, there is no existing status to leave
untouched — the meta key was never created — so "untouched" meant "never
set," not "stays at its current value." The gap only shows up on a new
vendor's very first save when that first save happens to request `active`
and gets blocked; every other path (a normal new vendor saved as `Pending`,
or an existing vendor being edited) was unaffected.

### Fix (`includes/meta.php`)

In the conflict branch, if `get_post_meta( $post_id, '_lis_pv_status', true )`
is currently empty, explicitly call `lis_directory_set_vendor_status(
$post_id, 'pending' )` before flagging the conflict notice — so a blocked
first save still leaves the vendor in a real, queryable `pending` state
instead of no state at all. Existing vendors with a real prior status are
unaffected (the empty-check means this only fires for genuinely new posts).

### Verified

Reinstalled v0.5.1 on livinginsandpoint.com and re-saved the same test vendor
that had triggered the bug. Confirmed: Approve/Reject row actions now appear,
Approve correctly re-blocks on the same category conflict (reusing
`lis_directory_get_active_conflict()`, same notice as the meta box path), and
Reject correctly sets status to Rejected and reopens the category — checked
via `lis_directory_get_active_vendor_for_category()` no longer returning the
rejected vendor.

---

## [0.5.0] — 2026-07-27 — WooCommerce Subscriptions integration

### What this version adds

Build order step 6 — the last item in v1 scope. Open Decision #1 is now
resolved: **recurring subscription**, via WooCommerce Subscriptions. Verified
directly in wp-admin > Plugins (not just told): WooCommerce 11.0.0-rc.1 and
WooCommerce Subscriptions 9.0.1 are both installed and Active on
livinginsandpoint.com as of 2026-07-27.

### Added (`includes/woocommerce.php`)

- **Variation category tagging.** A new "LIS Vendor Category" dropdown on
  each WooCommerce product variation's admin screen
  (`woocommerce_variation_options_pricing` / `woocommerce_save_product_variation`),
  storing `_lis_pv_category_term_id` on the variation. This plugin does **not**
  create the WooCommerce product itself — per the brief's recommendation, the
  admin builds one variable Subscription product ("Preferred Vendor") by hand
  with one variation per category, then tags each variation here. Tagging is
  how the rest of this file finds "the variation for category X" without a
  settings field naming the product.
- **Automatic stock sync**
  (`lis_directory_sync_stock_for_category()` / `_on_status_change()`): any
  variation tagged for a category has its stock quantity/status set to 0/
  out-of-stock when that category has an active vendor, and back to 1/
  in-stock when it doesn't — "0 in stock" doubles as the availability check
  per the brief, so WooCommerce's own "sold out" UI does this for free.
  Fires off a new centralized `lis_directory_vendor_status_changed` action
  (see Technical decisions below) rather than being hooked into every place
  status can change individually.
- **Thank-you page submission link**
  (`lis_directory_maybe_show_submission_link()`, on `woocommerce_thankyou`):
  if the order contains a tagged variation, shows a link straight to the
  submission form (`includes/submission.php`, extended this version to accept
  `?lis_pv_category=` + `?lis_pv_order=`), which locks the category field
  (no picking a different one than what was paid for) and carries the
  subscription ID through as a hidden `lis_pv_wc_order_id` field, so the
  resulting vendor post is tied back to what was actually purchased.
- **New "LIS Directory > Settings" admin page** — one field, which page has
  the `[lis_preferred_vendor_submit]` shortcode, needed to build the
  thank-you-page link above (`get_permalink()` needs a page ID; there's no
  other way to know where that shortcode lives on this specific site).
- **Auto-expire on cancellation**
  (`lis_directory_handle_subscription_ended()`, on
  `woocommerce_subscription_status_cancelled` / `_expired`): looks up the
  vendor post whose `_lis_pv_wc_order_id` matches the ended subscription's ID
  and calls `lis_directory_set_vendor_status( $id, 'expired' )` — which, via
  the stock-sync hook above, also reopens the category's variation
  automatically. Both `cancelled` and `expired` are handled the same way,
  since either means the paid term ended and the category-lock enforcement
  (v0.2.0) only cares about `active`.

### Technical decisions

- **New `lis_directory_set_vendor_status()`** in `includes/enforcement.php`
  replaces direct `update_post_meta( ..., '_lis_pv_status', ... )` calls at
  every existing call site (meta box save, Approve/Reject row actions,
  submission-form initial `pending`, and now the subscription-ended handler).
  It sets the meta and fires `lis_directory_vendor_status_changed`. This
  version is the reason it exists: stock sync needed to react to a status
  change from four different call sites, and wiring the sync call into each
  one individually would've meant remembering to do it again at the next call
  site too. One choke point, one place that reacts.
- **`_lis_pv_wc_order_id` stores the subscription ID, not the one-time order
  ID**, for subscription-based vendors — matches the field's original name
  ("Linked WooCommerce order **or subscription** ID", v0.1.0) and is what the
  cancellation hook actually has available to match against
  (`WC_Subscription::get_id()`), not the original order.
- **Only the thank-you page carries the submission link** — not a duplicate
  in the order confirmation email or My Account. The brief says "e.g.
  confirmation email/account page," not "both," and the thank-you page is
  simplest and shown reliably right after purchase. Flag if vendors turn out
  to miss it and need a second surface (e.g. if they close the tab before
  clicking through).
- **All WooCommerce/Subscriptions hooks are gated behind
  `class_exists( 'WooCommerce' )` / `class_exists( 'WC_Subscriptions' )`**
  inside a `plugins_loaded` callback — this plugin still works standalone
  without WooCommerce (v0.1.0–0.4.0 already do), so nothing in this file may
  assume either is present.
- No admin UI validates that a variation's category isn't tagged on *two*
  different variations, or that the site even has a "Preferred Vendor"
  product yet — both are wp-admin setup mistakes to catch by hand for now
  (documented in readme.txt's new Setup section) rather than defensive code
  for a one-person admin team setting this up once.

### Known limitations (carried over, unchanged)

Not yet tested on staging or deployed — this is the first version where that
matters most, since it touches WooCommerce/Subscriptions APIs that have never
run against this codebase before. Build order step 7 (staging test + confirm
deployment) is what's left.

---

## [0.4.0] — 2026-07-27 — Front-end submission + approval workflow

### What this version adds

Build order step 5: vendors can now submit themselves instead of being entered
by hand, and admins have a proper approve/reject workflow instead of only the
Status dropdown buried in the edit screen.

### Added

- `[lis_preferred_vendor_submit]` (`includes/submission.php`). Logged-in only —
  **requires a WordPress account** (resolved Open Decision #2); logged-out
  visitors see a log in/register prompt instead of the form. Fields: business
  name, category (select), tagline, color logo (required), B&W logo
  (optional), contact email (prefilled from the logged-in user's account).
  Posts to `admin-post.php` (`action=lis_directory_submit_vendor`).
- Category dropdown shows **every** category, including ones with a current
  active vendor — labeled "currently taken - apply to be waitlisted" rather
  than hidden. Chosen over hiding taken categories entirely: the brief allows
  either approach, and hiding them would mean a vendor literally cannot apply
  to a taken category at all (no queue to join), whereas allowing the
  submission lets an admin see and manage waitlist interest by hand. Matches
  the existing rule that **pending submissions never block a category** — only
  an `active` vendor does (v0.2.0).
- Submission → always lands as `_lis_pv_status = pending`, `post_author` set
  to the submitting user (ties it to their account for a future
  account-page/WooCommerce link in step 6), never auto-published.
- **File upload safety** (`lis_directory_handle_logo_upload()`): 2MB cap,
  `wp_check_filetype_and_ext()` + `getimagesize()` to confirm the file is a
  real PNG/JPEG (not a renamed file — extension alone is never trusted), and a
  request-scoped `upload_mimes` filter restricting the underlying
  `media_handle_upload()` call to PNG/JPEG only. **SVG is never accepted**, per
  the build brief's explicit XSS warning about SVG uploads.
- **Honeypot** (`lis_pv_hp`, positioned off-screen, `tabindex="-1"`,
  `autocomplete="off"`) + nonce (`lis_pv_submit_vendor`) on the form. A bot
  that fills the honeypot gets redirected to the same "success" page as a real
  submission, with nothing actually created — it never learns the submission
  was silently dropped.
- **wp-admin approval workflow**, built into the existing Preferred Vendors
  list table rather than a separate admin page: a Status filter dropdown
  (`restrict_manage_posts` / `parse_query`) to jump straight to Pending, and
  one-click **Approve**/**Reject** row actions (shown only when status is
  `pending`) that post to `admin-post.php` with a per-post nonce. Approve
  re-runs the exact same `lis_directory_get_active_conflict()` check the meta
  box save already used (v0.2.0) — if the vendor's category is already taken,
  the approve is blocked and flagged via the same
  `lis_directory_flag_active_conflict()` transient/notice, now generalized
  into `includes/enforcement.php` so both the meta box and the row actions
  share one implementation instead of two copies of the same check.
- New `_lis_pv_contact_email` meta field, registered alongside the others
  (v0.1.0) and added to the "Vendor Details" admin meta box so admins can see/
  edit what the vendor submitted.

### Technical decisions

- Refactored `lis_directory_conflict_notice()` (previously private to
  `includes/meta.php`) into `includes/enforcement.php` as
  `lis_directory_flag_active_conflict()` + `lis_directory_conflict_notice()`,
  since v0.4.0 needed the same "block + notify" behavior from a second call
  site (the Approve row action) that isn't a meta-box save.
- Post-insert uses WordPress's native `publish` status, not `pending` —
  deliberately, to avoid two overlapping meanings of "pending." This CPT
  already has its own single source of truth for workflow state
  (`_lis_pv_status`, v0.1.0: pending/active/expired/rejected); reusing WP's
  native post status for the same concept would create two systems that could
  silently disagree.
- Error handling redirects back to the referring page with error keys in a
  query arg (`?lis_pv_error=business_name,category`) rather than preserving
  submitted field values across the redirect. Simpler for v1; a rejected
  submission currently means re-entering the whole form. Worth revisiting if
  submission volume makes that friction actually matter.
- No email notifications (to the vendor on submit, or to admins on a new
  pending item) — the brief's approval-workflow description doesn't call for
  them, unlike LIS Events' submitter/approval emails. Flagging in case that
  turns out to be expected once this is live and admins are checking a list
  manually instead of getting pinged.

### Known limitations (carried over, unchanged)

No WooCommerce integration yet (step 6, blocked on the still-open billing
model decision). Not yet tested on staging or deployed.

---

## [0.3.0] — 2026-07-27 — Front-end display: card + ticker shortcodes

### What this version adds

Build order steps 3 and 4: the two front-end display shortcodes. Still no
submission form — vendors are still entered by hand in wp-admin.

### Added

- `[lis_preferred_vendor_card category="slug"]` (`includes/shortcodes.php`):
  looks up the `lis_vendor_category` term by slug, finds its active vendor via
  `lis_directory_get_active_vendor_for_category()` (reused from the
  enforcement module — same lookup the category-lock check uses), and renders
  a card (logo, business name, tagline, wrapped in a link if one is set).
- `[lis_preferred_vendor_ticker]`: renders every currently active vendor
  (`lis_directory_get_all_active_vendors()`, new in `includes/enforcement.php`)
  as the same card markup, in a horizontally auto-scrolling infinite strip.
  Pure CSS (`assets/css/vendor-ticker.css`) — no JS. Card-only, per the brief
  (no logo-only variant in v1).
- Both shortcodes fail closed for the public: a bad slug or a category with no
  active vendor yet renders nothing to anonymous visitors, but shows a small
  inline red hint to anyone who `can edit_posts` — so a shortcode left on a
  page mid-build doesn't look broken to visitors but also doesn't silently
  look "done" to whoever placed it.

### Technical decisions

- **CSS is inlined via `<style>` on first shortcode use per request, not
  `wp_enqueue_style()`.** Shortcodes execute inside `the_content` — by the time
  they run, most themes have already output `wp_head()`, so a style enqueued
  from inside the shortcode callback would silently never reach `<head>`. Both
  shortcodes read their `.css` file from disk once per request and print it
  inline instead, which sidesteps the timing problem entirely. The `.css`
  files still live under `assets/css/` as the source of truth — they're just
  delivered inline rather than as a linked stylesheet.
- **Ticker loop is pure CSS**, not JS: the track renders the vendor list
  twice (`.lis-pv-ticker-group` + an `aria-hidden` `.lis-pv-ticker-copy`
  duplicate) and animates `translateX` from `0` to `-50%` — since both groups
  are equal width, that's a seamless loop with no JS scroll math. `@media
  (prefers-reduced-motion: reduce)` drops the animation *and* the duplicate
  group (showing the same vendors twice in a static list would just be
  confusing without motion to explain why).
- The single-vendor card lookup (`lis_directory_get_active_vendor_for_category()`)
  was already built in v0.2.0 for enforcement — reused as-is here rather than
  writing a second, parallel "find the active vendor" query.
- Card logo uses the **color** logo (`_lis_pv_logo_color_id`); the B&W upload
  has no consumer yet in either shortcode. Brief doesn't specify where B&W is
  used — flagging in case a print/single-color placement is intended later.

### Known limitations (carried over, unchanged)

Still no shortcode auto-injection into Directorist pages (out of scope for
v1), no front-end submission form, no WooCommerce integration. Ticker
placement (Open Decision #4) is still unresolved — the shortcode exists but
where it actually goes on the live site hasn't been decided.

---

## [0.2.0] — 2026-07-27 — Category-lock enforcement

### What this version adds

Build order step 2: the rule the whole feature depends on — only one `active`
vendor per category at a time — now enforced server-side, not just left to
admin discipline in the UI.

### Added

- `includes/enforcement.php`: `lis_directory_get_active_vendor_for_category()`
  and `lis_directory_get_active_conflict()`. Kept as standalone helpers rather
  than inlined in the meta box save, since the front-end submission form (step
  5) will reuse `lis_directory_get_active_vendor_for_category()` to hide/
  waitlist categories that are already taken.
- The "Vendor Details" save handler (`includes/meta.php`) now checks for a
  conflict whenever the submitted status is `active`. If another vendor in any
  of this vendor's assigned categories is already `active`, the status change
  is **rejected** — the existing status is left untouched — and an admin
  notice on the next page load names the conflicting vendor with a link to
  edit it.

### Technical decisions

- Conflict state is passed across the redirect via a short-lived (45s)
  per-user transient (`lis_pv_conflict_{user_id}`), read once by
  `lis_directory_conflict_notice()` on `admin_notices` and immediately deleted.
  Simpler than hooking `redirect_post_location` to carry a query arg, and works
  the same whether the save came from Publish, Update, or Quick Edit.
- Conflict check runs against **all of a vendor's assigned categories**, since
  the taxonomy UI is the default hierarchical checkbox picker (a vendor isn't
  restricted to exactly one category at the data-model level, even though the
  Preferred Vendor Program's real-world use is one category per vendor).
- Enforcement only fires when the *incoming* status is `active`. Per the
  brief: pending submissions never block a category — only an actual `active`
  vendor does. Setting a vendor to `pending`/`expired`/`rejected` is never
  blocked, including moving an existing active vendor out of `active`, which is
  the intended way to free up a category for someone else.
- Known race condition, accepted for this version: two simultaneous
  wp-admin saves attempting to activate different vendors in the same category
  could both pass the conflict check before either write lands, since there's
  no DB-level lock. Not worth the complexity for an admin-only, low-concurrency
  editing surface; revisit only if the front-end submission form (step 5) ever
  lets an approval action trigger this path at meaningful concurrency.

### Known limitations (carried over, unchanged)

Same as v0.1.0 — no shortcodes, no front-end submission form, no WooCommerce
integration. See the v0.1.0 entry below for full details and open-decision
status.

---

## [0.1.0] — 2026-07-27 — Scaffold: CPT + admin fields

### What this version is

Step 1 of the Preferred Vendor Program build order: the data model and an admin
editing screen only. No enforcement logic, no shortcodes, no front-end submission
form yet — those are v0.2.0+.

### Added

- Custom post type `lis_preferred_vendor` (`includes/cpt.php`). Not public — no
  single/archive templates, admin-managed only. Deliberately its own CPT, not a
  hook into Directorist's `at_biz_dir`, to stay decoupled from Directorist's
  internal slugs/hooks.
- Taxonomy `lis_vendor_category`, hierarchical, mirrored/independent (does not
  read Directorist's `at_biz_dir-category` terms directly). Chosen over reading
  Directorist's taxonomy because it stays resilient if Directorist is ever
  removed or its taxonomy slug changes; trade-off is manual upkeep to keep the
  two lists in sync — revisit if that becomes painful in practice.
- "Vendor Details" meta box (`includes/meta.php`) with all fields from the build
  brief: tagline, link URL, logo (color) upload, logo (B&W) upload, status
  (pending/active/expired/rejected), term start/end dates, WooCommerce
  order/subscription ID. Logo uploads use the WP media library picker
  (`assets/js/admin.js`), restricted to PNG/JPEG in the media frame query (not yet
  server-side validated — see Known limitations).
- Admin list-table columns for category, status, and term end
  (`includes/admin.php`).

### Technical decisions

- All new post meta is registered with `show_in_rest => false`. Nothing here
  needs REST access yet (no front-end submission, no shortcodes); revisit once
  the front-end submission form (build order step 5) needs to read/write meta
  from outside wp-admin.
- Business name uses the CPT's native `post_title` rather than a separate meta
  field.

### Known limitations (by design, deferred to later versions)

- **No category-lock enforcement yet** (build order step 2) — nothing currently
  stops two `active` entries in the same category.
- **No shortcodes yet** (`[lis_preferred_vendor_card]`, `[lis_preferred_vendor_ticker]`
  — steps 3–4).
- **No front-end submission form** (step 5) — vendors are entered by hand in
  wp-admin for now.
- **No WooCommerce integration** (step 6) — blocked on Open Decision #1 (confirm
  WooCommerce Subscriptions is actually installed/licensed) plus Open Decision #2
  (vendor account requirement — **resolved: requires a WordPress account**).
- Logo upload only restricts file type via the media library query argument;
  real server-side mime-type/size validation (called out in the brief as
  important because of SVG XSS risk) isn't built yet since there's no public
  upload surface in this version — needs to land before the front-end
  submission form ships in v_next, since anonymous/logged-in vendor uploads
  will be the actual attack surface.
- Not yet tested on staging or deployed.

### Open decisions status

1. WooCommerce one-time vs. subscription — **still open**, blocks step 6.
2. Vendor WordPress account requirement — **resolved**: requires an account.
3. Category taxonomy source — **resolved**: mirrored/independent taxonomy.
4. Ticker placement — **still open**, needed before step 4 ships to production.
