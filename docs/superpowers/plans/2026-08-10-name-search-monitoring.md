# Name Search Monitoring Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fetch search results for the name `vertucio` from Brave (web, news, images) and Google News RSS (news), and store them as immutable daily snapshots.

**Architecture:** Providers implement a single `SearchProvider` interface and normalize their payloads into shared readonly data objects. A `SearchManager` enumerates provider/vertical pairs; an Artisan command iterates them, catching failures per pair so one bad feed cannot abort the rest, and hands each result set to a `StoreSearchSnapshot` action that writes a snapshot plus its results in a transaction.

**Tech Stack:** PHP 8.4, Laravel 13, Pest 5, MySQL, Larastan level 7, Pint (laravel preset).

**Spec:** `docs/superpowers/specs/2026-08-10-name-search-monitoring-design.md`

## Global Constraints

- PHP 8.4. Use constructor property promotion. Never leave an empty zero-parameter `__construct()`.
- Explicit return types and parameter type hints on every method.
- Curly braces on all control structures, even single-line bodies.
- PHPDoc blocks over inline comments. Use array shape definitions in PHPDoc.
- Enum keys in TitleCase.
- Larastan runs at level 7 over `app/`, `config/`, `database/`, `routes/`. Relation methods need generic PHPDoc (`@return HasMany<SearchResult, $this>`).
- Run `vendor/bin/pint --dirty --format agent` before every commit.
- Do not add or change Composer/npm dependencies.
- Every task ends with passing tests. Run the narrowest useful filter, not the whole suite.

## Prerequisite

Task 3 requires a live Brave Search API key to capture response fixtures. Obtain one at
<https://api-dashboard.search.brave.com/> (free tier: $5 of credit renewing monthly) and put it in
`.env` as `BRAVE_SEARCH_KEY` before starting Task 3. Tasks 1, 2, 4, 5, and 6 need no key.

## File Structure

| File | Responsibility |
| --- | --- |
| `app/Search/Enums/SearchVertical.php` | The three verticals, string-backed for persistence |
| `app/Search/Data/SearchQuery.php` | An immutable request: term, vertical, limit, locale |
| `app/Search/Data/SearchResultItem.php` | One normalized result row |
| `app/Search/Data/SearchResultSet.php` | One provider/vertical fetch and its items |
| `app/Search/Contracts/SearchProvider.php` | The provider interface |
| `app/Search/Exceptions/SearchProviderException.php` | Named failure constructors |
| `app/Search/Providers/BraveSearchProvider.php` | Brave HTTP calls + payload mapping |
| `app/Search/Providers/GoogleNewsRssProvider.php` | Google News RSS fetch + XML mapping |
| `app/Search/SearchManager.php` | Provider registry, pair enumeration, query construction |
| `app/Search/Actions/StoreSearchSnapshot.php` | Transactional persistence of one result set |
| `app/Models/SearchSnapshot.php` | Snapshot model |
| `app/Models/SearchResult.php` | Result model |
| `app/Console/Commands/FetchSearchResults.php` | `search:fetch`, per-pair error policy |
| `config/search.php` | Query, limit, locale, provider registry |

---

### Task 1: Domain foundation — enum, data objects, contract, exception

**Files:**
- Create: `app/Search/Enums/SearchVertical.php`
- Create: `app/Search/Data/SearchQuery.php`
- Create: `app/Search/Data/SearchResultItem.php`
- Create: `app/Search/Data/SearchResultSet.php`
- Create: `app/Search/Contracts/SearchProvider.php`
- Create: `app/Search/Exceptions/SearchProviderException.php`
- Modify: `tests/Pest.php`
- Test: `tests/Unit/Search/SearchResultSetTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `SearchVertical::{Web,News,Images}`; `SearchQuery(string $query, SearchVertical $vertical, int $limit = 20, string $language = 'en', string $country = 'US')`; `SearchResultItem(int $position, string $url, ?string $title = null, ?string $description = null, ?string $source = null, ?Carbon $publishedAt = null, ?string $imageUrl = null, array $meta = [])`; `SearchResultSet(string $provider, SearchVertical $vertical, string $query, Carbon $fetchedAt, array $items, array $meta = [])` with `count(): int`; `SearchProvider::{name(),supports(),search()}`; `SearchProviderException::{requestFailed(),unexpectedPayload(),unsupportedVertical()}`.

> **Note on the spec:** the spec listed a single `locale` field on `SearchQuery`. This plan splits it into `language` and `country`, because Brave and Google News RSS each need the two parts separately (`search_lang`/`country` vs `hl`/`gl`/`ceid`). `config/search.php` stores them as `search.locale.language` and `search.locale.country`.

- [ ] **Step 1: Bind the Laravel TestCase to Unit tests**

`tests/Pest.php` currently extends `Tests\TestCase` only in `Feature`, so files under `tests/Unit`
run as plain PHPUnit with no application container — the `Http` and `Config` facades used from
Task 3 onward would fatal. Extend `TestCase` into `Unit` too, but leave `RefreshDatabase` on
`Feature` only, so unit tests stay fast and database-free.

Replace the `pest()->extend(...)` call in `tests/Pest.php` with:

```php
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');
```

- [ ] **Step 2: Write the failing test**

Create `tests/Unit/Search/SearchResultSetTest.php`:

```php
<?php

use App\Search\Data\SearchResultItem;
use App\Search\Data\SearchResultSet;
use App\Search\Enums\SearchVertical;
use Illuminate\Support\Carbon;

it('exposes the vertical as a string backed value', function () {
    expect(SearchVertical::Web->value)->toBe('web')
        ->and(SearchVertical::News->value)->toBe('news')
        ->and(SearchVertical::Images->value)->toBe('images');
});

it('counts its items', function () {
    $set = new SearchResultSet(
        provider: 'brave',
        vertical: SearchVertical::Web,
        query: 'vertucio',
        fetchedAt: Carbon::parse('2026-08-10 09:00:00'),
        items: [
            new SearchResultItem(position: 1, url: 'https://example.com/a'),
            new SearchResultItem(position: 2, url: 'https://example.com/b'),
        ],
    );

    expect($set->count())->toBe(2);
});

it('defaults optional result item fields to null', function () {
    $item = new SearchResultItem(position: 1, url: 'https://example.com');

    expect($item->title)->toBeNull()
        ->and($item->description)->toBeNull()
        ->and($item->source)->toBeNull()
        ->and($item->publishedAt)->toBeNull()
        ->and($item->imageUrl)->toBeNull()
        ->and($item->meta)->toBe([]);
});
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php artisan test --compact --filter=SearchResultSetTest`
Expected: FAIL with `Class "App\Search\Data\SearchResultItem" not found`.

- [ ] **Step 4: Create the enum**

`app/Search/Enums/SearchVertical.php`:

```php
<?php

