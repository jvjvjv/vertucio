# Sponsored Results Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a hand-authored "Sponsored" slot to the SERP page, backed by its own table whose availability is derived from a date window, managed by three artisan commands.

**Architecture:** A new `SponsoredResult` model sits entirely outside the snapshot/fetch pipeline. Availability is never stored — a `#[Scope] active()` answers it in SQL and a `status()` method answers it in PHP, both from `starts_at` / `ends_at`. Link-safety logic currently private to `SearchResult` is extracted into an `App\Concerns\RendersResultLinks` trait so both models present the same surface to the Blade components.

**Tech Stack:** Laravel 13, PHP 8.4, Livewire 4 (single-file page components), Pest 5, Larastan 3, Pint, Tailwind 4.

Spec: `docs/superpowers/specs/2026-08-10-sponsored-results-design.md`

## Global Constraints

- **PHP style (from `CLAUDE.md`):** curly braces on all control structures; constructor property promotion; explicit return types and parameter type hints on every method; TitleCase enum keys; PHPDoc blocks preferred over inline comments; array shapes in PHPDoc.
- **Every change is programmatically tested.** Write or update a test, then run it.
- **`vendor/bin/pint --dirty --format agent`** must be run before any commit that touches PHP.
- **Do not add or change dependencies.** Everything needed is installed.
- **Do not create documentation files.** This plan and the spec are the only docs.
- **Artisan generators:** use `php artisan make:* --no-interaction` to create files, then edit them.
- **Window boundaries are half-open: `starts_at <= now` (inclusive) and `ends_at > now` (exclusive).** This exact rule appears in Tasks 3–6. Do not restate it as `>=` on the end.
- **Test commands:** `php artisan test --compact --filter=<name>` for a subset; `composer types:check` for Larastan; `composer lint` for Pint.

## File Structure

**Created:**

| Path | Responsibility |
| ---- | -------------- |
| `app/Concerns/RendersResultLinks.php` | Scheme allow-listing and host display, shared by any SERP row model |
| `app/Search/Enums/SponsorshipStatus.php` | The three derived states of a promo |
| `database/migrations/*_create_sponsored_results_table.php` | The table |
| `app/Models/SponsoredResult.php` | The model, its `active()` scope and `status()` |
| `database/factories/SponsoredResultFactory.php` | Factory with `active()` / `scheduled()` / `expired()` states |
| `app/Console/Commands/AddSponsoredResult.php` | `sponsored:add` |
| `app/Console/Commands/ListSponsoredResults.php` | `sponsored:list` |
| `app/Console/Commands/EndSponsoredResult.php` | `sponsored:end` |
| `resources/views/components/serp/sponsored-row.blade.php` | Paid-placement row markup |
| `tests/Feature/Search/SponsoredResultTest.php` | Scope and status boundaries |
| `tests/Feature/Search/SponsoredResultUrlSafetyTest.php` | Scheme rejection on the model |
| `tests/Feature/Search/SponsoredResultCommandTest.php` | All three commands |
| `tests/Feature/Search/SponsoredResultPageTest.php` | Rendering and placement |

**Modified:**

| Path | Change |
| ---- | ------ |
| `app/Models/SearchSnapshot.php` | `protected $guarded = []` → `#[Guarded([])]` |
| `app/Models/SearchResult.php` | `#[Guarded([])]`; link methods move to the trait, keeping two overrides |
| `resources/views/pages/⚡search.blade.php` | Load live promos; render the block between Images and Results |

---

### Task 1: Retrofit `#[Guarded]` onto the search models

Pure expression change. `GuardsAttributes` resolves `#[Guarded([])]` to `[]`, identical to the current property, so the existing tests are the entire gate. `User` already uses `#[Fillable]` / `#[Hidden]`; this brings the search models in line.

**Pass the explicit empty array. Do not write a bare `#[Guarded]`.** In Laravel 13.24.0 the attribute's constructor is `__construct(array|string ...$columns)` and its body reads `$columns[0]` unconditionally, so the no-argument form emits an "Undefined array key 0" warning that Laravel's error handler escalates to an `ErrorException`. Both forms yield `[]`; only the explicit one is warning-free.

**Files:**
- Modify: `app/Models/SearchSnapshot.php`
- Modify: `app/Models/SearchResult.php`

**Interfaces:**
- Consumes: nothing.
- Produces: nothing new. `SearchResult` and `SearchSnapshot` keep every public method and stay fully mass-assignable.

- [ ] **Step 1: Run the existing search tests to establish a green baseline**

Run: `php artisan test --compact tests/Feature/Search tests/Unit/Search`
Expected: PASS. If anything is already red, stop and report it — this task cannot be verified otherwise.

- [ ] **Step 2: Apply the attribute to `SearchSnapshot`**

Delete this block from the class body:

```php
    /**
     * Written only by StoreSearchSnapshot, never from request input.
     *
     * @var array<int, string>
     */
    protected $guarded = [];
```

Add the `Guarded` import alongside the other `Illuminate\Database\Eloquent` imports:

```php
use Illuminate\Database\Eloquent\Attributes\Guarded;
```

Then move the explanation into the existing class docblock and attach the attribute. The docblock currently opens straight into `@property int $id`; give it a summary line so the note has somewhere to live:

```php
/**
 * One provider/vertical fetch, stored immutably.
 *
 * Unguarded: written only by StoreSearchSnapshot, never from request input.
 *
 * @property int $id
 * @property string $provider
 * @property SearchVertical $vertical
 * @property string $query
 * @property CarbonImmutable $fetched_at
 * @property int $result_count
 * @property array<string, mixed>|null $meta
 * @property-read Collection<int, SearchResult> $results
 */
#[Guarded([])]
class SearchSnapshot extends Model
```

- [ ] **Step 3: Apply the attribute to `SearchResult`**

