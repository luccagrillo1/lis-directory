=== LIS Directory ===
Contributors: Lucca Grillo
Tags: directory, vendor showcase, custom post type
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.36.16

Vendor Showcase for Living in Sandpoint. Runs alongside Directorist without touching it.

== Description ==

LIS Directory is a purpose-built plugin for livinginsandpoint.com's Vendor
Showcase — a small number of paying vendors get an exclusive showcase slot
per category (e.g. one Insurance slot, one Plumber slot), displayed via
shortcodes. It does not replace or modify Directorist.

The full v1 build order (data model, category-lock enforcement, front-end
display, front-end submission, WooCommerce Subscriptions integration) is
complete and live-tested on production. See CHANGELOG.md for full details and
remaining manual setup (creating the actual WooCommerce product and its
variations is a wp-admin task, not something this plugin does for you).

* Custom post type `lis_preferred_vendor` for vendor data
* Taxonomy `lis_vendor_category`, independent of Directorist's own categories
* Admin meta box: tagline, Directorist Listing URL, logo upload,
  status, term start/end, linked WooCommerce order/subscription ID, contact email
* Only one `active` vendor allowed per category, enforced server-side
* A vendor must already have a real, published listing on the site's Local
  Directory before they can be set Active — enforced server-side both at
  submission and at activation, not just suggested in the UI
* Admin list columns for category, status, and term end; status filter
  dropdown; one-click Approve/Reject row actions for pending submissions
* `[lis_preferred_vendor_card category="slug"]` — single vendor card for one category
* `[lis_preferred_vendor_ticker]` — auto-scrolling row of every active vendor,
  pauses on hover, respects reduced-motion; `style="logos"` for a logos-only
  variant (default is full cards)
* `[lis_preferred_vendor_heading]` — "LIS Partners" branded heading (logomark
  + text) to sit above a card or ticker; `tag="h3"` etc. to fit the page
* `[lis_preferred_vendor_submit]` — logged-in-only front-end submission form;
  submissions always land as Pending for admin review
* WooCommerce Subscriptions integration: tag a product variation with a
  vendor category (LIS Directory > Settings + a per-variation field), its
  stock auto-syncs to whether that category is taken, the thank-you page
  links buyers straight to a pre-scoped submission form, and a cancelled/
  expired subscription automatically expires the linked vendor
* Self-hosted listings framework (`lis_listing` post type, `/listings/`) —
  gallery, price (hidden by default), business hours with a live open/closed
  badge, a Features taxonomy, video embed, and `[lis_listing_submit]` for
  logged-in front-end submissions (land as Pending, reviewed in the normal
  admin editor)

== Shortcodes ==

* `[lis_preferred_vendor_card category="slug"]` — renders the active vendor's
  card for the given `lis_vendor_category` slug. Renders nothing to the public
  if the category doesn't exist or has no active vendor yet (shows an inline
  hint to logged-in editors instead, so a misconfigured placement isn't
  silently invisible while building the page).
* `[lis_preferred_vendor_ticker]` — renders every active vendor in a
  horizontally auto-scrolling, infinite-loop strip. Pauses on hover/focus.
  Two display styles: `style="cards"` (default) shows the same card as
  `[lis_preferred_vendor_card]`; `style="logos"` shows just the color logo,
  larger, with no name or tagline (a vendor with no logo uploaded is skipped
  in this mode rather than leaving an empty slot). Placement is manual (no
  auto-injection into Directorist pages).
* `[lis_preferred_vendor_heading]` — renders the "LIS Partners" brand
  treatment: the LIS logomark icon followed by the word "Partners", as a
  real heading element (`<h2>` by default) so it inherits the theme's own
  heading typography, color, and spacing rather than the plugin guessing
  fonts. `tag="h1"`/`"h3"`/`"h4"`/`"div"` to fit wherever it's placed.
* `[lis_preferred_vendor_submit]` — vendor submission form. Requires a
  WordPress account (shows a log in / register prompt otherwise). Requires a
  Local Directory Listing URL — the vendor must already have a real,
  published listing on Directorist; the URL is validated server-side
  (`url_to_postid()` + post type/status check), not just requested in the
  form. Categories that already have an active vendor are still selectable,
  labeled "currently taken - apply to be waitlisted". Submissions are never
  auto-published; they land as Pending until an admin approves or rejects
  them from the Vendor Showcase list in wp-admin.
* `[lis_listing_submit]` — front-end listing submission form for the
  self-hosted listings framework. Requires a WordPress account. Business
  name, category, and a photo (real PNG/JPG, server-validated, 2MB cap) are
  required; address/phone/website/description are optional. Submissions
  land as native WordPress "Pending" — reviewed and published from the
  normal Listings admin editor, no separate approve/reject workflow needed
  (unlike the Vendor Showcase, there's no "one active per category" rule to
  enforce here). Additional photos, business hours, and features are
  admin-added after first publish, not part of this form yet.
