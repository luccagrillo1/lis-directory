# LIS Directory: theming contract

Every color, radius, shadow and font the plugin draws with comes from a CSS custom property,
so the site can restyle it by **setting variables**, not by overriding selectors with
`!important`. This file is the contract the site's CSS is written against. Variables and
classes listed here are kept stable across releases. Renaming or removing one counts as a
breaking change and gets called out in the CHANGELOG.

## How it works

Each declaration reads a chain, most specific first:

```css
.lis-listing-search-submit {
	background: var(--lis-dir-search-submit-bg,        /* 1. this component    */
	            var(--lis-dir-btn-bg,                   /* 2. shared role       */
	            var(--lis-dir-color-accent,             /* 3. plugin palette    */
	            var(--lis-pine-700, #2C5240))));        /* 4. site token, 5. literal */
}
```

- **The plugin never defines any `--lis-dir-*` variable.** It only reads them, and it never
  defines or redefines the site's `--lis-*` tokens either. Unset means "use the next
  fallback." So a site value set on `:root`, or on any ancestor, always applies, no matter
  what order the stylesheets load in.
- The last fallback is always a literal brand value, so the plugin still looks right with the
  "LIS Design Tokens" snippet switched off (wp-admin, block editor, feeds).
- You can scope overrides: set a variable on `.some-section` to restyle just that area.
- No `!important`, no `@layer`. Selector specificity is unchanged from 0.46.0.

### Example: glass search over a hero photo

```css
.lis-hero {
	--lis-dir-search-bg: rgba(22, 40, 31, .24);
	--lis-dir-search-backdrop: blur(8px) saturate(120%);
	--lis-dir-search-border: 1px solid rgba(251, 250, 246, .14);
	--lis-dir-search-radius: var(--lis-radius-container, 12px);
	--lis-dir-search-padding: 20px 24px;
}
```

## Global variables

Set these to restyle the whole plugin. Component variables fall back to them.

### Palette

| Variable | Falls back to | Final fallback | Used for |
|---|---|---|---|
| `--lis-dir-color-canvas` | `--lis-canvas` | `#FBFAF6` | Soft page-tone fills: panels, thumb placeholders, hover fills, progress track |
| `--lis-dir-color-surface` | `--lis-surface` | `#FFFFFF` | Card / input / button-text white |
| `--lis-dir-color-line` | `--lis-line` | `#DEDFD8` | Hairline borders and dividers |
| `--lis-dir-color-text` | `--lis-ink-900` | `#1A1D19` | Strong body text (content, links in meta box, tooltips on vendor cards) |
| `--lis-dir-color-text-muted` | `--lis-ink-600` | `#5C6159` | Secondary text: labels, hints, excerpts, table headers |
| `--lis-dir-color-accent` | `--lis-pine-700` | `#2C5240` | Primary brand accent: primary buttons, active states, links, positive status |
| `--lis-dir-color-accent-strong` | `--lis-pine-900` | `#16281F` | Hover for primary buttons; wizard question and choice titles |
| `--lis-dir-color-accent-soft` | `--lis-pine-100` | `#E4EBE4` | Pine tint fills: badges, chips, hover backgrounds |
| `--lis-dir-color-accent-border` | `--lis-pine-200` | `#C3D3C8` | Pine tint borders (Places search box, plan-card hover, job “live” status) |
| `--lis-dir-color-commerce` | `--lis-clay-600` | `#A94F2C` | Transaction cues: prices, pay links, the Showcase offer |
| `--lis-dir-color-commerce-strong` | `--lis-clay-700` | `#8C3E20` | Offer-button hover; Featured badge text |
| `--lis-dir-color-commerce-soft` | `--lis-clay-100` | `#F6E8DF` | Clay tint fills (Featured badge, offer banner, “category occupied” warning) |
| `--lis-dir-color-commerce-border` | `--lis-clay-200` | `#E8CDBC` | Clay tint borders |
| `--lis-dir-color-error` | `--lis-error` | `#8E1D33` | Errors, closed / sold / expired |
| `--lis-dir-color-error-soft` | (derived) | `color-mix(error 12%, canvas)` | Error tint fill |
| `--lis-dir-color-error-border` | (derived) | `color-mix(error 32%, canvas)` | Error tint border |
| `--lis-dir-color-info` | `--lis-info` | `#2A5B78` | Info / pending |
| `--lis-dir-color-info-soft` | (derived) | `color-mix(info 12%, canvas)` | Info tint fill |
| `--lis-dir-color-rating` | `--lis-gold-600` | `#96701E` | Review stars only (never text; below 4.5:1 on Canvas) |

### Shape and type

| Variable | Falls back to | Final fallback | Used for |
|---|---|---|---|
| `--lis-dir-radius-control` | `--lis-radius-control` | `6px` | Buttons, inputs, chips, badges, tags, small thumbnails, tooltips, menu items |
| `--lis-dir-radius-container` | `--lis-radius-container` | `12px` | Cards, panels, notices, dropdown menus, maps, galleries, videos |
| `--lis-dir-font-heading` | none | `'Gabarito', sans-serif` | Card titles, vendor-card name, job-card title, wizard question, section/hours/claim headings, thank-you title |
| `--lis-dir-font-body` | none | `inherit` | Component roots (search form, archive, single, wizard, dashboard, vendor card, job pages), buttons and inputs |
| `--lis-dir-focus-ring` | none | `0 0 0 3px color-mix(in srgb, var(--lis-dir-color-accent) 15%, transparent)` | Focused search inputs; the selected plan card |

The plugin doesn't enqueue fonts. Gabarito and Source Sans 3 are self-hosted by the site.
Radii of `50%` (avatars, info dots), `0` (underline inputs, business cards, segmented
toggle buttons) and the `999px` wizard progress track are geometry, not scale values, so
they stay literal. The progress track is still exposed as `--lis-dir-wizard-progress-radius`.

## Component variables (221)

Groups follow the markup. "Styles" lists the exact selector and property each variable
feeds. Where a component variable falls back to a shared one (e.g. `--lis-dir-btn-bg`),
setting the shared one changes every component that hasn't been given its own value.

### Buttons

Shared by every primary / secondary button. Component button variables (search submit, wizard, jobs…) fall back to these, so setting `--lis-dir-btn-bg` restyles all primary buttons at once.

| Variable | Default (fallback chain) | Styles |
|---|---|---|
| `--lis-dir-action-btn-bg` | `--lis-dir-color-surface` → `--lis-surface` → `#FFFFFF` | `.lis-listing-bookmark-btn, .lis-listing-share-btn` background |
| `--lis-dir-btn-bg` | `--lis-dir-color-accent` → `--lis-pine-700` → `#2C5240` | `.lis-job-btn--primary` background<br>`.lis-listing-claim-submit` background<br>`.lis-listing-search-submit` background<br>`.lis-listing-submit-button` background<br>`.lis-listing-thankyou-btn` background<br>`.lis-listing-wizard-next` background |
| `--lis-dir-btn-bg-hover` | `--lis-dir-color-accent-strong` → `--lis-pine-900` → `#16281F` | `.lis-job-btn--primary:hover` background<br>`.lis-listing-claim-submit:hover` background<br>`.lis-listing-search-submit:hover` background<br>`.lis-listing-submit-button:hover` background<br>`.lis-listing-thankyou-btn:hover` background<br>`.lis-listing-wizard-next:hover` background |
| `--lis-dir-btn-border` | `1px solid var(--lis-dir-color-accent)` | `.lis-listing-thankyou-btn` border |
| `--lis-dir-btn-border-hover` | `--lis-dir-color-accent-strong` → `--lis-pine-900` → `#16281F` | `.lis-listing-thankyou-btn:hover` border-color |
| `--lis-dir-btn-radius` | `--lis-dir-radius-control` → `--lis-radius-control` → `6px` | `.lis-job-btn` border-radius<br>`.lis-listing-bookmark-btn, .lis-listing-share-btn` border-radius<br>`.lis-listing-claim-btn` border-radius<br>`.lis-listing-claim-submit` border-radius<br>`.lis-listing-draft-save` border-radius<br>`.lis-listing-offer-button` border-radius<br>`.lis-listing-search-submit` border-radius<br>`.lis-listing-submit-button` border-radius<br>`.lis-listing-thankyou-btn` border-radius<br>`.lis-listing-wizard-back, .lis-listing-wizard-next` border-radius |
| `--lis-dir-btn-secondary-bg` | `transparent` | `.lis-listing-draft-save` background<br>`.lis-listing-wizard-back` background |
| `--lis-dir-btn-secondary-bg-hover` | `--lis-dir-color-canvas` → `--lis-canvas` → `#FBFAF6` | `.lis-listing-draft-save:hover` background<br>`.lis-listing-wizard-back:hover` background |
| `--lis-dir-btn-secondary-border` | `1px solid var(--lis-dir-color-line)` | `.lis-listing-bookmark-btn, .lis-listing-share-btn` border<br>`.lis-listing-draft-save` border<br>`.lis-listing-wizard-back` border |
| `--lis-dir-btn-secondary-text` | `--lis-dir-color-text-muted` → `--lis-ink-600` → `#5C6159` | `.lis-listing-bookmark-btn, .lis-listing-share-btn` color<br>`.lis-listing-draft-save` color<br>`.lis-listing-wizard-back` color |
| `--lis-dir-btn-text` | `--lis-dir-color-surface` → `--lis-surface` → `#FFFFFF` | `.lis-job-btn--primary:hover` color<br>`.lis-job-btn--primary` color<br>`.lis-listing-claim-submit` color<br>`.lis-listing-search-submit` color<br>`.lis-listing-submit-button` color<br>`.lis-listing-thankyou-btn` color<br>`.lis-listing-wizard-next` color |