Delete the same `protected $guarded = [];` block, add the same import, and rewrite the class docblock the same way:

```php
/**
 * A single result row belonging to a snapshot.
 *
 * Unguarded: written only by StoreSearchSnapshot, never from request input.
 *
 * @property int $id
 * @property int $search_snapshot_id
 * @property int $position
 * @property string $url
 * @property string $url_hash
 * @property string|null $title
 * @property string|null $description
 * @property string|null $source
 * @property CarbonImmutable|null $published_at
 * @property string|null $image_url
 * @property array<string, mixed>|null $meta
 * @property-read SearchSnapshot $snapshot
 */
#[Guarded([])]
class SearchResult extends Model
```

Leave `/** @use HasFactory<SearchResultFactory> */` exactly where it is. Larastan needs that generic to type `::factory()`, and `#[UseFactory]` would not replace it.

- [ ] **Step 4: Run the tests again**

Run: `php artisan test --compact tests/Feature/Search tests/Unit/Search`
Expected: PASS, same count as Step 1. A failure here means mass assignment broke — the models must remain fully unguarded, because `StoreSearchSnapshot` calls `create()` and `createMany()` with every column.

- [ ] **Step 5: Run static analysis and the formatter**

Run: `composer types:check && vendor/bin/pint --dirty --format agent`
Expected: no Larastan errors; Pint reports only formatting it fixed.

- [ ] **Step 6: Commit**

```bash
git add app/Models/SearchResult.php app/Models/SearchSnapshot.php
git commit -m "Express search model mass-assignment with the Guarded attribute"
```

---

### Task 2: Extract link safety into `RendersResultLinks`

`SearchResult` owns the scheme allow-list, and `SponsoredResult` needs it for the same reason: a hand-typed `javascript:` URL executes on click exactly like a fetched one. Extract the general case; leave the provider-specific fallbacks behind as overrides.

**Files:**
- Create: `app/Concerns/RendersResultLinks.php`
- Modify: `app/Models/SearchResult.php`
- Test: `tests/Feature/Search/SearchResultUrlSafetyTest.php` (existing, unchanged — it is the gate)

**Interfaces:**
- Consumes: nothing.
- Produces: trait `App\Concerns\RendersResultLinks` with
  - `public function safeUrl(): ?string`
  - `public function safeImageUrl(): ?string`
  - `public function displayHost(): string`
  - `protected function safeHttpUrl(string $url): ?string`
  - `protected function stripWww(string $host): string`

  Task 3 uses this trait on `SponsoredResult`; Tasks 5 and 7 call `displayHost()` and `safeUrl()`.

- [ ] **Step 1: Create the trait**

Create `app/Concerns/RendersResultLinks.php`:

```php
<?php

namespace App\Concerns;

/**
 * Link and host rendering shared by anything displayed as a SERP row.
 *
 * URLs on these models are never authored in Blade — they arrive from
 * third-party search APIs or from the sponsored:add command. Blade escaping
 * protects the attribute boundary but not the scheme, so a javascript:,
 * data:, or vbscript: URL would still execute on click. Callers render
 * unlinked text when safeUrl() returns null.
 *
 * @property string $url
 * @property string|null $image_url
 */
trait RendersResultLinks
{
    /**
     * The link target, or null when the stored URL is not safe to navigate to.
     */
    public function safeUrl(): ?string
    {
        return $this->safeHttpUrl($this->url);
    }

    /**
     * The image to render, or null when the stored value is not a plain
     * http(s) image reference.
     */
    public function safeImageUrl(): ?string
    {
        return is_string($this->image_url) ? $this->safeHttpUrl($this->image_url) : null;
    }

    /**
     * The host to show above a row.
     */
    public function displayHost(): string
    {
        $host = parse_url($this->url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $this->stripWww($host) : $this->url;
    }

    protected function stripWww(string $host): string
    {
        return (string) preg_replace('/^www\./i', '', $host);
    }

    /**
     * Allow-list http and https. Anything else — including scheme-relative and
     * schemeless values, which resolve unpredictably — is rejected.
     */
    protected function safeHttpUrl(string $url): ?string
    {
        $trimmed = trim($url);

        if ($trimmed === '') {
            return null;
        }

        $scheme = parse_url($trimmed, PHP_URL_SCHEME);

        if (! is_string($scheme)) {
            return null;
        }

        return in_array(strtolower($scheme), ['http', 'https'], true) ? $trimmed : null;
    }
}
```

Note the visibility change: `safeHttpUrl()` and `stripWww()` were `private` on `SearchResult` and are now `protected`, because the overrides in the next step call them.

- [ ] **Step 2: Reduce `SearchResult` to its overrides**

In `app/Models/SearchResult.php`:

Add the import `use App\Concerns\RendersResultLinks;` and apply the trait:

```php
    /** @use HasFactory<SearchResultFactory> */
    use HasFactory, RendersResultLinks;
```

Delete `safeUrl()`, `stripWww()`, and `safeHttpUrl()` outright — the trait versions are identical.

Replace `safeImageUrl()` with this override, which keeps the Brave fallback and nothing else:

```php
    /**
     * Falls back to the Brave thumbnail carried in meta when the row has no
     * image URL of its own.
     */
    public function safeImageUrl(): ?string
    {
        $thumbnail = $this->image_url;

        if (! is_string($thumbnail) || $thumbnail === '') {
            $thumbnail = is_array($this->meta) ? ($this->meta['thumbnail'] ?? null) : null;
        }

        return is_string($thumbnail) ? $this->safeHttpUrl($thumbnail) : null;
    }
```

Keep `displayHost()` exactly as it is today — it is already the override, and its docblock explaining the Google News redirect is the part that is not recoverable from the code.

A class method takes precedence over a trait method of the same name in PHP, so these two overrides win with no conflict resolution needed.

