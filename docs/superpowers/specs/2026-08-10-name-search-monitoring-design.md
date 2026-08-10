# Name Search Monitoring — Design

**Date:** 2026-08-10
**Status:** Approved
**Scope:** Service layer + scheduled persistence. No UI.

## Purpose

Fetch and store search engine results for the name `vertucio` across web, news, and image
verticals, on a daily schedule, so that search presence can be reviewed and diffed over time.

This spec covers the fetching and storage layer only. Presentation is deliberately deferred.

## Engine selection and why

The original request named five engines: Bing, Google, Yahoo, DuckDuckGo, Brave. Research during
design reduced this to two providers. The reasoning is recorded here because it is not
recoverable from the code.

### Index overlap

Only three of the five operate an independent web index:

| Engine     | Index                                                                              | First-party API                                |
| ---------- | ---------------------------------------------------------------------------------- | ---------------------------------------------- |
| Google     | Own                                                                                | Closed to new customers; shuts down 2027-01-01 |
| Bing       | Own                                                                                | Retired 2025-08-11                             |
| Brave      | Own (~30B pages)                                                                   | Available — web, news, images, videos          |
| Yahoo      | Bing's — no independent index since 2009                                           | None                                           |
| DuckDuckGo | Bing's for web and images; DuckDuckBot only serves link-health and Instant Answers | None returning web results                     |

Yahoo and DuckDuckGo are presentation layers over Bing's index. Querying them returns
substantially the same documents Bing returns, re-ranked and re-skinned. They were dropped
because they add cost and code without adding coverage.

### API availability

- **Bing Search APIs** were retired on 2025-08-11. Microsoft's stated migration path is
  Grounding with Bing Search inside Azure AI Foundry, which is an agent platform rather than a
  search endpoint. Bing results are now reachable only through third-party SERP proxies.
- **Google Custom Search JSON API** is closed to new customers, and existing customers must
  migrate by 2027-01-01. There is no key on this account, so it cannot be signed up for. It also
  supports only web and image search — it has never had a news vertical.
- **Brave Search API** is available and supported, covering web, news, and images first-party at
  $5 per 1,000 requests with $5 of credit renewing monthly.

### Resulting providers

- **Brave** — web, news, images.
- **Google News RSS** (`news.google.com/rss/search`) — news only. Free, requires no key, and is
  the only remaining no-cost route to Google-sourced results.

Google web and image results are **not covered**. Obtaining them would require a paid SERP proxy,
which was explicitly rejected. This is a known and accepted gap.

## Architecture

```
app/Search/
  Contracts/SearchProvider.php
  Enums/SearchVertical.php
  Data/SearchQuery.php
  Data/SearchResultSet.php
  Data/SearchResultItem.php
  Providers/BraveSearchProvider.php
  Providers/GoogleNewsRssProvider.php
  Actions/StoreSearchSnapshot.php
  Exceptions/SearchProviderException.php
  SearchManager.php

app/Models/SearchSnapshot.php
app/Models/SearchResult.php
app/Console/Commands/FetchSearchResults.php
```

`app/Search/` is a new base folder, approved during design. Eloquent models stay in `app/Models/`
alongside `User`, following the existing structure. The persistence action lives under
`app/Search/Actions/` rather than the existing `app/Actions/` because `app/Actions/` is
Fortify-owned in this application.

### Contract

```php
interface SearchProvider
{
    public function name(): string;

    public function supports(SearchVertical $vertical): bool;

    public function search(SearchQuery $query): SearchResultSet;
}
```

Each provider normalizes its own payload into the shared `SearchResultItem` shape. Consumers
never branch on provider identity.

An interface for two providers is justified by expected churn rather than current count: Brave is
the single point of failure for three of the four feeds, and both Bing (via proxy) and a Google
web replacement are plausible future additions. Adding one means a new class and a config entry,
with no changes to storage, command, or scheduling.

### Enum

`SearchVertical`: `Web`, `News`, `Images`. TitleCase keys per project PHP conventions, backed by
lowercase string values used for persistence.

### Data objects

Plain readonly PHP classes, not Eloquent models.

- **`SearchQuery`** — `query`, `vertical`, `limit`, `locale`.
- **`SearchResultSet`** — `provider`, `vertical`, `query`, `fetchedAt`, `items` (array of
  `SearchResultItem`), `meta`.
- **`SearchResultItem`** — `position`, `url`, `title`, `description`, `source`, `publishedAt`,
  `imageUrl`, `meta`.

Fields absent from a given provider are null. Image results carry `imageUrl`; web results do not.

### SearchManager

- `providersFor(SearchVertical $vertical): Collection<SearchProvider>`
- `fetchAll(string $query): Collection<SearchResultSet>` — every registered provider crossed with
  every vertical it supports.

Providers are resolved from `config('search.providers')` so the registry is a config concern.

## Provider details

### Brave

- `GET https://api.search.brave.com/res/v1/web/search`
- `GET https://api.search.brave.com/res/v1/news/search`
- `GET https://api.search.brave.com/res/v1/images/search`

Headers: `X-Subscription-Token: <key>`, `Accept: application/json`.

Response shapes differ per vertical — web results are nested under `web.results`, while news and
images return a top-level `results` array. **Exact field mapping must be derived from recorded
live responses during implementation, not assumed from documentation.** The first implementation
step is to capture one real response per vertical into `tests/Fixtures/search/` and build the
mapping against those fixtures.