### Search form

`[lis_listing_search]` and the search box above archives/grids. The container carries everything the glass recipe needs.

| Variable | Default (fallback chain) | Styles |
|---|---|---|
| `--lis-dir-search-backdrop` | `none` | `.lis-listing-search-form` -webkit-backdrop-filter<br>`.lis-listing-search-form` backdrop-filter |
| `--lis-dir-search-bg` | `transparent` | `.lis-listing-search-form` background |
| `--lis-dir-search-border` | `none` | `.lis-listing-search-form` border |
| `--lis-dir-search-input-bg` | `--lis-dir-input-bg` → `--lis-dir-color-surface` → `--lis-surface` → `#FFFFFF` | `.lis-listing-search-input, .lis-listing-search-select` background |
| `--lis-dir-search-input-border` | `--lis-dir-input-border` → `1px solid var(--lis-dir-color-line)` | `.lis-listing-search-input, .lis-listing-search-select` border |
| `--lis-dir-search-input-border-focus` | `--lis-dir-input-border-focus` → `--lis-dir-color-accent` → `--lis-pine-700` → `#2C5240` | `.lis-listing-search-input:focus, .lis-listing-search-select:focus` border-color |
| `--lis-dir-search-input-focus-ring` | `--lis-dir-focus-ring` → `0 0 0 3px color-mix(in srgb, var(--lis-dir-color-accent) 15%, transparent)` | `.lis-listing-search-input:focus, .lis-listing-search-select:focus` box-shadow |
| `--lis-dir-search-input-radius` | `--lis-dir-input-radius` → `--lis-dir-radius-control` → `--lis-radius-control` → `6px` | `.lis-listing-search-input, .lis-listing-search-select` border-radius |
| `--lis-dir-search-input-text` | `--lis-dir-color-text-muted` → `--lis-ink-600` → `#5C6159` | `.lis-listing-search-input, .lis-listing-search-select` color |
| `--lis-dir-search-label-text` | `--lis-dir-color-text-muted` → `--lis-ink-600` → `#5C6159` | `.lis-listing-search-field-label` color |
| `--lis-dir-search-more-bg` | `--lis-dir-color-surface` → `--lis-surface` → `#FFFFFF` | `.lis-listing-search-more summary` background |
| `--lis-dir-search-more-body-bg` | `--lis-dir-panel-bg` → `--lis-dir-color-canvas` → `--lis-canvas` → `#FBFAF6` | `.lis-listing-search-more-body` background |
| `--lis-dir-search-more-body-border` | `--lis-dir-panel-border` → `1px solid var(--lis-dir-color-line)` | `.lis-listing-search-more-body` border |
| `--lis-dir-search-more-body-radius` | `--lis-dir-panel-radius` → `--lis-dir-radius-container` → `--lis-radius-container` → `12px` | `.lis-listing-search-more-body` border-radius |
| `--lis-dir-search-more-radius` | `--lis-dir-radius-control` → `--lis-radius-control` → `6px` | `.lis-listing-search-more summary` border-radius |
| `--lis-dir-search-more-text` | `--lis-dir-color-accent` → `--lis-pine-700` → `#2C5240` | `.lis-listing-search-more summary` color |
| `--lis-dir-search-padding` | `20px 32px 0` | `.lis-listing-search-form` padding |
| `--lis-dir-search-padding-embedded` | `0` | `.lis-listing-archive .lis-listing-search-form` padding |
| `--lis-dir-search-radius` | `0` | `.lis-listing-search-form` border-radius |
| `--lis-dir-search-reset-text` | `--lis-dir-color-text-muted` → `--lis-ink-600` → `#5C6159` | `.lis-listing-search-reset` color |
| `--lis-dir-search-shadow` | `none` | `.lis-listing-search-form` box-shadow |
| `--lis-dir-search-submit-bg` | `--lis-dir-btn-bg` → `--lis-dir-color-accent` → `--lis-pine-700` → `#2C5240` | `.lis-listing-search-submit` background |
| `--lis-dir-search-submit-bg-hover` | `--lis-dir-btn-bg-hover` → `--lis-dir-color-accent-strong` → `--lis-pine-900` → `#16281F` | `.lis-listing-search-submit:hover` background |
| `--lis-dir-search-submit-radius` | `--lis-dir-btn-radius` → `--lis-dir-radius-control` → `--lis-radius-control` → `6px` | `.lis-listing-search-submit` border-radius |
| `--lis-dir-search-submit-text` | `--lis-dir-btn-text` → `--lis-dir-color-surface` → `--lis-surface` → `#FFFFFF` | `.lis-listing-search-submit` color |

### Form inputs

Bordered text inputs and selects outside the search bar (feature picker, sort, showcase fields, job forms). Search inputs fall back to these too.

| Variable | Default (fallback chain) | Styles |
|---|---|---|
| `--lis-dir-input-bg` | `--lis-dir-color-surface` → `--lis-surface` → `#FFFFFF` | `.lis-job-filters input, .lis-job-filters select, .lis-job-form input[type="text"], .lis-job-form input[type="url"], .lis-job-form input[type="email"], .lis-job-form input[type="number"], .lis-job-form input[type="date"], .lis-job-form select` background<br>`.lis-listing-feature-input` background<br>`.lis-listing-feature-suggest input[type="text"]` background<br>`.lis-listing-search-input, .lis-listing-search-select` background<br>`.lis-listing-showcase-fields input[type="text"], .lis-listing-showcase-fields select` background<br>`.lis-listing-sort-label select` background |
| `--lis-dir-input-border` | `1px solid var(--lis-dir-color-line)` | `.lis-job-filters input, .lis-job-filters select, .lis-job-form input[type="text"], .lis-job-form input[type="url"], .lis-job-form input[type="email"], .lis-job-form input[type="number"], .lis-job-form input[type="date"], .lis-job-form select` border<br>`.lis-listing-feature-input` border<br>`.lis-listing-feature-suggest input[type="text"]` border<br>`.lis-listing-search-input, .lis-listing-search-select` border<br>`.lis-listing-showcase-fields input[type="text"], .lis-listing-showcase-fields select` border<br>`.lis-listing-sort-label select` border |
| `--lis-dir-input-border-focus` | `--lis-dir-color-accent` → `--lis-pine-700` → `#2C5240` | `.lis-listing-search-input:focus, .lis-listing-search-select:focus` border-color |
| `--lis-dir-input-radius` | `--lis-dir-radius-control` → `--lis-radius-control` → `6px` | `.lis-job-filters input, .lis-job-filters select, .lis-job-form input[type="text"], .lis-job-form input[type="url"], .lis-job-form input[type="email"], .lis-job-form input[type="number"], .lis-job-form input[type="date"], .lis-job-form select` border-radius<br>`.lis-listing-feature-input` border-radius<br>`.lis-listing-feature-suggest input[type="text"]` border-radius<br>`.lis-listing-search-input, .lis-listing-search-select` border-radius<br>`.lis-listing-showcase-fields input[type="text"], .lis-listing-showcase-fields select` border-radius<br>`.lis-listing-sort-label select` border-radius |
| `--lis-dir-input-text` | `--lis-dir-color-text` → `--lis-ink-900` → `#1A1D19` | `.lis-job-filters input, .lis-job-filters select, .lis-job-form input[type="text"], .lis-job-form input[type="url"], .lis-job-form input[type="email"], .lis-job-form input[type="number"], .lis-job-form input[type="date"], .lis-job-form select` color |