* `[lis_listing_search]` — the search/filter widget (keyword, directory
  type, category, price tier, open-now, features). Plain GET form, no AJAX
  — submits straight to the `lis_listing` archive (or a category archive)
  and a `pre_get_posts`/`the_posts` hook applies the filters there, so
  results use the same archive template rather than a separate results
  page. `redirect="https://..."` to point it somewhere other than the
  default archive. `directory="local-business"` (or any directory-type key:
  `real-estate-sale`, `real-estate-rent`, `job-listing`) locks the form to a
  single directory and hides the "All Directories" dropdown — e.g.
  `[lis_listing_search directory="local-business"]` for a Local-Directory-only
  search box.
* `[lis_listing_compare]` — side-by-side comparison (up to 4) of whatever
  listings the visitor has added via the "+ Compare" button on archive
  cards, the single listing page, or Author Profile cards. Cookie-backed,
  works for anonymous visitors, no account needed.
* `[lis_listing_author_profile]` — public vendor page. Reads `?author_id=`
  from the URL; shows avatar, display name, bio, and a grid of that
  author's published listings.
* `[lis_listing_dashboard]` — logged-in user's own listings (any status)
  with a status pill and View/Edit actions. No front-end edit form yet —
  Edit only appears for users whose role actually has edit access
  (Author/Contributor+); most front-end registrants are Subscribers and
  will just see status + View.
* `[lis_listing_grid type="real-estate-sale" count="12"]` — a standalone
  grid pre-filtered to one directory type, for embedding on an ordinary
  page (the real filtered-browsing experience is the `lis_listing` archive
  itself; this is the lightweight version for a landing page). `type` is
  one of `local-business`, `real-estate-sale`, `real-estate-rent`,
  `job-listing`; omit it to show all types. Renders a matching search box
  above the grid by default when a `type` is set (scoped to that directory);
  `search="no"` hides it, `search="yes"` forces it on for an all-types grid.

== Setup: WooCommerce Subscriptions ==

**This site's WooCommerce Subscriptions install does not use the classic
"Subscription" / "Variable subscription" product types** — the `#product-type`
dropdown only offers Simple/Grouped/External/Variable/Listing Pricing Plan,
and even WC Subscriptions' own "Create subscription product" onboarding button
doesn't add them. Subscription pricing instead lives on a **"Subscriptions"
tab** on an ordinary Variable product, powered by "WooCommerce Subscribe All
The Things" (WCSATT). The underlying `_subscription_price` / `_subscription_period`
meta this creates are the same classic WC Subscriptions fields this plugin's
`includes/woocommerce.php` already expects, so nothing in the plugin code
needs to change — only the setup steps below.

1. Create one **Variable product** ("Vendor Showcase" / "LIS Partner") in
   WooCommerce, with a "Category" attribute (checked "Used for variations")
   and one variation per vendor category you want to sell, each with its own
   regular price.
2. On each variation's edit screen, set the new "LIS Vendor Category" field
   (added by this plugin) to the matching `lis_vendor_category` term.
   Untagged variations are ignored by this plugin entirely.
3. On the product's **Subscriptions** tab, set "Create custom subscription
   plans" → **Add subscription plan** → set the billing frequency (e.g. 1
   Month) and price ("Discount the product price" at 0% charges exactly the
   variation's own regular price; "Set a custom price" charges something
   else). Leave "Expire subscription after a set number of payments"
   unchecked for an ongoing subscription.
4. Consider unchecking "Customers can buy this product without subscribing"
   once you're ready to go live — a one-time purchase never creates a
   `WC_Subscription`, so it won't trigger this plugin's auto-expire-on-
   cancellation logic.
5. Under **Vendor Showcase > Settings**, pick the page that has the
   `[lis_preferred_vendor_submit]` shortcode.
6. That's it — stock on tagged variations now follows category availability
   automatically, and buyers get a submission link on the thank-you page.

== Changelog ==

= 0.36.16 =
* More spacing between logos in the logo-only Vendor Showcase ticker.

= 0.36.15 =
* Fixed: saving a listing could silently deactivate its Vendor Showcase entry (if the editor was loaded before the new showcase checkbox existed). The toggle now only acts when the checkbox was actually part of the submitted form.

= 0.36.14 =
* Vendor Showcase cards/logos/business-card ticker now use the LINKED listingâs logo, business card, and tagline when set (so editing a listingâs branding updates the showcase), falling back to the vendorâs own.

= 0.36.13 =
* Added "Link Vendor Showcases to listings" (Settings): sets _lis_pv_listing_id on every vendor to its matching lis_listing (by existing link, slug, or title) so cards/tickers point at the new listing.
* Added a Directorist-vs-LIS parity report: how many Directorist listings have a matching LIS listing and which are missing one.