### Google News RSS

- `GET https://news.google.com/rss/search?q={query}&hl=en-US&gl=US&ceid=US:en`

Returns RSS 2.0. Each `item` provides `title`, `link`, `pubDate`, `description` (HTML), and
`source` (with a `url` attribute). Notes that affect implementation:

- Feeds are capped at 100 items.
- `link` is a Google redirect URL, not the publisher URL. Store it as-is; resolving redirects is
  out of scope.
- Requests must follow 302 redirects.
- There is no SLA. Treat failures as expected and non-fatal.

## Storage

Immutable snapshots. Nothing is ever updated after write, which makes "what changed between two
dates" a plain query and preserves rank history for free.

### `search_snapshots`

| Column         | Type            | Notes                            |
| -------------- | --------------- | -------------------------------- |
| `id`           | id              |                                  |
| `provider`     | string          | `brave`, `google_news_rss`       |
| `vertical`     | string          | `web`, `news`, `images`          |
| `query`        | string          |                                  |
| `fetched_at`   | timestamp       |                                  |
| `result_count` | unsignedInteger |                                  |
| `meta`         | json, nullable  | provider-level response metadata |
| timestamps     |                 |                                  |

Index: `(provider, vertical, fetched_at)`.

### `search_results`

| Column               | Type                 | Notes                            |
| -------------------- | -------------------- | -------------------------------- |
| `id`                 | id                   |                                  |
| `search_snapshot_id` | foreignId            | constrained, cascadeOnDelete     |
| `position`           | unsignedSmallInteger | 1-based rank within the snapshot |
| `url`                | text                 |                                  |
| `url_hash`           | char(64)             | sha256 of `url`, indexed         |
| `title`              | text, nullable       |                                  |
| `description`        | text, nullable       |                                  |
| `source`             | string, nullable     |                                  |
| `published_at`       | timestamp, nullable  |                                  |
| `image_url`          | text, nullable       |                                  |
| `meta`               | json, nullable       |                                  |
| timestamps           |                      |                                  |

Index: `url_hash`.

**Why `url_hash`:** the database is MySQL, where a `text` column cannot take a plain index and the
utf8mb4 index limit of 3072 bytes caps a fully indexed `string` at 768 characters — shorter than
URLs reliably are. Storing a sha256 alongside the full URL gives exact-match lookups ("when did
this URL first appear") without truncating data or the index.

### Models

`SearchSnapshot hasMany SearchResult`; `SearchResult belongsTo SearchSnapshot`. Factories for
both. No seeders — there is no meaningful seed state for observed search results.

### Persistence action

`StoreSearchSnapshot` takes a `SearchResultSet` and writes the snapshot plus its results inside a
transaction, returning the `SearchSnapshot`.

## Configuration

Brave credentials live in `config/services.php`, per Laravel convention for third-party keys:

```php
'brave' => [
    'key' => env('BRAVE_SEARCH_KEY'),
],
```

Application settings live in a new `config/search.php`:

```php
return [
    'query' => env('SEARCH_QUERY', 'vertucio'),
    'results_per_vertical' => 20,
    'locale' => [
        'language' => 'en',
        'country' => 'US',
    ],
    'providers' => [
        BraveSearchProvider::class,
        GoogleNewsRssProvider::class,
    ],
];
```

`SearchQuery::$locale` is populated from `config('search.locale')` by `SearchManager`. Each
provider translates it into its own parameters — Brave uses `country` and `search_lang`, Google
News RSS uses `hl`, `gl`, and `ceid`.

`BRAVE_SEARCH_KEY` and `SEARCH_QUERY` are added to `.env.example`.

## Command and scheduling

`php artisan search:fetch {query?}` — defaults to `config('search.query')`.

Scheduled daily in `routes/console.php`. Daily cadence is four requests per day (three Brave, one
RSS), roughly 90 Brave requests per month, comfortably inside the renewing $5 monthly credit.

## Error handling

- `Http::retry(2, 200)` with an explicit timeout on every outbound request.
- Non-2xx responses and unparseable payloads throw `SearchProviderException`.
- The command catches per provider/vertical pair, so one failing feed cannot abort the other
  three. Failures are logged and the command exits non-zero if any pair failed.
- Partial runs persist whatever succeeded. A failed feed writes no snapshot for that day, which
  reads correctly as "no data collected" rather than "zero results found" — a distinction that
  matters when diffing.

## Testing

Pest. No live network in the suite: `Http::preventStrayRequests()` throughout, with recorded
fixtures in `tests/Fixtures/search/`.

- Unit, per provider — fixture payload maps to the expected `SearchResultSet`, covering field
  mapping, 1-based positions, and date parsing.
- Unit, per provider — a non-2xx response throws `SearchProviderException`.
- Unit, per provider — `supports()` reports the correct verticals.
- Unit — `SearchManager::fetchAll()` queries each provider only for verticals it supports.
- Feature — `search:fetch` writes the expected snapshot and result rows.
- Feature — when one provider throws, the others still persist and the command exits non-zero.

## Out of scope

- Any UI or Livewire component.
- Deduplication, diffing, or trend views over stored snapshots.
- Snapshot pruning. Add `search:prune` when table size justifies it, not before.
- Google web and image results.
- Bing, Yahoo, and DuckDuckGo.
- Resolving Google News redirect URLs to publisher URLs.