### Dropdowns & autocomplete

Feature-picker suggestions and the business-name (Google Places) autocomplete menu.

| Variable | Default (fallback chain) | Styles |
|---|---|---|
| `--lis-dir-dropdown-bg` | `--lis-dir-color-surface` → `--lis-surface` → `#FFFFFF` | `.lis-listing-feature-suggestions` background<br>`.lis-listing-place-ac-menu` background |
| `--lis-dir-dropdown-border` | `1px solid var(--lis-dir-color-line)` | `.lis-listing-feature-suggestions` border<br>`.lis-listing-place-ac-menu` border |
| `--lis-dir-dropdown-item-bg-hover` | `--lis-dir-color-accent-soft` → `--lis-pine-100` → `#E4EBE4` | `.lis-listing-place-ac-item:hover, .lis-listing-place-ac-item.is-active` background |
| `--lis-dir-dropdown-item-radius` | `--lis-dir-radius-control` → `--lis-radius-control` → `6px` | `.lis-listing-place-ac-item` border-radius |
| `--lis-dir-dropdown-radius` | `--lis-dir-radius-container` → `--lis-radius-container` → `12px` | `.lis-listing-feature-suggestions` border-radius<br>`.lis-listing-place-ac-menu` border-radius |
| `--lis-dir-dropdown-shadow` | `0 4px 12px rgba(0, 0, 0, 0.08)` | `.lis-listing-feature-suggestions` box-shadow |
| `--lis-dir-feature-option-bg-hover` | `--lis-dir-color-canvas` → `--lis-canvas` → `#FBFAF6` | `.lis-listing-feature-option:hover` background |
| `--lis-dir-place-menu-shadow` | `0 10px 28px rgba(0, 0, 0, 0.14)` | `.lis-listing-place-ac-menu` box-shadow |

### Chips & tags

Small pill-like labels: feature chips, social links, plan “Save”/“Included” tags, job type, services, listing tags.

| Variable | Default (fallback chain) | Styles |
|---|---|---|
| `--lis-dir-chip-bg` | `--lis-dir-color-accent-soft` → `--lis-pine-100` → `#E4EBE4` | `.lis-listing-feature-chip` background |
| `--lis-dir-chip-radius` | `--lis-dir-radius-control` → `--lis-radius-control` → `6px` | `.lis-job-chip` border-radius<br>`.lis-listing-feature-chip` border-radius<br>`.lis-listing-job-type` border-radius<br>`.lis-listing-plan-included-tag` border-radius<br>`.lis-listing-plan-save` border-radius<br>`.lis-listing-social-links a` border-radius |
| `--lis-dir-chip-text` | `--lis-dir-color-text-muted` → `--lis-ink-600` → `#5C6159` | `.lis-listing-feature-chip` color |
| `--lis-dir-tag-bg` | `--lis-dir-color-canvas` → `--lis-canvas` → `#FBFAF6` | `.lis-listing-tag` background |
| `--lis-dir-tag-radius` | `--lis-dir-radius-control` → `--lis-radius-control` → `6px` | `.lis-listing-services-list li` border-radius<br>`.lis-listing-tag` border-radius |
| `--lis-dir-tag-text` | `--lis-dir-color-text-muted` → `--lis-ink-600` → `#5C6159` | `.lis-listing-tag` color |

### Listing cards & archive

| Variable | Default (fallback chain) | Styles |
|---|---|---|
| `--lis-dir-card-bg` | `--lis-dir-color-surface` → `--lis-surface` → `#FFFFFF` | `.lis-job-card` background<br>`.lis-listing-card` background |
| `--lis-dir-card-border` | `1px solid var(--lis-dir-color-line)` | `.lis-job-card` border<br>`.lis-listing-card` border |
| `--lis-dir-card-excerpt-text` | `--lis-dir-color-text-muted` → `--lis-ink-600` → `#5C6159` | `.lis-listing-card-excerpt` color |
| `--lis-dir-card-facts-text` | `--lis-dir-color-accent` → `--lis-pine-700` → `#2C5240` | `.lis-listing-card-facts` color |
| `--lis-dir-card-radius` | `--lis-dir-radius-container` → `--lis-radius-container` → `12px` | `.lis-job-card` border-radius<br>`.lis-listing-card` border-radius |
| `--lis-dir-card-shadow` | `0 1px 3px rgba(0, 0, 0, 0.06)` | `.lis-listing-card` box-shadow |
| `--lis-dir-card-shadow-hover` | `0 6px 16px rgba(0, 0, 0, 0.1)` | `.lis-listing-card:hover` box-shadow |
| `--lis-dir-card-thumb-bg` | `--lis-dir-color-canvas` → `--lis-canvas` → `#FBFAF6` | `.lis-listing-card-thumb` background |
| `--lis-dir-card-title-font` | `--lis-dir-font-heading` → `'Gabarito', sans-serif` | `.lis-listing-card-title` font-family |
| `--lis-dir-map-border` | `1px solid var(--lis-dir-color-line)` | `.lis-listing-map-view` border |
| `--lis-dir-map-radius` | `--lis-dir-radius-container` → `--lis-radius-container` → `12px` | `.lis-listing-map-view` border-radius<br>`.lis-listing-map` border-radius |
| `--lis-dir-view-toggle-active-bg` | `--lis-dir-color-accent` → `--lis-pine-700` → `#2C5240` | `.lis-listing-view-btn.is-active` background |
| `--lis-dir-view-toggle-active-text` | `--lis-dir-color-surface` → `--lis-surface` → `#FFFFFF` | `.lis-listing-view-btn.is-active` color |
| `--lis-dir-view-toggle-border` | `1px solid var(--lis-dir-color-line)` | `.lis-listing-view-toggle` border |
| `--lis-dir-view-toggle-radius` | `--lis-dir-radius-control` → `--lis-radius-control` → `6px` | `.lis-listing-view-toggle` border-radius |

### Badges

Badges overlaid on card thumbnails and in listing headers.

| Variable | Default (fallback chain) | Styles |
|---|---|---|
| `--lis-dir-badge-radius` | `--lis-dir-radius-control` → `--lis-radius-control` → `6px` | `.lis-job-status` border-radius<br>`.lis-listing-badge` border-radius<br>`.lis-listing-category-badge` border-radius<br>`.lis-listing-dashboard-status` border-radius<br>`.lis-listing-open-status` border-radius |
| `--lis-dir-category-badge-bg` | `--lis-dir-color-accent-soft` → `--lis-pine-100` → `#E4EBE4` | `.lis-listing-category-badge` background |
| `--lis-dir-category-badge-letter-spacing` | `0.03em` | `.lis-listing-category-badge` letter-spacing |
| `--lis-dir-category-badge-radius` | `--lis-dir-badge-radius` → `--lis-dir-radius-control` → `--lis-radius-control` → `6px` | `.lis-listing-category-badge` border-radius |
| `--lis-dir-category-badge-text` | `--lis-dir-color-accent` → `--lis-pine-700` → `#2C5240` | `.lis-listing-category-badge` color |
| `--lis-dir-featured-badge-bg` | `--lis-dir-color-commerce-soft` → `--lis-clay-100` → `#F6E8DF` | `.lis-listing-badge--featured` background |
| `--lis-dir-featured-badge-text` | `--lis-dir-color-commerce-strong` → `--lis-clay-700` → `#8C3E20` | `.lis-listing-badge--featured` color |
| `--lis-dir-popular-badge-bg` | `--lis-dir-color-accent-soft` → `--lis-pine-100` → `#E4EBE4` | `.lis-listing-badge--popular` background |
| `--lis-dir-popular-badge-text` | `--lis-dir-color-accent` → `--lis-pine-700` → `#2C5240` | `.lis-listing-badge--popular` color |
| `--lis-dir-price-text` | `--lis-dir-color-commerce` → `--lis-clay-600` → `#A94F2C` | `.lis-job-dash-pay` color<br>`.lis-listing-price` color |
| `--lis-dir-rating-star` | `--lis-dir-color-rating` → `--lis-gold-600` → `#96701E` | `.lis-listing-review-stars` color |

