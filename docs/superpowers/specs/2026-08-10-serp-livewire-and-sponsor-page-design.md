# SERP Livewire Refactor, Search Bar, Sponsor Us Page, Footer — Design

**Date:** 2026-08-10
**Status:** Draft — pending review
**Scope:** Implement the "Sponsor Us" and "Search Results" canvases from the Figma file
(`rMMzAzhxu1QoqqUYaGrzf2`), refactor the search page onto Livewire, add a real search bar,
paginate organic results, and add a shared footer with a Privacy Policy page.

## Purpose

The site now boots straight into the SERP at `/` (route already changed by the user). This
design covers everything needed to match the Figma mockups for that page and the new Sponsor Us
page: a functional search bar, paginated results, a per-row "Sponsored" label, a shared footer,
and the sponsorship lead-gen form with a static PayPal link.

## Assumptions requiring confirmation

The Figma file is not fully self-consistent across breakpoints and contradicts one earlier
approved decision. Rather than guess silently, here is what this design commits to and why —
correct any of these in review and the plan changes accordingly.

1. **The search bar filters already-collected data; it does not call Brave/Google live.**
   `SearchVertical` results are fetched once a day by a scheduled command for one configured
   query (`vertucio`) — see `2026-08-10-name-search-monitoring-design.md`. There is no
   infrastructure for on-demand external queries, and adding one is a paid-API, non-trivial
   change. The Figma search bar's editable field is implemented as a filter over the
   already-stored News/Images/Sponsored/Results for the day (title/description match), not a new
   external search. The pill to its left keeps showing the locked monitored term. If real
   external search-on-demand was intended, that is a separate, much larger design.

2. **One shared search-bar component, not two header styles.** Figma has two different top-bar
   treatments: a full brand-logo + nav-links header, present only on the Sponsor Us **desktop**
   frame, and a compact pill/input/button search bar, present on every other frame (SERP at all
   three breakpoints, and Sponsor Us mobile/tablet). This design implements only the compact
   search bar, everywhere, for consistency. The "About SERP" / "Sponsor" nav links that appeared
   only in that one desktop frame are dropped; "Sponsor Us" and "Privacy Policy" links live in the
   footer instead, which Figma does show on every frame.

3. **Sponsored rows get an inline label, reversing the earlier decision.**
   `2026-08-10-sponsored-results-design.md` explicitly rejected a per-row "Sponsored" badge in
   favor of a section heading, reasoning that both together say the same thing twice. The new
   Figma mockup shows the opposite: no section heading, but each row carries an inline uppercase
   "SPONSORED" label next to its title. This design follows the newer Figma mockup (inline label,
   no heading) as the more recent instruction, superseding that paragraph of the earlier doc.

4. **Pagination applies only to the organic Results section.** Figma shows a numbered pager only
   under "Results"; News, Images, and Sponsored stay capped and unpaginated, matching the mockup.

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

- `#[Url(as: 'q')] public string $term = '';` — bound to the query string so a filtered view is
  linkable/bookmarkable; also lets the search-bar submit as a normal `wire:submit` without a full
  page reload.
- `use WithPagination;` — organic Results section only, per assumption 4.
- `#[Computed]` properties for `news`, `images`, `sponsored`, and a paginated `results` property,
  replacing the current `with()` array. Same underlying calls as today
  (`LatestSearchResults::for()`, `SponsoredResult::active()`), now passed `$this->term` as an
  optional filter.
- `updatedTerm()` resets the paginator to page 1, the standard Livewire search pattern.
- The display caps (`NEWS_LIMIT`, `IMAGE_LIMIT`) stay as they are; `RESULT_LIMIT` becomes the
  paginator's per-page size instead of a hard `limit()`.

### `LatestSearchResults` changes

- `for(SearchVertical $vertical, int $limit, ?string $term = null): Collection` — adds an
  optional term filter (`WHERE title LIKE ? OR description LIKE ?`) ahead of the existing
  `limit()`.
- New `paginate(SearchVertical $vertical, int $perPage, ?string $term = null): LengthAwarePaginator`
  for the Results section, same snapshot-id scoping as `for()`.

### `SponsoredResult`

- `active()` scope gains the same optional term match, applied by the page alongside the existing
  date-window condition.

## Shared component: `x-site.search-bar`

`resources/views/components/site/search-bar.blade.php` — the Figma pill: bold label (brand name
or locked query), a decorative swap icon (downloaded from Figma and committed as a static SVG,
never hand-authored), a text input, and a black pill "Search" button with a search icon.

- On the search page: input two-way-bound to `$term` via `wire:model`, `wire:submit` triggers
  the page's own search (no navigation).
- On the Sponsor Us page: same visual component, but submitting navigates to
  `route('search', ['q' => $value])` — there is no live data to filter on that page.

Replaces the search page's current inline header markup.

## Pagination

A custom Blade paginator view (Laravel's `paginationView()` hook) styled to match Figma: filled
black "current page" pill, outlined others, chevron prev/next buttons, ellipsis collapsing for
long ranges — Laravel's default paginator already collapses ranges, so no custom windowing logic
is needed, only styling.

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

- Unit: `LatestSearchResults::for()`/`paginate()` term filtering; `SponsoredResult::active()` term
  filtering.
- Livewire test: typing a term filters News/Images/Sponsored/Results; clearing it restores the
  full set; submitting a new term resets pagination to page 1; paging advances correctly.
- Feature: `/privacy` renders with 200; footer links resolve to the correct named routes from
  both pages; search-bar submission on the Sponsor Us page navigates to `/?q=...`.
- Sponsor Us: lead creation + validation + mail sending, as previously scoped.
- Regression: existing SERP tests updated to Livewire's testing API where they currently do a
  plain `get()`; same content assertions.

## Out of scope

- Real external live search against Brave/Google per keystroke or submit.
- Any admin UI.
- Real PayPal checkout/SDK integration.
- An "About SERP" page — no nav link to it survives the header unification in assumption 2.