namespace App\Search\Enums;

enum SearchVertical: string
{
    case Web = 'web';
    case News = 'news';
    case Images = 'images';
}
```

- [ ] **Step 5: Create the data objects**

`app/Search/Data/SearchQuery.php`:

```php
<?php

namespace App\Search\Data;

use App\Search\Enums\SearchVertical;

final readonly class SearchQuery
{
    public function __construct(
        public string $query,
        public SearchVertical $vertical,
        public int $limit = 20,
        public string $language = 'en',
        public string $country = 'US',
    ) {}
}
```

`app/Search/Data/SearchResultItem.php`:

```php
<?php

namespace App\Search\Data;

use Illuminate\Support\Carbon;

final readonly class SearchResultItem
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public int $position,
        public string $url,
        public ?string $title = null,
        public ?string $description = null,
        public ?string $source = null,
        public ?Carbon $publishedAt = null,
        public ?string $imageUrl = null,
        public array $meta = [],
    ) {}
}
```

`app/Search/Data/SearchResultSet.php`:

```php
<?php

namespace App\Search\Data;

use App\Search\Enums\SearchVertical;
use Illuminate\Support\Carbon;

final readonly class SearchResultSet
{
    /**
     * @param  array<int, SearchResultItem>  $items
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $provider,
        public SearchVertical $vertical,
        public string $query,
        public Carbon $fetchedAt,
        public array $items,
        public array $meta = [],
    ) {}

    public function count(): int
    {
        return count($this->items);
    }
}
```

- [ ] **Step 6: Create the contract and exception**

`app/Search/Contracts/SearchProvider.php`:

```php
<?php

namespace App\Search\Contracts;

use App\Search\Data\SearchQuery;
use App\Search\Data\SearchResultSet;
use App\Search\Enums\SearchVertical;

interface SearchProvider
{
    /**
     * The stable identifier persisted on every snapshot this provider produces.
     */
    public function name(): string;

    public function supports(SearchVertical $vertical): bool;

    public function search(SearchQuery $query): SearchResultSet;
}
```

`app/Search/Exceptions/SearchProviderException.php`:

```php
<?php

namespace App\Search\Exceptions;

use App\Search\Enums\SearchVertical;
use RuntimeException;

final class SearchProviderException extends RuntimeException
{
    public static function requestFailed(string $provider, SearchVertical $vertical, int $status): self
    {
        return new self("[{$provider}/{$vertical->value}] request failed with HTTP {$status}.");
    }

    public static function unexpectedPayload(string $provider, SearchVertical $vertical, string $reason): self
    {
        return new self("[{$provider}/{$vertical->value}] unexpected payload: {$reason}.");
    }

    public static function unsupportedVertical(string $provider, SearchVertical $vertical): self
    {
        return new self("[{$provider}] does not support the {$vertical->value} vertical.");
    }
}
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `php artisan test --compact --filter=SearchResultSetTest`
Expected: PASS, 3 tests.

- [ ] **Step 8: Format, analyse, and commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse --no-progress
git add app/Search tests/Unit/Search tests/Pest.php
git commit -m "Add search domain foundation: vertical enum, data objects, provider contract"
```

---

### Task 2: Storage — migrations, models, factories, persistence action

**Files:**
- Create: `database/migrations/<timestamp>_create_search_snapshots_table.php`
- Create: `database/migrations/<timestamp>_create_search_results_table.php`
- Create: `app/Models/SearchSnapshot.php`
- Create: `app/Models/SearchResult.php`
- Create: `database/factories/SearchSnapshotFactory.php`
- Create: `database/factories/SearchResultFactory.php`
- Create: `app/Search/Actions/StoreSearchSnapshot.php`
- Test: `tests/Feature/Search/StoreSearchSnapshotTest.php`

**Interfaces:**
- Consumes: `SearchResultSet`, `SearchResultItem`, `SearchVertical` from Task 1.
- Produces: `StoreSearchSnapshot::handle(SearchResultSet $resultSet): SearchSnapshot`; `SearchSnapshot::results(): HasMany`; `SearchResult::snapshot(): BelongsTo`.

- [ ] **Step 1: Generate the scaffolding**

```bash
php artisan make:model SearchSnapshot --migration --factory --no-interaction
php artisan make:model SearchResult --migration --factory --no-interaction
php artisan make:class Search/Actions/StoreSearchSnapshot --no-interaction
```

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/Search/StoreSearchSnapshotTest.php`:

```php
<?php

use App\Models\SearchSnapshot;
use App\Search\Actions\StoreSearchSnapshot;
use App\Search\Data\SearchResultItem;
use App\Search\Data\SearchResultSet;
use App\Search\Enums\SearchVertical;
use Illuminate\Support\Carbon;

function resultSet(array $items): SearchResultSet
{
    return new SearchResultSet(
        provider: 'brave',
        vertical: SearchVertical::Web,
        query: 'vertucio',
        fetchedAt: Carbon::parse('2026-08-10 09:00:00'),
        items: $items,
        meta: ['altered_query' => null],
    );
}

it('stores a snapshot and its results', function () {
    $snapshot = app(StoreSearchSnapshot::class)->handle(resultSet([
        new SearchResultItem(
            position: 1,
            url: 'https://example.com/a',
            title: 'First',
            description: 'A description',
            source: 'example.com',
            publishedAt: Carbon::parse('2026-08-01 12:00:00'),
            meta: ['thumbnail' => null],
        ),
        new SearchResultItem(position: 2, url: 'https://example.com/b', title: 'Second'),
    ]));

    expect($snapshot->provider)->toBe('brave')
        ->and($snapshot->vertical)->toBe(SearchVertical::Web)
        ->and($snapshot->query)->toBe('vertucio')
        ->and($snapshot->result_count)->toBe(2)
        ->and($snapshot->meta)->toBe(['altered_query' => null])
        ->and($snapshot->results)->toHaveCount(2);

    $first = $snapshot->results()->where('position', 1)->sole();

    expect($first->url)->toBe('https://example.com/a')
        ->and($first->title)->toBe('First')
        ->and($first->source)->toBe('example.com')
        ->and($first->published_at->toDateTimeString())->toBe('2026-08-01 12:00:00');
});

it('hashes the url so long urls stay indexable', function () {
    $url = 'https://example.com/'.str_repeat('a', 2000);

    $snapshot = app(StoreSearchSnapshot::class)->handle(resultSet([
        new SearchResultItem(position: 1, url: $url),
    ]));

    expect($snapshot->results()->sole()->url_hash)->toBe(hash('sha256', $url));
});

it('stores a snapshot with no results', function () {
    $snapshot = app(StoreSearchSnapshot::class)->handle(resultSet([]));

    expect($snapshot->result_count)->toBe(0)
        ->and($snapshot->results)->toHaveCount(0)
        ->and(SearchSnapshot::count())->toBe(1);
});
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php artisan test --compact --filter=StoreSearchSnapshotTest`
Expected: FAIL — the `search_snapshots` table does not exist.