### Status colors

Meaning-bearing colors. Defaults keep their current meaning: open/published/verified = Pine, closed/sold/expired = Error, pending = Info, draft = neutral.

| Variable | Default (fallback chain) | Styles |
|---|---|---|
| `--lis-dir-status-closed-bg` | `--lis-dir-color-error-soft` → `color-mix(in srgb, var(--lis-dir-color-error) 12%, var(--lis-dir-color-canvas))` | `.lis-listing-open-status.is-closed` background |
| `--lis-dir-status-closed-text` | `--lis-dir-color-error` → `--lis-error` → `#8E1D33` | `.lis-listing-open-status.is-closed` color |
| `--lis-dir-status-draft-bg` | `--lis-dir-color-line` → `--lis-line` → `#DEDFD8` | `.lis-listing-dashboard-status--draft` background |
| `--lis-dir-status-draft-text` | `--lis-dir-color-text-muted` → `--lis-ink-600` → `#5C6159` | `.lis-listing-dashboard-status--draft` color |
| `--lis-dir-status-expired-bg` | `--lis-dir-color-error-soft` → `color-mix(in srgb, var(--lis-dir-color-error) 12%, var(--lis-dir-color-canvas))` | `.lis-job-status--expired` background<br>`.lis-listing-dashboard-status--expired` background |
| `--lis-dir-status-expired-text` | `--lis-dir-color-error` → `--lis-error` → `#8E1D33` | `.lis-job-status--expired` color<br>`.lis-listing-dashboard-status--expired` color |
| `--lis-dir-status-open-bg` | `--lis-dir-color-accent-soft` → `--lis-pine-100` → `#E4EBE4` | `.lis-listing-open-status.is-open` background |
| `--lis-dir-status-open-text` | `--lis-dir-color-accent` → `--lis-pine-700` → `#2C5240` | `.lis-listing-open-status.is-open` color |
| `--lis-dir-status-pending-bg` | `--lis-dir-color-info-soft` → `color-mix(in srgb, var(--lis-dir-color-info) 12%, var(--lis-dir-color-canvas))` | `.lis-job-status--awaiting-payment, .lis-job-status--pending-review` background<br>`.lis-listing-dashboard-status--pending` background |
| `--lis-dir-status-pending-text` | `--lis-dir-color-info` → `--lis-info` → `#2A5B78` | `.lis-job-status--awaiting-payment, .lis-job-status--pending-review` color<br>`.lis-listing-dashboard-status--pending` color |
| `--lis-dir-status-published-bg` | `--lis-dir-color-accent-soft` → `--lis-pine-100` → `#E4EBE4` | `.lis-listing-dashboard-status--publish` background |
| `--lis-dir-status-published-text` | `--lis-dir-color-accent` → `--lis-pine-700` → `#2C5240` | `.lis-listing-dashboard-status--publish` color |
| `--lis-dir-status-sold-bg` | `--lis-dir-color-error-soft` → `color-mix(in srgb, var(--lis-dir-color-error) 12%, var(--lis-dir-color-canvas))` | `.lis-listing-badge--sold` background |
| `--lis-dir-status-sold-text` | `--lis-dir-color-error` → `--lis-error` → `#8E1D33` | `.lis-listing-badge--sold` color |
| `--lis-dir-status-verified-bg` | `--lis-dir-color-accent-soft` → `--lis-pine-100` → `#E4EBE4` | `.lis-listing-badge--verified` background |
| `--lis-dir-status-verified-text` | `--lis-dir-color-accent` → `--lis-pine-700` → `#2C5240` | `.lis-listing-badge--verified` color |

### Single listing panels

Info boxes on the single listing page (contact/meta box, hours, facts) and edit form, plus media rounding.

| Variable | Default (fallback chain) | Styles |
|---|---|---|
| `--lis-dir-claim-box-bg` | `--lis-dir-color-accent-soft` → `--lis-pine-100` → `#E4EBE4` | `.lis-listing-claim-box` background |
| `--lis-dir-claim-box-border` | `2px solid var(--lis-dir-color-accent)` | `.lis-listing-claim-box` border |
| `--lis-dir-claim-box-radius` | `--lis-dir-radius-container` → `--lis-radius-container` → `12px` | `.lis-listing-claim-box` border-radius |
| `--lis-dir-gallery-radius` | `--lis-dir-radius-container` → `--lis-radius-container` → `12px` | `.lis-listing-gallery-strip` border-radius<br>`.lis-listing-single-thumb` border-radius |
| `--lis-dir-hours-bg` | `--lis-dir-color-surface` → `--lis-surface` → `#FFFFFF` | `.lis-listing-hours-box` background |
| `--lis-dir-hours-border` | `--lis-dir-panel-border` → `1px solid var(--lis-dir-color-line)` | `.lis-listing-hours-box` border |
| `--lis-dir-hours-radius` | `--lis-dir-panel-radius` → `--lis-dir-radius-container` → `--lis-radius-container` → `12px` | `.lis-listing-hours-box` border-radius |
| `--lis-dir-offer-bg` | `--lis-dir-color-commerce-soft` → `--lis-clay-100` → `#F6E8DF` | `.lis-listing-offer-banner` background |
| `--lis-dir-offer-border` | `1px solid var(--lis-dir-color-commerce-border)` | `.lis-listing-offer-banner` border |
| `--lis-dir-offer-btn-bg` | `--lis-dir-color-commerce` → `--lis-clay-600` → `#A94F2C` | `.lis-listing-offer-button` background |
| `--lis-dir-offer-btn-bg-hover` | `--lis-dir-color-commerce-strong` → `--lis-clay-700` → `#8C3E20` | `.lis-listing-offer-button:hover` background |
| `--lis-dir-offer-btn-radius` | `--lis-dir-btn-radius` → `--lis-dir-radius-control` → `--lis-radius-control` → `6px` | `.lis-listing-offer-button` border-radius |
| `--lis-dir-offer-btn-text` | `--lis-dir-color-surface` → `--lis-surface` → `#FFFFFF` | `.lis-listing-offer-button:hover` color<br>`.lis-listing-offer-button` color |
| `--lis-dir-offer-eyebrow-text` | `--lis-dir-color-commerce` → `--lis-clay-600` → `#A94F2C` | `.lis-listing-offer-eyebrow` color |
| `--lis-dir-offer-radius` | `--lis-dir-radius-container` → `--lis-radius-container` → `12px` | `.lis-listing-offer-banner` border-radius |
| `--lis-dir-panel-bg` | `--lis-dir-color-canvas` → `--lis-canvas` → `#FBFAF6` | `.lis-job-single-side` background<br>`.lis-listing-meta` background<br>`.lis-listing-realestate-facts` background<br>`.lis-listing-search-more-body` background<br>`.lis-listing-showcase-fields` background |
| `--lis-dir-panel-border` | `1px solid var(--lis-dir-color-line)` | `.lis-listing-hours-box` border<br>`.lis-listing-meta` border<br>`.lis-listing-realestate-facts` border<br>`.lis-listing-search-more-body` border<br>`.lis-listing-showcase-fields` border |
| `--lis-dir-panel-radius` | `--lis-dir-radius-container` → `--lis-radius-container` → `12px` | `.lis-job-dash-item` border-radius<br>`.lis-job-empty` border-radius<br>`.lis-job-form fieldset` border-radius<br>`.lis-job-single-head` border-radius<br>`.lis-job-single-side` border-radius<br>`.lis-listing-claim-page-invalid` border-radius<br>`.lis-listing-edit-asset-current` border-radius<br>`.lis-listing-flag-form` border-radius<br>`.lis-listing-hours-box` border-radius<br>`.lis-listing-meta` border-radius<br>`.lis-listing-places-search` border-radius<br>`.lis-listing-realestate-facts` border-radius<br>`.lis-listing-search-more-body` border-radius<br>`.lis-listing-showcase-fields` border-radius |
| `--lis-dir-thumb-radius` | `--lis-dir-radius-control` → `--lis-radius-control` → `6px` | `.lis-listing-edit-asset-current img` border-radius<br>`.lis-listing-edit-gallery-item img` border-radius<br>`.lis-listing-gallery-thumb img` border-radius<br>`.lis-listing-photos-preview-item` border-radius |
| `--lis-dir-video-radius` | `--lis-dir-radius-container` → `--lis-radius-container` → `12px` | `.lis-listing-video-wrap` border-radius |