= 0.36.12 =
* Added a "De-duplicate listings" tool (LIS Directory Settings): keeps the correct migrated, real-owner copy of each listing and moves the stray admin-owned duplicate to Trash (reversible; batched; never touches unique listings or ones a showcase links to).
* Added a manual "Vendor Showcase" checkbox to the listing editor: ticking it creates/activates a Vendor Showcase entry from the listingâs category, tagline, logo, and business card; unticking deactivates it.

= 0.36.11 =
* Vendor Showcase ticker cards/logos/business cards now link to the new self-hosted listing (by linked listing ID, or by matching the slug) instead of the old Directorist listing.

= 0.36.10 =
* Vendor Showcase ticker motion rebuilt in JavaScript: hovering now pauses it exactly where it is (it used to jump back to the start), and you can scrub the row with the mouse wheel or a Mac two-finger swipe.

= 0.36.9 =
* Admin "Listing Details" meta box is now fully editable: tagline, logo, business card, Google Business link, owner (reassign to any user), plan/tier + billing, Featured, payment reference, and expiry (date + never-expire).
* A listingâs logo auto-fills the "Set listing image" (featured image) when none is set â in the admin box and the front-end forms.
* Business-card ticker cards now fill their frame edge-to-edge (no dead white margins).

= 0.36.8 =
* New [lis_preferred_vendor_top_category_card] shortcode: on a search/archive results page it finds the most common listing category among the results and shows that categoryâs Vendor Showcase card in the single-vendor spot. Optional fallback="category-slug".

= 0.36.7 =
* Simplified the checkout line item: the listing shows once as a clean "Listing: <name>" row instead of also being appended to the product name ("â Featuring: <name>"), which read as a cluttered run-on.

= 0.36.6 =
* Plan step now shows whole-dollar prices without the trailing .00 ($9/mo, not $9.00/mo); decimals still show if a price isnât round.

= 0.36.5 =
* Added an early, optional "Branding" step (tagline, logo, business card) to the add-listing wizard for every listing; the Vendor Showcase reuses these instead of asking again.
* Added "Save & finish later" â saves a draft you own and lets you resume from your dashboard or the edit form (handy if you donât have a logo yet).
* Branding is editable on the edit form (with Remove options) and stays in sync with a linked Vendor Showcase entry, so the tickers reflect edits.
* Tagline now shows under the business name on the listing page.

= 0.36.4 =
* Vendor Showcase is now a single Monthly/Annually subscription product (no more per-category variations). The category a buyer occupies is their listingâs own category; one-active-vendor-per-category exclusivity is enforced in PHP at checkout.
* Plan step warns "this category is currently occupied" when the chosen category already has an active showcase vendor, with a contact link.
* Showcase tagline, logo, and business card are now optional (a listing without them just wonât appear where those assets are required).

= 0.36.3 =
* Made the Showcase category cleanup batched (deleting hundreds of terms at once timed out): it trashes the product first, then deletes empty vendor categories 40 at a time and auto-continues until clear.

= 0.36.2 =
* Retired the category-mirror approach for good and added a one-click "Showcase category cleanup" (LIS Directory Settings) that removes the stray empty vendor categories it created and trashes the oversized per-category Showcase product. Prep for the Showcase using the listing's own category via a single product.

= 0.36.1 =
* Hotfix: the category-mirror added in 0.36.0 tried to sync all 233 listing categories and regenerate the whole Showcase product on every admin page load, slowing wp-admin. Removed the heavy one-time reconcile and per-category product regeneration; the lightweight live sync (a single category added/renamed/removed) stays.

= 0.36.0 =
* Vendor Showcase categories now mirror the directory's listing categories automatically. Adding, renaming, or removing a listing category syncs to the Showcase category set, and the Showcase product tops up its monthly/annual variations for any new category — no separate list to maintain.

= 0.35.4 =
* Fixed the Vendor Showcase logo and business-card upload inputs not appearing — a CSS rule meant to hide only the multi-photo input was hiding every file input in the form.

= 0.35.3 =
* Business hours now have a "No set hours" toggle on the add/edit forms — for by-appointment or hours-not-applicable businesses (insurance agents, consultants, etc.). Checking it hides the day grid and skips hours entirely (and clears any hours Google prefilled). Listings with no hours already show no hours box and no open/closed badge.

= 0.35.2 =
* Claim: the listing page now shows a compact "Is this your business? Claim it" button instead of the whole plan block. The block (plan picker) opens on a focused claim view (the button links to ?claim=1), so it isn't crowding the listing page.

= 0.35.1 =
* Hotfix: fixed a fatal error on single listing pages introduced in 0.35.0 (a stray `}` after a line comment in the claim-box include).

= 0.35.0 =
* Claim-by-subscription: check "Allow this listing to be claimed" on a listing (admin), and its public page shows a "Is this your business? Claim it." box. A logged-in visitor picks a plan and checks out; when the order is paid, the listing's ownership transfers to them and the plan tier is granted — so a claim always comes with an active subscription. Not-logged-in visitors get a log-in prompt.

