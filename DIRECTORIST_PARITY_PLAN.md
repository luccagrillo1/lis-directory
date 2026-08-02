# LIS Directory ⇄ Directorist Framework Parity — Build Plan

Started 2026-08-01. Goal (verbatim from Lucca): "everything framework wise
that's live on the site rn" gets a LIS Directory counterpart, built on a
**separate drafted page tree** — nothing touches or replaces the live
Directorist-powered pages. Running autonomously; no check-ins expected unless
genuinely blocked on a decision only Lucca can make.

## What's actually live right now (verified via REST API, 2026-08-01)

Directorist powers FOUR directory types on livinginsandpoint.com, not just
the one "Local Directory" I'd been treating as the whole scope:

- **Local Business Directory** — `/services/local-directory/` (108 published
  listings, 233 categories in a shared flat-ish `at_biz_dir-category` taxonomy
  that mixes business AND real-estate-flavored terms like "Apartment",
  "Agricultural Land" — confirms categories are NOT per-directory-type, one
  shared tree)
- **Real Estate Directory - Sale** — `/services/real-estate/all-listings-real-estate/`
- **Real Estate Directory - Rent** — `/services/real-estate/rentals-all-listings/`
- **Job Listing Directory** — page labeled "Job Listings" but its slug is
  `/services/rental-listings/` (mislabeled/reused on the live site, not
  something to fix here — out of scope, it's Directorist's own page, not mine)

Full page tree under Local Directory (parent 2883) — these are the pages
that need LIS counterparts: All Listings, Add Listing, Single Category,
Compare Listings, Author Profile, Dashboard, Search Result. Plus
Confirm Payment Details / Transaction Failure / Payment Receipt — these are
WooCommerce checkout redirect targets for Directorist's Pricing Plans, not
real content pages; **deliberately out of scope** (see below).

## Scope decisions (made autonomously, documented so they're not mistaken for oversights)

**Building:**
1. Multi-directory-type support in `lis_listing` (taxonomy: Local Business /
   Real Estate — Sale / Real Estate — Rent / Job Listing), so one CPT powers
   all four the way Directorist's `at_biz_dir` does.
2. Full 233-term category tree, imported 1:1 from Directorist's real
   `at_biz_dir-category` into `lis_listing_category` (was only ~1 term deep
   before this — the earlier "map the rest" session work populated
   `lis_vendor_category`, a *different* taxonomy for the Vendor Showcase
   program, not this one. Easy to conflate; they're unrelated.)
3. Type-specific fields: Real Estate gets bedrooms/bathrooms/square footage
   (price meta already existed, generic); Job Listing gets
   salary/employment-type/application method.
4. A real front-end search/filter widget — category, keyword, price range,
   open-now, features — the single most visible piece Directorist has that
   `lis_listing` didn't. Renders results via query vars on the archive, no
   AJAX (simpler, works without JS, matches how the rest of this plugin
   already works).
5. Compare Listings (side-by-side, small number of picks via a cookie-backed
   list, same spirit as the existing bookmark feature).
6. Author/vendor public profile page (all published listings by one author).
7. Front-end "Dashboard" — logged-in user's own listings, with edit access
   gated to `post_author === current user`.
8. A handful of clearly-labeled demo listings per type (not a full 108-listing
   migration — see below) so the framework has something real to show.
9. A brand-new **draft** page tree mirroring the live one, built entirely
   with `lis_listing` shortcodes, parented under a new top-level draft page
   so it's unambiguously separate from the live "All Services" tree.

**Explicitly NOT building** (and why):

- **Bulk migration of the real 108 listings / 233 categories' worth of live
  business data.** "Framework wise" is capability parity, not a content
  migration — that's a separate, much bigger, client-involved data-mapping
  project (matches what was already flagged as "longer-term, not urgent" in
  earlier planning). The 233 *categories* ARE being imported (that's
  structural, the search widget needs a real tree to be useful) — the
  *listings* are not.
- **Directorist's Pricing Plans / WooCommerce checkout flow** (Confirm
  Payment Details, Transaction Failure, Payment Receipt pages). This is real
  payment infrastructure — building and "testing" it would mean exercising
  actual financial transaction code paths, which I won't do autonomously
  regardless of framing. The Vendor Showcase program already has its own,
  separately-reasoned-about WooCommerce Subscriptions integration; a second
  parallel one for general listings is a real scope decision for Lucca, not
  something to improvise.
- **Coupons, Search Alerts (saved-search email notifications), Live Chat,
  Booking/appointments, Mark as Sold, Post Your Need, Digital Marketplace,
  Ads Manager, Announcement broadcast, Maps (needs a Google Maps API key
  that isn't available), GamiPress/BuddyPress/BuddyBoss/WPML/Oxygen
  integrations, Directory Linking.** These are monetization/social/
  third-party-integration bolt-ons, not core directory-browsing framework.
  Building shallow stubs of all of them would spread effort thin for
  features nobody's asked to use yet. Each is a reasonable follow-up request
  on its own if actually wanted later.

## Phases (versioned incrementally, each pushed to GitHub as it lands)

1. **v0.17.0** ✅ — Directory-type taxonomy + type-specific meta fields
2. **v0.18.0** ✅ — Full category tree import (233 terms) + search/filter
   widget + Compare Listings + Author Profile + front-end Dashboard
   (phases 2 and 3 landed together in one version — no reason to split the
   release once both were done)
3. **v0.19.0** — Demo listings per type + new draft page tree assembled from
   all of the above

Each phase gets its own CHANGELOG.md entry, version bump, commit, tag, and
GitHub release (zip attached), matching this plugin's existing convention.
Progress tracked via TaskCreate/TaskUpdate in-session; this file is the
durable record in case of a context compaction mid-run.

## Status log

- 2026-08-01: Plan written. Starting Phase 1.
- 2026-08-01: v0.17.0 shipped (directory types + Real Estate/Job Listing
  fields).
- 2026-08-01: v0.18.0 shipped — 233-term category tree imported, search
  widget, Compare Listings, Author Profile, Dashboard. Moving to Phase 4
  (demo listings + the actual draft page tree, the deliverable Lucca can
  click through).