### Notices

Success / error / warning / info message boxes.

| Variable | Default (fallback chain) | Styles |
|---|---|---|
| `--lis-dir-notice-error-bg` | `--lis-dir-color-error-soft` → `color-mix(in srgb, var(--lis-dir-color-error) 12%, var(--lis-dir-color-canvas))` | `.lis-job-notice--error` background<br>`.lis-listing-photos-error` background<br>`.lis-listing-submit-errors` background |
| `--lis-dir-notice-error-border` | `1px solid var(--lis-dir-color-error-border)` | `.lis-listing-photos-error` border |
| `--lis-dir-notice-error-border-color` | `--lis-dir-color-error-border` → `color-mix(in srgb, var(--lis-dir-color-error) 32%, var(--lis-dir-color-canvas))` | `.lis-job-notice--error` border-color |
| `--lis-dir-notice-error-text` | `--lis-dir-color-error` → `--lis-error` → `#8E1D33` | `.lis-listing-photos-error` color<br>`.lis-listing-submit-errors` color |
| `--lis-dir-notice-info-bg` | `--lis-dir-color-info-soft` → `color-mix(in srgb, var(--lis-dir-color-info) 12%, var(--lis-dir-color-canvas))` | `.lis-job-notice` background |
| `--lis-dir-notice-info-border` | `1px solid var(--lis-dir-color-info)` | `.lis-job-notice` border |
| `--lis-dir-notice-radius` | `--lis-dir-radius-container` → `--lis-radius-container` → `12px` | `.lis-job-notice` border-radius<br>`.lis-listing-photos-error` border-radius<br>`.lis-listing-showcase-occupied` border-radius<br>`.lis-listing-submit-errors` border-radius<br>`.lis-listing-submit-success` border-radius |
| `--lis-dir-notice-success-bg` | `--lis-dir-color-accent-soft` → `--lis-pine-100` → `#E4EBE4` | `.lis-job-notice--success` background<br>`.lis-listing-submit-success` background |
| `--lis-dir-notice-success-border` | `--lis-dir-color-accent` → `--lis-pine-700` → `#2C5240` | `.lis-job-notice--success` border-color |
| `--lis-dir-notice-success-text` | `--lis-dir-color-accent` → `--lis-pine-700` → `#2C5240` | `.lis-listing-submit-success` color |
| `--lis-dir-notice-text` | `--lis-dir-color-text` → `--lis-ink-900` → `#1A1D19` | `.lis-job-notice` color |
| `--lis-dir-notice-warning-bg` | `--lis-dir-color-commerce-soft` → `--lis-clay-100` → `#F6E8DF` | `.lis-listing-showcase-occupied` background |
| `--lis-dir-notice-warning-border` | `1px solid var(--lis-dir-color-commerce-border)` | `.lis-listing-showcase-occupied` border |
| `--lis-dir-notice-warning-text` | `--lis-dir-color-text` → `--lis-ink-900` → `#1A1D19` | `.lis-listing-showcase-occupied` color |

### Add Listing wizard

The one-question-at-a-time Add Listing wizard, its choice cards, plan step and photo dropzone.

| Variable | Default (fallback chain) | Styles |
|---|---|---|
| `--lis-dir-choice-bg` | `--lis-dir-color-surface` → `--lis-surface` → `#FFFFFF` | `.lis-listing-fork-choice` background |
| `--lis-dir-choice-bg-hover` | `--lis-dir-color-accent-soft` → `--lis-pine-100` → `#E4EBE4` | `.lis-listing-fork-choice:hover` background |
| `--lis-dir-choice-border` | `2px solid var(--lis-dir-color-line)` | `.lis-listing-fork-choice` border<br>`.lis-listing-plan-card` border |
| `--lis-dir-choice-border-active` | `--lis-dir-color-accent` → `--lis-pine-700` → `#2C5240` | `.lis-listing-fork-choice:hover` border-color<br>`.lis-listing-plan-card:has(input:checked)` border-color |
| `--lis-dir-choice-radius` | `--lis-dir-radius-container` → `--lis-radius-container` → `12px` | `.lis-listing-fork-choice` border-radius<br>`.lis-listing-plan-card` border-radius |
| `--lis-dir-dropzone-bg` | `--lis-dir-color-canvas` → `--lis-canvas` → `#FBFAF6` | `.lis-listing-photos-dropzone` background |
| `--lis-dir-dropzone-bg-active` | `--lis-dir-color-accent-soft` → `--lis-pine-100` → `#E4EBE4` | `.lis-listing-photos-dropzone:hover, .lis-listing-photos-dropzone.is-dragover` background |
| `--lis-dir-dropzone-border` | `2px dashed var(--lis-dir-color-line)` | `.lis-listing-photos-dropzone` border |
| `--lis-dir-dropzone-border-active` | `--lis-dir-color-accent` → `--lis-pine-700` → `#2C5240` | `.lis-listing-photos-dropzone:hover, .lis-listing-photos-dropzone.is-dragover` border-color |
| `--lis-dir-dropzone-radius` | `--lis-dir-radius-container` → `--lis-radius-container` → `12px` | `.lis-listing-photos-dropzone` border-radius |
| `--lis-dir-plan-card-border` | `--lis-dir-choice-border` → `2px solid var(--lis-dir-color-line)` | `.lis-listing-plan-card` border |
| `--lis-dir-plan-card-border-active` | `--lis-dir-choice-border-active` → `--lis-dir-color-accent` → `--lis-pine-700` → `#2C5240` | `.lis-listing-plan-card:has(input:checked)` border-color |
| `--lis-dir-plan-card-border-hover` | `--lis-dir-color-accent-border` → `--lis-pine-200` → `#C3D3C8` | `.lis-listing-plan-card:hover` border-color |
| `--lis-dir-plan-card-radius` | `--lis-dir-choice-radius` → `--lis-dir-radius-container` → `--lis-radius-container` → `12px` | `.lis-listing-plan-card` border-radius |
| `--lis-dir-plan-card-ring` | `--lis-dir-focus-ring` → `0 0 0 3px color-mix(in srgb, var(--lis-dir-color-accent) 15%, transparent)` | `.lis-listing-plan-card:has(input:checked)` box-shadow |
| `--lis-dir-plan-price-text` | `--lis-dir-color-accent` → `--lis-pine-700` → `#2C5240` | `.lis-listing-plan-price` color |
| `--lis-dir-submit-bg` | `--lis-dir-btn-bg` → `--lis-dir-color-accent` → `--lis-pine-700` → `#2C5240` | `.lis-listing-submit-button` background |
| `--lis-dir-submit-bg-hover` | `--lis-dir-btn-bg-hover` → `--lis-dir-color-accent-strong` → `--lis-pine-900` → `#16281F` | `.lis-listing-submit-button:hover` background |
| `--lis-dir-submit-radius` | `--lis-dir-btn-radius` → `--lis-dir-radius-control` → `--lis-radius-control` → `6px` | `.lis-listing-submit-button` border-radius |
| `--lis-dir-submit-text` | `--lis-dir-btn-text` → `--lis-dir-color-surface` → `--lis-surface` → `#FFFFFF` | `.lis-listing-submit-button` color |
| `--lis-dir-wizard-back-bg` | `--lis-dir-btn-secondary-bg` → `transparent` | `.lis-listing-wizard-back` background |
| `--lis-dir-wizard-back-bg-hover` | `--lis-dir-btn-secondary-bg-hover` → `--lis-dir-color-canvas` → `--lis-canvas` → `#FBFAF6` | `.lis-listing-wizard-back:hover` background |
| `--lis-dir-wizard-back-border` | `--lis-dir-btn-secondary-border` → `1px solid var(--lis-dir-color-line)` | `.lis-listing-wizard-back` border |
| `--lis-dir-wizard-back-text` | `--lis-dir-btn-secondary-text` → `--lis-dir-color-text-muted` → `--lis-ink-600` → `#5C6159` | `.lis-listing-wizard-back` color |
| `--lis-dir-wizard-btn-radius` | `--lis-dir-btn-radius` → `--lis-dir-radius-control` → `--lis-radius-control` → `6px` | `.lis-listing-draft-save` border-radius<br>`.lis-listing-wizard-back, .lis-listing-wizard-next` border-radius |
| `--lis-dir-wizard-input-border` | `1px solid var(--lis-dir-color-line)` | `.lis-listing-panel input[type="text"], .lis-listing-panel input[type="email"], .lis-listing-panel input[type="url"], .lis-listing-panel input[type="time"], .lis-listing-panel select` border-bottom |
| `--lis-dir-wizard-input-border-focus` | `--lis-dir-color-accent` → `--lis-pine-700` → `#2C5240` | `.lis-listing-panel input[type="text"]:focus, .lis-listing-panel input[type="email"]:focus, .lis-listing-panel input[type="url"]:focus, .lis-listing-panel input[type="time"]:focus, .lis-listing-panel select:focus` border-bottom-color |
| `--lis-dir-wizard-next-bg` | `--lis-dir-btn-bg` → `--lis-dir-color-accent` → `--lis-pine-700` → `#2C5240` | `.lis-listing-wizard-next` background |
| `--lis-dir-wizard-next-bg-hover` | `--lis-dir-btn-bg-hover` → `--lis-dir-color-accent-strong` → `--lis-pine-900` → `#16281F` | `.lis-listing-wizard-next:hover` background |
| `--lis-dir-wizard-next-text` | `--lis-dir-btn-text` → `--lis-dir-color-surface` → `--lis-surface` → `#FFFFFF` | `.lis-listing-wizard-next` color |
| `--lis-dir-wizard-progress-bar` | `--lis-dir-color-accent` → `--lis-pine-700` → `#2C5240` | `.lis-listing-wizard-progress-bar` background |
| `--lis-dir-wizard-progress-radius` | `999px` | `.lis-listing-wizard-progress-bar` border-radius<br>`.lis-listing-wizard-progress` border-radius |
| `--lis-dir-wizard-progress-track` | `--lis-dir-color-canvas` → `--lis-canvas` → `#FBFAF6` | `.lis-listing-wizard-progress` background |
| `--lis-dir-wizard-question-text` | `--lis-dir-color-accent-strong` → `--lis-pine-900` → `#16281F` | `.lis-listing-wizard-panel-question` color |
| `--lis-dir-wizard-section-text` | `--lis-dir-color-accent` → `--lis-pine-700` → `#2C5240` | `.lis-listing-wizard-panel-section` color |
| `--lis-dir-wizard-tooltip-bg` | `--lis-dir-color-accent-strong` → `--lis-pine-900` → `#16281F` | `.lis-listing-fork-choice-tooltip` background |
| `--lis-dir-wizard-tooltip-radius` | `--lis-dir-radius-control` → `--lis-radius-control` → `6px` | `.lis-listing-fork-choice-tooltip` border-radius |
| `--lis-dir-wizard-tooltip-text` | `--lis-dir-color-surface` → `--lis-surface` → `#FFFFFF` | `.lis-listing-fork-choice-tooltip` color |