= 0.34.0 =
* Directorist migration is now buildable end-to-end: a "Run migration" tool maps each at_biz_dir listing to a lis_listing, preserving the owner, expiration date, category, photos, phone/email/website/address, and featured status (idempotent — safe to re-run). A dry-run "Preview mapping" shows exactly what would be created first. Nothing runs automatically.
* Listings now support expiration: a daily check unpublishes any listing past its expiry, so migrated Directorist expirations (and future paid terms) carry through.

= 0.33.3 =
* Groundwork for migrating Directorist listings into this plugin: a read-only "Directorist → LIS migration" section on the Settings page with a dry-run preview of the real Directorist data, so the field mapping (owner, expiration, category, photos, hours, contact) can be confirmed before anything is written. No listings are migrated yet.

= 0.33.2 =
* The business-name step is now a single text box. Google autocomplete lives on the business-name field itself (with a custom suggestions dropdown) instead of a separate "Search for your business" box above it — start typing, pick a Google match to auto-fill, or just keep typing to enter it yourself.

= 0.33.1 =
* Added a "Featured Listing product" generator (LIS Directory Settings) that builds a monthly/annual subscription ($19/mo, $190/yr) and repoints the Featured Listing Product setting at it — so Featured has a real annual price instead of the same flat fee for both. Adjust prices and publish it when ready.

= 0.33.0 =
* Vendor Showcase is now a plan option inside the one add-listing wizard — no separate application form. Pick "Vendor Showcase" on the plan step to reveal its fields (open category slot, tagline, logo, business card); submitting creates the listing and the pending vendor entry together and sends you to the category's subscription checkout. Paying auto-activates the showcase (shows your card, locks the category). Only open categories are offered.

= 0.32.1 =
* Merged the "Find it on Google / Enter manually" choice into one flow. The add-listing wizard now opens on the business-name step with Google search built in: start typing to find your business (auto-fills address, phone, website, hours), or just type your name and fill the rest in yourself. When Google fills fields, an inline confirmation shows what it pulled — all still editable.

= 0.32.0 =
* The add-listing wizard now ends with a "Choose your plan" step (Standard / Featured, monthly or annually) and hands off to WooCommerce checkout; the listing auto-publishes when the order completes, and a post-payment thank-you shows on the order-received page.
* Safety net: until the Standard/Featured products are published + priced, submitting still works exactly as before (free, lands Pending) — so nothing breaks mid-setup.
* Set Standard placeholder pricing to $9/mo, $90/yr (edit in WooCommerce). Vendor Showcase stays its own exclusive per-category flow (linked from the plan step).

= 0.31.13 =
* Added a "Standard Listing product" generator in LIS Directory Settings — a monthly/annual subscription base tier (placeholder prices you edit in WooCommerce). First piece of moving every listing through checkout.

= 0.31.12 =
* After submitting a listing you now get a dedicated thank-you screen (checkmark, message, and next-step buttons) instead of a small success banner sitting on top of the still-visible form.

= 0.31.11 =
* Add/edit listing forms now let people suggest a special feature that isn't in the list. It's created but held for review — kept out of the public feature filters and off the public listing until an admin approves it (Listings → Features → Approve), or deletes it to veto.

= 0.31.10 =
* Per-type landing grids ([lis_listing_grid type="..."]) now show the keyword + category search box above them by default, matching the main archive (use search="no" to hide).
* Fixed the listings archive being squeezed into a narrow centered column with big side gaps — it now fills the theme content width.

= 0.31.9 =
* [lis_listing_search] gained a `directory="..."` attribute that locks the form to one directory type and hides the "All Directories" box — use `[lis_listing_search directory="local-business"]` for a Local-Directory-only search.
* Search fields are slightly larger/easier to tap.
* Vendor Showcase business-card ticker cards are now perfect rectangles (no rounded corners) so full-bleed card images no longer leave dead white space in the corners.

= 0.31.8 =
* Add-listing photos now show live thumbnail previews when picked or dropped (a markup bug had stopped them rendering).
* Oversized (>2MB) or wrong-type photos are now caught the instant they're chosen, with an inline message — so a bad photo no longer fails on submit and wipes the whole multi-step form.

= 0.31.7 =
* Single listing gallery now adapts to the number of photos: one photo fills as a full-width hero instead of a small thumbnail; multiple photos flow in an even grid.

= 0.31.6 =
* Single listing page: moved contact info + map into the right sidebar so the two-column layout stays balanced (no more big empty gap when a listing has no business hours); sticky sidebar, taller map, and full-width fallback when there is nothing for the sidebar.

= 0.31.5 =
* Vendor Showcase generator now syncs stock on build: categories that already have an active vendor start out-of-stock (their spot is not buyable). Re-run the generator to apply.

= 0.31.4 =
* Added a "Create $0 test spot" button (settings page) that spins up a disposable free, non-subscription category product, so the vendor pay-to-submit flow can be tested end-to-end without a card or charge.