- [ ] **Step 3: Run the tests**

Run: `php artisan test --compact tests/Feature/Search tests/Unit/Search`
Expected: PASS. `SearchResultUrlSafetyTest` covers every branch that moved — http/https acceptance, seven dangerous schemes, the `meta.thumbnail` fallback, and the end-to-end "no javascript: in an href" page assertion. `SearchPageTest` covers the `meta.source_url` publisher-host path. If either goes red, the extraction changed behavior.

- [ ] **Step 4: Static analysis and formatter**

Run: `composer types:check && vendor/bin/pint --dirty --format agent`
Expected: clean.

- [ ] **Step 5: Commit**

```bash
git add app/Concerns/RendersResultLinks.php app/Models/SearchResult.php
git commit -m "Extract SERP row link safety into a shared concern"
```

---

### Task 3: The `SponsoredResult` model

The table, the enum, the model with its two expressions of availability, the factory, and the tests that pin the window boundaries.

**Files:**
- Create: `app/Search/Enums/SponsorshipStatus.php`
- Create: `database/migrations/<timestamp>_create_sponsored_results_table.php`
- Create: `app/Models/SponsoredResult.php`
- Create: `database/factories/SponsoredResultFactory.php`
- Test: `tests/Feature/Search/SponsoredResultTest.php`
- Test: `tests/Feature/Search/SponsoredResultUrlSafetyTest.php`

**Interfaces:**
- Consumes: `App\Concerns\RendersResultLinks` (Task 2).
- Produces:
  - `App\Search\Enums\SponsorshipStatus` — backed string enum, cases `Scheduled` (`'scheduled'`), `Active` (`'active'`), `Expired` (`'expired'`).
  - `App\Models\SponsoredResult` with columns `id, position, url, title, description, image_url, starts_at, ends_at, created_at, updated_at`.
  - `SponsoredResult::active(?CarbonImmutable $at = null)` — query scope, callable statically.
  - `SponsoredResult::status(?CarbonImmutable $at = null): SponsorshipStatus` — instance method.
  - `SponsoredResultFactory` states `active()`, `scheduled()`, `expired()`.

  Tasks 4–7 all depend on these exact names.

- [ ] **Step 1: Generate the scaffolding**

```bash
php artisan make:model SponsoredResult --migration --factory --no-interaction
php artisan make:test SponsoredResultTest --pest --no-interaction
php artisan make:test SponsoredResultUrlSafetyTest --pest --no-interaction
```

Move both generated test files into `tests/Feature/Search/` (the generator puts them in `tests/Feature/`). Everything under `tests/Feature` gets `RefreshDatabase` from `tests/Pest.php`, which these need.

Create `app/Search/Enums/SponsorshipStatus.php` by hand — there is no generator convention in this repo for the existing `SearchVertical`:

```php
<?php

namespace App\Search\Enums;

enum SponsorshipStatus: string
{
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Expired = 'expired';
}
```

- [ ] **Step 2: Write the migration**

Replace the generated migration body:

```php
    public function up(): void
    {
        Schema::create('sponsored_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('position')->default(1);
            $table->text('url');
            $table->text('title');
            $table->text('description')->nullable();
            $table->text('image_url')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsored_results');
    }
```

`text` for `url` and `image_url` matches `search_results`; long URLs overflow a `string` column.

- [ ] **Step 3: Write the failing tests**

Replace `tests/Feature/Search/SponsoredResultTest.php` entirely:

```php
<?php

use App\Models\SponsoredResult;
use App\Search\Enums\SponsorshipStatus;
use Carbon\CarbonImmutable;

it('treats a promo with no window as active', function () {
    $promo = SponsoredResult::factory()->create();

    expect($promo->status())->toBe(SponsorshipStatus::Active)
        ->and(SponsoredResult::active()->pluck('id')->all())->toBe([$promo->id]);
});

it('opens the window inclusively at starts_at', function () {
    $at = CarbonImmutable::parse('2026-08-10 12:00:00');

    $promo = SponsoredResult::factory()->create([
        'starts_at' => $at,
        'ends_at' => null,
    ]);

    expect($promo->status($at))->toBe(SponsorshipStatus::Active)
        ->and($promo->status($at->subSecond()))->toBe(SponsorshipStatus::Scheduled)
        ->and(SponsoredResult::active($at)->pluck('id')->all())->toBe([$promo->id])
        ->and(SponsoredResult::active($at->subSecond())->pluck('id')->all())->toBe([]);
});

it('closes the window exclusively at ends_at', function () {
    $at = CarbonImmutable::parse('2026-08-10 12:00:00');

    $promo = SponsoredResult::factory()->create([
        'starts_at' => null,
        'ends_at' => $at,
    ]);

    expect($promo->status($at))->toBe(SponsorshipStatus::Expired)
        ->and($promo->status($at->subSecond()))->toBe(SponsorshipStatus::Active)
        ->and(SponsoredResult::active($at)->pluck('id')->all())->toBe([])
        ->and(SponsoredResult::active($at->subSecond())->pluck('id')->all())->toBe([$promo->id]);
});

it('reports expired even when the window never opened', function () {
    $promo = SponsoredResult::factory()->make([
        'starts_at' => CarbonImmutable::parse('2026-09-01 00:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-08-01 00:00:00'),
    ]);

    expect($promo->status(CarbonImmutable::parse('2026-08-10 12:00:00')))
        ->toBe(SponsorshipStatus::Expired);
});

it('provides factory states for each status', function () {
    expect(SponsoredResult::factory()->active()->create()->status())->toBe(SponsorshipStatus::Active)
        ->and(SponsoredResult::factory()->scheduled()->create()->status())->toBe(SponsorshipStatus::Scheduled)
        ->and(SponsoredResult::factory()->expired()->create()->status())->toBe(SponsorshipStatus::Expired);
});
```