- [ ] **Step 4: Write the migrations**

In the generated `create_search_snapshots_table` migration, replace the `Schema::create` body:

```php
Schema::create('search_snapshots', function (Blueprint $table) {
    $table->id();
    $table->string('provider');
    $table->string('vertical');
    $table->string('query');
    $table->timestamp('fetched_at');
    $table->unsignedInteger('result_count');
    $table->json('meta')->nullable();
    $table->timestamps();

    $table->index(['provider', 'vertical', 'fetched_at']);
});
```

In the generated `create_search_results_table` migration:

```php
Schema::create('search_results', function (Blueprint $table) {
    $table->id();
    $table->foreignId('search_snapshot_id')->constrained()->cascadeOnDelete();
    $table->unsignedSmallInteger('position');
    $table->text('url');
    $table->char('url_hash', 64);
    $table->text('title')->nullable();
    $table->text('description')->nullable();
    $table->string('source')->nullable();
    $table->timestamp('published_at')->nullable();
    $table->text('image_url')->nullable();
    $table->json('meta')->nullable();
    $table->timestamps();

    $table->index('url_hash');
});
```

`url` is `text` because URLs regularly exceed what MySQL can index inline — utf8mb4 caps a fully
indexed `string` at 768 characters. `url_hash` carries the index instead.

- [ ] **Step 5: Write the models**

`app/Models/SearchSnapshot.php`:

```php
<?php

namespace App\Models;

use App\Search\Enums\SearchVertical;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SearchSnapshot extends Model
{
    /** @use HasFactory<\Database\Factories\SearchSnapshotFactory> */
    use HasFactory;

    /**
     * Written only by StoreSearchSnapshot, never from request input.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * @return HasMany<SearchResult, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(SearchResult::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vertical' => SearchVertical::class,
            'fetched_at' => 'datetime',
            'result_count' => 'integer',
            'meta' => 'array',
        ];
    }
}
```

`app/Models/SearchResult.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchResult extends Model
{
    /** @use HasFactory<\Database\Factories\SearchResultFactory> */
    use HasFactory;

    /**
     * Written only by StoreSearchSnapshot, never from request input.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * @return BelongsTo<SearchSnapshot, $this>
     */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(SearchSnapshot::class, 'search_snapshot_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'published_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
```

- [ ] **Step 6: Write the factories**

`database/factories/SearchSnapshotFactory.php` — replace the `definition` method:

```php
/**
 * @return array<string, mixed>
 */
public function definition(): array
{
    return [
        'provider' => 'brave',
        'vertical' => SearchVertical::Web,
        'query' => 'vertucio',
        'fetched_at' => now(),
        'result_count' => 0,
        'meta' => [],
    ];
}
```

Import `App\Search\Enums\SearchVertical`.

`database/factories/SearchResultFactory.php`:

```php
/**
 * @return array<string, mixed>
 */
public function definition(): array
{
    $url = fake()->url();

    return [
        'search_snapshot_id' => SearchSnapshot::factory(),
        'position' => fake()->numberBetween(1, 20),
        'url' => $url,
        'url_hash' => hash('sha256', $url),
        'title' => fake()->sentence(),
        'description' => fake()->paragraph(),
        'source' => fake()->domainName(),
        'published_at' => fake()->dateTimeThisYear(),
        'image_url' => null,
        'meta' => [],
    ];
}
```

Import `App\Models\SearchSnapshot`.

- [ ] **Step 7: Write the persistence action**

`app/Search/Actions/StoreSearchSnapshot.php`:

```php
<?php

namespace App\Search\Actions;

use App\Models\SearchSnapshot;
use App\Search\Data\SearchResultItem;
use App\Search\Data\SearchResultSet;
use Illuminate\Support\Facades\DB;

final class StoreSearchSnapshot
{
    /**
     * Persist one provider/vertical fetch as an immutable snapshot.
     */
    public function handle(SearchResultSet $resultSet): SearchSnapshot
    {
        return DB::transaction(function () use ($resultSet): SearchSnapshot {
            $snapshot = SearchSnapshot::create([
                'provider' => $resultSet->provider,
                'vertical' => $resultSet->vertical,
                'query' => $resultSet->query,
                'fetched_at' => $resultSet->fetchedAt,
                'result_count' => $resultSet->count(),
                'meta' => $resultSet->meta,
            ]);

            if ($resultSet->items !== []) {
                $snapshot->results()->createMany(array_map(
                    static fn (SearchResultItem $item): array => [
                        'position' => $item->position,
                        'url' => $item->url,
                        'url_hash' => hash('sha256', $item->url),
                        'title' => $item->title,
                        'description' => $item->description,
                        'source' => $item->source,
                        'published_at' => $item->publishedAt,
                        'image_url' => $item->imageUrl,
                        'meta' => $item->meta,
                    ],
                    $resultSet->items,
                ));
            }

            return $snapshot;
        });
    }
}
```

- [ ] **Step 8: Run the test to verify it passes**

Run: `php artisan test --compact --filter=StoreSearchSnapshotTest`
Expected: PASS, 3 tests.

- [ ] **Step 9: Format, analyse, and commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse --no-progress
git add app/Models app/Search/Actions database/migrations database/factories tests/Feature/Search
git commit -m "Add immutable snapshot storage for search results"
```

---

### Task 3: Brave provider

**Files:**
- Create: `config/search.php`
- Modify: `config/services.php`
- Modify: `.env.example`
- Create: `app/Search/Providers/BraveSearchProvider.php`
- Create: `tests/Fixtures/search/brave-web.json`
- Create: `tests/Fixtures/search/brave-news.json`
- Create: `tests/Fixtures/search/brave-images.json`
- Test: `tests/Unit/Search/BraveSearchProviderTest.php`

**Interfaces:**
- Consumes: `SearchProvider`, `SearchQuery`, `SearchResultSet`, `SearchResultItem`, `SearchVertical`, `SearchProviderException` from Task 1.
- Produces: `BraveSearchProvider` implementing `SearchProvider`, `name()` returning `'brave'`, `supports()` returning true for all three verticals.

- [ ] **Step 1: Add configuration**

Append to the array in `config/services.php`:

```php
'brave' => [
    'key' => env('BRAVE_SEARCH_KEY'),
],
```

Create `config/search.php`:

```php
<?php