= 0.31.3 =
* Vendor Showcase generator fix: the product-page Vendor Category dropdown now resolves correctly for every category (names with "&", dashes or accents were previously read as "Any"). Re-running the generator repairs a product built by the previous version.

= 0.31.2 =
* Vendor Showcase: added a one-click generator on the LIS Directory
  settings page that builds the "Vendor Showcase" subscription product —
  a Monthly ($100/mo) and Annually ($1,000/yr) spot for every vendor
  category, each limited to one vendor (stock of 1). Created as a draft;
  safe to re-run (only fills gaps).

= 0.31.1 =
* Listings archive cards: fixed the Grid/List/Map view-toggle icons,
  which the theme's global button styles were inflating to full width
  and hiding — they now render as proper compact icon buttons.
* Listings archive cards: the category badge now sits in the card's
  top-right corner instead of below the title, and the badge/rating
  meta-row is hidden entirely when a card has nothing else to show
  (no more empty gap under the title).

= 0.31.0 =
* Vendor Showcase: new optional Business Card upload (front-end
  submission form + admin meta box), and a new
  `[lis_preferred_vendor_ticker style="business-cards"]` display that
  shows each vendor's uploaded card image directly instead of the
  usual name/tagline card.

= 0.30.9 =
* Widened the Vendor Showcase ticker's cards to match the standalone
  Single Card's proportions - long names/taglines no longer wrap onto
  extra lines and blow up the ticker's height.

= 0.30.8 =
* Removed Compare Listings entirely - the "+ Compare" button, the
  comparison table shortcode, and all related CSS/JS.

= 0.30.7 =
* Correction: 0.30.6 targeted the wrong cart type (this site's Cart/
  Checkout are classic templates, not WooCommerce Blocks). Now uses
  woocommerce_cart_item_name / woocommerce_order_item_name, confirmed
  working against the live cart - "Feature My Listing" now actually
  shows "— Featuring: <listing name>".

= 0.30.6 =
* The "Feature My Listing" cart line now shows which listing it's for
  (e.g. "Feature My Listing — Featuring: Panhandle Cone and Coffee"),
  using WooCommerce's Store API since this site's Cart/Checkout use
  WooCommerce Blocks rather than the classic templates.

= 0.30.5 =
* The progress bar and each step's small section label ("GET STARTED",
  etc.) now stay pinned at the top instead of moving with the rest of
  the centered content - only the question/answer block below them
  centers vertically.

= 0.30.4 =
* Correction: the wizard now centers vertically on the page (in the
  space between breadcrumb and footer) with left-aligned text, instead
  of the 0.30.3 behavior of centering horizontally with centered text.

= 0.30.3 =
* Centered the Add Listing / Edit Listing onboarding block on the page
  and center-aligned each step's label/question/hint text, instead of
  sitting flush against the left edge with empty space on the right.

= 0.30.2 =
* Simplified the Add Listing intro fork cards - title only, with a "?"
  tooltip for the explanation instead of always-visible text.
* Fixed spacing: progress bar no longer sits flush against the
  breadcrumb bar, and "GET STARTED" now reads as part of the progress
  bar instead of floating separately.

= 0.30.1 =
* Fixed: the real Submit button on the new Add Listing / Edit Listing
  review screen was invisible - present but never un-hidden, so no
  submission could actually go through. Caught by live click-through
  testing right after 0.30.0 shipped.

= 0.30.0 =
* Add Listing is now a real one-question-per-panel onboarding flow
  (progress bar, big bold question, clean input, elegant transition),
  ending in a review screen before the real submit.
* New: choose "Find it on Google" or "Enter details manually" right at
  the start - the Google path skips the Address/Phone/Website/Hours
  steps since they get auto-filled, landing you on Category next.
* Fixed: Add Listing / Edit Listing form inputs were rendering totally
  unstyled and the raw file picker wasn't hidden, due to a leftover CSS
  class mismatch from an earlier refactor - now fixed.

= 0.29.1 =
* Rebuilt Places autocomplete on Google's new PlaceAutocompleteElement -
  the old widget doesn't work for Google Cloud projects created after
  March 2025, caught by live-testing on the real form.
* Bigger, bolder step headings and a "Step X of Y" label right above
  each question, closer to a SurveyMonkey-style feel.

= 0.29.0 =
* Added a progress bar to the Add/Edit Listing wizard.
* Added Google Places autocomplete: pick your business from the dropdown
  on the Business Name field and Address, Phone, Website, and Business
  Hours all auto-fill from Google's data.

= 0.28.1 =
* Wizard polish: Submit for Review now only shows on the last step,
  caught by live-testing the actual form for the first time this
  session.

= 0.28.0 =
* Add Listing / Edit Listing are now a real multi-step wizard with a
  sidebar step list, matching Directorist's own flow — same single-page
  form underneath, just one section shown at a time.

