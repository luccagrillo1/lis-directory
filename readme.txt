=== LIS Directory ===
Contributors: Lucca Grillo
Tags: directory, preferred vendors, custom post type
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.6.0

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
* Admin meta box: tagline, Directorist Listing URL, color/B&W logo uploads,
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
* `[lis_preferred_vendor_submit]` — logged-in-only front-end submission form;
  submissions always land as Pending for admin review
* WooCommerce Subscriptions integration: tag a product variation with a
  vendor category (LIS Directory > Settings + a per-variation field), its
  stock auto-syncs to whether that category is taken, the thank-you page
  links buyers straight to a pre-scoped submission form, and a cancelled/
  expired subscription automatically expires the linked vendor

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
* `[lis_preferred_vendor_submit]` — vendor submission form. Requires a
  WordPress account (shows a log in / register prompt otherwise). Requires a
  Local Directory Listing URL — the vendor must already have a real,
  published listing on Directorist; the URL is validated server-side
  (`url_to_postid()` + post type/status check), not just requested in the
  form. Categories that already have an active vendor are still selectable,
  labeled "currently taken - apply to be waitlisted". Submissions are never
  auto-published; they land as Pending until an admin approves or rejects
  them from the Preferred Vendors list in wp-admin.

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
