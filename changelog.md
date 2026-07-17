## 14.0.32
- Added live block editor preview controls for suite blocks.
- Added WP-Cron webhook retries with exponential backoff and webhook delivery audit logging.
- Added provider health dashboard, consent-aware enquiry analytics, and redacted support diagnostics downloads.

14.0.31
- Added animal context to application intent events without collecting embedded form answers.
- Added test and retry controls for email/webhook enquiry integrations plus a webhook retry queue.
- Added admin-only last-known-good frontend notices, upload-based image cache files with generated resized/WebP variants, richer block attributes and expanded Help onboarding/provider/accessibility guidance.

14.0.30
- Added a Help / Guide tab explaining data sources, applications, forms, shortcodes, blocks and diagnostics.
- Added privacy-safe application intent logging with CSV export plus optional email and webhook notifications for Google Sheets, Zapier, Make or CRM workflows.
- Kept embedded ASM/third-party application form submissions in their source systems; the suite records only lightweight intent metadata, not applicant answers.

14.0.29
- Added server-rendered Gutenberg blocks for key suite shortcodes.
- Added provider field-map templates and admin-safe source preview mode for staging provider switches.
- Added last-known-good feed visibility in diagnostics to show cache age/count where available.

14.0.28
- Added an admin-configurable field mapper so provider-specific fields can be mapped into the suite animal model without code changes.
- Added last-known-good feed fallbacks for adoptables and adoptions so widgets can keep serving cached rescue data during provider outages.
- Expanded cache/uninstall cleanup for last-known-good feed snapshots.

14.0.27
- Added provider diagnostics that exercise the selected source through the public REST routes and show counts, statuses and sample keys.
- Added report fallbacks so summary and in-care statistics can be derived from adoptables/adoptions feeds when provider report endpoints or ASM custom reports are unavailable.
- Expanded cache/uninstall cleanup to include provider diagnostics and Shelterluv/PetPoint transient caches.

14.0.26
- Removed user-facing Plugin defaults so the suite is rescue-neutral.
- Added live configurable Shelterluv and PetPoint connector paths for adoptables, adoptions, reports, in-care counts and image proxying.
- Added shared frontend CSS/JS assets, analytics rate limiting, and full uninstall cleanup.

# Rescue Plugin Suite 14.0.25

## Fixed
- Added mobile Safari-focused image rendering safeguards for adoptable/adopted cards and modals.
- Added critical modal fallback styles so direct-linked animal modals do not flash unstyled before utility CSS is ready.
- Adoptable and adopted modal galleries now eager-load visible images and retry transient image failures before removing missing photos.

# Rescue Plugin Suite 14.0.24

## Improved
- Removed the trailing divider after the adopted modal below-story text while keeping the separator before it.

# Rescue Plugin Suite 14.0.23

## Improved
- Moved the divider-wrapped adopted modal text block below the animal story.
- Updated adopted modal settings labels so the configurable text is described as below-story content.

# Rescue Plugin Suite 14.0.22

## Improved
- Added subtle divider lines before and after the adopted modal text above story section.

# Rescue Plugin Suite 14.0.21

## Improved
- Moved the adopted modal tip below the adoptables CTA block and above the "Scroll to read my story" prompt.
- Reduced top and bottom padding around the configurable adopted modal text above story section.

# Rescue Plugin Suite 14.0.20

## Improved
- Adopted modal headers now show the shelter code under the animal name instead of repeating sex and age.
- Reduced adopted modal tip text size.
- Moved the "Scroll to read my story" prompt below the adoptables CTA block.
- Tightened spacing around the configurable modal text above the story.
- Increased the adopted modal Story heading size and weight.

# Rescue Plugin Suite 14.0.19

## Improved
- Widened the adopted modal story area so it spans the modal beneath the photo/details row.
- Changed adopted modal details from four narrow cards to a two-column card grid matching the Adoptables modal style more closely.
- Moved the configurable adopted modal text above the story section and clarified the settings label.
- Added "Scroll to read my story" hints to adopted modals.

## Fixed
- Restored close-on-outside-click for adopted modals by handling clicks on the empty viewport as well as the backdrop.

# Rescue Plugin Suite 14.0.18

## Improved
- Moved adopted modal story text into the photo column beneath the gallery thumbnails, matching the Adoptables modal flow where longer narrative content sits below the images instead of beside them.

# Rescue Plugin Suite 14.0.17

## Added
- Added social metadata previews for modal deep links, including animal title, shelter code, description excerpt and primary image.

## Improved
- Adoption quiz result cards now route the whole card to the configured Adoptables UI page and open the matching animal modal.
- The in-modal adoption form now loads in an isolated eager iframe so Shelter Manager's parser-loaded form script can render beside its target without duplicate page IDs.