= 0.27.0 =
* Description field on the Add Listing / Edit Listing forms is now a
  real rich-text editor instead of plain text. Reworked section styling
  to be airier and less boxy, taking cues from Directorist's own form.

= 0.26.0 =
* Fixed a real bug: the Add Listing form never loaded its own CSS, so
  it's been rendering completely unstyled. Also redesigned the photo
  upload as a proper dropzone with drag-and-drop and live previews.

= 0.25.0 =
* Added Grid/List/Map view toggle buttons and a Sort By dropdown to the
  listings archive, matching Directorist's own toolbar. Map view needs a
  Google Maps API key configured (same one used for the single-listing
  map).

= 0.24.4 =
* The search widget's Features filter is now a type-to-filter picker
  (type to narrow, click to add as a chip) instead of a wall of
  checkboxes. Works the same with JavaScript off.

= 0.24.3 =
* Fixes a duplicate `endif` that broke v0.24.2 — CI caught it before
  release, v0.24.2 was never installed anywhere.

= 0.24.2 =
* Maps now uses the Maps JavaScript API + client-side geocoding instead
  of the Embed API, matching the API key that was actually set up
  (scoped to Maps JavaScript API + Geocoding API).

= 0.24.1 =
* Fixed a real bug: the new Settings fields (Maps API key, Featured
  Listing Product, Listing Edit Page) never actually worked via the REST
  API because they were registered on the wrong hook. wp-admin itself
  was unaffected — this only blocked me from configuring them for you
  via the API.

= 0.24.0 =
* Added an embedded Google Maps map on each listing's page (needs a Maps
  API key configured under Settings > LIS Directory Settings).
* Created two new WooCommerce products for review: Featured Listing
  ($18/mo or $150/yr) and Standard Listing ($8/mo or $60/yr, not yet
  wired to anything). Both are drafts — review and publish when ready.

= 0.23.1 =
* Fixes a PHP syntax error in v0.23.0's Settings page (mismatched
  if/else syntax). v0.23.0's zip was never released — caught by CI
  before install.

= 0.23.0 =
* Added a Featured Listing paid upgrade (WooCommerce-backed, configured
  under Settings > LIS Directory Settings). Owners can feature their own
  published listing from the Dashboard. No real purchase has been made —
  run one real test purchase yourself before relying on it.

= 0.22.1 =
* Internal prep for migrating the 108 real Directorist listings — no
  user-facing change.

= 0.22.0 =
* Fixed a real bug: the one real listing (Talus Rock Retreat) had reviews
  turned off from before this CPT supported comments, so its Reviews
  section rendered nothing — not even a login prompt. Fixed live, and
  hardened new submissions so it can't happen again.
* Added Mark as Sold / Rented for Real Estate listings, with a badge and
  a Dashboard toggle for the listing owner.
* Added a front-end listing edit form (`[lis_listing_edit]`) so listing
  owners can update their own listing without needing wp-admin access.

= 0.21.0 =
* Fixed a real bug: the search/filter widget only ever appeared on the
  separate draft preview page, never on the actual live `/listings/`
  archive. It's now embedded directly at the top of the real archive and
  category pages.

= 0.20.1 =
* QA pass on the whole Directorist-parity build (v0.17.0–v0.20.0): fixed
  a missing width rule on the search widget's "More Filters" field
  wrappers. Everything else checked out.

= 0.20.0 =
* Directorist parity, phase 5: listings can now have FAQs (admin-managed,
  dynamic add/remove rows, plain accordion display) — the one gap that
  had been explicitly flagged as not-yet-built since early on.

= 0.19.0 =
* Directorist parity, phase 4 (see DIRECTORIST_PARITY_PLAN.md): added
  `[lis_listing_grid]`, 6 demo listings (Real Estate Sale/Rent, Job
  Listing — clearly `[Demo]`-labeled, since there's no real Directorist
  data for those three directory types to seed from), and the actual
  draft page tree ("LIS Directory (Preview)" + 8 child pages) for review.

= 0.18.0 =
* Directorist parity, phases 2+3 (see DIRECTORIST_PARITY_PLAN.md):
  imported the full 233-term category tree; added the `[lis_listing_search]`
  filter widget; added `[lis_listing_compare]`, `[lis_listing_author_profile]`,
  and `[lis_listing_dashboard]`.

= 0.17.0 =
* Directorist parity, phase 1 (see DIRECTORIST_PARITY_PLAN.md): `lis_listing`
  now supports the same four directory types Directorist runs live (Local
  Business, Real Estate Sale, Real Estate Rent, Job Listing), with
  type-specific fields (beds/baths/sqft, salary/employment type) shown on
  both the single listing page and archive cards.