Replace `tests/Feature/Search/SponsoredResultUrlSafetyTest.php` entirely. It deliberately mirrors `SearchResultUrlSafetyTest` — the trait is shared, but a regression on one model must not be able to hide behind the other's coverage:

```php
<?php

use App\Models\SponsoredResult;

it('accepts http and https urls', function (string $url) {
    expect(SponsoredResult::factory()->make(['url' => $url])->safeUrl())->toBe($url);
})->with([
    'https' => 'https://example.com/a',
    'http' => 'http://example.com/a',
    'uppercase scheme' => 'HTTPS://example.com/a',
]);

it('rejects dangerous url schemes', function (string $url) {
    expect(SponsoredResult::factory()->make(['url' => $url])->safeUrl())->toBeNull();
})->with([
    'javascript' => 'javascript:alert(document.cookie)',
    'javascript mixed case' => 'JaVaScRiPt:alert(1)',
    'data' => 'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
    'vbscript' => 'vbscript:msgbox(1)',
    'file' => 'file:///etc/passwd',
    'scheme relative' => '//evil.example.com/a',
    'schemeless' => 'evil.example.com/a',
    'empty' => '',
]);

it('rejects a dangerous image url', function () {
    $promo = SponsoredResult::factory()->make(['image_url' => 'javascript:alert(1)']);

    expect($promo->safeImageUrl())->toBeNull();
});

it('shows the host without a www prefix', function () {
    $promo = SponsoredResult::factory()->make(['url' => 'https://www.example.com/work']);

    expect($promo->displayHost())->toBe('example.com');
});
```

- [ ] **Step 4: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Search/SponsoredResultTest.php tests/Feature/Search/SponsoredResultUrlSafetyTest.php`
Expected: FAIL — `Call to undefined method ... status()` and similar, because the model is still the empty generated stub.

- [ ] **Step 5: Write the model**

Replace `app/Models/SponsoredResult.php` entirely:

```php
<?php

namespace App\Models;

use App\Concerns\RendersResultLinks;
use App\Search\Enums\SponsorshipStatus;
use Carbon\CarbonImmutable;
use Database\Factories\SponsoredResultFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A hand-authored promo rendered in the SERP's sponsored slot.
 *
 * Unrelated to the snapshot pipeline: never fetched, never expired by a
 * fetch. Unguarded because it is written only by the sponsored:* commands,
 * never from request input.
 *
 * @property int $id
 * @property int $position
 * @property string $url
 * @property string $title
 * @property string|null $description
 * @property string|null $image_url
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Guarded([])]
class SponsoredResult extends Model
{
    /** @use HasFactory<SponsoredResultFactory> */
    use HasFactory, RendersResultLinks;

    /**
     * Rows whose window is open at the given moment.
     *
     * The window is half-open: inclusive of starts_at, exclusive of ends_at.
     * A null bound is unbounded on that side.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function active(Builder $query, ?CarbonImmutable $at = null): void
    {
        $at ??= CarbonImmutable::now();

        $query
            ->where(fn (Builder $query) => $query
                ->whereNull('starts_at')
                ->orWhere('starts_at', '<=', $at))
            ->where(fn (Builder $query) => $query
                ->whereNull('ends_at')
                ->orWhere('ends_at', '>', $at));
    }

    /**
     * Where this promo sits relative to its window.
     *
     * Expired wins over Scheduled: a closed window is closed regardless of
     * when it opened.
     */
    public function status(?CarbonImmutable $at = null): SponsorshipStatus
    {
        $at ??= CarbonImmutable::now();

        if ($this->ends_at !== null && $this->ends_at->lessThanOrEqualTo($at)) {
            return SponsorshipStatus::Expired;
        }

        if ($this->starts_at !== null && $this->starts_at->greaterThan($at)) {
            return SponsorshipStatus::Scheduled;
        }

        return SponsorshipStatus::Active;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }
}
```

`#[Scope]` is Laravel 13's attribute form of a local scope. It accepts any non-private method, so `protected` is correct and `SponsoredResult::active(...)` resolves through `__callStatic`.

- [ ] **Step 6: Write the factory**

Replace `database/factories/SponsoredResultFactory.php` entirely:

```php
<?php

namespace Database\Factories;

use App\Models\SponsoredResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SponsoredResult>
 */
class SponsoredResultFactory extends Factory
{
    /**
     * @var class-string<SponsoredResult>
     */
    protected $model = SponsoredResult::class;

    /**
     * An unbounded promo — live from creation, never ending.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'position' => 1,
            'url' => 'https://vertucio.com/work',
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'image_url' => null,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }

    /**
     * A promo with an explicitly open window on both sides.
     */
    public function active(): static
    {
        return $this->state(fn (): array => [
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);
    }

    /**
     * A promo whose window has not opened yet.
     */
    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addWeek(),
        ]);
    }

    /**
     * A promo whose window has closed.
     */
    public function expired(): static
    {
        return $this->state(fn (): array => [
            'starts_at' => now()->subWeek(),
            'ends_at' => now()->subDay(),
        ]);
    }
}
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Search/SponsoredResultTest.php tests/Feature/Search/SponsoredResultUrlSafetyTest.php`
Expected: PASS.

If `SponsoredResult::active($at)` errors with "Call to undefined method", the `#[Scope]` attribute is not being picked up — confirm the import is `Illuminate\Database\Eloquent\Attributes\Scope` and not the `Illuminate\Database\Eloquent\Scope` interface, which is a different class with the same short name.

- [ ] **Step 8: Static analysis and formatter**

Run: `composer types:check && vendor/bin/pint --dirty --format agent`
Expected: clean. If Larastan rejects `@param Builder<$this> $query`, try `@param Builder<static> $query`; do not delete the docblock.

