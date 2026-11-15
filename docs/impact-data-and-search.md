# Impact statistics and rescue-system data

The default impact source automatically reuses an existing `Summary By Year` report
when it supplies valid annual totals, preserving earlier installations' health figures.
Missing intake/adoption fields fall back to documented ASM service methods. No installed
SQL report is needed for adoptables, adoption stories, current cats in care or standard
intake/adoption impact totals. Creating a report is optional.

| Value | ASM source | Meaning |
| --- | --- | --- |
| Cats brought in | `json_monthly_stats`, `species=feline`, `liveIntake` | Sum of the standard Shelter Animals Count live-intake categories for each month in the selected year, including returns and transfers. |
| Cats adopted | `json_monthly_stats`, `outComes.liveOutcomes.*.adoption` | Sum of the monthly adoption outcomes in the same statistics methodology. |
| In care now | `json_shelter_animals` | Current shelter-care records with cat species, including cats not yet available for adoption. Shown only when viewing the current year. |
| Vaccinated, neutered, microchipped | Existing `Summary By Year` aggregate, if supplied | Otherwise **Not available**, with normal text sizing and a visible/screen-reader explanation. Missing information never becomes zero. |
| Reserved | Adoptable feed's `HASACTIVERESERVE` / `HASACTIVERESERVENAME` | Native reserved/unreserved status. Detailed reservation-stage names are optional. |

The ASM service account needs **VIEW_ANIMAL** and **VIEW_MOVEMENT** for monthly
statistics, and VIEW_ANIMAL for the shelter-care count. Older/self-hosted ASM versions
may not offer the monthly method. A missing method, missing permission or failed month
produces unavailable annual totals, never a partial total presented as a complete year.
The suite does not request `sensitive=1`, and returns/stores only the aggregate in-care
count from the shelter dataset. Credentials and raw shelter records stay on the server.
Automatic report discovery has a three-second timeout. Valid report aggregates use the
General Statistics cache duration; a missing/unsupported report is remembered for five
minutes to avoid delaying each visitor. Only recognised numeric aggregate columns are
retained. A report for another year never supplies the selected year's figures. A valid
complete report also avoids unnecessary monthly API calls.

The year selector loads one year at a time, using the WordPress site calendar and the
configured minimum year. Future years/months are not requested. Monthly and annual
aggregates are cached: current-month/current-year results use **General → Statistics
cache seconds** (60 seconds by default), completed months/years use one day. Setting
Statistics cache seconds to zero disables both of these successful-data caches. Optional
custom reports use the integration's Reports cache seconds; the current-care count uses
its separate In-care cache seconds. The first uncached year needs up to twelve ASM calls; a whole
refresh has a twenty-second HTTP budget and a five-second limit per monthly call.
Completed months remain cached if the budget expires so a later refresh can resume.
An unavailable annual result is retried after thirty seconds. Explicit suite cache
bypass still bypasses these caches. ASM itself also caches these methods for an hour,
so these are operational statistics rather than an immediate live tally.

## Optional advanced data

The default automatic mode already restores available health metrics from an existing
report. **Optional custom reports** remains available when full report-driven behaviour
is wanted or the connected ASM version lacks the standard method. The existing
`Summary By Year` report accepts `YEAR`/`year` and `CATS_BROUGHT_IN`, `CATS_ADOPTED`,
`CATS_VACCINATED`, `CATS_NEUTERED`, `CATS_CHIPPED` (lowercase forms also work).
Omitted/null metrics stay unavailable; an explicit numeric zero remains zero.
`Cats In Care Now` can supply `cats_in_care`. These are suite adapter titles, **not
universal pre-installed ASM reports**. Report mode requires the corresponding installed
reports and VIEW_REPORT permission; failed impact reports fall back to standard ASM data.
Automatic mode works without VIEW_REPORT: a denied optional probe simply falls back to
standard endpoints. The standard API has no complete yearly health-event endpoint.
Microchip-registration exports track registration triggers rather than implantation
history and include owner data; they are deliberately not used for these public totals.