= 0.16.4 =
* Fixed: the search widget's "More Filters" panel rendered fully expanded
  and unstyled when previewing an unpublished draft (Directorist's own CSS
  wasn't loading). Live/published pages were never affected. See CHANGELOG.md
  for the root cause.

= 0.16.3 =
* `[lis_preferred_vendor_heading]` now renders the real "Vendor Showcase"
  lockup image instead of the old "LIS Partners" one.
* Vendor cards now show a small "Sponsored" badge in the top-right corner.

= 0.16.2 =
* Renamed "Preferred Vendor" to "Vendor Showcase" throughout wp-admin labels,
  activation notices, the submission form, and WooCommerce thank-you page
  copy — steers away from language that reads as a personal endorsement,
  since vendors are paying for placement, not being individually vouched for.
  Shortcode tags (`[lis_preferred_vendor_card]` etc.), the CPT slug, and meta
  keys are unchanged — this is a copy-only rename, no data migration needed.

= 0.16.1 =
* Fixed a fatal error: `register_comment_meta()` isn't a real WordPress
  function (only `register_post_meta()`/`register_term_meta()` exist) —
  crashed the entire site on every page load via `init`. Replaced with the
  correct `register_meta( 'comment', ... )` call. Caught live immediately
  after 0.16.0 installed; site was down for a few minutes while fixed.

= 0.16.0 =
* Matched Directorist's real field set (checked live, not guessed):
  submission form now also covers video, services, and social media links.
* Reviews — built on WordPress's own comment system (star rating as
  comment meta), so it inherits Akismet spam filtering and the existing
  Comments moderation screen for free. Reviews always land pending,
  regardless of the site's general comment-approval setting.
* Featured / Popular / Owner Verified badges. Featured and Verified are
  editorial (admin-set or claim-approved); Popular is computed from a
  view-count threshold, tracked automatically per listing.
* Bookmark (save/unsave, per logged-in user), Share (native share sheet or
  copy-link), Report, and Claim — Report/Claim share one lightweight
  "Claims & Reports" admin screen; approving a claim marks the listing
  Owner Verified and reassigns it to the claimant.
