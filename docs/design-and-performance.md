# Modern suite interface

This branch introduces a shared settings design, responsive public cards and profiles,
44px primary control targets, visible keyboard focus, reduced-motion support, named
settings fields, and an accessible description for the impact/statistics widget.
The impact widget remains a semantic, interactive section rather than an image: its
heading and description are associated with `aria-labelledby` and `aria-describedby`.

## Canonical animal URLs

Adoptable modal links (`?cat=42` and name/code variants) select the animal's
`/cats/42-name/` profile as canonical. Adopted links use `/happy-endings/42-name/`.
Tracking parameters never enter that canonical. The sitemap and animal list schema
use the same profile URLs. WordPress's competing page canonical is suppressed;
Yoast or Rank Math owns metadata when active, using the suite's existing filters.

After deploying, clear page/CDN caches, verify a representative modal URL's HTML has
one canonical pointing to a 200-response profile, and resubmit the WordPress sitemap
in Search Console. Request validation of the duplicate report after Google recrawls.
Modal variants may correctly remain excluded as duplicates; the profile is the intended
indexed URL. This does not force Google to accept a canonical or fix duplicates outside
this plugin's URLs.

## Performance

Public widgets share one locally bundled utility stylesheet (about 18 KB uncompressed)
instead of three independent Tailwind browser compilers. Generate it with `npm ci`
then `npm run build:css`; commit `assets/css/utilities.css` for PHP-only deployments.
The release workflow already includes the entire assets directory. Preflight is disabled
so host theme styles are retained. Configured fonts/brand colours remain supported.

Ordinary listing/statistics pages can use the host's page cache. Explicit cache bypass,
modal query pages and form/donation pages retain page-cache safeguards. Object/database
caches stay enabled. SEO hooks reuse each animal collection within the request, and
sitemaps omit invented last-modified dates and paginate at 2,000 URLs. Card thumbnails
use lazy loading while modal/profile images retain eager/high-priority behaviour.
Executable shortcode scripts are registered for the footer to avoid block-theme text
filters corrupting JavaScript and to move execution out of post content.

No production speed score is claimed: hosting, image sizes, configured API response
times and caching plugins still affect field performance.

## Validation

- Existing PHP regression scripts and Node card renderer test.
- `php tests/seo-canonical.php` tests canonical variants, metadata ownership, sitemap
  URLs, request reuse and safe JSON-LD encoding.
- In a disposable WordPress installation with the plugin active:
  `wp eval-file tests/redesign-wordpress.php`.
- Browser checks cover the settings page at desktop/mobile widths, impact statistics,
  modal links, keyboard interactions and automated WCAG A/AA checks with fixture data.
  These checks do not establish complete accessibility conformance for every setting,
  theme or live third-party integration.

## Completed widget redesign

Animal cards now use separate photo, name/details and action sections at all widths;
long names wrap instead of disappearing behind truncation or image overlays. Toolbars,
filters and modal surfaces share the new spacing and focus treatment. Impact cards use
static icon tiles, clear totals and captions. They remain visible when animations are
disabled. Listing pagination and impact columns use the width available to the embedded
widget, so a narrow content column does not get a full desktop grid.

The featured animal uses an image/details split that stacks on small screens. The quiz
has grouped, labelled fields and matching result cards. Both use unique per-instance IDs
and direct root lookup, including when WordPress moves scripts into the footer. Donation
panels use a segmented frequency selector, clearer amount selection and consistent input
and action styling. Form aliases share one styled wrapper with a named heading, readable
fields and a helpful unavailable state. Forms rendered in third-party cross-origin
iframes retain the provider's own styling.

Configured brand colours, border widths, radii, card order and per-device column/row
values remain available. The skin adds lightly tinted surfaces, typography and spacing;
it does not replace integration settings or payment providers.

### Repeatable browser review

Run `npm ci`, then install Playwright Chromium with `npx playwright install chromium`
(or set `RPS_CHROMIUM` to an installed Chromium executable). The browser test requires a
**disposable** WordPress instance with this plugin active and these published pages:

| Path | Content |
| --- | --- |
| `/adopt/` | `[adoptables]` |
| `/stories/` | `[adopted]` |
| `/impact/` | `[stats]` |
| `/widget-review/` | `[featured_animal] [adoption_match_quiz] [donation_widget] [adoption_form] [adoption_match_quiz] [featured_animal]` |

Enable featured animals, the quiz and donations in that instance. Set its ASM account to
`demo-fixture` solely to allow the form shell to render. Do not configure real payment
credentials or shelter credentials for this test. Seed the SEO animal transient with a
fixture or a cached empty array to avoid server-side requests to a real shelter service.
The browser intercepts animal/report/image requests and the ASM form script with fixture
data; it never submits the form or initiates checkout. It checks both 390px and 1440px
viewports, including WCAG A/AA rules, overflow, reduced-motion visibility, modal keyboard
behaviour, quiz results, unique IDs and donation amount selection.

```sh
RPS_BASE_URL=http://127.0.0.1:8086 npm run test:widgets
# Existing system browser, with screenshots saved to a chosen directory:
RPS_CHROMIUM=/usr/bin/chromium RPS_SCREENSHOT_DIR=/tmp/rps-review npm run test:widgets
```

Current-instance review passed these checks with fixture animal images and data. Live
ASM form delivery, payment checkout and production speed metrics require the respective
configured services and were not exercised by this visual review.

## 15.1.2 controls and isolation

General → Shared styling now includes transparent outer surfaces and separate title,
subtitle and footer visibility switches. Hidden titles remain available to assistive
technology for named widget regions. Cards and dialogs keep readable backgrounds; paw
patterns remain on transparent surfaces. Only widgets receive presentation flags.

Utility CSS and its variable defaults now require `data-rps-ui` on a suite root. The
build's selector pass scopes both roots and descendants and prefixes utility animations.
Modal roots carry the marker when moved into `body`. Native host headings, buttons,
layout classes, hidden overrides and pre-existing scroll styles are covered by
`tests/site-isolation.cjs`. Ordinary pages should request no suite assets.

Additional tests: `tests/presentation-browser.cjs` (runtime fixture admin credentials via
environment variables), `tests/settings-usability.php`, `tests/settings-save.php`,
`tests/payment-settings.php`, `tests/update-preferences.php`, and
`tests/updater-native-lifecycle.php`. The native lifecycle test is explicitly destructive
and refuses to run without `RPS_UPDATER_TESTS=1`; use only a disposable installation.

## WordPress sidebar navigation

The suite has its own **Rescue Plugin Suite** top-level admin menu, with General,
Integrations, Widgets, Forms, Donations, Diagnostics, Updates and Help Centre links.
Widgets groups Adoptables, Adopted, Featured Animal, Statistics and Match Quiz; their
existing tabs and customisation controls remain available. The menu requires
`manage_options` and does not alter other plugins' sidebar entries.

Canonical suite links use `wp-admin/admin.php?page=plugin-ui-suite`. Old bookmarks
under `options-general.php` redirect while retaining the selected tab, subsection and
notice parameters. Legacy module/payment routes and the hidden setup wizard continue
to work. Saving settings returns to the new location.

Run `tests/admin-navigation.cjs` against the disposable WordPress fixture with
`RPS_ADMIN_USER`, `RPS_ADMIN_PASSWORD`, `RPS_SUBSCRIBER_USER` and
`RPS_SUBSCRIBER_PASSWORD` supplied at runtime. It checks all sidebar sections, assets,
highlighting, old URLs, the setup wizard, a settings save and subscriber access denial.