Enable **Use optional reservation detail report** in ASM integration settings only when
`Active Reservations Proxy` is installed and richer reservation-stage labels are wanted.
It accepts `AID` plus a recognised status-name field such as `STATUSNAME`. Otherwise
the suite skips this request entirely and uses ASM's native reservation flag. This
avoids a failed optional report delaying every adoptable-feed refresh.

For Shelterluv, PetPoint and custom API connections, configured report/count adapters
remain usable. Their APIs, permissions and account-specific endpoints differ, so the
suite does not invent universal built-in report names. If a report is unavailable, an
adoption feed can supply **available adoption-record totals** grouped by its explicit
calendar year; duplicate animals within that year and non-cat records are excluded.
This limited fallback is labelled and cannot supply intake, health or all-in-care totals.
An adoptable-only list is never treated as all animals in care. A dedicated count adapter
must return an explicit numeric count. Complete event history or additional adapters may
still be necessary for these providers.

The adopted-animal feed is intentionally suitable for public success stories. ASM's
`json_adopted_animals` includes only living animals whose latest movement is adoption;
it is not a complete historical outcome ledger. It is therefore **not** used to calculate
ASM's standard yearly adoption statistics.

## Statistics sizing and layouts

Typography settings now apply to the actual card text: **Totals** controls numbers,
**Icons** controls icon tiles (the SVG scales with its tile), **Subheadings / card labels**
controls card headings and the year subtitle, and **Paragraph / card captions** controls
captions and footer text. These sizes are independently configurable for mobile, tablet
and desktop. Mobile defaults use 28px numbers, 28px icon tiles and 14px card padding;
**Mobile card padding** can be changed separately from the regular card padding.

Breakpoints follow the available widget width: under 640px uses mobile settings,
640–1023px uses tablet settings and 1024px upwards uses desktop settings. A narrow
desktop content column therefore uses the smaller values. Resize observation updates
the active settings when an embed's width changes without a window resize.

Tiles, compact cards and the impact band all honour the saved totals/icon/text sizes.
The band wraps into the configured columns instead of forcing one long row; dividers
continue between rows. Unavailable labels use caption typography rather than the large
number size. All styling remains scoped to this widget.

## Search Console description warning

The current impact widget is an accessible HTML section with a text description; its
icons are decorative SVGs. It does not output a `Dataset` rich result. A regression test
keeps Dataset markup absent. Existing animal Article descriptions are now plain text,
have a nonempty fallback and are bounded to 500 Unicode characters; social/search
summaries are bounded to 160. Full animal stories remain visible on their profile pages.

The reported **invalid string length in field `description`** cannot be attributed to
a specific current schema without an affected URL and the Search Console enhancement
type. A cached older Dataset, theme or another SEO plugin may be the source. After
deployment, clear page/CDN caches and inspect the affected URL's rendered JSON-LD and
Google Rich Results Test output. Correct the actual emitting component and use Search
Console's validation after Google can fetch the corrected markup. This release does not
claim that Google has already recrawled or cleared the report.

## References and validation

Implementation verified against ASM's published [service manual](https://github.com/bobintetley/asm3/blob/ade32e55e8e7449d4342e3880c2c3f1bd739a710/doc/manual/service.rst#json_monthly_stats-xml_monthly_stats-csv_monthly_stats)
and [SAC metrics serializer](https://github.com/bobintetley/asm3/blob/ade32e55e8e7449d4342e3880c2c3f1bd739a710/src/asm3/publishers/sacmetrics.py#L138).
`tests/impact-data.php` exercises aggregate/category/date/cache/error/report behaviour
without live credentials; `tests/seo-canonical.php` covers bounded summaries and impact
schema. `tests/statistics-data-browser.cjs` verifies rendered missing-data/zero/loading
and safe-failure behaviour. `tests/statistics-settings.cjs` saves and restores settings,
then checks the computed typography/icon/padding values across every preset and embed
width in the disposable WordPress `/impact/` fixture.
