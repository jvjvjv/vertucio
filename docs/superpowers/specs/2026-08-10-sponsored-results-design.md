# Sponsored Results — Design

**Date:** 2026-08-10
**Status:** Approved
**Scope:** New `SponsoredResult` model, CLI management, SERP rendering, and an attribute retrofit of the
existing search models.

## Purpose

The SERP page currently renders only fetched results. Add a paid-placement slot holding **self-authored
promos** — entries written by hand to advertise our own work, occupying the "Sponsored" block above the
organic results.

Two decisions frame everything below.

### Sponsored results are content, not captured data

They are authored, not fetched. They never arrive from Brave or Google News, never belong to a snapshot,
and never expire when the next fetch runs.

This rules out the alternative considered during design: an `is_sponsored` flag on `search_results` plus a
synthetic snapshot to hang promos from. `search_snapshots` is an append-only fetch log, and mixing authored
content into it means promos are either re-inserted on every fetch or orphaned by it. The lifecycles are
genuinely different, so the tables are separate.

Capturing _provider_ ads — seeing who bids on the name over time — was considered and rejected. The Brave
Search API does not return ad listings, so it would require a new paid source. Not in scope.

### Availability is derived from dates, never stored

There is no `is_active` column. A stored boolean drifts out of sync with the schedule the moment a promo
starts or ends without someone running a command. Availability is computed from `starts_at` / `ends_at`
every time it is asked for.

## Schema

```
sponsored_results
  id
  position      unsigned small int   -- order within the sponsored block
  url           text
  title         text
  description   text       nullable
  image_url     text       nullable
  starts_at     timestamp  nullable   -- null = live from creation
  ends_at       timestamp  nullable   -- null = runs indefinitely
  timestamps

  index(['starts_at', 'ends_at'])
```

Deliberately absent:

- **`search_snapshot_id`** — promos are not part of any fetch.
- **`url_hash`** — `search_results` carries it to dedupe URLs across snapshots. Hand-authored rows have
  nothing to dedupe against.
- **`meta`** — the JSON column on `search_results` absorbs provider-specific payload variance. There is no
  provider here.
- **`is_active`** — derived; see above.

### Window boundaries

**`starts_at <= now` and `ends_at > now`.** Inclusive start, exclusive end.

This is stated explicitly because it is the one detail that will otherwise be reinterpreted. The exclusive
end makes "retire this promo right now" a well-defined operation — setting `ends_at = now()` takes the
promo down on the same tick, with no rounding ambiguity. It is also the convention every campaign
scheduler uses.

`AppServiceProvider` already calls `Date::use(CarbonImmutable::class)`, so both columns cast to
`immutable_datetime` and type as `CarbonImmutable|null`.

## Status is a first-class concept

Derived availability has three states, not two. `sponsored:list` has to distinguish "not yet" from "no
longer", and so does anyone debugging why a promo is not showing.

`App\Search\Enums\SponsorshipStatus` — beside the existing `SearchVertical`:

```php
enum SponsorshipStatus: string
{
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Expired = 'expired';
}
```

The enum lives under `app/Search/Enums/` rather than a new `app/Enums/` because sponsored results exist to
be rendered on the SERP, and that keeps the two enums in the application together.

## The model

```php
#[Guarded]
class SponsoredResult extends Model
{
    /** @use HasFactory<SponsoredResultFactory> */
    use HasFactory, RendersResultLinks;

    #[Scope]
    protected function active(Builder $query, ?CarbonImmutable $at = null): void
    {
        $at ??= CarbonImmutable::now();

        $query
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $at))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $at));
    }

    public function status(?CarbonImmutable $at = null): SponsorshipStatus
    {
        // Expired takes precedence: a row whose window has closed is expired
        // regardless of when it opened.
    }
}
```

Availability is expressed twice, at two different layers, because both are needed and neither can serve the
other's job:

- **`#[Scope] active()`** answers it in SQL, for fetching the rows to render.
- **`status()`** answers it in PHP for a row already in hand, for `sponsored:list`.

Both accept an optional `$at` so tests assert the boundary conditions directly rather than leaning on
`travelTo` for every case.

`#[Scope]` is Laravel 13's attribute form of a local scope, replacing the `scopeActive()` prefix
convention. Verified against the installed framework: `Model.php` accepts any non-private method carrying
the attribute, so `protected` is correct.

No `app/Search/Queries/` read-model class for this. `LatestSearchResults` earns its keep because "newest
snapshot per provider, merged across providers" is non-trivial; `active()->orderBy('position')` is not.
The page calls the scope directly.

## Shared link safety

`SearchResult` currently owns `safeUrl()`, `safeImageUrl()`, `displayHost()`, and the private
`safeHttpUrl()` / `stripWww()` helpers. `x-serp.result-row` calls them.

`SponsoredResult` needs the same guarantees. Authorship is not safety — a hand-typed `javascript:` URL
executes on click exactly like a fetched one, and the scheme check is what stops it.

Extract the common implementations into **`app/Concerns/RendersResultLinks.php`**, alongside the existing
`PasswordValidationRules` and `ProfileValidationRules` traits.

The trait provides the general case:

| Method           | Trait behavior                      |
| ---------------- | ----------------------------------- |
| `safeUrl()`      | http(s) allow-list over `url`       |
| `safeImageUrl()` | http(s) allow-list over `image_url` |
| `displayHost()`  | host of `url`, `www.` stripped      |