- [ ] **Step 9: Commit**

```bash
git add app/Models/SponsoredResult.php app/Search/Enums/SponsorshipStatus.php \
        database/migrations database/factories/SponsoredResultFactory.php \
        tests/Feature/Search/SponsoredResultTest.php \
        tests/Feature/Search/SponsoredResultUrlSafetyTest.php
git commit -m "Add SponsoredResult with availability derived from its date window"
```

---

### Task 4: `sponsored:add`

**Files:**
- Create: `app/Console/Commands/AddSponsoredResult.php`
- Test: `tests/Feature/Search/SponsoredResultCommandTest.php`

**Interfaces:**
- Consumes: `SponsoredResult`, `SponsorshipStatus` (Task 3).
- Produces: the `sponsored:add` command. Tasks 5 and 6 add to the same test file.

- [ ] **Step 1: Write the failing tests**

```bash
php artisan make:test SponsoredResultCommandTest --pest --no-interaction
```

Move it to `tests/Feature/Search/` and replace its contents:

```php
<?php

use App\Models\SponsoredResult;
use App\Search\Enums\SponsorshipStatus;

it('creates a sponsored result from options', function () {
    $this->artisan('sponsored:add', [
        '--url' => 'https://vertucio.com/work',
        '--title' => 'Hire Jason Vertucio',
        '--description' => 'Senior Laravel engineer.',
        '--position' => '2',
        '--no-interaction' => true,
    ])->assertSuccessful();

    $promo = SponsoredResult::sole();

    expect($promo->url)->toBe('https://vertucio.com/work')
        ->and($promo->title)->toBe('Hire Jason Vertucio')
        ->and($promo->description)->toBe('Senior Laravel engineer.')
        ->and($promo->position)->toBe(2)
        ->and($promo->starts_at)->toBeNull()
        ->and($promo->ends_at)->toBeNull()
        ->and($promo->status())->toBe(SponsorshipStatus::Active);
});

it('stores a date window when given one', function () {
    $this->artisan('sponsored:add', [
        '--url' => 'https://vertucio.com/work',
        '--title' => 'Autumn promo',
        '--starts-at' => '2026-09-01 09:00',
        '--ends-at' => '2026-10-01 09:00',
        '--no-interaction' => true,
    ])->assertSuccessful();

    $promo = SponsoredResult::sole();

    expect($promo->starts_at?->toDateTimeString())->toBe('2026-09-01 09:00:00')
        ->and($promo->ends_at?->toDateTimeString())->toBe('2026-10-01 09:00:00');
});

it('rejects a url that is not http or https', function () {
    $this->artisan('sponsored:add', [
        '--url' => 'javascript:alert(1)',
        '--title' => 'Sketchy',
        '--no-interaction' => true,
    ])->assertFailed();

    expect(SponsoredResult::count())->toBe(0);
});

it('rejects an image url that is not http or https', function () {
    $this->artisan('sponsored:add', [
        '--url' => 'https://vertucio.com/work',
        '--title' => 'Sketchy image',
        '--image-url' => 'javascript:alert(1)',
        '--no-interaction' => true,
    ])->assertFailed();

    expect(SponsoredResult::count())->toBe(0);
});

it('rejects an end date that does not fall after the start date', function () {
    $this->artisan('sponsored:add', [
        '--url' => 'https://vertucio.com/work',
        '--title' => 'Backwards',
        '--starts-at' => '2026-09-01 09:00',
        '--ends-at' => '2026-08-01 09:00',
        '--no-interaction' => true,
    ])->assertFailed();

    expect(SponsoredResult::count())->toBe(0);
});

it('rejects a date it cannot parse', function () {
    $this->artisan('sponsored:add', [
        '--url' => 'https://vertucio.com/work',
        '--title' => 'Whenever',
        '--starts-at' => 'sometime next quarter',
        '--no-interaction' => true,
    ])->assertFailed();

    expect(SponsoredResult::count())->toBe(0);
});

it('requires a url', function () {
    $this->artisan('sponsored:add', [
        '--title' => 'No link',
        '--no-interaction' => true,
    ])->assertFailed();

    expect(SponsoredResult::count())->toBe(0);
});
```

`--no-interaction` is passed explicitly so the prompt fallbacks never fire during tests. The command must also behave correctly without it — that is what the `isInteractive()` guard below is for.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Search/SponsoredResultCommandTest.php`
Expected: FAIL — `Command "sponsored:add" is not defined.`

- [ ] **Step 3: Write the command**

```bash
php artisan make:command AddSponsoredResult --no-interaction
```

Replace `app/Console/Commands/AddSponsoredResult.php` entirely:

```php
<?php

namespace App\Console\Commands;

use App\Models\SponsoredResult;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

use function Laravel\Prompts\text;