use App\Search\Providers\BraveSearchProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Monitored Query
    |--------------------------------------------------------------------------
    |
    | The term fetched by the "search:fetch" command when no argument is given.
    |
    */

    'query' => env('SEARCH_QUERY', 'vertucio'),

    'results_per_vertical' => 20,

    'locale' => [
        'language' => 'en',
        'country' => 'US',
    ],

    'providers' => [
        BraveSearchProvider::class,
    ],

];
```

Task 4 appends `GoogleNewsRssProvider::class` to this array. It is left out here on purpose:
Larastan analyses `config/`, so referencing a class that does not exist yet would fail the static
analysis step of this task's commit.

Add to `.env.example` and to your local `.env`:

```
BRAVE_SEARCH_KEY=
SEARCH_QUERY=vertucio
```

- [ ] **Step 2: Capture real Brave fixtures**

The Brave API reference is SPA-rendered and its response schema could not be read during design,
so the field mapping in this task is derived from Brave's prose documentation and **must be
verified against real payloads**. With `BRAVE_SEARCH_KEY` set in your shell:

```bash
mkdir -p tests/Fixtures/search

for VERTICAL in web news images; do
  curl -s "https://api.search.brave.com/res/v1/${VERTICAL}/search?q=vertucio&count=20&country=US&search_lang=en" \
    -H "Accept: application/json" \
    -H "X-Subscription-Token: ${BRAVE_SEARCH_KEY}" \
    | python3 -m json.tool > "tests/Fixtures/search/brave-${VERTICAL}.json"
done
```

- [ ] **Step 3: Verify the assumed field paths against the fixtures**

```bash
python3 -c "
import json
for v, path in [('web','web.results'),('news','results'),('images','results')]:
    d = json.load(open(f'tests/Fixtures/search/brave-{v}.json'))
    node = d
    for part in path.split('.'):
        node = node.get(part, {})
    print(v, 'count =', len(node))
    print(' keys:', sorted(node[0].keys()) if node else 'EMPTY')
"
```

Expected: each vertical prints a non-zero count and a key list containing at least `title`, `url`,
and `description`.

**If the printed keys differ from the mapping in Step 5**, update `RESULTS_PATH` and the
`data_get()` paths in `mapItems()` to match what you actually see, and update the assertions in
Step 4 to match the fixture. The mapping is deliberately isolated to those two places so this is a
localized edit. Do not proceed with a mapping the fixtures contradict.

**If a vertical returns zero results for `vertucio`**, re-run that one with a query that does
return results (`?q=laravel`) so the fixture exercises the mapping, and note the substituted query
in a comment at the top of the test file.

- [ ] **Step 4: Write the failing test**

Create `tests/Unit/Search/BraveSearchProviderTest.php`:

```php
<?php

use App\Search\Data\SearchQuery;
use App\Search\Enums\SearchVertical;
use App\Search\Exceptions\SearchProviderException;
use App\Search\Providers\BraveSearchProvider;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    config()->set('services.brave.key', 'test-key');
});

function braveFixture(string $vertical): string
{
    return file_get_contents(base_path("tests/Fixtures/search/brave-{$vertical}.json"));
}

it('supports every vertical', function () {
    $provider = new BraveSearchProvider;

    expect($provider->name())->toBe('brave')
        ->and($provider->supports(SearchVertical::Web))->toBeTrue()
        ->and($provider->supports(SearchVertical::News))->toBeTrue()
        ->and($provider->supports(SearchVertical::Images))->toBeTrue();
});

it('sends the subscription token and query parameters', function () {
    Http::fake([
        'api.search.brave.com/res/v1/web/*' => Http::response(braveFixture('web')),
    ]);

    (new BraveSearchProvider)->search(new SearchQuery(
        query: 'vertucio',
        vertical: SearchVertical::Web,
        limit: 20,
    ));

    Http::assertSent(function ($request) {
        return $request->hasHeader('X-Subscription-Token', 'test-key')
            && $request['q'] === 'vertucio'
            && $request['count'] === 20
            && $request['country'] === 'US'
            && $request['search_lang'] === 'en';
    });
});

it('maps results into a result set with 1-based positions', function (string $vertical, SearchVertical $enum) {
    Http::fake([
        "api.search.brave.com/res/v1/{$vertical}/*" => Http::response(braveFixture($vertical)),
    ]);

    $set = (new BraveSearchProvider)->search(new SearchQuery('vertucio', $enum));

    expect($set->provider)->toBe('brave')
        ->and($set->vertical)->toBe($enum)
        ->and($set->query)->toBe('vertucio')
        ->and($set->count())->toBeGreaterThan(0);

    $first = $set->items[0];

    expect($first->position)->toBe(1)
        ->and($first->url)->toStartWith('http')
        ->and($first->title)->not->toBeEmpty();

    expect(array_map(fn ($item) => $item->position, $set->items))
        ->toBe(range(1, $set->count()));
})->with([
    'web' => ['web', SearchVertical::Web],
    'news' => ['news', SearchVertical::News],
    'images' => ['images', SearchVertical::Images],
]);

it('strips brave highlighting markup from titles and descriptions', function () {
    Http::fake([
        'api.search.brave.com/res/v1/web/*' => Http::response([
            'web' => [
                'results' => [[
                    'title' => 'Jason <strong>Vertucio</strong>',
                    'url' => 'https://example.com',
                    'description' => 'A <strong>bio</strong> page',
                ]],
            ],
        ]),
    ]);

    $set = (new BraveSearchProvider)->search(new SearchQuery('vertucio', SearchVertical::Web));

    expect($set->items[0]->title)->toBe('Jason Vertucio')
        ->and($set->items[0]->description)->toBe('A bio page');
});

it('populates image url only for the images vertical', function () {
    Http::fake([
        'api.search.brave.com/res/v1/images/*' => Http::response([
            'results' => [[
                'title' => 'A photo',
                'url' => 'https://example.com/page',
                'properties' => ['url' => 'https://example.com/photo.jpg'],
            ]],
        ]),
        'api.search.brave.com/res/v1/web/*' => Http::response([
            'web' => ['results' => [['title' => 'A page', 'url' => 'https://example.com']]],
        ]),
    ]);

    $images = (new BraveSearchProvider)->search(new SearchQuery('vertucio', SearchVertical::Images));
    $web = (new BraveSearchProvider)->search(new SearchQuery('vertucio', SearchVertical::Web));

    expect($images->items[0]->imageUrl)->toBe('https://example.com/photo.jpg')
        ->and($web->items[0]->imageUrl)->toBeNull();
});

it('throws when the request fails', function () {
    Http::fake([
        'api.search.brave.com/*' => Http::response('rate limited', 429),
    ]);

    (new BraveSearchProvider)->search(new SearchQuery('vertucio', SearchVertical::Web));
})->throws(SearchProviderException::class, 'request failed with HTTP 429');