## Fixed
- Removed failed or unpublished photo slots from Adoptables and Adopted modal galleries instead of showing broken image thumbnails.

# Rescue Plugin Suite 14.0.16

## Added
- Added Global settings for the Adoptables UI page URL and Adopted UI page URL.
- Added direct adopted modal links, adopted modal sharing and direct-link opening from `?adopted=...` URLs.

## Improved
- Featured animal and adoption story widget links now route to the configured UI pages and open the relevant modal instead of sending visitors to the standalone profile layout.
- Animal sitemap and list schema URLs prefer configured UI modal URLs when available, while retaining standalone profile fallbacks.

## Fixed
- Fixed the adoptables modal application form loader so the ASM form script is inserted next to its target and blank renders show a visible error instead of an empty panel.
- Fixed duplicated gallery photos caused by the image proxy serving later photo slots as fallbacks for missing requested slots.

# Rescue Plugin Suite 14.0.15

## Fixed
- Fixed Adoptables and Adopted widgets rendering as blank space when an HTML/entity processor decoded inline JavaScript entity strings and broke script parsing before the widgets could reveal themselves.

# Rescue Plugin Suite 14.0.14

## Added
- Added a Global cache bypass toggle so ASM/Custom API data and SEO profile feeds can skip plugin transients for immediate content updates.
- Added Adoptables and Adopted settings to hide the upper previous/next pager while keeping the lower pager.
- Added a configurable adopted-modal section with custom text, button label and URL for linking visitors back to the adoptables page.

## Improved
- Made adopted fallback cards link to their canonical `/happy-endings/{animal-id}-{animal-name}/` profile pages for better crawlability.
- Made adopted profile and sitemap feeds respect the selected Custom API source where configured.
- Expanded cache clearing to include the SEO profile-list transients.

## Fixed
- Prevented hidden settings subtabs from resetting unrelated fields when saved.
- Made frontend pagination scripts tolerate disabled upper navigation controls.

# Rescue Plugin Suite 14.0.13

## Fixed
- Removed duplicate core initialization from the class file so hooks and handlers register once through the main plugin file.
- Restored the bundled Forms module aliases and made them use the configured Suite ASM account.
- Added accessible dialog roles, focus trapping and focus restoration for Adoptables filters, animal, favourites and comparison modals.
- Added focus trapping to the Adopted modal.
- Escaped feed-provided text in dynamic card, modal, quiz and story HTML rendering.
- Restored moved adoption forms to their original page position when the modal closes.
- Prevented settings exports and setting packs from including API keys and password-type global credentials.
- Fixed invalid wrapper markup in the Adoption Stories widget.

## Improved
- Made Custom API a live selectable data source from the Global tab.
- Expanded Custom API normalization for common feed fields such as `id`, `name`, `breed`, `age`, `description` and `adoption_date`.
- Made Custom API and ASM proxy caches respect the suite cache settings, and clear both ASM and Custom API cache keys from the cache tool.
- Added Favourite to the modal header layout builder action order.

# Rescue Plugin Suite 14.0.12

## Fixed
- Prevented the Adoptables modal, filter overlay, favourites overlay and comparison overlay from flashing as unstyled content during page refresh.
- Added critical inline hiding that works before Tailwind CDN utilities are available.
- Constrained modal SVG icons during initial rendering so navigation arrows cannot briefly expand to their browser-default size.

# Rescue Plugin Suite 14.0.11

- Reworked ASM application success detection so the modal scrolls to the top even when Shelter Manager does not dispatch a conventional form submit event.
- Detects confirmation text, major form replacement, button activation and iframe reloads.
- Resets every relevant modal and nested form scroll container repeatedly while the asynchronous confirmation is rendered.

# Rescue Plugin Suite 14.0.10

- Fixed the Adoptables application form so the modal scrolls back to the top after submission.
- Added delayed scroll correction for Shelter Manager's asynchronous form replacement.
- Expanded success-message detection and submit-button detection.
- Scrolls the modal viewport itself rather than the page behind the modal.
- Retained the single-form-instance fix and all V14 SEO and modal functionality.

# Rescue Plugin Suite 14.0.9

- Fixed the Adoptables modal form conflict when the same ASM adoption form is already present elsewhere on the page.
- Prevented duplicate `asm3-onlineform` IDs, which stop Shelter Manager's embedded form script from initialising the modal copy.
- The modal now reuses the existing, already-initialised page form and temporarily moves it into the modal.
- If no form exists elsewhere, the Suite creates the single ASM form instance only after Apply is clicked.
- The form is restored to its original page position when the user returns to the animal details.
- Retained submission success scroll-to-top behaviour and all V14 SEO and modal features.

