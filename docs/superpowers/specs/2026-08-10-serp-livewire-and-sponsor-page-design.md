# SERP Livewire Refactor, Search Bar, Sponsor Us Page, Footer — Design

**Date:** 2026-08-10
**Status:** Approved
**Scope:** Implement the "Sponsor Us" and "Search Results" canvases from the Figma file
(`rMMzAzhxu1QoqqUYaGrzf2`), refactor the search page onto Livewire, add a real (live, cached)
search bar, paginate every section, and add a shared footer with a Privacy Policy page.

## Purpose

The site now boots straight into the SERP at `/` (route already changed by the user). This
design covers everything needed to match the Figma mockups for that page and the new Sponsor Us
page: a search bar that performs a real search, pagination on every section, a per-row
"Sponsored" label, a shared footer, and the sponsorship lead-gen form with a static PayPal link.

## Resolved decisions

The Figma file was not fully self-consistent across breakpoints. These points were raised for
review and resolved as follows.

1. **The search bar performs a real, live search — cached, but never persisted.** With no term
   typed, every section reads the daily-collected snapshot for the one monitored query
   (`config('search.query')`), unchanged from before. Once a term is submitted, News, Images, and
   the organic Results section call through the existing `SearchManager`/provider layer live for
   that term, via a new `LiveSearch` action. The result is cached (`Cache::remember`, 15 minutes)
   so repeating the same typed query doesn't re-hit paid APIs, but it is never written to
   `search_snapshots`/`search_results` — those tables stay exclusively the daily-fetch log. Live
   results are wrapped as unsaved `SearchResult::make(...)` instances so the existing result-card
   components render them identically to stored rows. Sponsored results have no live equivalent
   (they're authored, not fetched) — a typed term filters the existing table directly, as before.

2. **One shared search-bar component, with an optional logo.** Figma showed two different top-bar
   treatments: a full brand-logo header only on the Sponsor Us desktop frame, and a compact
   pill/input/button bar everywhere else. Rather than pick one, the shared `x-site.search-bar`
   component takes a `logo` prop — pages that want the brand mark pass `:logo="true"`. All three
   pages (search, sponsor, privacy) show it. The "About SERP" / "Sponsor" nav links that appeared
   only in the Sponsor Us desktop frame live in the shared footer instead, present on every page.

3. **Sponsored rows get an inline label; no section heading.** Confirmed against Figma, which is
   the corrected source of truth — an earlier text-only design doc
   (`2026-08-10-sponsored-results-design.md`) had this backwards, predating a deliberate correction
   made directly in Figma. Each sponsored row carries its own inline uppercase "Sponsored" label
   next to the title; there is no separate section heading for the block.

4. **Pagination applies to every section, not just Results.** News, Images, Sponsored, and Results
   each paginate independently via Livewire's named-paginator support. Per-page sizes start at the
   design's original display caps (News 3, Images 6, Sponsored 3, Results 4) and are expected to
   rise once this initial implementation is handed off.

5. **The Sponsor Us form, model, mail, and PayPal link are unchanged from the design already
   approved earlier this session** (store the lead, email a notification, static PayPal.me link
   via config). Not re-litigated here.

## Routing

```php
Route::livewire('/', 'pages::search')->name('search');       // already done
Route::livewire('sponsor', 'pages::sponsor')->name('sponsor');
Route::view('privacy', 'pages.privacy')->name('privacy');
```

`resources/views/welcome.blade.php` is deleted — it was the Laravel starter page and nothing
routes to it now that `/` serves the SERP.

## Search page: Livewire refactor

`pages/⚡search.blade.php` stops being a `with()`-driven display component and becomes a
stateful Livewire component:

- `#[Url(as: 'q')] public string $term = '';` — bound to the query string so a searched view is
  linkable/bookmarkable; also lets the search-bar submit as a normal `wire:submit` without a full
  page reload.
- `use WithPagination;` — every section, each under its own paginator name (`'news'`, `'images'`,
  `'sponsored'`, `'results'`), per resolved decision 4.
- `#[Computed]` properties for `news`, `images`, `sponsored`, `results`, each a
  `LengthAwarePaginator`, replacing the current `with()` array.
- With `$term` blank: each of News/Images/Results reads
  `LatestSearchResults::paginate($vertical, $perPage, $pageName)` — the daily snapshot for the
  monitored query, exactly as before. Sponsored always reads
  `SponsoredResult::active()->paginate(...)`.
- With `$term` filled: News/Images/Results instead call `LiveSearch::for($vertical, $term)` (a
  cached, non-persisted, live provider call — see below), wrap each item as an unsaved
  `SearchResult::make(...)` so the existing card components render it unchanged, and paginate the
  in-memory collection manually with a `LengthAwarePaginator`. Sponsored applies
  `->matching($term)` to the same DB query instead — no live equivalent exists for authored
  content.
- `updatedTerm()` resets every section's paginator to page 1 — the standard Livewire search
  pattern, done per-paginator-name since each section tracks its own page.
- Per-page sizes start at the design's original display caps (News 3, Images 6, Sponsored 3,
  Results 4) as class constants, easy to raise later.

### New: `App\Search\Actions\LiveSearch`