it('throws when the results array is missing', function () {
    Http::fake([
        'api.search.brave.com/*' => Http::response(['unexpected' => true]),
    ]);

    (new BraveSearchProvider)->search(new SearchQuery('vertucio', SearchVertical::Web));
})->throws(SearchProviderException::class, 'unexpected payload');
```

- [ ] **Step 5: Run the test to verify it fails**

Run: `php artisan test --compact --filter=BraveSearchProviderTest`
Expected: FAIL with `Class "App\Search\Providers\BraveSearchProvider" not found`.

- [ ] **Step 6: Write the provider**

`app/Search/Providers/BraveSearchProvider.php`:

```php
<?php

namespace App\Search\Providers;

use App\Search\Contracts\SearchProvider;
use App\Search\Data\SearchQuery;
use App\Search\Data\SearchResultItem;
use App\Search\Data\SearchResultSet;
use App\Search\Enums\SearchVertical;
use App\Search\Exceptions\SearchProviderException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

final class BraveSearchProvider implements SearchProvider
{
    /**
     * @var array<string, string>
     */
    private const ENDPOINTS = [
        'web' => 'https://api.search.brave.com/res/v1/web/search',
        'news' => 'https://api.search.brave.com/res/v1/news/search',
        'images' => 'https://api.search.brave.com/res/v1/images/search',
    ];

    /**
     * Where the result array lives in each vertical's payload. Web nests its
     * results; news and images return them at the top level.
     *
     * @var array<string, string>
     */
    private const RESULTS_PATH = [
        'web' => 'web.results',
        'news' => 'results',
        'images' => 'results',
    ];

    public function name(): string
    {
        return 'brave';
    }

    public function supports(SearchVertical $vertical): bool
    {
        return true;
    }