# Rescue Plugin Suite 14.0.8

- Restored the proven V13.6.15 adoption-form rendering method.
- The exact application-form shortcode saved in Plugin Suite settings is now rendered server-side inside the Adoptables modal.
- The Apply button only switches the modal from animal details to the already initialised form, avoiding failed AJAX and dynamically injected ASM scripts.
- Added a visible configuration error if the saved shortcode is unavailable.
- Retained the post-submission modal scroll-to-top behaviour and all V14 SEO and modal improvements.

## 14.0.7
- Fixed Adopted modal text colours not following the configured Primary text and Muted text colour settings after the modal is moved under the page body.
- Preserved the configured brand colour, modal width, typography variables and font family inside the detached modal.

## 14.0.6
- Restored the adopted date inside the Adopted modal only.
- Removed adopted-date badges from Adopted UI cards.
- Rebuilt the Adoptables Apply form loader to use the exact Suite setting as its source of truth.
- The form now loads on demand and explicitly executes the returned ASM script after inserting the form container.
- Added clear loading and error states for the application form.

# Rescue Plugin Suite changelog

## 14.0.5

- Fixed the Adoptables Apply view by actually rendering the exact application-form shortcode saved in Plugin Suite settings.
- Removed a duplicated MutationObserver declaration that could break the Adoptables modal script.
- Moved the Adopted modal directly under `document.body` so theme headers and transformed page containers cannot sit above it.
- Added robust page scroll locking while preserving the visitor’s original page position.
- Made the Adopted modal’s own content area independently scrollable on desktop and mobile.
- Removed the redundant Adopted/date information card from Adopted modals.
- Replaced the header date line with useful animal details.

# 14.0.3

- Loads the exact adoption-form shortcode saved in Suite settings when the Apply button is selected.
- Executes external scripts returned by the configured form shortcode so ASM forms initialise reliably inside the modal.
- Preserves application-form settings when Adoptables options are saved.
- Adds previous and next picture controls, thumbnail navigation and swipe support to Adopted modals.
- Adds previous and next adopted-cat navigation to Adopted modals.
- Resolves adopted dates from all supported ASM adoption and movement date fields, including UK-formatted dates.
- Removes a duplicate JavaScript declaration that could stop Adopted UI initialisation.

# 14.0.2
- Fixed Adoptables modal Apply buttons failing to display the bundled adoption form after upgrades from legacy shortcode settings.
- Added backwards-compatible `adoption_form` and `volunteer_form` shortcode aliases.
- Fixed Adopted cards failing to open their modal because the modal attempted to call an undefined analytics function.
- Retained all V14 SEO, filtering, proxy, reservation and modal features.

# 14.0.1
- Fixed a JavaScript syntax error in the Adopted UI card accessibility label that left the widget hidden as a blank space.
- Retained all V14.0.0 SEO profile, sitemap, metadata and structured-data features.

# Rescue Plugin Suite changelog

## 14.0.0 - SEO and profile architecture

- Added crawlable individual profile URLs for adoptable cats at `/cats/{animal-id}-{name}/`.
- Added crawlable adopted success-story URLs at `/happy-endings/{animal-id}-{name}/`.
- Added canonical URLs, meta descriptions, Open Graph and X card metadata for animal profiles.
- Added compatibility filters for Yoast SEO and Rank Math canonical and description output.
- Added a custom WordPress XML sitemap provider for adoptable and adopted animal profiles.
- Added server-generated ItemList structured data to Adoptables and Adopted pages.
- Added Dataset structured data to Statistics pages.
- Added WebApplication structured data to the adoption matching quiz.
- Added NGO and ContactPage structured data where relevant.
- Added semantic headings, sections, forms, live regions and more descriptive image alternative text.
- Added crawlable profile links to Featured Animal, Adoption Stories and Quiz results.
- Updated the adoption quiz to retain exact cumulative AND matching across all selected preferences.
- Added accessible no-JavaScript guidance to online forms.
- Retained all V13.6.18 modal, filter, reservation, form-scroll and proxy-settings fixes.

## 14.0.4
- Restored the adoption form to server-side rendering using the exact shortcode saved in Plugin Suite settings.
- Removed unreliable AJAX insertion of the ASM external form script.
- Removed `defer` from the Suite-generated ASM form script so it runs immediately after its target container.
- Moved adopted previous/next cat controls inside the modal as a contained footer.
- Corrected adopted modal current-cat indexing and navigation button state.
- Improved adopted modal sizing and mobile navigation layout.
