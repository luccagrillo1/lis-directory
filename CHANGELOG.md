# LIS Directory — Detailed Changelog

All notable changes to the LIS Directory plugin. Format inspired by [Keep a Changelog](https://keepachangelog.com/).
For each version: user-facing changes, files touched, data model deltas, technical decisions, and known limitations.

This file is the authoritative project history.

---

## [0.36.0] — 2026-08-09 — Showcase categories mirror the directory categories

New `includes/vendor-category-sync.php`. The Vendor Showcase taxonomy
(`lis_vendor_category`) is now kept mirrored to `lis_listing_category` by slug:
`created/edited_lis_listing_category` create/rename the matching vendor category;
`delete_lis_listing_category` removes it (only if no vendor holds that slot). A
one-time `admin_init` reconcile mirrors existing categories, and
`created_lis_vendor_category` tops up the Showcase product so a new category is
immediately buyable. Helpers `lis_directory_is_listing_category_showcase_taken()`
and `_vendor_category_for_listing_category()` support the next step (Showcase
using the listing's already-selected category with an occupied warning).

**Files:** includes/vendor-category-sync.php (new), lis-directory.php.

---

## [0.35.4] — 2026-08-09 — Fix hidden logo/business-card uploads

The photo dropzone visually hides its file input via `.lis-listing-panel input[type="file"]`,
which also hid the Vendor Showcase logo and business-card file inputs (no visible
"choose file"). Scoped the hide to `#lis_listing_photos` only; other uploads now
render as normal visible file inputs.

**Files:** assets/css/listings.css, lis-directory.php, readme.txt.

---

## [0.35.3] — 2026-08-09 — "No set hours" toggle

Business Hours step now leads with a "No set hours (by appointment / not
applicable)" checkbox on both the add-listing wizard and the edit form (and the
admin Business Hours metabox). Checking it hides the day grid (JS) and, on save,
sets `_lis_listing_no_hours` and clears every hours meta — so a by-appointment
business (insurance agent, consultant) isn't forced to invent hours, and any
hours Google prefilled get wiped. The public display already omitted the hours
box + open/closed badge when no hours are set, so no display change was needed.

**Files:** includes/listings-hours.php (meta + metabox toggle + save), includes/listings-submission.php
(wizard toggle + save), includes/listings-edit.php (edit toggle), assets/css/listings.css,
lis-directory.php, readme.txt.

---

## [0.35.2] — 2026-08-09 — Claim: button, not the whole block, on the listing

Per feedback, the full claim plan-block was too much to sit on every claimable
listing. `lis_directory_render_claim_box()` now returns a compact "Is this your
business? Claim it →" button by default; the button links to `?claim=1`, and
only that focused view renders the plan block (heading + Standard/Featured cards
+ billing toggle + Claim & subscribe / log-in prompt), anchored `#lis-claim`.

**Files:** includes/listings-claim.php, assets/css/listings.css, lis-directory.php,
readme.txt.

---

## [0.35.1] — 2026-08-09 — Hotfix: single-listing fatal

The claim-box include added to templates/single-listing.php in 0.35.0 put the
closing `}` after a `//` line comment, so PHP treated the brace as commented
out — a `syntax error, unexpected token "endwhile"` fatal on every single
listing page. Reformatted onto its own lines. (The linter only scanned
`includes/`, not `templates/`, so it wasn't caught pre-ship; templates are
linted now too.)

**Files:** templates/single-listing.php, lis-directory.php, readme.txt.

---

## [0.35.0] — 2026-08-09 — Claim-by-subscription (#16)

New `includes/listings-claim.php`. A listing flagged `_lis_listing_claimable`
(a "Claim" checkbox metabox on the listing editor) shows a **Claim box** on its
public single-listing page (`lis_directory_render_claim_box()`, injected at the
top of `.lis-listing-main`):

- Not logged in → a log-in prompt (returns to the listing).
- Logged in → a plan picker (Standard / Featured, monthly/annual, reusing the
  `.lis-listing-plan-*` cards + billing-toggle) → posts to `admin-post`
  `lis_directory_claim_listing` → builds the tier checkout URL (reusing
  `lis_directory_build_listing_checkout_url()`), tags it `lis_listing_claim=1`,
  and redirects to checkout.
- On payment, `lis_directory_handle_claim_order()` (order processing/completed)
  transfers **ownership** to the buyer (`post_author`), clears the claimable
  flag, and records `_lis_listing_claimed_by`/`_at`. The existing pricing hook
  publishes + grants the tier in parallel — so claiming is always tied to an
  active subscription, never free.

The claim flag rides cart→order via its own `woocommerce_add_cart_item_data` /
`woocommerce_checkout_create_order_line_item` filters, decoupled from the
pricing plumbing.

**Files:** includes/listings-claim.php (new), lis-directory.php (require +
version), templates/single-listing.php (claim box), assets/css/listings.css,
readme.txt.

**Not payment-tested** — verified the claimable flag, box render, and login
gate; real card claim → ownership transfer must be confirmed live.

---

## [0.34.0] — 2026-08-09 — Directorist migration writer + listing expiration