    public function search(SearchQuery $query): SearchResultSet
    {
        $response = Http::withHeaders([
            'X-Subscription-Token' => (string) config('services.brave.key'),
            'Accept' => 'application/json',
        ])
            ->timeout(15)
            ->retry(2, 200, throw: false)
            ->get(self::ENDPOINTS[$query->vertical->value], [
                'q' => $query->query,
                'count' => $query->limit,
                'country' => $query->country,
                'search_lang' => $query->language,
            ]);

        if ($response->failed()) {
            throw SearchProviderException::requestFailed($this->name(), $query->vertical, $response->status());
        }

        $path = self::RESULTS_PATH[$query->vertical->value];
        $results = data_get($response->json(), $path);

        if (! is_array($results)) {
            throw SearchProviderException::unexpectedPayload(
                $this->name(),
                $query->vertical,
                "missing \"{$path}\" array"
            );
        }

        return new SearchResultSet(
            provider: $this->name(),
            vertical: $query->vertical,
            query: $query->query,
            fetchedAt: Carbon::now(),
            items: $this->mapItems($results, $query->vertical),
            meta: ['altered_query' => data_get($response->json(), 'query.altered')],
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @return array<int, SearchResultItem>
     */
    private function mapItems(array $results, SearchVertical $vertical): array
    {
        $items = [];
        $position = 1;

        foreach (array_values($results) as $result) {
            $url = data_get($result, 'url');

            if (! is_string($url) || $url === '') {
                continue;
            }

            $items[] = new SearchResultItem(
                position: $position,
                url: $url,
                title: $this->clean(data_get($result, 'title')),
                description: $this->clean(data_get($result, 'description')),
                source: $this->clean(data_get($result, 'meta_url.hostname') ?? data_get($result, 'source')),
                publishedAt: $this->parseDate(data_get($result, 'page_age') ?? data_get($result, 'age')),
                imageUrl: $vertical === SearchVertical::Images
                    ? $this->clean(data_get($result, 'properties.url') ?? data_get($result, 'thumbnail.src'))
                    : null,
                meta: ['thumbnail' => data_get($result, 'thumbnail.src')],
            );

            $position++;
        }

        return $items;
    }

    /**
     * Brave wraps query term matches in <strong> tags.
     */
    private function clean(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return html_entity_decode(strip_tags($value));
    }

    /**
     * Brave returns ISO-8601 in "page_age" but human phrasing ("3 hours ago")
     * in "age". Only the former is usable as a timestamp.
     */
    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `php artisan test --compact --filter=BraveSearchProviderTest`
Expected: PASS. If the fixture-driven test fails on a field name, correct `RESULTS_PATH` or the
`data_get()` paths per Step 3 and re-run.

- [ ] **Step 8: Format, analyse, and commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse --no-progress
git add app/Search/Providers/BraveSearchProvider.php config/search.php config/services.php .env.example tests/Fixtures tests/Unit/Search/BraveSearchProviderTest.php
git commit -m "Add Brave search provider for web, news, and image verticals"
```

---

### Task 4: Google News RSS provider

**Files:**
- Create: `app/Search/Providers/GoogleNewsRssProvider.php`
- Modify: `config/search.php`
- Create: `tests/Fixtures/search/google-news.xml`
- Test: `tests/Unit/Search/GoogleNewsRssProviderTest.php`

**Interfaces:**
- Consumes: the Task 1 contract and data objects.
- Produces: `GoogleNewsRssProvider` implementing `SearchProvider`, `name()` returning `'google_news_rss'`, `supports()` true only for `SearchVertical::News`.

- [ ] **Step 1: Capture a real RSS fixture**

No key needed:

```bash
curl -sL "https://news.google.com/rss/search?q=vertucio&hl=en-US&gl=US&ceid=US:en" \
  -o tests/Fixtures/search/google-news.xml

head -40 tests/Fixtures/search/google-news.xml
```

Confirm the file contains `<rss`, a `<channel>`, and at least one `<item>` with `<title>`,
`<link>`, `<pubDate>`, and `<source url="...">`. If `vertucio` returns no items, capture with
`q=laravel` instead and note the substitution in a comment at the top of the test file — the
mapping is what is under test, not the query.

- [ ] **Step 2: Write the failing test**

Create `tests/Unit/Search/GoogleNewsRssProviderTest.php`:

```php
<?php

use App\Search\Data\SearchQuery;
use App\Search\Enums\SearchVertical;
use App\Search\Exceptions\SearchProviderException;
use App\Search\Providers\GoogleNewsRssProvider;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('supports only the news vertical', function () {
    $provider = new GoogleNewsRssProvider;

    expect($provider->name())->toBe('google_news_rss')
        ->and($provider->supports(SearchVertical::News))->toBeTrue()
        ->and($provider->supports(SearchVertical::Web))->toBeFalse()
        ->and($provider->supports(SearchVertical::Images))->toBeFalse();
});

it('refuses an unsupported vertical without making a request', function () {
    (new GoogleNewsRssProvider)->search(new SearchQuery('vertucio', SearchVertical::Web));
})->throws(SearchProviderException::class, 'does not support the web vertical');

it('sends the locale parameters', function () {
    Http::fake([
        'news.google.com/*' => Http::response(file_get_contents(base_path('tests/Fixtures/search/google-news.xml'))),
    ]);

    (new GoogleNewsRssProvider)->search(new SearchQuery('vertucio', SearchVertical::News));

    Http::assertSent(function ($request) {
        return $request['q'] === 'vertucio'
            && $request['hl'] === 'en-US'
            && $request['gl'] === 'US'
            && $request['ceid'] === 'US:en';
    });
});

it('maps rss items into a result set', function () {
    Http::fake([
        'news.google.com/*' => Http::response(file_get_contents(base_path('tests/Fixtures/search/google-news.xml'))),
    ]);

    $set = (new GoogleNewsRssProvider)->search(new SearchQuery('vertucio', SearchVertical::News));

    expect($set->provider)->toBe('google_news_rss')
        ->and($set->vertical)->toBe(SearchVertical::News)
        ->and($set->count())->toBeGreaterThan(0);

    $first = $set->items[0];

    expect($first->position)->toBe(1)
        ->and($first->url)->toStartWith('http')
        ->and($first->title)->not->toBeEmpty()
        ->and($first->imageUrl)->toBeNull();
});

it('parses fields from a known item', function () {
    Http::fake([
        'news.google.com/*' => Http::response(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <rss version="2.0"><channel>
              <item>
                <title>Vertucio ships a thing</title>
                <link>https://news.google.com/rss/articles/ABC123</link>
                <pubDate>Mon, 03 Aug 2026 14:30:00 GMT</pubDate>
                <description>&lt;a href="x"&gt;Vertucio ships&lt;/a&gt;</description>
                <source url="https://example.com">Example News</source>
              </item>
            </channel></rss>
            XML),
    ]);

    $item = (new GoogleNewsRssProvider)->search(new SearchQuery('vertucio', SearchVertical::News))->items[0];

    expect($item->title)->toBe('Vertucio ships a thing')
        ->and($item->url)->toBe('https://news.google.com/rss/articles/ABC123')
        ->and($item->description)->toBe('Vertucio ships')
        ->and($item->source)->toBe('Example News')
        ->and($item->publishedAt->toDateTimeString())->toBe('2026-08-03 14:30:00')
        ->and($item->meta['source_url'])->toBe('https://example.com');
});

it('honours the requested limit', function () {
    $items = str_repeat(
        '<item><title>T</title><link>https://example.com/a</link></item>',
        30
    );

    Http::fake([
        'news.google.com/*' => Http::response('<?xml version="1.0"?><rss><channel>'.$items.'</channel></rss>'),
    ]);

    $set = (new GoogleNewsRssProvider)->search(new SearchQuery('vertucio', SearchVertical::News, limit: 5));

    expect($set->count())->toBe(5);
});

it('throws when the request fails', function () {
    Http::fake(['news.google.com/*' => Http::response('nope', 503)]);

    (new GoogleNewsRssProvider)->search(new SearchQuery('vertucio', SearchVertical::News));
})->throws(SearchProviderException::class, 'request failed with HTTP 503');

it('throws when the body is not valid xml', function () {
    Http::fake(['news.google.com/*' => Http::response('not xml at all')]);

    (new GoogleNewsRssProvider)->search(new SearchQuery('vertucio', SearchVertical::News));
})->throws(SearchProviderException::class, 'unexpected payload');
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php artisan test --compact --filter=GoogleNewsRssProviderTest`
Expected: FAIL with `Class "App\Search\Providers\GoogleNewsRssProvider" not found`.

- [ ] **Step 4: Write the provider**

`app/Search/Providers/GoogleNewsRssProvider.php`:

```php
<?php

namespace App\Search\Providers;

use App\Search\Contracts\SearchProvider;
use App\Search\Data\SearchQuery;
use App\Search\Data\SearchResultItem;
use App\Search\Data\SearchResultSet;
use App\Search\Enums\SearchVertical;
use App\Search\Exceptions\SearchProviderException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;
use Throwable;

final class GoogleNewsRssProvider implements SearchProvider
{
    private const ENDPOINT = 'https://news.google.com/rss/search';

    public function name(): string
    {
        return 'google_news_rss';
    }

    public function supports(SearchVertical $vertical): bool
    {
        return $vertical === SearchVertical::News;
    }

    public function search(SearchQuery $query): SearchResultSet
    {
        if (! $this->supports($query->vertical)) {
            throw SearchProviderException::unsupportedVertical($this->name(), $query->vertical);
        }

        $response = Http::timeout(15)
            ->retry(2, 200, throw: false)
            ->withOptions(['allow_redirects' => true])
            ->get(self::ENDPOINT, [
                'q' => $query->query,
                'hl' => "{$query->language}-{$query->country}",
                'gl' => $query->country,
                'ceid' => "{$query->country}:{$query->language}",
            ]);

        if ($response->failed()) {
            throw SearchProviderException::requestFailed($this->name(), $query->vertical, $response->status());
        }

        return new SearchResultSet(
            provider: $this->name(),
            vertical: $query->vertical,
            query: $query->query,
            fetchedAt: Carbon::now(),
            items: $this->mapItems($this->parseXml($response->body(), $query->vertical), $query->limit),
            meta: [],
        );
    }

    private function parseXml(string $body, SearchVertical $vertical): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false || ! isset($xml->channel)) {
            throw SearchProviderException::unexpectedPayload($this->name(), $vertical, 'body is not an RSS channel');
        }

        return $xml;
    }

    /**
     * @return array<int, SearchResultItem>
     */
    private function mapItems(SimpleXMLElement $xml, int $limit): array
    {
        $items = [];
        $position = 1;

        foreach ($xml->channel->item as $item) {
            if ($position > $limit) {
                break;
            }

            $url = trim((string) $item->link);

            if ($url === '') {
                continue;
            }

            $items[] = new SearchResultItem(
                position: $position,
                url: $url,
                title: $this->clean((string) $item->title),
                description: $this->clean((string) $item->description),
                source: $this->clean((string) $item->source),
                publishedAt: $this->parseDate((string) $item->pubDate),
                imageUrl: null,
                meta: ['source_url' => (string) ($item->source['url'] ?? '')],
            );

            $position++;
        }

        return $items;
    }

    /**
     * Google News descriptions are HTML fragments.
     */
    private function clean(string $value): ?string
    {
        $stripped = trim(strip_tags(html_entity_decode($value)));

        return $stripped === '' ? null : $stripped;
    }

    private function parseDate(string $value): ?Carbon
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --compact --filter=GoogleNewsRssProviderTest`
Expected: PASS, 8 tests.

- [ ] **Step 6: Register the provider**

Now that the class exists, add it to the `providers` array in `config/search.php`:

```php
'providers' => [
    BraveSearchProvider::class,
    GoogleNewsRssProvider::class,
],
```

Add `use App\Search\Providers\GoogleNewsRssProvider;` to that file's imports.

- [ ] **Step 7: Format, analyse, and commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse --no-progress
git add app/Search/Providers/GoogleNewsRssProvider.php config/search.php tests/Fixtures/search/google-news.xml tests/Unit/Search/GoogleNewsRssProviderTest.php
git commit -m "Add Google News RSS provider for the news vertical"
```

---

### Task 5: SearchManager and container registration

**Files:**
- Create: `app/Search/SearchManager.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Unit/Search/SearchManagerTest.php`

**Interfaces:**
- Consumes: `SearchProvider`, `SearchQuery`, `SearchResultSet`, `SearchVertical`.
- Produces: `SearchManager::__construct(array $providers)`; `providersFor(SearchVertical $vertical): Collection<int, SearchProvider>`; `pairs(): Collection<int, array{provider: SearchProvider, vertical: SearchVertical}>`; `fetch(SearchProvider $provider, SearchVertical $vertical, string $query): SearchResultSet`.

The manager deliberately does **not** catch provider exceptions. Error policy belongs to the
command (Task 6), which needs to continue past a failed pair — a manager that swallowed failures
would make that impossible to express.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Search/SearchManagerTest.php`:

```php
<?php

use App\Search\Contracts\SearchProvider;
use App\Search\Data\SearchQuery;
use App\Search\Data\SearchResultSet;
use App\Search\Enums\SearchVertical;
use App\Search\SearchManager;
use Illuminate\Support\Carbon;

function fakeProvider(string $name, array $verticals): SearchProvider
{
    return new class($name, $verticals) implements SearchProvider
    {
        /**
         * @param  array<int, SearchVertical>  $verticals
         */
        public function __construct(private string $providerName, private array $verticals) {}

        public function name(): string
        {
            return $this->providerName;
        }

        public function supports(SearchVertical $vertical): bool
        {
            return in_array($vertical, $this->verticals, true);
        }

        public function search(SearchQuery $query): SearchResultSet
        {
            return new SearchResultSet(
                provider: $this->providerName,
                vertical: $query->vertical,
                query: $query->query,
                fetchedAt: Carbon::now(),
                items: [],
                meta: ['limit' => $query->limit, 'country' => $query->country],
            );
        }
    };
}

it('returns only providers supporting a vertical', function () {
    $manager = new SearchManager([
        fakeProvider('all', [SearchVertical::Web, SearchVertical::News, SearchVertical::Images]),
        fakeProvider('news-only', [SearchVertical::News]),
    ]);

    expect($manager->providersFor(SearchVertical::Web))->toHaveCount(1)
        ->and($manager->providersFor(SearchVertical::News))->toHaveCount(2)
        ->and($manager->providersFor(SearchVertical::Images))->toHaveCount(1);
});

it('enumerates every supported provider and vertical pair', function () {
    $manager = new SearchManager([
        fakeProvider('all', [SearchVertical::Web, SearchVertical::News, SearchVertical::Images]),
        fakeProvider('news-only', [SearchVertical::News]),
    ]);

    $pairs = $manager->pairs()
        ->map(fn (array $pair): string => $pair['provider']->name().'/'.$pair['vertical']->value)
        ->all();

    expect($pairs)->toBe([
        'all/web',
        'all/news',
        'all/images',
        'news-only/news',
    ]);
});

it('builds the query from configuration', function () {
    config()->set('search.results_per_vertical', 7);
    config()->set('search.locale.country', 'GB');
    config()->set('search.locale.language', 'en');

    $manager = new SearchManager([$provider = fakeProvider('all', [SearchVertical::Web])]);

    $set = $manager->fetch($provider, SearchVertical::Web, 'vertucio');

    expect($set->query)->toBe('vertucio')
        ->and($set->meta['limit'])->toBe(7)
        ->and($set->meta['country'])->toBe('GB');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact --filter=SearchManagerTest`
Expected: FAIL with `Class "App\Search\SearchManager" not found`.

- [ ] **Step 3: Write the manager**

`app/Search/SearchManager.php`:

```php
<?php

namespace App\Search;

use App\Search\Contracts\SearchProvider;
use App\Search\Data\SearchQuery;
use App\Search\Data\SearchResultSet;
use App\Search\Enums\SearchVertical;
use Illuminate\Support\Collection;

final class SearchManager
{
    /**
     * @param  array<int, SearchProvider>  $providers
     */
    public function __construct(private array $providers) {}

    /**
     * @return Collection<int, SearchProvider>
     */
    public function providersFor(SearchVertical $vertical): Collection
    {
        return collect($this->providers)
            ->filter(static fn (SearchProvider $provider): bool => $provider->supports($vertical))
            ->values();
    }

    /**
     * Every provider crossed with the verticals it supports, in registration order.
     *
     * @return Collection<int, array{provider: SearchProvider, vertical: SearchVertical}>
     */
    public function pairs(): Collection
    {
        return collect($this->providers)->flatMap(
            static fn (SearchProvider $provider): array => collect(SearchVertical::cases())
                ->filter(static fn (SearchVertical $vertical): bool => $provider->supports($vertical))
                ->map(static fn (SearchVertical $vertical): array => [
                    'provider' => $provider,
                    'vertical' => $vertical,
                ])
                ->all()
        )->values();
    }

    /**
     * Exceptions propagate — callers decide whether a failed pair is fatal.
     */
    public function fetch(SearchProvider $provider, SearchVertical $vertical, string $query): SearchResultSet
    {
        return $provider->search(new SearchQuery(
            query: $query,
            vertical: $vertical,
            limit: (int) config('search.results_per_vertical', 20),
            language: (string) config('search.locale.language', 'en'),
            country: (string) config('search.locale.country', 'US'),
        ));
    }
}
```

- [ ] **Step 4: Register the manager in the container**

In `app/Providers/AppServiceProvider.php`, inside `register()`:

```php
$this->app->singleton(SearchManager::class, static function (Application $app): SearchManager {
    /** @var array<int, class-string<SearchProvider>> $providers */
    $providers = $app['config']->get('search.providers', []);

    return new SearchManager(
        array_map(static fn (string $provider): SearchProvider => $app->make($provider), $providers)
    );
});
```

Add the imports `App\Search\Contracts\SearchProvider`, `App\Search\SearchManager`, and
`Illuminate\Contracts\Foundation\Application`.

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --compact --filter=SearchManagerTest`
Expected: PASS, 3 tests.

- [ ] **Step 6: Verify the container wiring resolves**

Run: `php artisan tinker --execute 'dump(app(App\Search\SearchManager::class)->pairs()->count());'`
Expected: `4` — Brave across three verticals plus Google News RSS on news.

- [ ] **Step 7: Format, analyse, and commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse --no-progress
git add app/Search/SearchManager.php app/Providers/AppServiceProvider.php tests/Unit/Search/SearchManagerTest.php
git commit -m "Add SearchManager to enumerate provider and vertical pairs"
```

---

### Task 6: Artisan command and daily schedule

**Files:**
- Create: `app/Console/Commands/FetchSearchResults.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Search/FetchSearchResultsTest.php`

**Interfaces:**
- Consumes: `SearchManager::{pairs(),fetch()}`, `StoreSearchSnapshot::handle()`, `SearchProviderException`.
- Produces: the `search:fetch {query?}` command; exit code 0 when every pair succeeds, 1 when any pair fails.

- [ ] **Step 1: Generate the command**

```bash
php artisan make:command FetchSearchResults --no-interaction
```

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/Search/FetchSearchResultsTest.php`:

```php
<?php

use App\Models\SearchSnapshot;
use App\Search\Enums\SearchVertical;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    config()->set('services.brave.key', 'test-key');
    config()->set('search.query', 'vertucio');
});

function braveResponse(string $vertical): array
{
    $result = [
        'title' => 'Vertucio',
        'url' => 'https://example.com/'.$vertical,
        'description' => 'A page',
    ];

    return $vertical === 'web'
        ? ['web' => ['results' => [$result]]]
        : ['results' => [$result]];
}

function fakeAllProviders(): void
{
    Http::fake([
        'api.search.brave.com/res/v1/web/*' => Http::response(braveResponse('web')),
        'api.search.brave.com/res/v1/news/*' => Http::response(braveResponse('news')),
        'api.search.brave.com/res/v1/images/*' => Http::response(braveResponse('images')),
        'news.google.com/*' => Http::response(
            '<?xml version="1.0"?><rss><channel><item>'
            .'<title>A headline</title><link>https://news.google.com/rss/articles/X</link>'
            .'</item></channel></rss>'
        ),
    ]);
}

it('stores a snapshot for every provider and vertical pair', function () {
    fakeAllProviders();

    $this->artisan('search:fetch')->assertExitCode(0);

    expect(SearchSnapshot::count())->toBe(4);

    expect(SearchSnapshot::where('provider', 'brave')->pluck('vertical')->map->value->sort()->values()->all())
        ->toBe(['images', 'news', 'web']);

    $googleSnapshot = SearchSnapshot::where('provider', 'google_news_rss')->sole();

    expect($googleSnapshot->vertical)->toBe(SearchVertical::News)
        ->and($googleSnapshot->query)->toBe('vertucio')
        ->and($googleSnapshot->result_count)->toBe(1);
});

it('uses the query argument when given', function () {
    fakeAllProviders();

    $this->artisan('search:fetch', ['query' => 'something else'])->assertExitCode(0);

    expect(SearchSnapshot::pluck('query')->unique()->all())->toBe(['something else']);
});

it('stores the surviving pairs and fails when one provider errors', function () {
    Http::fake([
        'api.search.brave.com/res/v1/web/*' => Http::response('boom', 500),
        'api.search.brave.com/res/v1/news/*' => Http::response(braveResponse('news')),
        'api.search.brave.com/res/v1/images/*' => Http::response(braveResponse('images')),
        'news.google.com/*' => Http::response(
            '<?xml version="1.0"?><rss><channel><item>'
            .'<title>A headline</title><link>https://news.google.com/rss/articles/X</link>'
            .'</item></channel></rss>'
        ),
    ]);

    $this->artisan('search:fetch')->assertExitCode(1);

    expect(SearchSnapshot::count())->toBe(3)
        ->and(SearchSnapshot::where('provider', 'brave')->where('vertical', 'web')->exists())->toBeFalse();
});
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php artisan test --compact --filter=FetchSearchResultsTest`
Expected: FAIL — the `search:fetch` command does not exist.

- [ ] **Step 4: Write the command**

Replace `app/Console/Commands/FetchSearchResults.php`:

```php
<?php

namespace App\Console\Commands;

use App\Search\Actions\StoreSearchSnapshot;
use App\Search\SearchManager;
use Illuminate\Console\Command;
use Throwable;

final class FetchSearchResults extends Command
{
    /**
     * @var string
     */
    protected $signature = 'search:fetch
                            {query? : The term to search for; defaults to the configured query}';

    /**
     * @var string
     */
    protected $description = 'Fetch search results from every configured provider and store them as snapshots';

    public function handle(SearchManager $manager, StoreSearchSnapshot $store): int
    {
        $query = $this->argument('query') ?? (string) config('search.query');
        $failed = false;

        foreach ($manager->pairs() as $pair) {
            $provider = $pair['provider'];
            $vertical = $pair['vertical'];
            $label = "{$provider->name()}/{$vertical->value}";

            try {
                $snapshot = $store->handle($manager->fetch($provider, $vertical, $query));

                $this->components->info("{$label}: stored {$snapshot->result_count} results.");
            } catch (Throwable $exception) {
                $failed = true;

                report($exception);

                $this->components->error("{$label}: {$exception->getMessage()}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
```

Each pair is caught individually so a single failing feed cannot cost you the other three. A
failed pair writes no snapshot at all, which reads as "no data collected" rather than "zero
results found" — a distinction that matters once these rows are diffed.

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --compact --filter=FetchSearchResultsTest`
Expected: PASS, 3 tests.

- [ ] **Step 6: Register the daily schedule**

Append to `routes/console.php`:

```php
Schedule::command('search:fetch')
    ->daily()
    ->withoutOverlapping();
```

Add `use Illuminate\Support\Facades\Schedule;` to the imports.

- [ ] **Step 7: Verify the schedule is registered**

Run: `php artisan schedule:list`
Expected: a row for `php artisan search:fetch` running `0 0 * * *`.

- [ ] **Step 8: Run the whole suite**

Run: `php artisan test --compact`
Expected: PASS — the pre-existing auth, dashboard, settings, and example tests plus the new
search tests.

- [ ] **Step 9: Format, analyse, and commit**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse --no-progress
git add app/Console/Commands/FetchSearchResults.php routes/console.php tests/Feature/Search/FetchSearchResultsTest.php
git commit -m "Add search:fetch command with daily schedule and per-pair error handling"
```

---

## Manual verification

After Task 6, with a real `BRAVE_SEARCH_KEY` in `.env`:

```bash
php artisan search:fetch
php artisan tinker --execute 'App\Models\SearchSnapshot::with("results")->get()->each(function ($s) { dump($s->provider."/".$s->vertical->value." => ".$s->result_count); });'
```

Expected: four snapshots, each with a non-zero result count for a name that has any web presence.
A zero count is a legitimate outcome, not a bug — check the stored `meta.altered_query` to confirm
Brave did not rewrite the term.

## Follow-up work (not in this plan)

- A Livewire page rendering the latest snapshot per provider/vertical.
- Diffing two snapshots to show what entered and left the results.
- `search:prune` once snapshot volume justifies it.
- Replacing the Google web and image gap if a viable API appears.