`SearchResult` overrides two of them to keep its provider-specific fallbacks, which have nothing to do with
sponsored results:

- `displayHost()` — prefers `meta.source_url` (Google News RSS returns an opaque `news.google.com`
  redirect as the link, so the real publisher only survives in meta), then `source`, then the URL host.
- `safeImageUrl()` — falls back to `meta.thumbnail` (Brave).

Both models then satisfy the same rendering surface, which is what lets the Blade components work for
either.

## Rendering

Page order on `resources/views/pages/⚡search.blade.php`:

```
News
Images
Sponsored     ← paid placement, directly above organic
Results
```

A **"Sponsored" section heading** labels the block, matching the uppercase headings the other three
sections already use. The rows carry no separate per-row badge — a heading plus a badge on every row says
the same thing twice, and a labelled block is how a real SERP marks paid placement.

**`x-serp.sponsored-row`** — the `result-row` markup with two changes: the title is authored, so there is no
`displayHost()` fallback for the label, and the link carries `rel="sponsored"` alongside the existing
`noopener noreferrer nofollow`. That `rel` value is the correct declaration for a paid link and is the
reason this is a separate component rather than a prop on `result-row`, which stays untouched.

The block **renders nothing at all** when no promo is live — no heading, no container. It does not use
`x-serp.section`, whose empty states ("Not collected yet" / "No results found") are both meaningless for
authored content. A real SERP with no paid placement simply omits the slot.

The page's `with()` gains:

```php
'sponsored' => SponsoredResult::active()->orderBy('position')->orderBy('id')->get(),
```

`orderBy('id')` breaks position ties deterministically, matching how `LatestSearchResults` orders.

## CLI

Three commands in `app/Console/Commands/`, following `FetchSearchResults`' style — `final class`,
`$signature` with inline argument docs, `$this->components->` output, `self::SUCCESS` / `self::FAILURE`.

| Command                      | Purpose                                                                                                                                                                           |
| ---------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `sponsored:add`              | Laravel Prompts for url / title / description / image / position / starts-at / ends-at, with `--url=` and friends for non-interactive use.                                        |
| `sponsored:list`             | All rows — id, position, status, window, title, host. Not filtered to active; a scheduled or expired promo is exactly what you are looking for when one is missing from the page. |
| `sponsored:end {id} {--at=}` | Sets `ends_at`, defaulting to now.                                                                                                                                                |

`sponsored:end` rather than a delete or an `is_active` toggle. Ending preserves the record and its window,
which is the entire reason the dates exist. Hard deletion is not offered; it is available through tinker if
ever genuinely needed.

### Validation in `sponsored:add`

- `url` must parse to an `http` or `https` scheme — rejected at input rather than silently rendering as
  unlinked text later. The model's `safeUrl()` still guards at render; this is fail-fast, not a
  replacement.
- `ends_at` must be strictly after `starts_at` when both are given.
- Unparseable dates fail with a message rather than falling back to null.

## Attribute retrofit

`User` already carries `#[Fillable]` and `#[Hidden]`. `SearchResult` and `SearchSnapshot` were written with
`protected $guarded = []` and drifted from that house style.

Apply `#[Guarded]` to `SearchResult`, `SearchSnapshot`, and the new `SponsoredResult`. Verified against the
installed framework: `GuardsAttributes` resolves a no-argument `#[Guarded]` to `[]`, identical to the
current property, so this is a pure expression change.

```php
/**
 * Written only by StoreSearchSnapshot, never from request input.
 */
#[Guarded]
class SearchResult extends Model
```

The comment explaining _why_ the model is unguarded moves above the attribute. It is the part that is not
recoverable from the code.

Two things deliberately not changed:

- **The `/** @use HasFactory<SearchResultFactory> \*/`docblocks stay.**`#[UseFactory]`exists in Laravel
13, but Larastan needs the generic to type`::factory()`, and `User` keeps its docblock for the same
  reason. Using both would be redundant; the docblock is the one that carries type information.
- **`casts()` stays a method.** Laravel 13 has no class attribute for casts, and `User` uses the method
  form.

## Testing

Pest, following the existing `tests/Feature/Search/` and `tests/Unit/Search/` split.

**New:**

| Test                           | Covers                                                                                                                                                  |
| ------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Scope and status boundaries    | `starts_at == now` is Active; `ends_at == now` is Expired; null start and null end each behave as open-ended. Uses the `$at` parameter, not `travelTo`. |
| `SponsoredResultUrlSafetyTest` | Mirrors `SearchResultUrlSafetyTest` — `javascript:`, `data:`, scheme-relative, and empty values all reject.                                             |
| Command tests                  | `sponsored:add` happy path and both validation failures; `sponsored:list` status column; `sponsored:end` closing an active promo.                       |
| Page test                      | Sponsored rows render between Images and Results; block absent entirely when nothing is live; a scheduled promo does not appear.                        |

`SponsoredResultFactory` with `active()`, `scheduled()`, and `expired()` states.

**Regression:** every existing test under `tests/Feature/Search/` and `tests/Unit/Search/` must stay green
throughout. They are the check on the
trait extraction and the `#[Guarded]` swap — both are refactors that should be invisible to behavior, and
a red test there means one of them was not.

`vendor/bin/pint --dirty` and a Larastan run before finishing.

## Not in scope

- Capturing sponsored listings returned by search providers.
- An admin UI. The CLI is the management surface.
- Click tracking or impression counting on promos.
- Any change to the fetch, snapshot, or provider layers.