### Dashboard filters

Status filter tabs on `[lis_listing_dashboard]`.

| Variable | Default (fallback chain) | Styles |
|---|---|---|
| `--lis-dir-filter-active-bg` | `--lis-dir-color-accent` → `--lis-pine-700` → `#2C5240` | `.lis-listing-dashboard-filter.is-active` background<br>`.lis-listing-dashboard-filter.is-active` border-color |
| `--lis-dir-filter-active-text` | `--lis-dir-color-surface` → `--lis-surface` → `#FFFFFF` | `.lis-listing-dashboard-filter.is-active` color |
| `--lis-dir-filter-border` | `1px solid var(--lis-dir-color-line)` | `.lis-listing-dashboard-filter` border |
| `--lis-dir-filter-radius` | `--lis-dir-radius-control` → `--lis-radius-control` → `6px` | `.lis-listing-dashboard-filter` border-radius |
| `--lis-dir-filter-text` | `--lis-dir-color-text-muted` → `--lis-ink-600` → `#5C6159` | `.lis-listing-dashboard-filter` color |

### Vendor Showcase card, heading, ticker, bizcards

These stylesheets are inlined per shortcode, so they may render on pages without `listings.css`.

| Variable | Default (fallback chain) | Styles |
|---|---|---|
| `--lis-dir-bizcard-bg` | `--lis-dir-color-surface` → `--lis-surface` → `#FFFFFF` | `.lis-pv-ticker-bizcard` background |
| `--lis-dir-bizcard-radius` | `0` | `.lis-pv-ticker-bizcard` border-radius |
| `--lis-dir-bizcard-shadow` | `0 1px 4px rgba(0, 0, 0, 0.15)` | `.lis-pv-ticker-bizcard` box-shadow |
| `--lis-dir-ticker-gap` | `16px` | `.lis-pv-ticker-group` gap<br>`.lis-pv-ticker-group` padding-right |
| `--lis-dir-ticker-logo-height` | `100px` | `.lis-pv-ticker-logo-item` height<br>`.lis-pv-ticker-logo` max-height |
| `--lis-dir-ticker-logo-padding` | `0 44px` | `.lis-pv-ticker-logo-item` padding |
| `--lis-dir-vendor-card-bg` | `--lis-dir-color-surface` → `--lis-surface` → `#FFFFFF` | `.lis-pv-card` background |
| `--lis-dir-vendor-card-border` | `1px solid var(--lis-dir-color-line)` | `.lis-pv-card` border |
| `--lis-dir-vendor-card-name-font` | `--lis-dir-font-heading` → `'Gabarito', sans-serif` | `.lis-pv-card-name` font-family |
| `--lis-dir-vendor-card-name-text` | `inherit` | `.lis-pv-card-name` color |
| `--lis-dir-vendor-card-padding` | `16px` | `.lis-pv-card` padding |
| `--lis-dir-vendor-card-radius` | `--lis-dir-radius-container` → `--lis-radius-container` → `12px` | `.lis-pv-card` border-radius |
| `--lis-dir-vendor-card-tagline-text` | `--lis-dir-color-text-muted` → `--lis-ink-600` → `#5C6159` | `.lis-pv-card-tagline` color |
| `--lis-dir-vendor-directory-gap` | `20px` | `.lis-pv-directory-grid` gap |
| `--lis-dir-vendor-info-border` | `1.5px solid var(--lis-dir-color-text-muted)` | `.lis-pv-card-info` border<br>`.lis-pv-heading-info` border |
| `--lis-dir-vendor-info-text` | `--lis-dir-color-text-muted` → `--lis-ink-600` → `#5C6159` | `.lis-pv-card-info` color<br>`.lis-pv-heading-info` color |
| `--lis-dir-vendor-tooltip-bg` | `--lis-dir-color-text` → `--lis-ink-900` → `#1A1D19` | `.lis-pv-card-tooltip::after` border-top-color<br>`.lis-pv-card-tooltip` background<br>`.lis-pv-heading-tooltip::after` border-top-color<br>`.lis-pv-heading-tooltip` background |
| `--lis-dir-vendor-tooltip-radius` | `--lis-dir-radius-control` → `--lis-radius-control` → `6px` | `.lis-pv-card-tooltip` border-radius<br>`.lis-pv-heading-tooltip` border-radius |
| `--lis-dir-vendor-tooltip-text` | `--lis-dir-color-surface` → `--lis-surface` → `#FFFFFF` | `.lis-pv-card-tooltip` color<br>`.lis-pv-heading-tooltip` color |

### Job board