* Not built: an embedded interactive map (needs a Google Maps API key this
  project doesn't have yet — the address already links out to Google Maps)
  and FAQs (needs a dynamic add/remove-row admin UI).

= 0.15.0 =
* Added `[lis_listing_submit]` — front-end listing submission, same pattern
  as the vendor form (login required, honeypot, real server-side photo
  validation). Submissions land as native WP Pending for admin review.

= 0.14.0 =
* Contact block now labels each field (Address/Phone/Website/Email) and
  links the address to Google Maps.
* Business Hours zebra striping removed (theme default, now explicitly off).
* Archive cards show up to 3 feature tags.
* Single/archive layouts widened (1000px/1100px → 1300px/1400px) — was
  reading as squished on wide screens.

= 0.13.3 =
* Fixed a leftover top/left border on the Business Hours box — 0.13.1 reset
  the border on individual table cells but not the `<table>` element
  itself, so the theme's own outer table border was still showing through.

= 0.13.2 =
* Price is no longer displayed on cards or single listing pages — the
  field/data stays intact, just not shown for now.

= 0.13.1 =
* Fixed the Business Hours table showing a busy full grid of borders — the
  theme's default table styling was bleeding through since the CSS never
  explicitly reset it. Now clean row dividers instead of a full grid.

= 0.13.0 =
* Listings gained: photo gallery, price, business hours with a live "Open
  now"/"Closed" badge, a Features taxonomy (checklist), and video embed
  (YouTube/Vimeo). Archive cards now show category/price/open-status too.
  Reviews/ratings and appointment booking are NOT included — those need a
  real data model, not a template addition.

= 0.12.0 =
* New `lis_listing` post type + `lis_listing_category` taxonomy — the start
  of a self-hosted listings framework meant to eventually replace
  Directorist (same approach as LIS Events replacing EventON). Framework
  only: CPT, basic contact-info fields, and basic archive/single templates
  at `/listings/`. No migration of Directorist's existing categories or
  listings yet — that's a deliberate later step.

= 0.11.0 =
* Preferred vendor cards can now appear automatically on Directorist's own
  category and single-listing pages — map a `lis_vendor_category` term to a
  Directorist category (new dropdown on the category edit screen) and its
  card shows up there once it has an active vendor. Unmapped categories are
  unaffected.

= 0.10.0 =
* Vendors now upload one logo instead of two. Removed the separate "Logo —
  Black & White" upload entirely; a black or white version is derived from
  the single logo via CSS when needed (used by
  `[lis_preferred_vendor_ticker style="logos" tone="black|white"]`). The
  logo upload is now PNG-only (was PNG or JPG) — required for the recolor
  effect to look clean instead of showing a solid box.
* WooCommerce: disabled "customers can buy without subscribing" on the LIS
  Partner product — a one-time purchase would never enter the subscription
  lifecycle the auto-expire logic depends on.

= 0.9.0 =
* Added GitHub-based auto-update (same mechanism as LIS Events) — one-click
  "Update available" in Plugins straight from the private GitHub repo,
  instead of manual zip installs going forward. Requires a
  `LIS_DIRECTORY_UPDATE_TOKEN` constant in `wp-config.php` (see README.md);
  without it the plugin works exactly as before, just with no update
  banner.

= 0.8.5 =
* The 0.8.3 heading-to-card margin fix wasn't actually taking effect live —
  the theme's own CSS (`.entry-content h2`) has higher selector specificity
  than the plugin's plain `.lis-pv-heading` class, so it kept winning.
  Added `!important` so the override actually applies. Confirmed via
  computed styles: margin-bottom was still 19.6px before this, now 8px.

= 0.8.4 =
* Reverted the "LIS Partners" lockup image to the exact original file as
  supplied — no cropping/spacing edits. The only gap that was ever supposed
  to change is the space between the heading and the card/ticker below it
  (fixed in 0.8.3), not the spacing baked into the logo itself.

= 0.8.3 =
* Reduced the space between the "LIS Partners" heading and the card/ticker
  underneath it — was inheriting the theme's default `<h2>` bottom margin
  (~20px), now a tight 8px.
* 0.8.1 trimmed the gap between the logomark and "Partners" too far (they
  read as crowded together); widened it back out to a middle ground.

= 0.8.2 =
* The heading lockup image URL now carries `?ver=<plugin version>` so
  browser/host caching can't keep serving a stale copy of the image across
  plugin updates.

= 0.8.1 =
* Tightened the whitespace baked into the "LIS Partners" lockup image
  between the logomark and "Partners" — was too wide as originally supplied.

= 0.8.0 =
* `[lis_preferred_vendor_heading]` now renders the client-supplied, pre-built
  "LIS Partners" lockup image instead of assembling the icon and "Partners"
  text separately — fixes the alignment issues in 0.7.1–0.7.3 by construction
  (it's one already-designed image, not two pieces this plugin was trying to
  line up).

= 0.7.3 =
* `[lis_preferred_vendor_heading]` — 0.7.2's alignment fix wasn't enough.
  Switched to top-aligning the icon with the text (instead of centering)
  and tightened the icon size and gap so it reads as one solid lockup.

= 0.7.2 =
* `[lis_preferred_vendor_heading]` — the icon was optically misaligned with
  the "Partners" text (its own internal weight sits low in its box) and the
  whole heading read too large next to a compact card. Nudged the icon up
  and shrunk the heading to a fixed, smaller size so it reads as a label
  above the card/ticker instead of a full section title.

= 0.7.1 =
* `[lis_preferred_vendor_heading]` now forces the "Partners" text to a bold
  sans-serif font instead of inheriting the theme's serif heading font —
  matches the brand's own "Lis" logotype instead of clashing with it.

= 0.7.0 =
* Added `[lis_preferred_vendor_heading]` — the "LIS Partners" branded
  heading (LIS logomark + "Partners" text) meant to sit above a card or
  ticker. Renders as a real `<h2>` (or `tag="h3"` etc.) for layout/spacing,
  with its own bold sans-serif type treatment for the text.

= 0.6.0 =
* Added: a vendor must have a real, published Local Directory (Directorist)
  listing before they can be set Active — enforced server-side at both
  submission and activation, reusing the existing Link URL field (relabeled
  "Directorist Listing URL").
* Added: `[lis_preferred_vendor_ticker style="logos"]` — a logos-only ticker
  variant alongside the original card version.
* Fixed: readme's WooCommerce setup section described the wrong product
  type (classic "Variable subscription" instead of this site's actual
  WCSATT-based "Subscriptions" tab on a Variable product).

= 0.5.2 =
* Fixed: both shortcodes' CSS was `echo`'d directly instead of being included
  in the shortcode's own returned string. Invisible on a normal page, but
  corrupted any context that captures the_content as a string instead of
  streaming it directly — e.g. the WordPress REST API's content.rendered
  field, which broke entirely (returned raw HTML instead of JSON) for a page
  containing either shortcode.

= 0.5.1 =
* Fixed: a brand-new vendor whose first save was blocked by the category
  lock (e.g. status set to Active on creation, but that category was already
  taken) ended up with no status meta at all — invisible to the Pending
  filter and missing its Approve/Reject row actions. Now defaults to Pending.

= 0.5.0 =
* Added WooCommerce Subscriptions integration: variation category tagging,
  automatic stock sync, thank-you page submission link, and auto-expire on
  subscription cancellation.

= 0.4.0 =
* Added `[lis_preferred_vendor_submit]` front-end form (logged-in vendors only).
* Added wp-admin status filter + one-click Approve/Reject row actions.

= 0.3.0 =
* Added `[lis_preferred_vendor_card]` and `[lis_preferred_vendor_ticker]` shortcodes.

= 0.2.0 =
* Server-side category-lock enforcement: blocks a second vendor from going
  Active in a category that already has one.

= 0.1.0 =
* Scaffold: CPT + admin fields, no front-end yet.