final class AddSponsoredResult extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sponsored:add
                            {--url= : Where the promo links to; must be http or https}
                            {--title= : The headline shown in the sponsored slot}
                            {--description= : Optional supporting copy}
                            {--image-url= : Optional http(s) image shown with the promo}
                            {--position=1 : Sort order within the sponsored block}
                            {--starts-at= : When it goes live; blank means immediately}
                            {--ends-at= : When it comes down; blank means never}';

    /**
     * @var string
     */
    protected $description = 'Create a sponsored result for the SERP page';

    public function handle(): int
    {
        $url = $this->valueFor('url', 'Link URL');
        $title = $this->valueFor('title', 'Headline');

        if ($url === null || $title === null) {
            $this->components->error('Both --url and --title are required.');

            return self::FAILURE;
        }

        if (! $this->isHttpUrl($url)) {
            $this->components->error('The URL must start with http:// or https://.');

            return self::FAILURE;
        }

        $imageUrl = $this->valueFor('image-url', 'Image URL (optional)');

        if ($imageUrl !== null && ! $this->isHttpUrl($imageUrl)) {
            $this->components->error('The image URL must start with http:// or https://.');

            return self::FAILURE;
        }

        try {
            $startsAt = $this->parseMoment($this->valueFor('starts-at', 'Starts at (optional)'));
            $endsAt = $this->parseMoment($this->valueFor('ends-at', 'Ends at (optional)'));
        } catch (Throwable) {
            $this->components->error('Dates must be parseable, for example "2026-09-01 09:00".');

            return self::FAILURE;
        }

        if ($startsAt !== null && $endsAt !== null && $endsAt->lessThanOrEqualTo($startsAt)) {
            $this->components->error('The end date must fall after the start date.');

            return self::FAILURE;
        }

        $promo = SponsoredResult::create([
            'position' => (int) $this->option('position'),
            'url' => $url,
            'title' => $title,
            'description' => $this->valueFor('description', 'Description (optional)'),
            'image_url' => $imageUrl,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $this->components->info(
            "Sponsored result #{$promo->id} created ({$promo->status()->value})."
        );

        return self::SUCCESS;
    }

    /**
     * The option, falling back to a prompt when running interactively.
     *
     * Empty strings collapse to null so an unanswered optional prompt and an
     * omitted option mean the same thing.
     */
    private function valueFor(string $option, string $label): ?string
    {
        $value = $this->option($option);

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        if (! $this->input->isInteractive()) {
            return null;
        }

        $answer = trim(text($label));

        return $answer === '' ? null : $answer;
    }

    private function isHttpUrl(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return is_string($scheme) && in_array(strtolower($scheme), ['http', 'https'], true);
    }

    private function parseMoment(?string $value): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse($value);
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Search/SponsoredResultCommandTest.php`
Expected: PASS, 7 tests.

If any test hangs waiting for input, the `isInteractive()` guard is not firing — check that `--no-interaction` is present in that test's option array and that `valueFor()` returns before calling `text()`.

- [ ] **Step 5: Static analysis and formatter**

Run: `composer types:check && vendor/bin/pint --dirty --format agent`
Expected: clean.

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/AddSponsoredResult.php tests/Feature/Search/SponsoredResultCommandTest.php
git commit -m "Add sponsored:add command with url and window validation"
```

---

### Task 5: `sponsored:list`

**Files:**
- Create: `app/Console/Commands/ListSponsoredResults.php`
- Modify: `tests/Feature/Search/SponsoredResultCommandTest.php`

**Interfaces:**
- Consumes: `SponsoredResult`, its `status()` and `displayHost()`.
- Produces: the `sponsored:list` command.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Search/SponsoredResultCommandTest.php`:

```php
it('lists every promo with its derived status', function () {
    SponsoredResult::factory()->create(['title' => 'Live promo']);
    SponsoredResult::factory()->expired()->create(['title' => 'Old promo']);
    SponsoredResult::factory()->scheduled()->create(['title' => 'Future promo']);

    $this->artisan('sponsored:list')
        ->assertSuccessful()
        ->expectsOutputToContain('Live promo')
        ->expectsOutputToContain('Old promo')
        ->expectsOutputToContain('Future promo')
        ->expectsOutputToContain('Active')
        ->expectsOutputToContain('Expired')
        ->expectsOutputToContain('Scheduled')
        ->expectsOutputToContain('vertucio.com');
});

it('says so when there are no promos at all', function () {
    $this->artisan('sponsored:list')
        ->assertSuccessful()
        ->expectsOutputToContain('No sponsored results yet.');
});
```

Listing expired and scheduled rows is the point: when a promo is missing from the page, this command is how you find out which side of its window it fell on.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Search/SponsoredResultCommandTest.php`
Expected: the two new tests FAIL with `Command "sponsored:list" is not defined.` The seven from Task 4 still pass.

- [ ] **Step 3: Write the command**

```bash
php artisan make:command ListSponsoredResults --no-interaction
```

Replace `app/Console/Commands/ListSponsoredResults.php` entirely:

```php
<?php

namespace App\Console\Commands;

use App\Models\SponsoredResult;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class ListSponsoredResults extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sponsored:list';

    /**
     * @var string
     */
    protected $description = 'List every sponsored result with its current status';

    public function handle(): int
    {
        $promos = SponsoredResult::query()
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        if ($promos->isEmpty()) {
            $this->components->warn('No sponsored results yet. Add one with "sponsored:add".');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Pos', 'Status', 'Starts', 'Ends', 'Title', 'Host'],
            $promos->map(fn (SponsoredResult $promo): array => [
                $promo->id,
                $promo->position,
                ucfirst($promo->status()->value),
                $promo->starts_at?->toDateTimeString() ?? '—',
                $promo->ends_at?->toDateTimeString() ?? '—',
                Str::limit($promo->title, 40),
                $promo->displayHost(),
            ])->all(),
        );

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Search/SponsoredResultCommandTest.php`
Expected: PASS, 9 tests.

- [ ] **Step 5: Static analysis and formatter**

Run: `composer types:check && vendor/bin/pint --dirty --format agent`
Expected: clean.

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/ListSponsoredResults.php tests/Feature/Search/SponsoredResultCommandTest.php
git commit -m "Add sponsored:list command showing derived status per promo"
```

---

### Task 6: `sponsored:end`

**Files:**
- Create: `app/Console/Commands/EndSponsoredResult.php`
- Modify: `tests/Feature/Search/SponsoredResultCommandTest.php`

**Interfaces:**
- Consumes: `SponsoredResult`, its `status()`.
- Produces: the `sponsored:end` command.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Search/SponsoredResultCommandTest.php`:

```php
it('closes an active promo immediately', function () {
    $promo = SponsoredResult::factory()->create();

    $this->artisan('sponsored:end', ['id' => $promo->id])->assertSuccessful();

    expect($promo->fresh()->status())->toBe(SponsorshipStatus::Expired)
        ->and(SponsoredResult::active()->count())->toBe(0);
});

it('accepts an explicit end moment', function () {
    $promo = SponsoredResult::factory()->create();

    $this->artisan('sponsored:end', ['id' => $promo->id, '--at' => '2026-12-25 00:00'])
        ->assertSuccessful();

    expect($promo->fresh()->ends_at?->toDateTimeString())->toBe('2026-12-25 00:00:00');
});

it('fails on an id that does not exist', function () {
    $this->artisan('sponsored:end', ['id' => '999'])->assertFailed();
});

it('rejects an end moment it cannot parse', function () {
    $promo = SponsoredResult::factory()->create();

    $this->artisan('sponsored:end', ['id' => $promo->id, '--at' => 'eventually'])
        ->assertFailed();

    expect($promo->fresh()->ends_at)->toBeNull();
});
```

The first test leans on the exclusive end boundary: `ends_at` set to the moment the command ran is already in the past by assertion time, and would be Expired even at exactly that instant.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Search/SponsoredResultCommandTest.php`
Expected: the four new tests FAIL with `Command "sponsored:end" is not defined.`

- [ ] **Step 3: Write the command**

```bash
php artisan make:command EndSponsoredResult --no-interaction
```

Replace `app/Console/Commands/EndSponsoredResult.php` entirely:

```php
<?php

namespace App\Console\Commands;

use App\Models\SponsoredResult;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

final class EndSponsoredResult extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sponsored:end
                            {id : The sponsored result to retire}
                            {--at= : When it comes down; defaults to now}';

    /**
     * @var string
     */
    protected $description = 'Close a sponsored result window so it stops rendering';

    public function handle(): int
    {
        $id = (string) $this->argument('id');
        $promo = SponsoredResult::query()->find($id);

        if ($promo === null) {
            $this->components->error("No sponsored result with id [{$id}].");

            return self::FAILURE;
        }

        $at = $this->option('at');

        try {
            $endsAt = is_string($at) && trim($at) !== ''
                ? CarbonImmutable::parse(trim($at))
                : CarbonImmutable::now();
        } catch (Throwable) {
            $this->components->error('The --at value must be parseable, for example "2026-09-01 09:00".');

            return self::FAILURE;
        }

        $promo->update(['ends_at' => $endsAt]);

        $this->components->info(
            "Sponsored result #{$promo->id} ends at {$endsAt->toDateTimeString()} ({$promo->status()->value})."
        );

        return self::SUCCESS;
    }
}
```

There is no delete command. Ending preserves the record and its window, which is the reason the dates exist at all.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Search/SponsoredResultCommandTest.php`
Expected: PASS, 13 tests.

- [ ] **Step 5: Static analysis and formatter**

Run: `composer types:check && vendor/bin/pint --dirty --format agent`
Expected: clean.

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/EndSponsoredResult.php tests/Feature/Search/SponsoredResultCommandTest.php
git commit -m "Add sponsored:end command to retire a promo without deleting it"
```

---

### Task 7: Render the sponsored block

The last task, and the only one a visitor can see. The block sits between Images and Results, and disappears entirely when no promo is live.

**Files:**
- Create: `resources/views/components/serp/sponsored-row.blade.php`
- Modify: `resources/views/pages/⚡search.blade.php`
- Test: `tests/Feature/Search/SponsoredResultPageTest.php`

**Interfaces:**
- Consumes: `SponsoredResult::active()`, `safeUrl()`, `displayHost()`, and the `title` / `description` columns.
- Produces: the `x-serp.sponsored-row` Blade component, taking a single `:result` prop.

- [ ] **Step 1: Write the failing tests**

```bash
php artisan make:test SponsoredResultPageTest --pest --no-interaction
```

Move it to `tests/Feature/Search/` and replace its contents:

```php
<?php

use App\Models\SearchResult;
use App\Models\SearchSnapshot;
use App\Models\SponsoredResult;
use App\Search\Enums\SearchVertical;
use Illuminate\Support\Carbon;

/**
 * One organic web result, so the ordering assertions have something to sit
 * above. Deliberately local to this file rather than reusing SearchPageTest's
 * global helper.
 */
function seedOneWebResult(string $title): void
{
    $snapshot = SearchSnapshot::factory()->create([
        'provider' => 'brave',
        'vertical' => SearchVertical::Web,
        'fetched_at' => Carbon::parse('2026-08-09 09:00:00'),
        'result_count' => 1,
    ]);

    SearchResult::factory()->create([
        'search_snapshot_id' => $snapshot->id,
        'position' => 1,
        'title' => $title,
        'url' => 'https://example.com/web/1',
        'source' => 'example.com',
        'meta' => [],
    ]);
}

it('renders a live promo between the images and the organic results', function () {
    seedOneWebResult('Organic web result');

    SponsoredResult::factory()->create([
        'title' => 'Hire Jason Vertucio',
        'url' => 'https://vertucio.com/work',
        'description' => 'Senior Laravel engineer.',
    ]);

    $response = $this->get(route('search'))->assertOk();

    $response->assertSee('Sponsored')
        ->assertSee('Hire Jason Vertucio')
        ->assertSee('Senior Laravel engineer.')
        ->assertSee('vertucio.com')
        ->assertSee('rel="noopener noreferrer nofollow sponsored"', escape: false);

    $body = (string) $response->getContent();

    expect(strpos($body, 'Images'))->toBeLessThan(strpos($body, 'Hire Jason Vertucio'))
        ->and(strpos($body, 'Hire Jason Vertucio'))->toBeLessThan(strpos($body, 'Organic web result'));
});

it('orders promos by position', function () {
    SponsoredResult::factory()->create(['position' => 2, 'title' => 'Second promo']);
    SponsoredResult::factory()->create(['position' => 1, 'title' => 'First promo']);

    $body = (string) $this->get(route('search'))->assertOk()->getContent();

    expect(strpos($body, 'First promo'))->toBeLessThan(strpos($body, 'Second promo'));
});

it('omits the sponsored block entirely when nothing is live', function () {
    SponsoredResult::factory()->expired()->create(['title' => 'Old promo']);
    SponsoredResult::factory()->scheduled()->create(['title' => 'Future promo']);

    $this->get(route('search'))
        ->assertOk()
        ->assertDontSee('Sponsored')
        ->assertDontSee('Old promo')
        ->assertDontSee('Future promo');
});

it('never renders a javascript url into a sponsored href', function () {
    SponsoredResult::factory()->create([
        'title' => 'Sketchy promo',
        'url' => 'javascript:alert(document.cookie)',
    ]);

    $response = $this->get(route('search'))->assertOk();

    // The promo still shows, so it is not silently dropped...
    $response->assertSee('Sketchy promo');

    // ...but nothing navigable was emitted for it.
    $response->assertDontSee('href="javascript:', escape: false)
        ->assertDontSee('javascript:alert', escape: false);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Search/SponsoredResultPageTest.php`
Expected: FAIL — the page renders without any sponsored markup, so the `assertSee` calls miss. The "omits the block" test may pass already; that is fine, it is guarding against a later regression.

- [ ] **Step 3: Create the row component**

Create `resources/views/components/serp/sponsored-row.blade.php`. It mirrors `result-row.blade.php` with two differences: the title is authored so there is no `displayHost()` fallback for the label, and the link carries `rel="sponsored"`, which is the correct declaration for a paid placement.

```blade
@props(['result'])

@php
    $href = $result->safeUrl();
@endphp

<article class="flex flex-col gap-1">
    <p class="truncate text-xs md:text-[13px] text-(--serp-url)">
        {{ $result->displayHost() }}
    </p>

    <h3 class="text-lg md:text-xl leading-snug">
        @if ($href)
            <a
                href="{{ $href }}"
                target="_blank"
                rel="noopener noreferrer nofollow sponsored"
                class="line-clamp-2 text-(--serp-link) underline-offset-2 hover:underline focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--serp-link)"
            >
                {{ $result->title }}
            </a>
        @else
            <span class="line-clamp-2 text-(--serp-text)">{{ $result->title }}</span>
        @endif
    </h3>

    @if (filled($result->description))
        <p class="line-clamp-2 text-sm leading-relaxed text-(--serp-meta)">
            {{ $result->description }}
        </p>
    @endif
</article>
```

- [ ] **Step 4: Wire the page**

In `resources/views/pages/⚡search.blade.php`:

Add the import beside the existing ones at the top of the PHP block:

```php
use App\Models\SponsoredResult;
```

Add one entry to the array returned by `with()`, directly after the `'images' => ...` line:

```php
            'sponsored' => SponsoredResult::active()->orderBy('position')->orderBy('id')->get(),
```

`orderBy('id')` breaks position ties deterministically, matching how `LatestSearchResults` orders.

Then insert this block between the closing `</x-serp.section>` of Images and the opening `<x-serp.section :heading="__('Results')" ...>`:

```blade
        @if ($sponsored->isNotEmpty())
            <section class="flex flex-col gap-3 md:gap-4">
                <h2 class="text-[13px] md:text-sm font-medium tracking-wide uppercase text-(--serp-meta)">
                    {{ __('Sponsored') }}
                </h2>

                <div class="flex flex-col gap-6 md:gap-7 xl:gap-8">
                    @foreach ($sponsored as $promo)
                        <x-serp.sponsored-row :result="$promo" :wire:key="'sponsored-'.$promo->id" />
                    @endforeach
                </div>
            </section>
        @endif
```

A bare `<section>` rather than `x-serp.section`, because that component's two empty states — "Not collected yet" and "No results found" — are both meaningless for authored content. The heading is the label; the rows carry no separate badge, which is how a real SERP marks a paid block.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Search/SponsoredResultPageTest.php`
Expected: PASS, 4 tests.

- [ ] **Step 6: Run the whole suite**

Run: `php artisan test --compact`
Expected: PASS. Every pre-existing search test must still be green — they are the regression check on Tasks 1 and 2, whose changes should be invisible to behavior.

- [ ] **Step 7: Static analysis and formatter**

Run: `composer types:check && vendor/bin/pint --dirty --format agent`
Expected: clean.

- [ ] **Step 8: Commit**

```bash
git add resources/views/components/serp/sponsored-row.blade.php \
        "resources/views/pages/⚡search.blade.php" \
        tests/Feature/Search/SponsoredResultPageTest.php
git commit -m "Render live sponsored promos above the organic results"
```

- [ ] **Step 9: Verify in the browser**

Run:

```bash
php artisan sponsored:add --url=https://vertucio.com/work --title="Hire Jason Vertucio" \
    --description="Senior Laravel engineer, available now." --no-interaction
php artisan sponsored:list
```

Then load the search page (`composer run dev` if it is not already serving) and confirm the Sponsored block appears between Images and Results. Run `php artisan sponsored:end <id>` and reload — the block should vanish.

---

## Verification Checklist

Run before declaring the feature complete:

- [ ] `composer test` — clears config, checks Pint, runs Larastan, runs the full Pest suite. All green.
- [ ] `php artisan sponsored:add` with no options, interactively — prompts appear and a promo is created.
- [ ] `php artisan sponsored:list` — shows the promo as Active.
- [ ] `php artisan sponsored:end <id>` then `sponsored:list` — shows Expired, and the page no longer renders the block.