| Variable | Default (fallback chain) | Styles |
|---|---|---|
| `--lis-dir-job-btn-bg` | `--lis-dir-color-surface` → `--lis-surface` → `#FFFFFF` | `.lis-job-btn` background |
| `--lis-dir-job-btn-bg-hover` | `--lis-dir-color-accent-soft` → `--lis-pine-100` → `#E4EBE4` | `.lis-job-btn:hover` background |
| `--lis-dir-job-btn-border` | `1px solid var(--lis-dir-color-accent)` | `.lis-job-btn` border |
| `--lis-dir-job-btn-primary-bg` | `--lis-dir-btn-bg` → `--lis-dir-color-accent` → `--lis-pine-700` → `#2C5240` | `.lis-job-btn--primary` background |
| `--lis-dir-job-btn-primary-bg-hover` | `--lis-dir-btn-bg-hover` → `--lis-dir-color-accent-strong` → `--lis-pine-900` → `#16281F` | `.lis-job-btn--primary:hover` background |
| `--lis-dir-job-btn-primary-text` | `--lis-dir-btn-text` → `--lis-dir-color-surface` → `--lis-surface` → `#FFFFFF` | `.lis-job-btn--primary:hover` color<br>`.lis-job-btn--primary` color |
| `--lis-dir-job-btn-radius` | `--lis-dir-btn-radius` → `--lis-dir-radius-control` → `--lis-radius-control` → `6px` | `.lis-job-btn` border-radius |
| `--lis-dir-job-btn-text` | `--lis-dir-color-accent-strong` → `--lis-pine-900` → `#16281F` | `.lis-job-btn` color |
| `--lis-dir-job-card-bg` | `--lis-dir-card-bg` → `--lis-dir-color-surface` → `--lis-surface` → `#FFFFFF` | `.lis-job-card` background |
| `--lis-dir-job-card-border` | `--lis-dir-card-border` → `1px solid var(--lis-dir-color-line)` | `.lis-job-card` border |
| `--lis-dir-job-card-border-hover` | `--lis-dir-color-accent` → `--lis-pine-700` → `#2C5240` | `.lis-job-card:hover` border-color |
| `--lis-dir-job-card-radius` | `--lis-dir-card-radius` → `--lis-dir-radius-container` → `--lis-radius-container` → `12px` | `.lis-job-card` border-radius |
| `--lis-dir-job-card-text` | `--lis-dir-color-text` → `--lis-ink-900` → `#1A1D19` | `.lis-job-card` color |
| `--lis-dir-job-chip-bg` | `--lis-dir-color-accent-soft` → `--lis-pine-100` → `#E4EBE4` | `.lis-job-chip` background |
| `--lis-dir-job-chip-pay-bg` | `--lis-dir-color-commerce-soft` → `--lis-clay-100` → `#F6E8DF` | `.lis-job-chip--pay` background |
| `--lis-dir-job-chip-pay-text` | `--lis-dir-color-commerce` → `--lis-clay-600` → `#A94F2C` | `.lis-job-chip--pay` color |
| `--lis-dir-job-chip-radius` | `--lis-dir-chip-radius` → `--lis-dir-radius-control` → `--lis-radius-control` → `6px` | `.lis-job-chip` border-radius |
| `--lis-dir-job-chip-text` | `--lis-dir-color-accent-strong` → `--lis-pine-900` → `#16281F` | `.lis-job-chip` color |
| `--lis-dir-job-logo-bg` | `--lis-dir-color-accent-soft` → `--lis-pine-100` → `#E4EBE4` | `.lis-job-card-logo` background<br>`.lis-job-single-logo` background |
| `--lis-dir-job-logo-radius` | `--lis-dir-radius-container` → `--lis-radius-container` → `12px` | `.lis-job-card-logo` border-radius<br>`.lis-job-single-logo` border-radius |
| `--lis-dir-job-panel-bg` | `--lis-dir-color-surface` → `--lis-surface` → `#FFFFFF` | `.lis-job-dash-item` background<br>`.lis-job-form fieldset` background<br>`.lis-job-single-head` background |
| `--lis-dir-job-panel-border` | `1px solid var(--lis-dir-color-line)` | `.lis-job-dash-item` border<br>`.lis-job-form fieldset` border<br>`.lis-job-single-head` border<br>`.lis-job-single-side` border |
| `--lis-dir-job-panel-radius` | `--lis-dir-panel-radius` → `--lis-dir-radius-container` → `--lis-radius-container` → `12px` | `.lis-job-dash-item` border-radius<br>`.lis-job-form fieldset` border-radius<br>`.lis-job-single-head` border-radius<br>`.lis-job-single-side` border-radius |
| `--lis-dir-job-status-expired-border` | `--lis-dir-color-error-border` → `color-mix(in srgb, var(--lis-dir-color-error) 32%, var(--lis-dir-color-canvas))` | `.lis-job-status--expired` border-color |
| `--lis-dir-job-status-live-bg` | `--lis-dir-color-accent-soft` → `--lis-pine-100` → `#E4EBE4` | `.lis-job-status--live` background |
| `--lis-dir-job-status-live-border` | `--lis-dir-color-accent-border` → `--lis-pine-200` → `#C3D3C8` | `.lis-job-status--live` border-color |
| `--lis-dir-job-status-live-text` | `--lis-dir-color-accent-strong` → `--lis-pine-900` → `#16281F` | `.lis-job-status--live` color |
| `--lis-dir-job-status-pending-border` | `--lis-dir-color-info` → `--lis-info` → `#2A5B78` | `.lis-job-status--awaiting-payment, .lis-job-status--pending-review` border-color |

## Stable class hooks

Every class below is styled by the plugin and is a public hook. They keep their names.
New classes are only ever added, BEM-style and `lis-`-prefixed.

