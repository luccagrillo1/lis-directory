=== LIS Directory ===
Contributors: Lucca Grillo
Tags: directory, preferred vendors, custom post type
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.16.1

Preferred Vendor Program for Living in Sandpoint. Runs alongside Directorist without touching it.

== Description ==

LIS Directory is a purpose-built plugin for livinginsandpoint.com's Preferred
Vendor Program — a small number of paying vendors get an exclusive "preferred"
slot per category (e.g. one preferred Insurance vendor, one preferred Plumber),
displayed via shortcodes. It does not replace or modify Directorist.

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
  them from the Preferred Vendors list in wp-admin.
* `[lis_listing_submit]` — front-end listing submission form for the
  self-hosted listings framework. Requires a WordPress account. Business
  name, category, and a photo (real PNG/JPG, server-validated, 2MB cap) are
  required; address/phone/website/description are optional. Submissions
  land as native WordPress "Pending" — reviewed and published from the
  normal Listings admin editor, no separate approve/reject workflow needed
  (unlike Preferred Vendor, there's no "one active per category" rule to
  enforce here). Additional photos, business hours, and features are
  admin-added after first publish, not part of this form yet.

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

1. Create one **Variable product** ("Preferred Vendor" / "LIS Partner") in
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
5. Under **Preferred Vendors > Settings**, pick the page that has the
   `[lis_preferred_vendor_submit]` shortcode.
6. That's it — stock on tagged variations now follows category availability
   automatically, and buyers get a submission link on the thank-you page.

== Changelog ==

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