`LiveSearch::for(SearchVertical $vertical, string $query): Collection<SearchResultItem>` — merges
every provider supporting the vertical via the existing `SearchManager`, catching a per-provider
`SearchProviderException` so one failing provider doesn't blank the whole result (same tolerance
`FetchSearchResults` already has for the scheduled fetch). The merged result is cached via
`Cache::remember` for 15 minutes, keyed by vertical and lowercased query, so repeatedly searching
the same term doesn't repeatedly hit paid APIs. **Nothing here ever writes to
`search_snapshots`/`search_results`** — those tables stay exclusively the daily-fetch log for
`config('search.query')`.

### `LatestSearchResults` changes

- `paginate(SearchVertical $vertical, int $perPage, string $pageName = 'page'): LengthAwarePaginator`
  gains a `$pageName` parameter so News/Images/Results can each track their own page
  independently. `for()` is unchanged.

### `SponsoredResult`

- New `matching(?string $term)` local scope (`WHERE title LIKE ? OR description LIKE ?`, a no-op
  when the term is blank), applied by the page alongside the existing `active()` date-window
  scope.

## Shared component: `x-site.search-bar`

`resources/views/components/site/search-bar.blade.php` — the Figma pill: bold label (brand name
or locked query), a decorative swap icon (downloaded from Figma and committed as a static SVG,
never hand-authored), a text input, and a black pill "Search" button with a search icon. An
optional `logo` prop prepends the "Vertucio" wordmark and brand dot (resolved decision 2);
defaults to `false`, and every page in this design passes `:logo="true"`.

- On the search page: input two-way-bound to `$term` via `wire:model`, `wire:submit` triggers
  the page's own search (no navigation).
- On the Sponsor Us and Privacy pages: same visual component, but submitting navigates to
  `route('search', ['q' => $value])` — there is no live data to search from those pages.

Replaces the search page's current inline header markup.

## Pagination

A custom Blade paginator view (`resources/views/vendor/pagination/serp.blade.php`, passed
explicitly to `->links('vendor.pagination.serp', ['pageName' => ...])`) styled to match Figma:
filled black "current page" pill, outlined others, chevron prev/next buttons, ellipsis collapsing
for long ranges — Laravel's default paginator already collapses ranges, so no custom windowing
logic is needed, only styling. The view accepts a `$pageName` variable (defaulting to `'page'`) so
each of the four independently-paginated sections wires its buttons to the correct named
paginator's `previousPage`/`nextPage`/`setPage` methods.

## Sponsored rows

New `x-serp.sponsored-row`, replacing the sponsored block's use of `x-serp.section`'s heading:
URL line, then a row with an inline uppercase "Sponsored" label beside the title, then the
description — matching the Figma layout in assumption 3. The block still renders nothing at all
when no promo is live (unchanged from the original spec).

## Footer

`resources/views/components/site/footer.blade.php` — copyright plus "Privacy Policy"
(`route('privacy')`) and "Sponsor Us" (`route('sponsor')`, bold/active when already on that page).
Added to both the search page and the new Sponsor Us page.

## Privacy Policy page

`resources/views/pages/privacy.blade.php` — a plain Blade view (no Livewire, nothing dynamic),
sharing `layouts.serp` and the new header/footer. Content reflects what the site actually does:
no user accounts, no data collected beyond what the sponsorship form submits, what that data is
used for, and how to request removal. Short and honest, not filler legal boilerplate.

## Sponsor Us page (unchanged from prior approval)

- `pages::sponsor` Livewire page, `SponsorshipInquiry` model with explicit `#[Fillable([...])]`
  (not `#[Guarded([])]` — this one is written directly from public request input), migration,
  factory.
- Form: full name, email, company name, monthly budget (fixed tiers via `<select>`), message;
  `#[Validate]` attributes; on submit, creates the row and sends
  `App\Mail\SponsorshipInquiryReceived` to `config('sponsorship.notify_email')`.
- PayPal button is a static link to `config('sponsorship.paypal_url')`, both added to
  `config/sponsorship.php` and `.env.example`.

## Testing

Pest throughout, following existing `tests/Feature/Search/` and `tests/Unit/Search/` structure.

- Unit: `LiveSearch` — merges multi-provider results, tolerates a provider throwing, caches
  repeated queries, never touches `search_snapshots`/`search_results`.
- Unit: `LatestSearchResults::paginate()` named-paginator behavior.
- Feature: `SponsoredResult::matching()` term filtering.
- Livewire test: with no term, the default monitored-query view renders; submitting a term
  performs a live search and does not persist anything; a new term resets every section's page to
  1 independently; sponsored results filter by the same term.
- Feature: `/privacy` renders with 200; footer links resolve to the correct named routes from
  every page; search-bar submission on the Sponsor Us/Privacy pages navigates to `/?q=...`.
- Sponsor Us: lead creation + validation + mail sending, as previously scoped.
- Regression: existing SERP tests updated to Livewire's testing API where they currently do a
  plain `get()`; same content assertions.

## Out of scope

- Real PayPal checkout/SDK integration.
- An admin UI for anything.
- A dedicated "About SERP" page — no nav link to it survives the header unification.
- An "About SERP" page — no nav link to it survives the header unification in assumption 2.
