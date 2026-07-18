14.0.25
- Added mobile Safari-focused image rendering safeguards for adoptable/adopted cards and modals.
- Added critical modal fallback styles so direct-linked animal modals do not flash unstyled before utility CSS is ready.
- Adoptable and adopted modal galleries now eager-load visible images and retry transient image failures before removing missing photos.

14.0.24
- Removed the trailing divider after the adopted modal below-story text.

14.0.23
- Moved the divider-wrapped adopted modal text block below the animal story.
- Updated adopted modal settings labels to describe the text as below-story content.

14.0.22
- Added subtle dividers before and after the adopted modal text above story section.

14.0.21
- Moved the adopted modal tip below the adoptables CTA and above the story scroll prompt.
- Reduced vertical padding around the adopted modal text above story section.

14.0.20
- Made adopted modal headers show the shelter code under the animal name.
- Reduced the adopted modal tip size and moved the story scroll hint below the adoptables CTA section.
- Tightened the modal text above story spacing and made the Story heading more prominent.

14.0.19
- Widened the adopted modal story area so it spans the modal below the image/details row.
- Changed adopted modal detail cards to a cleaner two-column layout.
- Added a configurable modal text section above adopted animal stories.
- Added adopted modal scroll hints and restored closing by clicking outside the modal card.

14.0.18
- Moved adopted modal story text below the photo gallery so it matches the Adoptables modal reading flow.

14.0.17
- Added modal-link metadata previews so shared `?cat=` and `?adopted=` links use the animal name, shelter code, description excerpt and primary image.
- Made adoption quiz result cards open the configured Adoptables UI modal from the full card, not just the animal name.
- Hardened in-modal adoption form loading by using an isolated eager iframe for the ASM online form.
- Removed failed/unpublished gallery image slots from adoptable and adopted modals instead of showing broken thumbnails.

14.0.16
- Added Global settings for Adoptables UI page URL and Adopted UI page URL.
- Added adopted animal modal deep links, sharing, direct URL opening and SEO/sitemap URL support.
- Fixed modal application form loading so the ASM script runs beside its form target and blank loads show an error.
- Fixed duplicate/missing gallery photos caused by image proxy sequence fallback.

14.0.15

14.0.14
- Added a Global cache bypass toggle for immediate ASM/Custom API refreshes.
- Added settings to hide the upper Adoptables and Adopted pager controls.
- Added configurable adopted-modal text/button linking to the adoptables page.
- Improved adopted animal direct profile discoverability and SEO cache behaviour.

14.0.13
- Custom API is now a live selectable source from the Global tab.
- Improved modal accessibility with dialog roles, focus trapping and focus restoration.
- Hardened frontend rendering for feed-provided animal text.
- Fixed duplicate core bootstrap registration and restored bundled form aliases.
- Settings exports now omit saved API keys and password-type fields.

13.0.0
- Admin submenu routing rebuilt to stop panel bleed
- Advanced setup wizard expanded with data source selection
- Live configurable data source connectors added for ASM, Shelterluv, PetPoint and Custom API
- Adopted modal controls surfaced cleanly in suite admin
- Top adoptables page label removed; bottom pager retained
- Stats header emoji removed

Rescue Plugin Suite v12.5.1
SEO FEATURES (V14.0.0)
======================
The suite keeps its interactive modal interfaces while also exposing canonical, crawlable animal profile pages.

Adoptable profile format:
/cats/{animal-id}-{animal-name}/

Adopted profile format:
/happy-endings/{animal-id}-{animal-name}/

Animal URLs are included automatically in the WordPress XML sitemap at /wp-sitemap.xml.
The suite emits relevant JSON-LD structured data for animal lists, statistics, quiz pages, forms and the rescue organisation. It also integrates with Yoast SEO and Rank Math canonical and description filters where those plugins are active.

SEO performance still depends on the surrounding page title, written introductory content, hosting speed, image quality, internal links and the site's general WordPress SEO configuration.
