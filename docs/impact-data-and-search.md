# Impact statistics and rescue-system data

The default **Impact statistics source → Built-in system data** uses documented ASM
service methods. No installed SQL report is needed for adoptables, adoption stories,
current cats in care, or the standard intake/adoption impact totals.

| Value | ASM source | Meaning |
| --- | --- | --- |
| Cats brought in | `json_monthly_stats`, `species=feline`, `liveIntake` | Sum of the standard Shelter Animals Count live-intake categories for each month in the selected year, including returns and transfers. |
| Cats adopted | `json_monthly_stats`, `outComes.liveOutcomes.*.adoption` | Sum of the monthly adoption outcomes in the same statistics methodology. |
| In care now | `json_shelter_animals` | Current shelter-care records with cat species, including cats not yet available for adoption. Shown only when viewing the current year. |
| Vaccinated, neutered, microchipped | Not provided by these standard statistics methods | Unavailable, displayed as **—** with a visible and screen-reader status explaining that a dash is not zero. |
| Reserved | Adoptable feed's `HASACTIVERESERVE` / `HASACTIVERESERVENAME` | Native reserved/unreserved status. Detailed reservation-stage names are optional. |

The ASM service account needs **VIEW_ANIMAL** and **VIEW_MOVEMENT** for monthly
statistics, and VIEW_ANIMAL for the shelter-care count. Older/self-hosted ASM versions
may not offer the monthly method. A missing method, missing permission or failed month
produces unavailable annual totals, never a partial total presented as a complete year.
The suite does not request `sensitive=1`, and returns/stores only the aggregate in-care
count from the shelter dataset. Credentials and raw shelter records stay on the server.

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

Select **Optional custom reports** only when an existing report supplies additional
metrics or the connected ASM version lacks the standard method. The existing
`Summary By Year` report accepts `YEAR`/`year` and `CATS_BROUGHT_IN`, `CATS_ADOPTED`,
`CATS_VACCINATED`, `CATS_NEUTERED`, `CATS_CHIPPED` (lowercase forms also work).
Omitted/null metrics stay unavailable; an explicit numeric zero remains zero.
`Cats In Care Now` can supply `cats_in_care`. These are suite adapter titles, **not
universal pre-installed ASM reports**. Report mode requires the corresponding installed
reports and VIEW_REPORT permission; failed impact reports fall back to standard ASM data.

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
and safe-failure behaviour on the disposable WordPress `/impact/` fixture.
