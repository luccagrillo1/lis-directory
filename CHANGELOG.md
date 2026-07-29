# LIS Directory — Detailed Changelog

All notable changes to the LIS Directory plugin. Format inspired by [Keep a Changelog](https://keepachangelog.com/).
For each version: user-facing changes, files touched, data model deltas, technical decisions, and known limitations.

This file is the authoritative project history.

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