- `.lis-job-archive-head`, `.lis-job-archive-title`
- `.lis-job-back`
- `.lis-job-board`, `.lis-job-board-cta`
- `.lis-job-btn`, `.lis-job-btn--primary`
- `.lis-job-card`, `.lis-job-card-body`, `.lis-job-card-company`, `.lis-job-card-date`, `.lis-job-card-logo`, `.lis-job-card-logo-fallback`, `.lis-job-card-title`
- `.lis-job-chip`, `.lis-job-chip--cat`, `.lis-job-chip--pay`
- `.lis-job-chips`
- `.lis-job-dash-actions`, `.lis-job-dash-delete`, `.lis-job-dash-item`, `.lis-job-dash-list`, `.lis-job-dash-meta`, `.lis-job-dash-pay`, `.lis-job-dash-title`
- `.lis-job-dashboard`
- `.lis-job-details`
- `.lis-job-empty`
- `.lis-job-filters`
- `.lis-job-form`, `.lis-job-form-editor`, `.lis-job-form-logo`, `.lis-job-form-row`
- `.lis-job-formwrap`
- `.lis-job-hint`
- `.lis-job-list`
- `.lis-job-notice`, `.lis-job-notice--error`, `.lis-job-notice--success`
- `.lis-job-optional`
- `.lis-job-price-note`
- `.lis-job-single`, `.lis-job-single-apply`, `.lis-job-single-apply--bottom`, `.lis-job-single-body`, `.lis-job-single-company`, `.lis-job-single-content`, `.lis-job-single-head`, `.lis-job-single-logo`, `.lis-job-single-side`, `.lis-job-single-title`
- `.lis-job-status`, `.lis-job-status--awaiting-payment`, `.lis-job-status--expired`, `.lis-job-status--live`, `.lis-job-status--pending-review`
- `.lis-listing-archive`, `.lis-listing-archive-title`
- `.lis-listing-author-bio`, `.lis-listing-author-header`, `.lis-listing-author-name`, `.lis-listing-author-profile`
- `.lis-listing-badge`, `.lis-listing-badge--featured`, `.lis-listing-badge--popular`, `.lis-listing-badge--sold`, `.lis-listing-badge--verified`
- `.lis-listing-bookmark-btn`
- `.lis-listing-card`, `.lis-listing-card-body`, `.lis-listing-card-excerpt`, `.lis-listing-card-facts`, `.lis-listing-card-facts--salary`, `.lis-listing-card-meta-row`, `.lis-listing-card-tags`, `.lis-listing-card-thumb`, `.lis-listing-card-thumb-badges`, `.lis-listing-card-thumb-badges--left`, `.lis-listing-card-thumb-badges--right`, `.lis-listing-card-thumb-wrap`, `.lis-listing-card-title`, `.lis-listing-card-title-right`, `.lis-listing-card-title-row`
- `.lis-listing-category-badge`
- `.lis-listing-claim-box`, `.lis-listing-claim-btn`, `.lis-listing-claim-cta`, `.lis-listing-claim-page`, `.lis-listing-claim-page-divider`, `.lis-listing-claim-page-invalid`, `.lis-listing-claim-submit`
- `.lis-listing-columns`
- `.lis-listing-content`
- `.lis-listing-dashboard`, `.lis-listing-dashboard-filter`, `.lis-listing-dashboard-filters`, `.lis-listing-dashboard-inline-form`, `.lis-listing-dashboard-link-button`, `.lis-listing-dashboard-status`, `.lis-listing-dashboard-status--draft`, `.lis-listing-dashboard-status--expired`, `.lis-listing-dashboard-status--pending`, `.lis-listing-dashboard-status--publish`, `.lis-listing-dashboard-table`
- `.lis-listing-draft-bar`, `.lis-listing-draft-save`
- `.lis-listing-edit-asset-current`, `.lis-listing-edit-flat`, `.lis-listing-edit-gallery`, `.lis-listing-edit-gallery-item`
- `.lis-listing-empty`
- `.lis-listing-faq`
- `.lis-listing-feature-chip`, `.lis-listing-feature-chip-remove`, `.lis-listing-feature-chips`, `.lis-listing-feature-input`, `.lis-listing-feature-option`, `.lis-listing-feature-picker`, `.lis-listing-feature-suggest`, `.lis-listing-feature-suggest-hint`, `.lis-listing-feature-suggestions`
- `.lis-listing-features`
- `.lis-listing-flag-actions`, `.lis-listing-flag-form`
- `.lis-listing-fork-choice`, `.lis-listing-fork-choice-info`, `.lis-listing-fork-choice-title`, `.lis-listing-fork-choice-tooltip`, `.lis-listing-fork-choices`
- `.lis-listing-gallery-img`, `.lis-listing-gallery-preview`, `.lis-listing-gallery-remove`, `.lis-listing-gallery-strip`, `.lis-listing-gallery-thumb`
- `.lis-listing-grid`, `.lis-listing-grid--list`, `.lis-listing-grid-search`
- `.lis-listing-header`, `.lis-listing-header-actions`, `.lis-listing-header-meta`
- `.lis-listing-hours-box`, `.lis-listing-hours-table-display`
- `.lis-listing-job-facts`, `.lis-listing-job-salary`, `.lis-listing-job-type`
- `.lis-listing-main`
- `.lis-listing-map`, `.lis-listing-map-canvas`, `.lis-listing-map-view`, `.lis-listing-map-view-canvas`
- `.lis-listing-meta`, `.lis-listing-meta-label`, `.lis-listing-meta-row`
- `.lis-listing-no-hours-toggle`
- `.lis-listing-offer-banner`, `.lis-listing-offer-button`, `.lis-listing-offer-eyebrow`
- `.lis-listing-open-status`, `.lis-listing-open-status--sm`
- `.lis-listing-optional`
- `.lis-listing-panel`
- `.lis-listing-photos-dropzone`, `.lis-listing-photos-dropzone-hint`, `.lis-listing-photos-dropzone-label`, `.lis-listing-photos-error`, `.lis-listing-photos-preview`, `.lis-listing-photos-preview-item`
- `.lis-listing-place-ac`, `.lis-listing-place-ac-item`, `.lis-listing-place-ac-main`, `.lis-listing-place-ac-menu`, `.lis-listing-place-ac-sub`, `.lis-listing-place-confirm`
- `.lis-listing-places-confirm`, `.lis-listing-places-search`, `.lis-listing-places-search-holder`
- `.lis-listing-plan-billing`, `.lis-listing-plan-blurb`, `.lis-listing-plan-card`, `.lis-listing-plan-card--included`, `.lis-listing-plan-included-tag`, `.lis-listing-plan-name`, `.lis-listing-plan-price`, `.lis-listing-plan-save`, `.lis-listing-plan-showcase-note`, `.lis-listing-plan-total`, `.lis-listing-plan-total-amount`
- `.lis-listing-plans`
- `.lis-listing-price`
- `.lis-listing-rating-field`, `.lis-listing-rating-stars`, `.lis-listing-rating-summary`
- `.lis-listing-realestate-facts`
- `.lis-listing-review-stars`
- `.lis-listing-search-checkbox`, `.lis-listing-search-field`, `.lis-listing-search-field-label`, `.lis-listing-search-form`, `.lis-listing-search-input`, `.lis-listing-search-more`, `.lis-listing-search-more-body`, `.lis-listing-search-radio`, `.lis-listing-search-reset`, `.lis-listing-search-row`, `.lis-listing-search-select`, `.lis-listing-search-submit`
- `.lis-listing-section`
- `.lis-listing-services-list`
- `.lis-listing-share-btn`
- `.lis-listing-showcase-fields`, `.lis-listing-showcase-inputs`, `.lis-listing-showcase-note`, `.lis-listing-showcase-occupied`
- `.lis-listing-sidebar`
- `.lis-listing-single`, `.lis-listing-single-thumb`
- `.lis-listing-social-links`
- `.lis-listing-sort-form`, `.lis-listing-sort-label`
- `.lis-listing-submit-button`, `.lis-listing-submit-checkbox`, `.lis-listing-submit-checkbox-grid`, `.lis-listing-submit-editor`, `.lis-listing-submit-errors`, `.lis-listing-submit-form`, `.lis-listing-submit-hours-table`, `.lis-listing-submit-success`
- `.lis-listing-tag`
- `.lis-listing-tagline`
- `.lis-listing-thankyou`, `.lis-listing-thankyou-actions`, `.lis-listing-thankyou-btn`, `.lis-listing-thankyou-btn--ghost`, `.lis-listing-thankyou-icon`, `.lis-listing-thankyou-text`, `.lis-listing-thankyou-title`
- `.lis-listing-title`
- `.lis-listing-toolbar`
- `.lis-listing-video-wrap`
- `.lis-listing-view-btn`, `.lis-listing-view-toggle`
- `.lis-listing-wizard`, `.lis-listing-wizard-back`, `.lis-listing-wizard-controls`, `.lis-listing-wizard-next`, `.lis-listing-wizard-panel`, `.lis-listing-wizard-panel-body`, `.lis-listing-wizard-panel-hint`, `.lis-listing-wizard-panel-question`, `.lis-listing-wizard-panel-section`, `.lis-listing-wizard-panel-section-row`, `.lis-listing-wizard-progress`, `.lis-listing-wizard-progress-bar`, `.lis-listing-wizard-review-list`, `.lis-listing-wizard-stage`
- `.lis-pv-card`, `.lis-pv-card-body`, `.lis-pv-card-info`, `.lis-pv-card-info-icon`, `.lis-pv-card-inner`, `.lis-pv-card-logo`, `.lis-pv-card-name`, `.lis-pv-card-tagline`, `.lis-pv-card-tooltip`
- `.lis-pv-directory-grid`
- `.lis-pv-heading`, `.lis-pv-heading-info`, `.lis-pv-heading-info-icon`, `.lis-pv-heading-link`, `.lis-pv-heading-lockup`, `.lis-pv-heading-tooltip`
- `.lis-pv-ticker`, `.lis-pv-ticker-bizcard`, `.lis-pv-ticker-bizcard-item`, `.lis-pv-ticker-copy`, `.lis-pv-ticker-group`, `.lis-pv-ticker-logo`, `.lis-pv-ticker-logo--black`, `.lis-pv-ticker-logo--white`, `.lis-pv-ticker-logo-item`, `.lis-pv-ticker-track`

Shortcode tags, the `lis_preferred_vendor` / `lis_listing` post types, and `_lis_*` meta keys
are unchanged too.

## Known limits

- **Search text-input text color.** Astra's `input[type="text"]` rule outranks the plugin's
  single-class `.lis-listing-search-input`, so the text input's typed-text color is still the
  theme's (`#666`). `--lis-dir-search-input-text` reaches the category `<select>`, where it
  defaults to Ink 600 to match. Raising specificity would break this contract, so to change
  the text input's color, set it from the site with a selector that outranks Astra's.
- `vendor-heading.css` has one pre-existing `margin: 0 0 8px 0 !important` on
  `.lis-pv-heading`. It's layout, not theme, and is unchanged.
- wp-admin-only inline colors (Showcase slot status, feature approval column, migration log)
  keep WordPress-admin colors and aren't themable.
- `assets/img/lis-logomark-green.svg` has its own fill and is loaded as an `<img>`, so page
  CSS variables can't reach it.
- `lib/plugin-update-checker` is vendored third-party code and isn't themed.