**Migration (#14).** `includes/directorist-migration.php` gained the real writer:
`lis_directory_migrate_map()` (pure field mapping) + `lis_directory_migrate_one()`
(idempotent create, records `_lis_migrated_from`). Maps, per confirmed data model:
title/content, **post_author (owner preserved)**, status (expired→draft+flag),
`_address/_phone/_email/_website`, `_featured`, **`_expiry_date`→`_lis_listing_expiry`**,
`_directory_type` 1374 → `local-business`, `at_biz_dir-category` names →
`lis_listing_category` (created if missing), and `_listing_prv_img`+`_listing_img`
(JSON) → featured image + `_lis_listing_gallery_ids` (attachments reused, not
re-uploaded). Settings page shows a **mapped dry-run preview** ("what would be
created") + raw source dump, and a guarded **Run migration** button (JS confirm,
nonce, admin-only). Not auto-run. (Business hours `_bdbh` mapping deferred — its
format is complex; noted as follow-up.)

**Expiration (#15).** New `includes/listings-expiry.php`: `_lis_listing_expiry`
meta + a daily cron (`lis_directory_daily_expiry_check`) that sets any published
listing past its expiry to draft and flags `_lis_listing_expired`. `_lis_listing_never_expire`
opts out. Gives migrated expirations (and paid terms) a real effect.

**Files:** includes/directorist-migration.php, includes/listings-expiry.php (new),
includes/woocommerce.php (settings section), lis-directory.php, readme.txt.

---

## [0.33.3] — 2026-08-09 — Directorist migration groundwork (dry-run preview)

First step of moving Directorist listings into `lis_listing`. Added
`includes/directorist-migration.php` + a "Directorist → LIS migration" section on
the Settings page: shows how many `at_biz_dir` listings exist and how many are
already migrated, and a **read-only dry-run preview** that dumps a few real
listings' author, status, taxonomies, and every meta key/value — so the field
mapping (owner, expiration, category, photos, hours, contact, featured) is built
against the actual data model, not assumed. The run handler is a guarded stub;
no listing is written yet. Idempotent design: created listings will record
`_lis_migrated_from` so re-runs skip them.

**Files:** includes/directorist-migration.php (new), lis-directory.php (require +
version), includes/woocommerce.php (settings section), readme.txt.

---

## [0.33.2] — 2026-08-09 — Business-name step: one box, not two

The merged Google flow (0.32.1) still rendered two fields: Google's
`PlaceAutocompleteElement` is a self-contained web component with its own input,
so it sat as a separate "Search for your business" box above the real
business-name field. Rewrote the autocomplete to the new **programmatic** Places
API (`AutocompleteSuggestion.fetchAutocompleteSuggestions` +
`AutocompleteSessionToken`), which lets us hang a **custom suggestions dropdown**
directly off `#lis_listing_business_name` — one box: type, pick a Google match
to auto-fill address/phone/website/hours (with the "✓ Pulled from Google…"
confirmation), or just keep typing to enter it manually. Debounced (220ms, 3+
chars), keyboard nav (↑/↓/Enter/Esc), session-token billing, and a graceful
no-op if the programmatic API isn't available.

**Files:** assets/js/listing-places-autocomplete.js (rewrite), includes/listings-submission.php
(placeholder on the input), assets/css/listings.css (dropdown + confirmation),
lis-directory.php, readme.txt.

---

## [0.33.1] — 2026-08-09 — Featured as a monthly/annual subscription (real annual price)

The Featured tier was a simple one-price product, so the plan step showed the
same amount for monthly and annual. Added a Featured Listing generator (mirrors
the Standard one): a variable **subscription** with Monthly ($19) and Annually
($190) variations, marked `_lis_featured_listing_product`, and generating it
repoints `lis_directory_featured_listing_product_id` at the new product. Draft +
hidden; adjust prices and publish. The old simple "Feature My Listing" product
is left untouched (trashable once the new one is live).

**Files:** includes/listings-pricing.php (generator + handler + hook),
includes/woocommerce.php (settings button), lis-directory.php, readme.txt.

---

## [0.33.0] — 2026-08-09 — Vendor Showcase folded into the one wizard (#13)

Vendor Showcase is now the third selectable plan on the add-listing wizard's
"Choose your plan" step — the whole application lives in the one wizard, no
separate `[lis_preferred_vendor_submit]` page needed.

- **Plan step:** "Vendor Showcase" appears only when its product exists and at
  least one category slot is open. Selecting it reveals inline fields — the open
  **category slot** (taken categories aren't listed), **tagline**, **logo**
  (transparent PNG, required for this tier), and an optional **business card**.
  A small JS toggles these fields + their `required` state with the plan choice.
- **Submit:** creates the `lis_listing` as a draft (as the other tiers do) *and*
  a **pending** `lis_preferred_vendor` entry linked to it (`_lis_pv_listing_id`,
  `_lis_pv_link_url` = the new listing's permalink, tagline/logo/card/category/
  contact-email reusing the existing `lis_directory_handle_logo_upload` /
  `_business_card_upload`), then hands off to the **category-scoped subscription
  variation** at checkout. Logo/category are validated server-side.
- **Payment:** the order-complete hook publishes the listing and **activates**
  the vendor (`set_vendor_status('active')`, guarded by the category-conflict
  check), storing the subscription id in `_lis_pv_wc_order_id` so the existing
  cancelled/expired handler can expire it later. Exclusivity holds because a
  taken category's variation is out of stock → `build_listing_checkout_url()`
  returns '' for it and it isn't offered.
- **Enforcement:** `lis_directory_is_valid_directorist_url()` now also accepts a
  published `lis_listing` (not just Directorist `at_biz_dir`), so admin
  re-approval of a merged-flow vendor isn't blocked.

The standalone `[lis_preferred_vendor_submit]` form and its post-checkout
thank-you link still exist as a fallback for any direct product purchase, but
the wizard is now the path.

**Files:** includes/listings-submission.php (showcase card + fields + JS + vendor
creation on submit), includes/listings-pricing.php (showcase tier recognition,
category-variation resolver, showcase checkout URL, order-complete activation),
includes/enforcement.php (accept lis_listing), assets/css/listings.css,
lis-directory.php, readme.txt.

**Not payment-tested** — verified the plan card, field reveal, and validation
paths; real card checkout + auto-activation must be confirmed live.

---

## [0.32.1] — 2026-08-09 — One merged business-lookup flow (no Google/manual fork)

Removed the intro fork panel ("How do you want to add your listing? Find it on
Google / Enter details manually"). The wizard now opens directly on the
business-name step, and the Google Places search box is always shown there:

- Start typing → pick your business from Google → it fills business name,
  address, phone, website, and hours; an inline **confirmation** ("✓ Pulled from
  Google: address, phone, website, hours. Everything's editable…") makes it
  obvious what was prefilled and that it's all editable.
- Ignore the search and just type → everything stays manual, no path to choose
  up front.

This works because the autocomplete script already inserted its search box
immediately whenever a form had no fork panel (that's how [lis_listing_edit]
always behaved) — removing the fork panel from [lis_listing_submit] makes both
forms behave the same. The address/phone/website/hours steps now always show
(previously skipped on the Google path), so a Google-filled listing is reviewed
step by step and any field can be overridden. The old `data-google-fillable`
attributes are now inert but harmless.

**Files:** includes/listings-submission.php (fork panel removed), assets/js/listing-places-autocomplete.js
(always-insert + confirmation), assets/css/listings.css (confirmation style),
lis-directory.php, readme.txt.

---

## [0.32.0] — 2026-08-09 — Unified paid submission: plan step → checkout → auto-publish

Implements the all-paid model (#4b–#4d) for the Standard and Featured tiers:

- **#4b — plan step.** `[lis_listing_submit]` gains a final "Choose your plan"
  panel: a Monthly/Annually toggle and one card per tier whose product exists
  (Standard, Featured), each showing the live price for the selected billing
  (JS swaps month/year). Plan choice is required. Vendor Showcase is linked from
  here (its exclusive per-category flow stays separate — full merge is later).
- **#4c — submit → checkout.** When the chosen tier's product is published and
  purchasable, the listing is created as a **draft** ("awaiting payment"),
  tagged `_lis_listing_pending_tier`/`_billing`, and the buyer is redirected to
  WooCommerce checkout with that product/variation in the cart (tagged with the
  listing id, reusing the existing Featured add-to-cart→order-item pattern,
  generalised to all tier products).
- **#4d — order complete.** `woocommerce_order_status_processing|completed` now
  **auto-publishes** the draft listing for any tier product and grants Featured
  when that's the tier; `woocommerce_thankyou` shows a post-payment confirmation
  ("…is now live in the directory", View my listing / Browse).

**Safety fallback:** if the tier's product isn't published/purchasable yet
(they're generated as drafts), `lis_directory_build_listing_checkout_url()`
returns '' and submission falls back to the **original free pending** flow — so
the live form keeps working while prices/publishing are still being set up.

Standard placeholder price set to $9/mo · $90/yr.

**Files:** includes/listings-pricing.php (tier resolver, checkout URL, generalised
cart tag + order-publish + thank-you), includes/listings-submission.php (plan step +
submit branch), assets/css/listings.css (plan cards), lis-directory.php, readme.txt.

**Not payment-tested** — no real transaction was run. Verified: plan step renders,
required-plan gating, and the free fallback. Real card checkout + auto-publish
must be confirmed live once the products are published. Vendor Showcase full
in-wizard merge (its logo/tagline/business-card + category slot) is still to do.

---

## [0.31.13] — 2026-08-09 — Standard Listing product (base paid tier) — groundwork for the all-paid unified checkout

Decision (with Lucca): every listing becomes paid, all three tiers hand off to
WooCommerce checkout at the end of the wizard, and a listing auto-publishes when
its order completes. Featured (#6598) and Vendor Showcase (generated) products
already exist; the missing piece was a product for a plain Standard listing.

This version adds that: a "Standard Listing product" generator on the LIS
Directory Settings page — a variable **subscription** with Monthly/Annually
billing variations (placeholder $15/mo · $150/yr the admin edits in WooCommerce),
no category exclusivity. Marked with `_lis_standard_listing_product` meta and
found via `lis_directory_get_standard_listing_product_id()`; idempotent top-up
like the Showcase generator. Created as a hidden draft to price + publish.

This is groundwork only — the wizard plan-selection step, the submit→checkout
handoff, and the order-complete auto-publish come next (tracked as #4b–#4d).

**Files:** includes/listings-pricing.php (generator + setting), includes/woocommerce.php
(settings-page button), lis-directory.php (version), readme.txt.

---

## [0.31.12] — 2026-08-09 — Thank-you screen after submit

A successful `[lis_listing_submit]` used to show a green success banner on top
of the still-rendered wizard (so the whole form sat there under it). It now
renders a dedicated, centered thank-you screen — checkmark, "Thanks — your
listing is in!", a short review-queue message, and Submit-another / Browse-the-
directory buttons — and returns early so the form isn't shown. Same PRG redirect
(`?lis_listing_submitted=1`) as before; only the success view changed. The edit
form's "changes saved" banner is unchanged (a banner is right for an edit).

**Files:** includes/listings-submission.php, assets/css/listings.css,
lis-directory.php (version), readme.txt.

---

## [0.31.11] — 2026-08-09 — Suggest-a-feature with admin veto

The Features step of the add-listing / edit-listing forms now has a "Don't see
it? Suggest one" text field. A suggestion is turned into a real
`lis_listing_feature` term immediately (so it can attach to the listing) but
flagged pending via `_lis_feature_pending` term meta.

Pending suggestions are kept **out of every public surface** until approved:
the submission/edit checkbox lists, the search widget's Features filter, and
the public single-listing feature list all use a new
`lis_directory_get_public_feature_terms()` / `lis_directory_filter_public_features()`
that exclude pending terms. The edit form still shows a listing's own pending
suggestion (so editing doesn't silently drop it).

**Admin veto/approve:** the Features taxonomy screen (Listings → Features) gets
a Status column ("● Pending review — suggested by X — Approve" vs "● Live") and
an "Approve suggestion" row action (nonce-checked `admin-post` handler clears
the pending meta). Deleting the term is the veto. New terms are created case-
insensitively deduped against existing ones; max 3 suggestions per submit, 40
chars each.

**Files:** includes/listings-features.php (new), lis-directory.php (require +
version), includes/listings-submission.php (save + form), includes/listings-edit.php
(form + keep-own-pending), includes/listings-search.php (filter list),
templates/single-listing.php (hide pending), assets/css/listings.css, readme.txt.

---

## [0.31.10] — 2026-08-09 — Search on per-type landing grids + fix archive width

Two parity/layout fixes from live review:

1. **Search box on the per-type landing pages.** `[lis_listing_grid type="..."]`
   (the Local Business / Real Estate / Job Listings landing grids) now renders
   the keyword + category search box above the grid by default, scoped to that
   directory (so no "All Directories" box) — matching what Directorist's "All
   Listings" page has. `search="no"` hides it; `search="yes"` forces it on for
   an all-types grid. No page edits needed — behaviour is baked into the
   shortcode. The form submits to the archive with the filters applied.

2. **Archive width.** Astra's `.ast-container` is a flex row, so the block
   `.lis-listing-archive` shrank to its content width and `margin:0 auto` then
   left ~100px of dead space on each side (in a 1200px content area it rendered
   at ~965px). Added `width:100%; flex:1 1 auto` so it fills the theme content
   width instead of sitting in a narrow centered column.

**Files:** includes/listings-account.php, assets/css/listings.css,
readme.txt, lis-directory.php (version).

---

## [0.31.9] — 2026-08-09 — Local-Directory-scoped search, bigger fields, rectangular business cards

Three small polish items from live review:

1. **Directory-scoped search shortcode.** `[lis_listing_search]` gained a
   `directory="..."` attribute (any key from `lis_directory_get_listing_types()`
   — `local-business`, `real-estate-sale`, `real-estate-rent`, `job-listing`).
   When set, the "All Directories" `<select>` is replaced by a hidden `lis_type`
   field, so the widget is a single-directory search box. `[lis_listing_search
   directory="local-business"]` is the Local-Directory-only variant that was
   requested. No new shortcode name — same one, opt-in attribute.

2. **Slightly larger search fields.** Input/select padding 10→13px vertical,
   font 0.95→1.02em, submit padding bumped to match. Just a touch bigger.

3. **Business-card ticker cards are perfect rectangles.** The bizcard image had
   `border-radius: 6px`; since a business-card image is a full-bleed rectangle,
   rounding clipped the corners and left dead white space. Set to 0.

**Files:** includes/listings-search.php, assets/css/listings.css,
assets/css/vendor-ticker-bizcards.css, readme.txt, lis-directory.php (version).

---

## [0.31.8] — 2026-08-09 — Add-listing photos: live previews + client-side validation (stop the wizard wipe)

Two problems on the "Photos & Video" step of the add-listing / edit-listing
wizard, both reported from live testing:

1. **No thumbnail previews.** The preview container is authored inside a
   `<p>` in the template, but the HTML parser hoists a block-level `<div>`
   out of a `<p>`, so it ended up as a sibling — and the JS looked for it via
   `input.closest('p').querySelector(...)`, which then returned `null`, so
   `renderPreview()` bailed and no thumbnails ever showed. Fixed by scoping
   the lookup to the whole `.lis-listing-panel`, and by moving the preview
   `<div>` out of the `<p>` in both templates so the markup is valid.

2. **An oversized photo wiped the entire wizard.** A photo over 2MB (or a
   non-PNG/JPG) was only caught server-side, which reloads the page — and a
   browser can never repopulate a file input, so the whole multi-step form
   (everything already typed) was lost, dumping the person back at step 1.
   Now photos are validated the instant they're picked or dropped: bad files
   are rejected before they can ever be submitted, with an inline message
   ("Skipped 'x.jpg' — it's 3.4MB, over the 2MB limit…"), the count is capped
   at 6, and `input.files` is rebuilt (via DataTransfer) from just the valid
   ones. The destructive server round-trip no longer happens for this case.

Also revokes each preview's object URL on load to avoid leaking blob URLs.

**Files:** assets/js/listing-photos.js, includes/listings-submission.php,
includes/listings-edit.php, assets/css/listings.css, lis-directory.php (version).

**Known limitation:** other (text-field) server-side validation errors still
reload the wizard to step 1 — addressed separately when the submission flow
is unified around the scope picker.

---

## [0.31.7] — 2026-08-09 — Single listing gallery adapts to image count

Continued page review: a listing with a single photo showed it as a lone
quarter-width thumbnail (the strip was a fixed 4-column grid). The gallery
now uses an auto-fit grid, and a single image fills full-width as a proper
hero (380px tall); two or three photos split the row evenly.

**Files:** assets/css/listings.css, lis-directory.php (version).

---

## [0.31.6] — 2026-08-09 — Single listing: balanced two-column layout

Reviewing the new directory pages against Directorist: a single listing
with no business hours left its right sidebar empty, so the whole page sat
in a narrow left column with a big blank gap on the right. Moved the
contact box (address/phone/website/email) and the map out of the main
column into the sidebar, so the two-column layout is reliably filled and
the description stays a readable width. Sidebar is now sticky, its map is
taller to suit the narrower column, and if a listing genuinely has no
contact/map/hours the layout collapses to a single centered column instead
of leaving dead space (via :has()).

**Files:** templates/single-listing.php, assets/css/listings.css, lis-directory.php.

---

## [0.31.5] — 2026-08-09 — Vendor Showcase generator: initial stock sync

Bug found in live testing: the Vendor Showcase get_variation lookup showed
taken categories (e.g. Lodging, already held by an active vendor) as "1 in
stock" and therefore buyable. The generator created every variation
in-stock, and categories taken *before* the product existed never fired
the stock-sync hook against these new variations. Fix: after building/
repairing variations, call lis_directory_sync_stock_for_category() for every
category, so taken ones go to 0 / out-of-stock. Re-run the generator to
apply to an existing product.

**Files:** includes/woocommerce.php, lis-directory.php (version).

---

## [0.31.4] — 2026-08-09 — Vendor Showcase: $0 test-spot dev tool

Adds a "Create $0 test spot" button on the settings page. A real Vendor
Showcase purchase is a subscription, which requires a saved card at
checkout — awkward to verify end-to-end without real billing. This button
creates a disposable $0, non-subscription variable product whose single
variation is tagged (`_lis_pv_category_term_id`) to the first vendor
category with no active vendor. Buying it is free and needs no payment
method, so the pay -> thank-you submission-link -> category-lock chain can
be exercised for real. Throwaway: delete the product, its order, and any
test vendor afterward.

**Files:** includes/woocommerce.php, lis-directory.php (version).

---

## [0.31.3] — 2026-08-09 — Vendor Showcase generator: reliable category attribute

Follow-up to 0.31.2. The generated variations all carried the correct
`_lis_pv_category_term_id` tag (so stock-sync and the thank-you submission
link worked), but the WooCommerce **Vendor Category** *display* attribute
was empty on most variations, so the product page's category dropdown read
"Any" and couldn't resolve to a specific variation. Cause: term names like
"Arts & Entertainment" come back HTML-encoded, and `set_attributes`' fuzzy
option-matching dropped anything with `&`, em dashes or accents.

Fix (includes/woocommerce.php):
- Decode entities once (`html_entity_decode`) and use the decoded name for
  both the parent attribute options and each variation's value.
- Added a **repair pass** that writes `attribute_vendor-category` /
  `attribute_billing` post meta directly from each variation's own
  category tag + billing marker — guaranteeing it equals the parent
  option. Because it's driven by the marker meta, **re-running the
  generator repairs a product built by 0.31.2** (no need to delete and
  rebuild).

**Files:** includes/woocommerce.php, lis-directory.php (version).

---

## [0.31.2] — 2026-08-09 — Vendor Showcase product generator

Lucca settled the monetization model on **upgrades, not tiers**: a base
listing (the existing front-end submission), a paid **Featured** upgrade
(the existing "Feature My Listing" product), and a **Vendor Showcase**
(preferred-vendor) upgrade. This ships the last piece.

**New: one-click Vendor Showcase generator** (includes/woocommerce.php,
on the LIS Directory settings page). Building the showcase by hand meant a
variable WooCommerce Subscription product with a variation per vendor
category × billing period — ~50 variations, each needing the
`_lis_pv_category_term_id` tag and stock-of-1. A button now does it:

- Creates a single **"Vendor Showcase"** product
  (`WC_Product_Variable_Subscription` when WooCommerce Subscriptions is
  active, else `WC_Product_Variable`), as a **draft**, catalog-hidden,
  virtual, sold-individually.
- Attributes: **Vendor Category** (every `lis_vendor_category` term) ×
  **Billing** (Monthly / Annually).
- One variation per (category, period): **$100/mo** or **$1,000/yr**
  (annual = 2 months free), `manage_stock` on with **stock = 1** so a
  category can only be held by one vendor, tagged with
  `_lis_pv_category_term_id` (drives the existing stock-sync +
  thank-you-page submission link) and a `_lis_pv_billing_period` marker.
- **Idempotent** — keyed on that marker, so re-running only fills gaps
  (e.g. after a new category is added) and never overwrites prices edited
  by hand. Button label flips to "Top up…" once the product exists, and
  the page shows the current product + status.

Nothing is charged or published automatically — the product stays a draft
until an admin publishes it.

**Files:** includes/woocommerce.php, lis-directory.php (version).

**Known limitations:** the generator sets flat $100 / $1,000 across every
category; per-category price overrides are done by hand afterward.
Publishing, and pointing the vendor-recruitment page at the product, are
still manual.

---

## [0.31.1] — 2026-08-08 — Listings archive card polish

Lucca's ask, from a screenshot of the live Listings page: "fix the view
icons and move the category to around the pink area" (top-right of each
card).

**Grid/List/Map view-toggle icons** (assets/css/listings.css): the SVG
icons and the correct `width: 38px` button sizing were both deployed, but
the buttons still rendered full-width and empty on the live site. Root
cause: the Astra theme styles every `<button>` globally (padding,
box-shadow, appearance), which inflated the toggle buttons and hid the
inline SVGs. Fixed by resetting `padding`/`min-width`/`box-shadow`/
`appearance` on `.lis-listing-view-btn`, adding a one-level-deeper
`.lis-listing-view-toggle .lis-listing-view-btn` block so those resets
out-specify the theme, and pinning the SVG to `18×18` with
`stroke: currentColor` (so it's grey when inactive, white on the green
active button).

**Category badge moved to the card's top-right**
(templates/archive-listing.php + assets/css/listings.css): the category
badge used to sit in the meta-row below the title; it now lives in a new
`.lis-listing-card-title-right` group inside the title-row, pushed to the
right edge via `margin-left: auto` (the title-row is already flex +
space-between). The meta-row (Sold/Featured/Popular/Verified/rating) is
now only rendered when it actually has content, so cards whose only badge
was the category — the common case — no longer leave an empty gap under
the title.

**Files:** assets/css/listings.css, templates/archive-listing.php,
lis-directory.php (version).

**Known limitation:** unverified in a live browser at ship time — Claude
in Chrome was disconnected when this shipped, so the theme-specificity
fix is reasoned from the deployed CSS/HTML, not confirmed on-screen. If
the toggle buttons are still inflated after deploy, the reset block needs
another specificity bump (add `.lis-listing-toolbar` in front).

---

## [0.31.0] — 2026-08-06 — Vendor Showcase: business card upload + new ticker style

Lucca's ask: a new Vendor Showcase display where the "panels" are each
vendor's own business card image, uploaded during onboarding.

**New optional upload**, both places a vendor's fields get set:
- Front-end submission form (`[lis_preferred_vendor_submit]`,
  includes/submission.php): new "Business Card (optional)" file field,
  PNG or JPEG, 2MB cap — deliberately optional (unlike the required
  logo) since this is a brand-new field and shouldn't block anyone
  from completing signup. Handled by a new
  `lis_directory_handle_business_card_upload()`, same validation shape
  as the existing logo handler (real-image check via getimagesize(),
  restricted upload_mimes) but PNG-or-JPEG rather than PNG-only - this
  is a photo/scan of an already-designed printed card, not something
  that ever needs the logo's CSS black/white recolor trick.
- Admin meta box ("Vendor Details", includes/meta.php): new "Business
  Card" row reusing the same media-picker field UI as Logo
  (`lis_directory_render_logo_field()`, now takes an optional
  `$description` override so its help text isn't wrong for a
  non-logo field) - so staff can add or update a vendor's business
  card themselves, including backfilling one for a vendor who signed
  up before this field existed.
- New meta key `_lis_pv_business_card_id`, registered alongside the
  existing vendor meta fields.

**New ticker style**: `[lis_preferred_vendor_ticker style="business-cards"]`
shows each vendor's uploaded card image directly - no constructed
name/tagline overlay, since the card graphic already has whatever
design the vendor wants on it. Sized to standard US business card
proportions (3.5in x 2in = 1.75:1, displayed at 350x200px,
`object-fit: contain` so nothing gets cropped). Vendors with no
business card uploaded are skipped, same pattern as vendors with no
logo in the existing `style="logos"` ticker.

Nothing about the existing `style="cards"` (default) or `style="logos"`
tickers changed - this is purely additive, a third option alongside
them.

**Known limitation**: none of the existing real vendor entries have a
business card uploaded yet (the field is brand new) - the new ticker
style will show nothing until at least one vendor has one added, either
through a fresh submission or an admin backfill via the meta box.

---

## [0.30.9] — 2026-08-06 — Ticker vendor cards: match the Single Card's slim proportions

Lucca's feedback on the live "Preferred Vendor Preview" page: the
"Ticker — all active vendors" cards were much taller than the standalone
"Single card" example, and he wanted the ticker to have that same
slimmer look.

Both use the exact same card markup
(`lis_directory_render_vendor_card_html()` - one function, shared by
both the single-card shortcode and the ticker's "cards" mode), so the
height difference was pure CSS: `vendor-ticker.css` constrained each
ticker card to `width: 320px`, while the standalone card
(`vendor-card.css`) only caps at `max-width: 420px`. At 320px, a longer
vendor name or tagline (e.g. "Bonner Mobile Detailing" / "Professional
mobile detailing, wherever you are.") wraps onto 2-3 lines, and since
`.lis-pv-card-inner` is a flex row (logo left, text right), that extra
wrapped height stretches the whole card taller - exactly what showed up
live. Widened `.lis-pv-ticker .lis-pv-card` to `420px`, matching the
Single Card's own width, so ticker cards get the same amount of room
for text and wrap (or don't) exactly the same way.

---

## [0.30.8] — 2026-08-05 — Removed Compare Listings

Removed by request. Was one of the original Directorist-parity pieces
(DIRECTORIST_PARITY_PLAN.md phase 3) - a cookie-backed "+ Compare"
button on every listing card/single page, feeding a `[lis_listing_compare]`
side-by-side comparison table (up to 4 listings, no login required,
same as Directorist's own).

Removed entirely rather than just hidden:
- `[lis_listing_compare]` shortcode and its render function
- The "+ Compare" button from the archive card, single-listing header,
  and the shared `lis_directory_render_listing_card()` helper (used by
  Author Profile and the `[lis_listing_grid]` shortcode)
- `assets/js/listing-compare.js` (client-side cookie list logic) - file
  deleted
- Its enqueue path (`lis_directory_enqueue_compare_assets()`, the
  `wp`-hooked `lis_directory_enqueue_compare_on_listing_views()`) and
  the cookie reader (`lis_directory_get_compare_ids()`)
- All `.lis-listing-compare-*` CSS

`includes/listings-account.php`'s top docblock updated - it now
describes two remaining pieces (Author Profile, Dashboard) instead of
three. No other feature depended on Compare Listings, so this was a
clean removal with no follow-on changes needed elsewhere.

---

## [0.30.7] — 2026-08-05 — Correction: this site's Cart/Checkout are classic, not Blocks

0.30.6 misdiagnosed the cause: it assumed this site's Cart/Checkout use
WooCommerce Blocks (registered Store API extension data + a
`registerCheckoutFilters` front-end script) because the theme's styling
looks modern. Live-tested the actual add-to-cart flow again and
inspected the real DOM: `.woocommerce-cart-form`, `table.shop_table`,
`form.woocommerce-checkout`, `#order_review` are all present on both
`/cart/` and `/checkout/` - these are 100% classic-template markers, no
`wp-block-woocommerce-cart`/`-checkout` wrapper anywhere. The Store API
registration and its matching JS filter never had any effect and are
removed (`assets/js/feature-listing-cart-filter.js` deleted).

Separately, the ORIGINAL classic `woocommerce_get_item_data` hook
(`lis_directory_show_listing_in_cart_item_data()`, from the initial
Pricing Plans build) also wasn't rendering - not because it's broken,
but because inspecting the live cart HTML showed *neither* cart line
(not just "Feature My Listing") has the `<dl class="variation">` item-
data block at all. This theme's cart/checkout templates simply don't
call `wc_get_formatted_cart_item_data()` for any product.

Real fix: `woocommerce_cart_item_name` and `woocommerce_order_item_name`
filters, which wrap the product name text/link itself rather than a
separate optional meta block - confirmed present and rendering on both
cart rows in the live DOM. These now append " — Featuring: <listing
name>" to "Feature My Listing" everywhere the theme actually shows an
item name: cart, checkout review, order-received, admin order view, and
emails. The old `woocommerce_get_item_data` hook is left in place
(harmless, would work automatically if the cart template ever changes
to one that does render item data).

---

## [0.30.6] — 2026-08-03 — Show which listing "Feature My Listing" is for, in the cart

Built and live-tested the Featured Listing payment flow end to end this
session: created the "Feature My Listing" WooCommerce product ($19,
virtual, hidden from shop browsing), assigned it in LIS Directory
Settings, and confirmed add-to-cart works. Found one gap while testing:
the cart line just said "Feature My Listing" with no indication of
*which* listing was being paid for - functionally fine (the order-item
meta linking it to the right listing was always correct, see
`lis_directory_persist_listing_id_to_order_item()`), but confusing for
someone with more than one listing.

Root cause: this site's Cart/Checkout use WooCommerce Blocks, not the
classic templates, so `lis_directory_show_listing_in_cart_item_data()`'s
classic `woocommerce_get_item_data` filter (added in the original
Pricing Plans work) is silently ignored by Blocks - it's still there for
any classic-template context, but does nothing here.

Fixed properly rather than working around it: registered the listing
name as Store API extension data
(`lis_directory_register_feature_listing_store_api_data()`, hooked on
`woocommerce_blocks_loaded`), and added a matching front-end filter
(`assets/js/feature-listing-cart-filter.js`, using
`wc.blocksCheckout.registerCheckoutFilters`'s `cartItemName` filter) that
appends "— Featuring: <listing name>" to the cart line. This is the
supported extensibility path for WooCommerce Blocks; the classic filter
was never going to work here no matter how it was written.

---

## [0.30.5] — 2026-08-03 — Pin progress bar + section label to the top

Follow-up to 0.30.4: the progress bar and each panel's small section
label (e.g. "GET STARTED") were being vertically centered along with
everything else, when they should stay pinned at the top like a normal
page header, with only the question/hint/field/controls block below
them centering in the remaining space.

Restructured `listing-form-wizard.js` so each panel now wraps
everything except the section label in a new `.lis-listing-wizard-panel-body`
div (question, hint, the real field markup, and Back/Continue), applied
to both the regular panels and the review panel. CSS chains `flex: 1`
from `.lis-listing-submit-form` down through `.lis-listing-wizard` →
`.lis-listing-wizard-stage` → the active `.lis-listing-wizard-panel`,
with the progress bar and section label as `flex-shrink: 0` and
`.lis-listing-wizard-panel-body` as `flex: 1; justify-content: center`.

---

## [0.30.4] — 2026-08-03 — Correction: vertical, not horizontal, centering

0.30.3 misread the ask as horizontal centering (moving the whole block
to the middle of the page width, center-aligning its text). What was
actually wanted was vertical centering - the wizard block sitting
centered in the empty space between the breadcrumb and the footer,
staying left-aligned as before. Reverted the `margin: 0 auto` on
`.lis-listing-submit-form` and the `text-align: center` on the
section/question/hint text. Added `.entry-content:has(.lis-listing-submit-form)
{ display:flex; flex-direction:column; justify-content:center;
min-height:60vh; }` - scoped via `:has()` to only the page(s) that
actually contain this form, so ordinary long-form pages/posts are
unaffected.

---

## [0.30.3] — 2026-08-03 — Center the onboarding wizard on the page

The wizard block (`.lis-listing-submit-form`) was left-anchored inside
its much wider page container, so on wide viewports it hugged the left
edge with a large empty gap on the right - especially noticeable now
that the page banner/title is disabled and there's nothing else on the
page to anchor against. Added `margin: 0 auto` to center the whole
900px block. Also center-aligned the small section label, the big
question heading, and the hint text (`.lis-listing-wizard-panel-section`,
`-panel-question`, `-panel-hint`) so each step reads as a centered
Q&A card rather than left-aligned copy sitting inside a centered box.
Controls (Back/Continue) and multi-field panels (Business Hours, Social
Media) are unaffected - only the short label/heading/hint lines that
appear identically on every panel.

---

## [0.30.2] — 2026-08-03 — Fork panel visual cleanup + layout fixes

Follow-up to Lucca's design feedback on the live Add Listing page:

- Removed the page's H1 title and intro paragraph (done directly on the
  WordPress page/Astra "Disable Banner Area" setting, not plugin code -
  the breadcrumb stays, only the banner/title block is hidden).
- Fixed a layout regression from disabling that banner: the wizard's
  progress bar was left sitting flush against the breadcrumb bar with
  no breathing room. Added `padding-top` to `.lis-listing-submit-form`.
- Tightened `.lis-listing-wizard-progress`'s bottom margin (32px to
  14px) so the "GET STARTED" section label reads as part of the
  progress bar instead of a separate floating element.
- Simplified the intro fork cards: each was showing a title plus a
  permanently-visible description paragraph, which read as busy for a
  first screen. Now each card shows only the title with a small "?"
  info bubble; hovering or focusing it reveals the same explanation
  text in a tooltip. Added `event.target.closest()` guard in
  `listing-form-wizard.js` so clicking the "?" doesn't also trigger
  that card's fork choice.
- Site-wide (via Customizer Additional CSS, not this plugin - applies
  to every page, not just listing pages): footer now sticks to the
  bottom of the viewport on short pages instead of leaving a gap below
  it, using the standard flex-column `#page` / `flex:1 0 auto`
  `#content` pattern.

---

## [0.30.1] — 2026-08-03 — Fix: Submit button was invisible on the review screen

Found via live click-through verification of v0.30.0 in Chrome (search
Google → Starbucks Coffee Company → walked every panel to the review
screen). The real "Submit for Review" / "Save Changes" button element
was present in the DOM and correctly moved into the review panel's
controls, but never had its `display: none` (set at wizard init, when
every button was expected to stay hidden until the review step) cleared
back to visible once it landed there — so on both `[lis_listing_submit]`
and `[lis_listing_edit]`, the review screen rendered with no way to
actually submit. This has been true since the wizard was first rewritten
into the one-question-per-panel format earlier this cycle, so no real
submission could have gone through the new flow at all until now.
One-line fix in `listing-form-wizard.js`: reset `submitWrap.style.display`
to `''` at the same point it's appended into `reviewControls`.

---

## [0.30.0] — 2026-08-03 — Real onboarding-flow rebuild + Google-search-vs-manual fork

### Add Listing is now a real onboarding flow, not a form

Lucca's spec: "progress bar, little section name, big bold questions,
clean input below that, one question per panel, a nice elegant
transition, and a final preview at the end." Rebuilt
`listing-form-wizard.js` from scratch — the v0.28.0/0.29.x sidebar-nav
wizard (numbered step list down the side) is gone entirely. Both
`listings-submission.php` and `listings-edit.php` markup went from 7
grouped `.lis-listing-submit-section` blocks to ~13 flat
`.lis-listing-panel` divs (one question each), carrying
`data-panel-section` / `data-panel-question` / `data-panel-hint` so the
actual copy lives in the PHP templates, not hardcoded in JS. Panels
crossfade sequentially (200ms) rather than all animating at once. A
dynamically-built review panel at the end summarizes every field
(handles TinyMCE, checkboxes, per-day hours, photo counts, selects,
grouped social links) with the real Submit button moved into it.

### New: search Google or enter manually, right at the start

Follow-up ask: fork the flow up front so Google-found businesses skip
straight past the fields Google already knows. `[lis_listing_submit]`
now opens on a choice panel — "Find it on Google" vs "Enter details
manually." Choosing Google keeps the existing Places search (see
0.29.1) but now only inserts it after that choice, and the wizard skips
the Address/Phone/Website/Business Hours panels entirely for the rest
of the walkthrough (marked via `data-google-fillable="true"`) since
Google already filled them — landing the user on Category next instead.
Those fields still show up on the review screen so they can double
check what Google filled in before submitting. Picking "Enter manually"
shows every panel in the original order, no search box at all.
`[lis_listing_edit]` is untouched by the fork (no fork panel, all
panels always shown) since an existing listing already has real values.

### Fixed: submission-form inputs were unstyled and the raw file input was showing

Found while updating the CSS for the above: `assets/css/listings.css`
still styled inputs/selects/the hidden file-input via
`.lis-listing-submit-section`, a wrapper class the panel markup stopped
using entirely earlier this cycle when the wizard rewrite began. Every
text/email/url/time input and select on the live Add Listing and Edit
Listing forms has been rendering completely unstyled (default browser
chrome) and the native file picker for photos wasn't hidden — since
nothing else on the standalone Add Listing page happens to render a
`.lis-listing-submit-section`, this had zero visual signal pointing at
it. Retargeted every rule at `.lis-listing-panel`. Also removed the now
fully-dead sidebar-nav CSS (`.lis-listing-wizard-nav*`,
`-body`, `-steps`, `-progress-label`) and the dead
`.lis-listing-submit-section h3` rules (no more `<h3>` — replaced by
the JS-injected `.lis-listing-wizard-panel-question`).

### Known limitation

If someone picks "Find it on Google" but then backs out without
actually selecting a business, Address/Phone/Website/Hours stay empty
but are still skipped from the walkthrough — they'd need to go back to
`.lis-listing-fork-choice[data-fork-choice="manual"]`'s panel... there's
no in-flow way to switch from Google mode back to manual mode once
chosen (only a page reload). Acceptable for now; revisit if this comes
up in practice.

---

## [0.29.1] — 2026-08-02 — Places autocomplete rebuilt; SurveyMonkey-style typography

### Places autocomplete: rebuilt on PlaceAutocompleteElement

v0.29.0's Places autocomplete used `google.maps.places.Autocomplete`, the
classic widget bound directly onto the existing Business Name `<input>`.
Live-tested it on the actual deployed form (Claude in Chrome) and hit a
real console error: "You're calling a legacy API, which is not enabled
for your project" — `google.maps.places.Autocomplete` was retired for
any Google Cloud project created after March 2025, and this one was
created today. Not a config problem, a hard platform cutoff.

Rebuilt on `google.maps.places.PlaceAutocompleteElement`
(`google.maps.importLibrary('places')`) — a self-contained web component
with its own shadow-DOM input, so it can't attach to an existing plain
`<input>` the way the old class did. Inserted as a separate "Search for
your business (optional)" field above Business Name instead; picking a
result still fills Business Name plus Address/Phone/Website/Hours, same
as originally intended. Field/event names are the new camelCase Places
API (New) shape (`displayName`, `formattedAddress`,
`regularOpeningHours.periods` with `{hour, minute}` instead of the old
`"HHMM"` string), not the old snake_case Places API fields.

### Typography: less form-section, more "question"

Lucca pointed at a SurveyMonkey-style reference (bold single question,
small "Question X of Y" label directly above it, generous whitespace,
no visual clutter). Moved `listing-form-wizard.js`'s progress label from
the top of the whole wizard to sit immediately above each step's own
heading, and restyled `.lis-listing-submit-section h3`: bold sans-serif
instead of italic, larger, non-italic. Sidebar step nav kept as-is for
now (real navigation utility, not something asked to remove) — can drop
it later if the reference was meant literally rather than as a tone cue.

### Files touched

- `assets/js/listing-places-autocomplete.js` (rewritten)
- `assets/js/listing-form-wizard.js` (progress label placement)
- `assets/css/listings.css` (search field styling, heading typography, label styling)

---

## [0.29.0] — 2026-08-02 — Progress bar + Google Places autocomplete

### Progress bar

SurveyMonkey-style thin bar above the wizard sidebar, filled
`(step / total) * 100%`, with a "Step X of Y" label — Lucca's request,
straightforward addition to `listing-form-wizard.js`'s existing
`goToStep()`.

### Google Places autocomplete

Lucca had already enabled Places API and Places API (New) in Google
Cloud (alongside a long list of other Maps Platform APIs enabled at the
same time — only Places API, Places API (New), and Maps JavaScript API
are actually used by anything in this plugin; the rest is harmless but
unused). New `assets/js/listing-places-autocomplete.js`, binds
`google.maps.places.Autocomplete` to `#lis_listing_business_name` on
both `[lis_listing_submit]` and `[lis_listing_edit]`. Picking a real
business from the dropdown auto-fills Address, Phone, Website, and
per-day Business Hours from Google's Place Details response
(`opening_hours.periods`, converted from Google's `{day: 0-6, time:
"HHMM"}` shape into this form's per-weekday `HH:MM` open/close time
inputs). Purely additive — typing a name without picking a suggestion
leaves every field exactly as before.

New shared `lis_directory_enqueue_places_autocomplete()` (in
`listings-submission.php`, called from both forms) loads the Maps
JavaScript API with `libraries=places`, no-ops entirely if no Maps API
key is configured yet.

Also gave the two per-day closing-time inputs actual `id` attributes
(`lis_listing_hours_{day}_close`) — they only had `name` before, which
the autocomplete script needs for reliable targeting, and which the
opening-time inputs already had for their own `<label for>`.

### Files touched

- `assets/js/listing-places-autocomplete.js` (new)
- `assets/js/listing-form-wizard.js` (progress bar)
- `assets/css/listings.css` (progress bar, wizard body wrapper restructure)
- `includes/listings-submission.php` (shared enqueue helper, close-input id)
- `includes/listings-edit.php` (enqueue call, close-input id)

### Known limitation

A business open past midnight (close time on the following calendar
day) isn't handled specially — the close time still lands on the open
day's row, which is what this form's one-shift-per-day model can
represent anyway. Rare enough for local listings that it wasn't worth
a more complex data model.

---

## [0.28.1] — 2026-08-02 — Wizard polish: Submit button on the last step only

First real live-tested fix this session: clicked through the actual
deployed v0.28.0 wizard via the newly-connected Claude in Chrome
extension (navigate, screenshot, fill fields, click Continue/Back — the
whole flow, not just a static screenshot) and caught one redundancy —
the real "Submit for Review" button sat outside the stepped sections, so
it showed on every step alongside "Continue," not just the final one.
`listing-form-wizard.js` now hides it (or its wrapping `<p>`) except on
the last step.

### Files touched

- `assets/js/listing-form-wizard.js`

---

## [0.28.0] — 2026-08-02 — Real multi-step wizard for Add/Edit Listing

The bigger rebuild explicitly deferred in v0.27.0 ("not something to risk
without being able to see it live") — became buildable once Lucca
connected the Claude in Chrome extension mid-session, which finally let
this get visually verified against the real draft page instead of
reasoned about blind.

New `assets/js/listing-form-wizard.js`, applied to both
`[lis_listing_submit]` and `[lis_listing_edit]` automatically (any
`.lis-listing-submit-form` with 2+ `.lis-listing-submit-section`
children gets wizard-ified — no per-shortcode wiring). Deliberately
**not** a real multi-page wizard with server-side partial saves — every
field still lives in the same `<form>` and posts in one request exactly
as before. This only changes which section is visible at a time, driven
entirely client-side:

- Sidebar step list, auto-generated from each section's own `<h3>` text
  (can't drift out of sync if a section is ever renamed/reordered/added).
- Numbered steps, checkmark on completed ones, current step highlighted.
- Back/Continue buttons per step; Continue is gated on that step's own
  `:invalid` fields before advancing (native HTML5 validation).
- Sidebar items are freely clickable regardless of step (jump ahead or
  back anytime) — only Continue-driven linear flow is gated.
- Final backstop on the real submit button: reveals every section right
  before checking `form.checkValidity()`, so a required field on a
  step you jumped past can't silently block submission — if invalid,
  jumps to the offending step and calls `reportValidity()` on it.

### Files touched

- `assets/js/listing-form-wizard.js` (new)
- `assets/css/listings.css` (sidebar/step layout, `.lis-listing-submit-form` widened to fit two columns)
- `includes/listings-submission.php` / `includes/listings-edit.php` (enqueue)

---

## [0.27.0] — 2026-08-02 — Rich-text description, airier form styling

Lucca shared a screenshot of Directorist's own real "Add Listing" flow —
a multi-step wizard with a rich-text editor and clean, underline-input
typography — asking for the submission form to take cues from it. A full
multi-step wizard is a much bigger rebuild (real step navigation, partial
validation per step) than a CSS pass, and not something to risk without
being able to see it live myself — deliberately not attempted here. Took
the two changes that mattered most and were safely scoped:

- **Description is now a real rich-text editor** (`wp_editor()` — WordPress's
  own TinyMCE, `teeny` mode for a simpler toolbar, `quicktags` for the
  Visual/Text tab pair, `media_buttons` off since inline images would be
  redundant with the listing's own photo gallery) instead of a plain
  `<textarea>`, on both `[lis_listing_submit]` and `[lis_listing_edit]`.
  Save handlers switched from `sanitize_textarea_field()` to
  `wp_kses_post()` accordingly — this field can now legitimately contain
  HTML.
- **Section styling reworked** to match the airier, minimal look in the
  reference: no more gray boxed cards — a thin divider between sections,
  italic accent-colored headings (the plugin's existing green, not
  Directorist's own gold — staying consistent with the rest of this
  plugin's palette rather than importing a second accent color), and
  underline-only inputs instead of bordered boxes.

### Files touched

- `includes/listings-submission.php`
- `includes/listings-edit.php`
- `assets/css/listings.css`

### Known limitation

Still could not visually verify — no wp-admin login session for the
draft page. Built carefully from the markup and the reference
screenshot, but another screenshot after this deploys would confirm it.

---

## [0.26.0] — 2026-08-02 — Fixed: Add Listing form was rendering unstyled

### Real bug found and fixed

User feedback: the Add Listing page "looks ass." Root cause:
`lis_directory_render_listing_submission_form_shortcode()`
(`includes/listings-submission.php`) never called `wp_enqueue_style()` for
`listings.css` — every `.lis-listing-submit-*` rule that's existed since
this form was first built has simply never applied on a page that only
has this one shortcode on it (which is exactly the standalone "Add
Listing" page's situation). The form has been raw, completely unstyled
browser-default HTML the entire time. Fixed by adding the missing
enqueue call. `[lis_listing_edit]` already had it (that one was fine).

Found the same *class* of bug in the older Vendor Showcase submission
form (`includes/submission.php`, `[lis_preferred_vendor_submit]`) —
except there, no CSS was ever written for its `lis-pv-submit-*` classes
at all, not just a missing enqueue. Bigger job, flagged separately
rather than scope-creeping it into this fix.

### While in there: redesigned the photo upload

The bare native `<input type="file" multiple>` was very likely the
single ugliest part of an otherwise reasonably-styled form. Replaced
with a proper dropzone: the raw input is visually hidden (still real,
still what actually submits), a styled `<label>` triggers it on click,
and new `assets/js/listing-photos.js` adds drag-and-drop onto the
dropzone plus live thumbnail previews of exactly what's selected before
upload. Shared by both `[lis_listing_submit]` and `[lis_listing_edit]`
since they use identical `#lis_listing_photos` markup.

Also: mobile-responsive breakpoint for the whole submit form (hours
table, checkbox grid, full-width submit button under 600px), better
section card styling (white cards with subtle shadow instead of flat
gray), and focus states on inputs.

### Files touched

- `includes/listings-submission.php` (missing enqueue, dropzone markup)
- `includes/listings-edit.php` (dropzone markup, photo-preview enqueue)
- `assets/js/listing-photos.js` (new)
- `assets/css/listings.css`

### Known limitation

Could not visually verify this against the live draft "Add Listing" page
— no wp-admin login session, and Application Passwords don't authenticate
normal page loads (REST-only). Built and reasoned through carefully, but
a screenshot after this deploys would confirm it actually looks right.

---

## [0.25.0] — 2026-08-02 — Grid/List/Map view toggle + Sort By

Matches a toolbar Lucca pointed to on Directorist's real listings page:
view-mode buttons (Grid/List/Map) plus a Sort By dropdown, rendered by
the new `lis_directory_render_listing_toolbar()` just above the results
grid in `templates/archive-listing.php`.

- **Grid/List** is a pure client-side display preference — same query
  results, different layout — handled by `assets/js/listing-view-toggle.js`
  toggling a `.lis-listing-grid--list` class and remembering the choice
  in `localStorage`. Deliberately not a URL param since it doesn't change
  what's queried.
- **Map** view only appears if a Google Maps API key is configured
  (Settings > LIS Directory Settings) — same key used for the
  single-listing map. Lazy-loads the Maps JavaScript API only when
  actually clicked (no point loading it for visitors who never do),
  geocodes every visible card's address client-side, drops a marker per
  result, and fits the map bounds to show them all. Clicking a marker
  goes to that listing.
- **Sort By** (`lis_sort` GET param: Newest/Oldest/A→Z/Z→A) is a real
  `pre_get_posts` change, handled in `lis_directory_apply_search_filters()`
  in `includes/listings-search.php` — added to the same guard/dispatch
  function that already owns the archive query rather than a second
  competing hook. The sort `<select>` auto-submits via `onchange`, but
  it's a real GET form with every other active filter re-emitted as
  hidden fields, so it still works with JavaScript off.
- **Not built**: sorting by rating. Ratings are computed on the fly from
  approved comments, not stored as queryable post meta, so `ORDER BY`
  can't reach it without a denormalized rating field kept in sync on
  every new review — a real follow-up if it turns out to matter, not
  something to fake with a wrong sort.

### Files touched

- `includes/listings-search.php` (sort options, toolbar render function, `pre_get_posts` sort handling)
- `templates/archive-listing.php` (toolbar placement, `data-address`/`data-title` on cards, map view container)
- `assets/js/listing-view-toggle.js` (new)
- `assets/css/listings.css`

---

## [0.24.4] — 2026-08-02 — Features filter: checkbox wall → type-to-filter

User feedback on the search widget's "More Filters" panel: the Features
list was a wall of plain checkboxes, wanted something you type into
instead. New `assets/js/listing-search.js`, progressive enhancement —
the server still renders the full checkbox grid unchanged (that's what
actually submits with the form, and is what a no-JS visitor sees and
uses), but on page load JS hides that grid and replaces it with a
type-to-filter input: type to narrow a dropdown of remaining feature
names, click one to add it as a removable chip, chips stay in sync with
the underlying (now hidden) checkboxes. No new dependency — vanilla JS,
matching this plugin's existing pattern (`assets/js/listing-compare.js`).

### Files touched

- `assets/js/listing-search.js` (new)
- `includes/listings-search.php` (enqueue + `data-feature-field`/`data-feature-checkboxes` hooks)
- `assets/css/listings.css`

---

## [0.24.2] — 2026-08-02 — Maps: switched to the Maps JavaScript API

v0.24.0's map used the Maps Embed API (a plain iframe — no JS SDK, no
billing account needed historically). Once the key was actually live,
Google rejected it: "This API is not activated on your API key" — the
key Lucca provisioned was scoped to Maps JavaScript API + Geocoding API,
not Embed API. Rather than ask him to also enable Embed API, rebuilt the
map to use what was actually provisioned:
`templates/single-listing.php` now loads the Maps JavaScript API
(async, via a `callback=` query param — Google's current recommended
loading pattern) and geocodes the listing's plain address string
client-side with `google.maps.Geocoder`, then drops a `google.maps.Map`
+ marker into a plain `<div>`. Same trigger condition as before (address
+ API key both present), same fallback (plain "open in Google Maps"
link) otherwise.

CSS: `.lis-listing-map iframe` → `.lis-listing-map-canvas` (a plain div
now, not an iframe), same absolute-fill-inside-aspect-ratio-box technique.

### Files touched

- `templates/single-listing.php`
- `assets/css/listings.css`

---

## [0.24.1] — 2026-08-02 — Fix: settings never actually visible via REST

Real bug, caught immediately after v0.24.0 deployed: every
`register_setting( ..., array( 'show_in_rest' => true ) )` call added
this session (`lis_directory_listing_edit_page_id`,
`lis_directory_featured_listing_product_id`,
`lis_directory_google_maps_api_key`) was hooked on `admin_init` — which
never fires on a REST API request (that's not a wp-admin page load), so
none of them ever actually appeared via `/wp-json/wp/v2/settings`,
regardless of plugin version or deployment. Moved all three to `init`,
which fires on every request type (front-end, admin, REST, AJAX) and is
just as safe for the wp-admin Settings form. Confirmed via REST
immediately after this deployed.

### Files touched

- `includes/woocommerce.php`
- `includes/listings-edit.php`
- `includes/listings-pricing.php`

---

## [0.24.0] — 2026-08-02 — Embedded map, plus two new WooCommerce pricing products

### Maps

`templates/single-listing.php`'s doc comment previously flagged this as
"not built — needs a Google Maps API key this project doesn't have." Lucca
provided one. New Settings > LIS Directory Settings field
(`lis_directory_google_maps_api_key`), and when both that key and a
listing's address are present, a Google Maps Embed API iframe (`place`
mode — geocodes the plain address string, no lat/long stored on the
listing) renders right after the contact info block. No key or no
address: falls back to exactly the previous behavior (address just links
out to Google Maps).

### Two new WooCommerce products (created as drafts — review before publishing)

Per Lucca's direction, created via the WooCommerce REST API (same
Application Password used all session — no real purchase was made,
creating a draft catalog product isn't a transaction):

- **Featured Listing** (product 6439, variable): Monthly $18 (variation
  6440) / Annual $150 (variation 6441). Configure Settings > LIS Directory
  Settings > Featured Listing Product to point at 6439 once published —
  `includes/listings-pricing.php` already matches on the parent product
  ID regardless of which variation was purchased, so both prices work
  with no code change.
- **Standard Listing** (product 6442, variable): Monthly $8 (6443) /
  Annual $60 (6444). **Not wired to anything** — submitting a listing via
  `[lis_listing_submit]` is still completely free. Gating submission
  behind payment would be a real behavior change to existing, live
  functionality and wasn't something to decide unilaterally; flagged
  here rather than silently built. Say the word if that's actually
  wanted and it's a straightforward follow-up.

Both products used `type: "variable"` with subscription meta
(`_subscription_price`/`_subscription_period`/etc.) set at the variation
level — WooCommerce's REST API on this site rejects a bare
`type: "subscription"` (its product-type enum here is only `simple,
grouped, external, variable, listing_pricing_plans` — the last one being
Directorist's own pricing-plan product type, not something this plugin's
generic cart/order hooks integrate with), which is also exactly how the
existing, already-working Vendor Showcase product is structured.

### Files touched

- `includes/woocommerce.php` (Maps API key + settings field)
- `templates/single-listing.php` (map embed)
- `assets/css/listings.css` (map container)

---

## [0.23.1] — 2026-08-02 — PHP syntax fix

Fixed a mismatched brace/alt-syntax `if` in `includes/woocommerce.php`'s
Settings page (the Featured Listing Product dropdown block) that CI
caught via `php -l` before it ever reached a release — v0.23.0's zip was
deleted and never installed anywhere.

---

## [0.23.0] — 2026-08-02 — Featured Listing paid upgrade

### What changed

Directorist Pricing Plans parity, scoped down to one real upgrade
(Featured) instead of a general multi-tier plan builder — see
`DIRECTORIST_PARITY_PLAN.md`. New `includes/listings-pricing.php`, follows
the exact same pattern as the existing Vendor Showcase WooCommerce
integration (`includes/woocommerce.php`): a WooCommerce product configured
by hand in wp-admin (Settings > LIS Directory Settings > Featured Listing
Product), one-time or WC Subscriptions — this plugin only reacts to order
completion, it doesn't care which.

Dashboard now shows a "⭐ Feature this listing" link on any of the user's
own published, not-yet-featured listings (hidden if no product is
configured yet). Clicking it adds the configured product to the cart via
a plain add-to-cart URL carrying which listing it's for
(`woocommerce_add_cart_item_data` → `woocommerce_checkout_create_order_line_item`
→ order line item meta, same linking approach as Vendor Showcase's
category-tagged variations, just listing-tagged instead). On
`woocommerce_order_status_completed` **and** `processing` (many payment
gateways for virtual products only ever reach `processing`), the matching
order item's linked listing gets `_lis_listing_featured` set. If WC
Subscriptions is active, `cancelled`/`expired` un-features it, same as
Vendor Showcase's expiry handling.

**No real purchase was executed while building or testing this** —
completing a real transaction isn't something done autonomously,
regardless of framing. The code is ready for one real test purchase, which
is on you to run before trusting it fully.

### Known limitation

A one-time (non-subscription) purchase features a listing indefinitely —
there's no automatic time-boxed expiry for one-time purchases, only for
WC Subscriptions cancellation/expiry. Building real expiry (a cron job
checking purchase date against some configured duration) is a reasonable
follow-up if a one-time "Featured for 30 days" model turns out to be what's
actually wanted, rather than guessing a duration now.

### Files touched

- `includes/listings-pricing.php` (new)
- `includes/listings-account.php` (Dashboard "Feature this listing" link)
- `includes/woocommerce.php` (Settings: Featured Listing Product dropdown)
- `lis-directory.php`

---

## [0.22.1] — 2026-08-02 — Prep for real-listing migration

New `_lis_listing_migrated_from` meta (integer, REST-registered) on
`lis_listing` — stores the original Directorist `at_biz_dir` post ID once
the bulk migration of the 108 real Local Business listings runs. Purely
prep: lets the migration script (run via REST, not shipped as UI) check
"have I already migrated this one?" before creating a duplicate, so a
retried/resumed run given the site's occasional REST flakiness is safe.
No user-facing change in this version by itself.

Also confirmed via Directorist's own REST namespace
(`/wp-json/directorist/v2/listings/{id}`) that real listing data —
address/phone/email/website/categories/hours/price/gallery (as *existing*
attachment IDs already in the media library, not new uploads) — is
cleanly readable there, which is what the migration script will read
from rather than reverse-engineering Directorist's raw postmeta storage.

---

## [0.22.0] — 2026-08-02 — Reviews fix, Mark as Sold, front-end edit form

### Reviews: fixed a real dead-end bug

Reviews were never actually not-built — `includes/listings-reviews.php` has
had a full working submission mechanism since early on (WordPress's own
comment system, extended with a required 1–5 star rating, forced to
`pending` for moderation regardless of the site's general Discussion
setting). What was broken: **Talus Rock Retreat** (the one real listing,
post 6386) had `comment_status: closed`, because it was created before
this CPT supported comments at all — so `comments_open()` was false and
`$review_count` was 0, meaning `templates/single-listing.php`'s
`if ( comments_open() || $review_count )` guard never rendered anything
under "Reviews," not even a login prompt. Fixed live via a one-time REST
PATCH (`comment_status` → `open`). The 6 demo listings were already fine
(created after comment support existed). Hardened
`includes/listings-submission.php`'s `wp_insert_post()` call to explicitly
set `'comment_status' => 'open'` going forward, instead of relying on the
site-wide Discussion default.

### Mark as Sold / Rented

New `_lis_listing_sold` meta (Real Estate Sale/Rent only). Admin checkbox
in the meta box, a "Sold"/"Rented" badge on the card and single page
(label depends on sale vs. rent), and a Dashboard toggle button so the
listing owner can flip it themselves without wp-admin access — ownership
enforced by `post_author` match, not just being logged in.

### Front-end listing edit form

New `[lis_listing_edit]` shortcode + `includes/listings-edit.php`,
deliberately a separate file/functions from `[lis_listing_submit]` rather
than one shared form — real differences (photos optional vs. required,
`wp_update_post` vs. `wp_insert_post`, ownership check instead of "anyone
logged in") made a merged version harder to follow than two smaller ones.

Editing does **not** reset a published listing back to `pending` — an
owner fixing a typo shouldn't have to wait for re-approval every time.
Existing gallery photos show with a "Remove" checkbox each; new photos are
optional (unlike submission, where at least one is required). Type-specific
fields (bedrooms/bathrooms/sqft, salary/employment type, directory type)
are **not editable here** — the original submission form never collected
them either (admin-only, set via the wp-admin meta box) — the form says so
inline rather than silently omitting them.

New "Listing Edit Page" setting (Settings > LIS Directory Settings,
`lis_directory_listing_edit_page_id`, `show_in_rest` so it can be set via
the REST settings endpoint too) — the Dashboard's Edit link uses it when
configured, falling back to the wp-admin edit link otherwise. New draft
page "Edit Listing" (post 6438) added under "LIS Directory (Preview)"
with the `[lis_listing_edit]` shortcode, matching the existing 8-page tree.

### Files touched

- `includes/listings-reviews.php` (unchanged — confirmed correct, not
  the source of the bug)
- `templates/single-listing.php` (Sold/Rented badge)
- `templates/archive-listing.php` (Sold/Rented badge)
- `includes/listings-meta.php` (`_lis_listing_sold` meta + admin checkbox)
- `includes/listings-account.php` (Dashboard toggle + smarter Edit link)
- `includes/listings-submission.php` (explicit `comment_status`)
- `includes/listings-edit.php` (new)
- `assets/css/listings.css`

### Known limitation

The edit form can't touch directory type or its type-specific fields
(bedrooms/bathrooms/sqft, salary, employment type) — same gap as the
original submission form, just not newly introduced here. A reasonable
follow-up if it turns out to matter in practice.

---

## [0.21.0] — 2026-08-02 — Search widget on the real archive page

### What changed

Fixed a real, user-reported gap: the `[lis_listing_search]` filter widget
(keyword, directory type, category, price, open now, features) was only
ever placed on the separate draft "LIS Directory (Preview)" page — it was
never embedded into `templates/archive-listing.php`, so the actual live
`/listings/` archive (and any `lis_listing_category` term archive) had no
visible search/filter UI at all, unlike Directorist's real site where the
search bar sits directly atop the results.

`templates/archive-listing.php` now calls
`lis_directory_render_search_form_shortcode()` directly at the top of
`.lis-listing-archive`, right after the page title, before the listing
grid. Existing `pre_get_posts` filtering logic in `listings-search.php`
needed no changes — it was already scoped to the archive/category query,
just never had a form on that page pointing at it.

Added one CSS override
(`.lis-listing-archive .lis-listing-search-form`) — the form's own
`max-width`/padding were sized for standalone shortcode placement inside
a narrower page-content column; nested inside `.lis-listing-archive`
(which already provides matching width/padding), those would have
doubled up. Reset to `padding: 0` and `max-width: none` in that context.

### Files touched

- `templates/archive-listing.php`
- `assets/css/listings.css`

### Known limitation

The form's `action` always points at the plain `/listings/` archive
(via `get_post_type_archive_link()`), not the current category term
archive — so filtering from inside a category page redirects to the
unscoped archive rather than staying scoped to that category. Existing
behavior, unchanged by this fix; not in scope of the reported bug.

---

## [0.20.1] — 2026-08-01 — QA pass

Read back through every file touched this session (v0.16.4 → v0.20.0) with
fresh eyes: cross-checked shortcode tag names against what's actually
registered and what the 9 new draft pages use (all match), cross-checked
every `<input name="...">` in the search widget against the `$_GET[...]`
keys the `pre_get_posts` handler reads (all match), cross-checked every
`lis-listing-*` type-slug string literal across templates/includes for
typos (none found), and diffed every CSS class referenced in the new PHP
against what's actually defined in `listings.css`.

Found one real gap: `.lis-listing-search-field` (the wrapper around each
filter group in the "More Filters" panel — Open Now, Price, Features,
Reset) had no width rule, so it could shrink awkwardly inside the flex
row. Added `min-width: 160px`. Everything else the diff flagged
(`lis-listing-author-missing`, `lis-listing-dashboard-login-required`) is
intentionally unstyled — matches the existing, already-established
convention for login-required messages elsewhere in this plugin
(`lis-listing-submit-login-required` has never had dedicated CSS either).

---

## [0.20.0] — 2026-08-01 — Directorist parity, phase 5: listing FAQs

### What changed

`lis_listing` listings can now have FAQs — the one gap explicitly flagged
as "not built" in `templates/single-listing.php`'s own doc comment since
the framework's early passes ("needs a dynamic add/remove-row admin UI,
not yet built").

* New `_lis_listing_faqs` post meta (`includes/listings-meta.php`) — a
  JSON-encoded array of `{question, answer}` pairs in one meta field.
  There's no fixed number of FAQs (that's the whole point of a dynamic
  add/remove UI) and WordPress has no built-in repeatable-field-group
  primitive, so JSON-in-one-field is the pragmatic choice over one meta
  row per question.
* Admin meta box: a plain `<template>` + vanilla-JS clone/remove pattern
  (matching the existing type-field-toggle script already in this file,
  not a new dependency) — "+ Add FAQ" clones a blank row, each row has its
  own Remove button. Submits as parallel `lis_listing_faq_question[]` /
  `lis_listing_faq_answer[]` arrays rather than assembling JSON
  client-side; the save handler zips them together, drops any row missing
  either half, and JSON-encodes the result server-side.
* `lis_directory_get_listing_faqs( $post_id )` — always returns a clean,
  re-indexed array, never trusts the stored JSON blindly (defensive
  against anything else that might one day write to that meta key
  outside this admin UI).
* `templates/single-listing.php` — new FAQs section, plain `<details>`/
  `<summary>` accordion (no JS needed for the front-end, browsers handle
  `<details>` natively), positioned between Social and Reviews.
* `assets/css/listings.css` — matching accordion styles. No new admin CSS
  for the meta box rows — consistent with the rest of this plugin's admin
  UI, which has never had custom styling beyond default wp-admin form
  table markup.

---

## [0.19.0] — 2026-08-01 — Directorist parity, phase 4: demo listings + draft page tree

### Found mid-phase: the live site was still on v0.16.4

Six new demo listings' type-specific meta (`_lis_listing_type`, bedrooms,
bathrooms, sqft, salary, employment_type) came back missing from a REST GET
immediately after being set via REST POST. Root cause: every zip since
v0.16.4 (v0.17.0, v0.18.0) had only been pushed to GitHub / handed to Lucca
as a download — nothing had actually been installed on the live site yet.
WordPress's REST API only persists submitted meta for keys that are
actually registered via `register_post_meta()`; the still-running old code
doesn't register any of the new Phase 1 fields, so they were silently
dropped, no error. The category assignments and the pages themselves saved
fine — those only depend on taxonomies/post types that already existed in
v0.16.4. Sent v0.18.0 to Lucca to install; **once confirmed live, the
type/bedrooms/bathrooms/sqft/salary/employment_type meta for listing IDs
6415–6420 needs to be re-submitted**, since it never actually saved the
first time.

### What changed

* New `[lis_listing_grid type="real-estate-sale" count="12"]` shortcode
  (`includes/listings-account.php`) — a standalone grid pre-filtered to one
  directory type, for embedding on an ordinary page. Needed because the
  real filtered-browsing experience is the `lis_listing` post-type/taxonomy
  archive (a real WP query against `templates/archive-listing.php`), which
  a plain page can't embed directly.
* 6 demo listings created (2 each: Real Estate Sale, Real Estate Rent, Job
  Listing). Confirmed via REST first that Real Estate/Job Listing have
  **zero real Directorist data on this site** — all 108 real published
  listings are Local Business Directory only — so these are honestly
  `[Demo]`-prefixed fictional entries, not real business data repurposed.
  Local Business needed no demo addition; the existing real "Talus Rock
  Retreat" listing already covers that type.
* New draft page tree: **"LIS Directory (Preview)"** (draft, top-level,
  unlinked from live nav, contains the search widget) with 8 draft
  children — Local Business, Real Estate — For Sale, Real Estate — For
  Rent, Job Listings, Add Listing, Compare Listings, Vendor Profile, My
  Dashboard. Mirrors the live Directorist page tree's actual structure
  minus what was already ruled out of scope (WooCommerce checkout pages)
  or made redundant by how this framework works (Search Result — the
  search widget already routes to the real archive; Single Category — the
  taxonomy archive handles this natively, no page needed).

---

## [0.18.0] — 2026-08-01 — Directorist parity, phases 2+3

### What changed

**Phase 2a — full category tree.** `lis_listing_category` was ~1 term deep;
imported Directorist's real `at_biz_dir-category` tree 1:1 via the REST API
(both taxonomies are `show_in_rest`, so no direct DB access was needed) —
233 terms total, confirmed exactly 2 levels deep (29 top-level, 204
children) before importing, two-pass (parents first, building an old-ID →
new-ID map, then children against that map). Idempotent by name, so it was
safe to re-run after the browser tab's 30-second tool-call timeout cut the
first attempt off partway through — it just skipped what already existed
and picked up where it left off. Verified after: 233 terms, 0 orphaned
children, spot-checked hierarchy (e.g. "Acupuncturists" correctly parented
under "Health & Wellness").

**Phase 2b — search/filter widget.** New `includes/listings-search.php`:
`[lis_listing_search]` shortcode — keyword, directory type, category, price
tier ($/$$/$$$/$$$$, matching how this site's listing prices are actually
stored — free text, not a strict number, so no numeric range slider),
open-now, and features. Deliberately GET-based with no AJAX and no separate
results page: the form submits to the existing `lis_listing` archive (or a
category archive) and a `pre_get_posts` hook (plus a `the_posts` filter
specifically for open-now, since that's derived at request time from hours
meta, not a single stored value a `meta_query` can match) applies the
filters to that same query. Only touches the query when one of this
plugin's own filter params is present, so a plain `/listings/` visit is
unaffected.

**Phase 3 — Compare, Author Profile, Dashboard.** New
`includes/listings-account.php`:
* `[lis_listing_compare]` — up to 4 listings side by side (photo, category,
  price, rating, address, features). Cookie-backed (`assets/js/listing-compare.js`),
  not a DB record, so it works for anonymous visitors the same way
  Directorist's own Compare does. "+ Compare" buttons added to archive
  cards, the single listing page, and author-profile cards.
* `[lis_listing_author_profile]` — public page for one vendor
  (`?author_id=`): avatar, display name, bio, grid of their published
  listings.
* `[lis_listing_dashboard]` — logged-in user's own listings (any status)
  with a status pill and View/Edit actions. **Scope note:** no front-end
  edit form. The existing `[lis_listing_submit]` form requires a fresh
  photo upload every submit, which is right for a first submission and
  wrong for an edit — a real pre-filled edit variant is separate work, not
  something to rush. Edit links only appear when the user's role actually
  has `edit_post` capability (Author/Contributor+, not the default
  Subscriber most front-end registrants get) — honest about current
  capability rather than a dead link.

### Deliberately unchanged

`templates/archive-listing.php` keeps its own inline card markup rather
than being refactored to call the new `lis_directory_render_listing_card()`
helper (used by Author Profile) — a mid-flight refactor of the one template
every listing view already depends on wasn't worth the risk this pass; the
duplication is small and contained.

---

## [0.17.0] — 2026-08-01 — Directorist parity, phase 1: directory types

### Why

Directive from Lucca: "everything framework wise that's live on the site rn"
should get a `lis_listing` counterpart, on a separate draft page tree,
touching nothing live. Full plan and scope reasoning (what's in, what's
deliberately out) lives in `DIRECTORIST_PARITY_PLAN.md` at the project root
— not duplicated here since it'll grow across several versions.

Verified via REST API what's actually live before assuming: Directorist
powers **four** directory types on this site, not just the one "Local
Directory" this plugin had been shadowing — Local Business (108 listings),
Real Estate Sale, Real Estate Rent, and Job Listing, all sharing one 233-term
category taxonomy (`at_biz_dir-category` — confirmed real-estate terms like
"Apartment" and business terms like "Accounting & Tax Services" coexist in
the same flat-ish tree, not separate per-type taxonomies).

### What changed

* **`includes/listings-meta.php`** — new `_lis_listing_type` post meta
  (`local-business` / `real-estate-sale` / `real-estate-rent` / `job-listing`),
  a plain `<select>` in the meta box rather than a taxonomy — Directorist's
  own "Add Listing" screen uses a single dropdown, not checkboxes, and a
  listing only ever has one type. Defaults to `local-business` so the one
  pre-existing listing (Talus Rock Retreat, saved before this field existed)
  resolves sanely with no migration needed.
* New type-specific fields, shown/hidden via a small inline script keyed off
  the type `<select>`: Real Estate gets `_lis_listing_bedrooms` /
  `_lis_listing_bathrooms` / `_lis_listing_sqft`; Job Listing gets
  `_lis_listing_salary` / `_lis_listing_employment_type` (full-time/
  part-time/contract/seasonal). Job application contact deliberately reuses
  the existing generic Email/Website fields instead of adding new ones.
* New "Directory" admin list-table column showing each listing's type at a
  glance.
* `templates/single-listing.php` and `templates/archive-listing.php` — both
  now show the type-specific facts (bed/bath/sqft on Real Estate, salary +
  employment type on Job Listing) in the obvious places (a facts strip on
  the single page, a one-line summary on archive cards).
* `assets/css/listings.css` — matching styles for the new facts strip/line.

### Deliberately unchanged

Categories are still ~1 term deep (`lis_listing_category`) — the full
233-term import is phase 2, tracked separately since it's a data operation,
not a code change, and worth its own commit.

---

## [0.16.4] — 2026-08-01 — Fix: Directorist CSS missing on draft previews

### Found by

Client flagged the "Local Directory" draft's search widget rendering with
its "More Filters" panel fully expanded and unstyled (raw checkboxes/labels
stacked down the page) instead of collapsed behind the usual toggle. Same
draft, same page — client saw it twice across two separate check-ins, so
worth actually fixing instead of re-explaining.

### Root cause

Confirmed the *live*, published version of the same page (`/services/local-directory/`)
renders correctly — checked both as a logged-in admin and as a logged-out
visitor, computed `height: 0px` on `.directorist-search-modal` both times.
The break is real but scoped specifically to *unpublished drafts viewed via
their `?preview=true` link*.

Directorist decides whether to enqueue its own `assets/build/css/public/main.css`
(the file responsible for collapsing that panel) by scanning post content
during `wp_enqueue_scripts`. WordPress's preview mechanism swaps in the
actual post content *later* than that hook fires — via `the_preview` filter
logic inside `get_post()` calls made during template loading — so on a
preview of content that's never been published, Directorist's scan sees
stale/empty content and skips the enqueue entirely. Verified directly:
scanned every same-origin stylesheet loaded on the broken preview for any
rule mentioning `.directorist-search-modal` — zero matches, while the same
scan on the live page returned 111 matching rules, all from that one file.
Manually injecting that exact file into the broken preview fixed it
instantly, confirming both the cause and the fix.

### Fix

`includes/directorist-integration.php` — new
`lis_directory_fix_directorist_preview_css()`, hooked on `wp_enqueue_scripts`
at priority 20, force-enqueues that one Directorist stylesheet whenever
`is_preview()` is true. No-op on every normal page load (`is_preview()` is
false), and harmless even if Directorist's own logic *does* fire on some
future preview path — WordPress dedupes styles by handle, so at worst it's
loaded twice under two different handles, not a conflict.

### Deliberately not done

Did not patch Directorist's own plugin files directly — any changes there
get silently reverted on Directorist's next update. Fixing it from this
plugin's side, even though the root cause lives in someone else's code, is
the only change that survives.

---

## [0.16.3] — 2026-08-01 — Real "Vendor Showcase" lockup + Sponsored badge

### What changed

* `[lis_preferred_vendor_heading]` now renders the client-supplied
  `lis-vendor-showcase-lockup.png` (icon + "Vendor Showcase" wordmark,
  same 811px source height as the old file so the existing
  `height: 28px; width: auto` CSS scales it correctly with no other
  changes needed) instead of the old "LIS Partners" lockup. Alt text
  updated to "LIS Vendor Showcase". Old `assets/img/lis-partners-lockup.png`
  deleted — nothing referenced it after the swap.
* Every vendor card (`lis_directory_render_vendor_card_html()` — used by
  both `[lis_preferred_vendor_card]` and the ticker's `style="cards"` mode)
  now shows a small "Sponsored" badge, top-right corner. Reinforces the
  0.16.2 rename: these are paid placements, and the badge says so plainly
  instead of leaving it implied.
* `assets/css/vendor-card.css` — `.lis-pv-card` gained `position: relative`
  so the new `.lis-pv-card-badge` (`position: absolute`, small uppercase
  label, muted gray pill) can anchor to the card's corner.

### Deliberately unchanged

* The "logos" ticker style (`style="logos"`) — no room for a badge next to
  a bare logo without redesigning that layout; the "Sponsored" framing is
  the primary use case (full cards) for now.

---

## [0.16.2] — 2026-08-01 — Renamed "Preferred Vendor" to "Vendor Showcase"

### Why

Client feedback: "Preferred Vendor" reads as a personal endorsement
("we prefer this business"), when the reality is a business is paying for
placement, not being individually vouched for. "Vendor Showcase" describes
the same paid-slot mechanic without implying endorsement.

### What changed

Copy-only rename across admin and customer-facing text — no data migration,
no breaking change:

* `includes/cpt.php` — CPT labels (`name`, `singular_name`, `add_new_item`,
  `edit_item`, menu label, etc.) now read "Vendor Showcase" / "Vendor
  Showcase Entry" instead of "Preferred Vendor(s)".
* `includes/enforcement.php` — the activation-blocked admin notice ("A
  Preferred Vendor slot requires...") now reads "A Vendor Showcase slot
  requires...".
* `includes/shortcodes.php` — logged-in-editor hint text ("No active
  preferred vendor(s)...") and the ticker's `aria-label` ("Preferred
  vendors" → "Vendor showcase").
* `includes/submission.php` — front-end submission form copy, including the
  login-required notice and the "must already have a Local Directory
  listing" helper text (previously "...to become a Preferred Vendor",
  rewritten to "...before you can join the Vendor Showcase" to drop the
  endorsement framing specifically called out).
* `includes/woocommerce.php` — the WooCommerce thank-you page message
  ("Your Preferred Vendor slot is reserved!" → "Your Vendor Showcase spot
  is reserved!") and the variation-tagging admin UI copy.
* `includes/admin.php`, `includes/listings-cpt.php`,
  `includes/listings-submission.php`, `lis-directory.php` — doc comments
  and the plugin's own `Description:` header updated for consistency.
* `readme.txt` — description, shortcode docs, and WooCommerce setup steps
  updated to match.

### Deliberately unchanged

* Shortcode tags (`[lis_preferred_vendor_card]`, `[lis_preferred_vendor_ticker]`,
  `[lis_preferred_vendor_heading]`, `[lis_preferred_vendor_submit]`) — these
  are already embedded in live page content (including the homepage); renaming
  the tags themselves would silently break every existing placement without a
  backward-compat alias, for zero user-visible benefit (nobody sees the raw
  shortcode tag).
* The `lis_preferred_vendor` CPT slug, `lis_vendor_category` taxonomy slug,
  and `_lis_pv_*` meta key prefix — internal identifiers, invisible to users;
  renaming them is a real migration with real risk (rewrite rules, stored
  meta keys) for no visible payoff.
* The public "LIS Partners" branded heading (`[lis_preferred_vendor_heading]`
  shortcode's rendered output) — that name was never "Preferred Vendor"
  language to begin with, so it didn't need to change.

---

## [0.16.1] — 2026-07-31 — Fix: fatal error crashed the whole site

### Found by

Installed v0.16.0 live, then immediately checked the real listing page —
`WordPress › Error`, whole site down (WP's critical-error screen, "Suspected
plugin: LIS Directory v0.16.0"). Error detail: `Uncaught Error: Call to
undefined function register_comment_meta()` in `includes/listings-reviews.php:71`,
thrown from an `init` hook — meaning **every single page load** hit this
fatal, not just listing pages, since `init` fires universally.

### Root cause

`register_comment_meta()` doesn't exist in WordPress core. Only
`register_post_meta()` and `register_term_meta()` are real convenience
wrappers; comment meta has no equivalent and must go through the generic
`register_meta( 'comment', $meta_key, $args )` instead. Assumed a
consistent post/term/comment API existed without checking — it doesn't.

### Immediate response

Deactivated the plugin directly from WordPress's own critical-error
recovery screen (the "Deactivate" button/form it renders for a logged-in
admin) to restore the site immediately, *before* writing the fix — stopping
the outage took priority over fixing the code.

### Fixed

- **`includes/listings-reviews.php`**: `register_comment_meta( 'rating', ... )`
  → `register_meta( 'comment', 'rating', ... )`.
- Manually audited every other new function call across
  `listings-reviews.php`, `listings-badges.php`, and `listings-actions.php`
  against known WordPress core APIs before shipping this fix — PHP lint
  (`php -l`) only catches syntax errors, not calls to undefined functions,
  so it had already passed clean on the broken 0.16.0 code and would pass
  clean on this fix too; it isn't sufficient alone to catch this class of
  bug.

---

## [0.16.0] — 2026-07-31 — Reviews, badges, bookmark/share/report/claim

### Context

Direct follow-up on v0.15.0's submission form: client said it "isn't as
robust as it should be" and asked to match Directorist's own real field
set — checked live on this site (Add Listing pricing-plan comparison at
`/services/local-directory/add-listing-2/?directory_type=local-business-directory`,
stopped short of the actual paid checkout step) rather than guessed:
Business Name, Address, Phone, Email, Website, Photos, Video, Services,
Social Media, Map, Business Hours, FAQs, Customer Reviews, Claim Badge.
Then, in the same pass: Bookmark/Share/Report (visible in the original
reference screenshot's card actions), and Featured/Popular/Owner Verified
badges.

### Added: submission form now covers Video, Services, Social Media

- **`includes/listings-submission.php`** / **`includes/listings-meta.php`**:
  added Video URL, Services (one per line, stored as a newline-delimited
  string — not a dynamic repeater), and four social links (Facebook,
  Instagram, X/Twitter, LinkedIn) to both the public form and the admin
  meta box, with matching save logic in both.
- Submission form markup restructured into labeled sections
  (`.lis-listing-submit-section`) with real CSS instead of bare `<p>`
  tags — the "needs to look better" half of the request.

### Added: Reviews (`includes/listings-reviews.php`)

Built on WordPress's own comment system rather than a parallel table —
this site already has Akismet active, and wp-admin's Comments screen
already has moderation/reply/notification UI a custom system would have
to rebuild badly. Only additions needed:
- `register_comment_meta( 'rating', ... )` — 1-5 stars.
- `preprocess_comment` rejects a review with no rating (`wp_die()`, same
  mechanism core uses for its own comment validation).
- `pre_comment_approved` forces every `lis_listing` comment to pending,
  regardless of Settings > Discussion's general auto-approve setting — a
  review should always be moderated even if blog comments aren't.
- `comment_form_before_fields` injects a star-rating radio group;
  `comment_text` filter prepends rendered stars (★☆ glyphs, no icon font)
  to each review's text; `comment_form_defaults` relabels the form
  "Leave a Review" / "Submit Review" — all filter-based, so it works with
  the active theme's own `comments.php` without a template override.
- `lis_directory_get_listing_average_rating()` returns `null` (not `0`)
  when a listing has no reviews yet, so templates can show "no reviews"
  instead of a misleading 0.0.

### Added: Featured / Popular / Owner Verified badges (`includes/listings-badges.php`)

- Featured and Verified are editorial — a new "Badges" side meta box,
  gated on `edit_others_posts` (not the listing owner's own call to make).
- Popular is computed, not set: `template_redirect` increments a raw view
  counter on every single-listing pageview (no visitor dedup — enough to
  badge "popular", not analytics-grade), badge shows above a threshold
  constant (`LIS_DIRECTORY_POPULAR_VIEW_THRESHOLD`, currently 20).
- Verified gets set automatically by claim approval, not directly editable
  in the normal case (the checkbox exists for the manual-override case).

### Added: Bookmark / Share / Report / Claim (`includes/listings-actions.php`)

- **Bookmark**: usermeta array (`_lis_listing_bookmarks`) toggled via a
  small `wp_ajax_` handler (`assets/js/listing-actions.js`), nonce-checked,
  logged-in only (redirects to login otherwise).
- **Share**: no server component — Web Share API where available, clipboard
  copy fallback, pure front-end.
- **Report** and **Claim** share one lightweight, non-public
  `lis_listing_flag` post type (own "Claims & Reports" admin screen under
  Listings) instead of two separate systems — both are "something needs a
  human's attention on this listing," differing only in `_flag_type`.
  Reusing WordPress's own post-list UI (Edit/Trash/search) rather than a
  custom admin screen.
- Approving a claim (`lis_directory_handle_claim_approval()`, nonce-gated,
  requires `edit_others_posts`) sets `_lis_listing_verified` and reassigns
  the listing's `post_author` to the claimant in one action.

### Deliberately not built

An embedded interactive map — Directorist's plan comparison lists "Map,"
but that needs a Google Maps JavaScript API key, a credential this project
doesn't have and shouldn't provision without being asked; the address
already links out to Google Maps (built in v0.14.0), which covers the
practical need without the API dependency. FAQs need a dynamic
add/remove-row admin UI, not yet built — noted, not silently dropped.

---

## [0.15.0] — 2026-07-31 — Front-end listing submission form

### Context

Second of the two next-step options offered after the framework/styling
work (migration vs. submission form) — client picked submission form.
Directorist lets business owners submit their own listings; the
self-hosted replacement needs the same to eventually take over that role.

### Added

- **`includes/listings-submission.php`**: new — `[lis_listing_submit]`
  shortcode + `admin-post.php` handler, deliberately mirroring
  `includes/submission.php` (the Preferred Vendor form)'s exact security
  pattern: login-required gate, hidden honeypot field (bot fills it → fake
  success redirect, nothing created), nonce-verified POST, real
  server-side upload validation (`wp_check_filetype_and_ext` +
  `getimagesize()`, not just an `accept` attribute).
  - Required: business name, category (must resolve to a real
    `lis_listing_category` term), contact email, one photo. Optional:
    description, address, phone, website.
  - Photo upload accepts **PNG or JPG** (`lis_directory_handle_listing_photo_upload()`)
    — unlike the vendor logo upload, which is PNG-only because it gets
    CSS-recolored to black/white. A listing photo is never recolored, so
    there's no reason to exclude JPG here; copied the vendor upload
    function's validation logic but with the wider mime whitelist.
  - Submissions land as **native WordPress `pending` post status**, not a
    custom workflow meta. Deliberate difference from the vendor form:
    Preferred Vendor needs `_lis_pv_status` because of the "one active
    vendor per category" rule it enforces (conflict flagging, category
    locking); `lis_listing` has no equivalent rule, so there's nothing a
    custom status would buy over WordPress's own Pending → editor opens it
    → clicks Publish flow, which the existing "Listing Details" meta box
    (built for admin-created listings) already supports with zero changes.
  - Sets the uploaded photo as the post's featured image
    (`set_post_thumbnail()`) rather than populating the gallery meta field
    — the single/archive templates already fall back to the featured
    image when `_lis_listing_gallery_ids` is empty (built in v0.13.0), so
    this needed no template changes to display correctly.

### Deliberately not in this form

Additional photos (beyond the one required), business hours, and features
are not submittable yet — added by an admin after first publish, using
the existing meta boxes. Keeps the public form short; can be widened
later if that turns out to matter.

---

## [0.14.0] — 2026-07-31 — Contact labels+maps, tags, wider layout, no stripes

### Context

A round of direct feedback on the live test listing: contact fields had
no labels, category wasn't obviously visible, features weren't visible
on cards, the layout looked squished on a wide screen, and the theme's
zebra-striped table rows on Business Hours weren't wanted.

### Changed

- **`templates/single-listing.php`**: contact block now has a small caps
  label (Address/Phone/Website/Email) per row via `.lis-listing-meta-row`
  + `.lis-listing-meta-label`. Address now links to Google Maps
  (`google.com/maps/search/?api=1&query=...`) instead of being plain text
  — a universal link that works across devices/map apps, not tied to one
  provider's app.
- **`templates/archive-listing.php`**: cards now show up to 3 feature
  taxonomy terms as small tag pills (`array_slice( $features, 0, 3 )`).
  Category badge was already present here and on the single page's
  header — confirmed, not re-added.
- **`assets/css/listings.css`**: `.lis-listing-single` max-width
  1000px→1300px, `.lis-listing-archive` 1100px→1400px (both were reading
  as squished with too much empty margin on wide screens). Explicit
  `background: transparent` on hours-table rows to override the theme's
  own zebra-striping, which was never something this plugin's CSS added
  in the first place. New `.lis-listing-card-tags` / `.lis-listing-tag`
  styles for the card-level feature pills.

---

## [0.13.3] — 2026-07-31 — Fix: leftover table border on hours box

### Found by

Client screenshot after 0.13.1 — a thin border still visible along the
top and left edge of the Business Hours table, forming a partial frame.
0.13.1 reset `border` on `td`/`tr` but never on the `<table>` element
itself, so the theme's own default table border was still rendering on
the two edges `border-collapse: collapse` didn't otherwise cover.

### Fixed

- **`assets/css/listings.css`**: `.lis-listing-hours-table-display` now
  also sets `border: none` on the table element (and `thead`/`tbody`),
  not just on `td`. Verified live by injecting the fix directly on the
  page and screenshotting before shipping.

---

## [0.13.2] — 2026-07-31 — Price display hidden for now

### Changed

- **`templates/single-listing.php`** / **`templates/archive-listing.php`**:
  removed the price badge from both the single-page header and archive
  cards, per direct request. The `_lis_listing_price` meta field, its
  admin input, and REST registration are untouched — this is a display
  change only, easy to re-enable by adding the badge markup back if price
  comes back into scope later.

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
