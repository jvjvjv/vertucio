# SERP Livewire Refactor, Search Bar, Sponsor Us Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the Figma "Search Results" and "Sponsor Us" canvases: a Livewire-driven search
page with a working (filter-based) search bar and paginated results, a shared footer with a real
Privacy Policy page, and a Sponsor Us lead-gen form with a static PayPal link.

**Architecture:** The search page (`pages::search`, mounted at `/`) becomes a stateful Livewire
component with `#[Url]`-bound search term, `#[Computed]` properties per section, and
`WithPagination` for the organic Results section. A new `pages::sponsor` Livewire component and a
static `pages.privacy` view round out the routes. Two new Blade components
(`x-site.search-bar`, `x-site.footer`) are shared across all three pages. `LatestSearchResults`
and `SponsoredResult` gain an optional term filter used by the search bar.

**Tech Stack:** Laravel 13 (PHP 8.4), Livewire 4 single-file page components, Pest, Tailwind CSS
v4 with the project's existing `--serp-*` CSS custom properties.

## Global Constraints

- Follow `.ai/rules/models.md`: always pass an explicit array to `#[Guarded]` / `#[Fillable]` —
  never a bare attribute.
- `SponsorshipInquiry` is written directly from public request input, so it must use
  `#[Fillable([...])]` with an explicit allow-list, **not** `#[Guarded([])]` (that pattern is
  reserved for models written only by internal code, per the existing doc-comments on
  `SearchResult`/`SponsoredResult`/`SearchSnapshot`).
- Every model change ships with a Pest test; run `php artisan test --compact --filter=<Name>`
  after each task, not the full suite, until the final task.
- Run `vendor/bin/pint --dirty --format agent` after any PHP file changes, before committing.
- Icons are pre-downloaded, real Figma vector assets at `public/icons/*.svg` — never hand-author
  an `<svg>` inline.
- Colors: use the project's existing `--serp-*` CSS custom properties (`--serp-text`,
  `--serp-meta`, `--serp-line`, `--serp-link`, `--serp-url`, `--serp-surface`, `--serp-raised`)
  for anything that should flip between light/dark. Elements that are a fixed color in the Figma
  source regardless of theme (the black search button, the black "current page" pagination pill's
  icon-bearing sibling) use literal Tailwind classes (e.g. `bg-zinc-900 text-white`) instead, so a
  white icon never lands on a light background after a dark-mode flip.

---

### Task 1: Routing cleanup and sponsorship config

**Files:**
- Modify: `routes/web.php`
- Delete: `resources/views/welcome.blade.php`
- Create: `config/sponsorship.php`
- Modify: `.env.example`
- Test: `tests/Feature/RouteSmokeTest.php`

**Interfaces:**
- Produces: named routes `sponsor` (`GET /sponsor`) and `privacy` (`GET /privacy`); `config('sponsorship.notify_email')` and `config('sponsorship.paypal_url')`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/RouteSmokeTest.php`:

```php
<?php

it('has a search route at the home page', function () {
    expect(route('search'))->toBe(url('/'));
});

it('has named routes for sponsor and privacy pages', function () {
    expect(route('sponsor'))->toBe(url('/sponsor'))
        ->and(route('privacy'))->toBe(url('/privacy'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=RouteSmokeTest`
Expected: FAIL — `route('sponsor')` and `route('privacy')` throw
`Symfony\Component\Routing\Exception\RouteNotFoundException`.

- [ ] **Step 3: Add the routes**

Replace the contents of `routes/web.php`:

```php
<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::search')->name('search');
Route::livewire('sponsor', 'pages::sponsor')->name('sponsor');
Route::view('privacy', 'pages.privacy')->name('privacy');
```

Delete `resources/views/welcome.blade.php` — nothing routes to it now that `/` serves the search
page.

- [ ] **Step 4: Create the sponsorship config**

Create `config/sponsorship.php`:

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sponsorship inquiry notifications
    |--------------------------------------------------------------------------
    |
    | Where a copy of every submitted "Sponsor Us" form is emailed. Left blank
    | in local/testing, the mail is skipped rather than erroring.
    |
    */

    'notify_email' => env('SPONSORSHIP_NOTIFY_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Express sponsorship PayPal link
    |--------------------------------------------------------------------------
    |
    | A static PayPal.me (or equivalent) URL. No PayPal SDK/checkout
    | integration — this is a plain outbound link.
    |
    */

    'paypal_url' => env('SPONSORSHIP_PAYPAL_URL', 'https://paypal.me/vertucio'),

];
```

Add to `.env.example`, after the existing `SEARCH_QUERY=vertucio` line:

```
SPONSORSHIP_NOTIFY_EMAIL=
SPONSORSHIP_PAYPAL_URL=https://paypal.me/vertucio
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=RouteSmokeTest`
Expected: PASS

Also run: `php artisan route:list --name=search --name=sponsor --name=privacy` and confirm all
three appear.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add routes/web.php config/sponsorship.php .env.example tests/Feature/RouteSmokeTest.php
git rm resources/views/welcome.blade.php
git commit -m "Add sponsor/privacy routes and sponsorship config"
```

---

### Task 2: `SponsorshipInquiry` model, migration, factory

**Files:**
- Create: `database/migrations/2026_08_10_170000_create_sponsorship_inquiries_table.php`
- Create: `app/Models/SponsorshipInquiry.php`
- Create: `database/factories/SponsorshipInquiryFactory.php`
- Test: `tests/Feature/Sponsorship/SponsorshipInquiryTest.php`

**Interfaces:**
- Produces: `SponsorshipInquiry` model with fillable `full_name`, `email`, `company_name`,
  `monthly_budget`, `message`; factory `SponsorshipInquiry::factory()`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Sponsorship/SponsorshipInquiryTest.php`:

```php
<?php

use App\Models\SponsorshipInquiry;

it('is mass-assignable only for the sponsorship form fields', function () {
    $inquiry = SponsorshipInquiry::create([
        'full_name' => 'Jane Doe',
        'email' => 'jane@company.com',
        'company_name' => 'Acme Corp',
        'monthly_budget' => '1000_5000',
        'message' => 'We would like to sponsor the top result.',
    ]);

    expect($inquiry->fresh())
        ->full_name->toBe('Jane Doe')
        ->email->toBe('jane@company.com')
        ->company_name->toBe('Acme Corp')
        ->monthly_budget->toBe('1000_5000')
        ->message->toBe('We would like to sponsor the top result.');
});

it('has a working factory', function () {
    $inquiry = SponsorshipInquiry::factory()->create();

    expect($inquiry->exists)->toBeTrue()
        ->and($inquiry->email)->not->toBeEmpty();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=SponsorshipInquiryTest`
Expected: FAIL — class `App\Models\SponsorshipInquiry` not found.

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_08_10_170000_create_sponsorship_inquiries_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sponsorship_inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email');
            $table->string('company_name');
            $table->string('monthly_budget');
            $table->text('message');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sponsorship_inquiries');
    }
};
```

- [ ] **Step 4: Write the model**

Create `app/Models/SponsorshipInquiry.php`:

```php
<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SponsorshipInquiryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A lead submitted through the public "Sponsor Us" form.
 *
 * Fillable, not guarded, because this is the one model in the application
 * written directly from public request input — the explicit allow-list is
 * the mass-assignment guard, unlike SearchResult/SponsoredResult/
 * SearchSnapshot, which are unguarded because nothing but internal code
 * ever writes them.
 *
 * @property int $id
 * @property string $full_name
 * @property string $email
 * @property string $company_name
 * @property string $monthly_budget
 * @property string $message
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['full_name', 'email', 'company_name', 'monthly_budget', 'message'])]
class SponsorshipInquiry extends Model
{
    /** @use HasFactory<SponsorshipInquiryFactory> */
    use HasFactory;
}
```

- [ ] **Step 5: Write the factory**

Create `database/factories/SponsorshipInquiryFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\SponsorshipInquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SponsorshipInquiry>
 */
class SponsorshipInquiryFactory extends Factory
{
    /**
     * @var class-string<SponsorshipInquiry>
     */
    protected $model = SponsorshipInquiry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'company_name' => fake()->company(),
            'monthly_budget' => fake()->randomElement(['under_1000', '1000_5000', '5000_10000', '10000_plus']),
            'message' => fake()->paragraph(),
        ];
    }
}
```

- [ ] **Step 6: Run migrations and the test**

Run: `php artisan migrate`
Run: `php artisan test --compact --filter=SponsorshipInquiryTest`
Expected: PASS

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_08_10_170000_create_sponsorship_inquiries_table.php \
        app/Models/SponsorshipInquiry.php \
        database/factories/SponsorshipInquiryFactory.php \
        tests/Feature/Sponsorship/SponsorshipInquiryTest.php
git commit -m "Add SponsorshipInquiry model for the public sponsor form"
```

---

### Task 3: `SponsorshipInquiryReceived` mailable

**Files:**
- Create: `app/Mail/SponsorshipInquiryReceived.php`
- Create: `resources/views/mail/sponsorship-inquiry-received.blade.php`
- Test: `tests/Feature/Sponsorship/SponsorshipInquiryReceivedTest.php`

**Interfaces:**
- Consumes: `App\Models\SponsorshipInquiry` (Task 2).
- Produces: `new SponsorshipInquiryReceived(SponsorshipInquiry $inquiry)`, a `Mailable`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Sponsorship/SponsorshipInquiryReceivedTest.php`:

```php
<?php

use App\Mail\SponsorshipInquiryReceived;
use App\Models\SponsorshipInquiry;

it('renders the inquiry details in the notification email', function () {
    $inquiry = SponsorshipInquiry::factory()->create([
        'full_name' => 'Jane Doe',
        'email' => 'jane@company.com',
        'company_name' => 'Acme Corp',
        'monthly_budget' => '1000_5000',
        'message' => 'Interested in the top slot.',
    ]);

    $mailable = new SponsorshipInquiryReceived($inquiry);

    $mailable->assertSeeInHtml('Jane Doe');
    $mailable->assertSeeInHtml('jane@company.com');
    $mailable->assertSeeInHtml('Acme Corp');
    $mailable->assertSeeInHtml('Interested in the top slot.');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=SponsorshipInquiryReceivedTest`
Expected: FAIL — class `App\Mail\SponsorshipInquiryReceived` not found.

- [ ] **Step 3: Write the mailable**

Create `app/Mail/SponsorshipInquiryReceived.php`:

```php
<?php

namespace App\Mail;

use App\Models\SponsorshipInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SponsorshipInquiryReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly SponsorshipInquiry $inquiry)
    {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New sponsorship inquiry from {$this->inquiry->full_name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.sponsorship-inquiry-received',
        );
    }
}
```

- [ ] **Step 4: Write the mail view**

Create `resources/views/mail/sponsorship-inquiry-received.blade.php`:

```blade
<p>A new sponsorship inquiry was submitted on {{ config('app.name', 'Vertucio.com') }}.</p>

<table>
    <tr>
        <td><strong>Name</strong></td>
        <td>{{ $inquiry->full_name }}</td>
    </tr>
    <tr>
        <td><strong>Email</strong></td>
        <td>{{ $inquiry->email }}</td>
    </tr>
    <tr>
        <td><strong>Company</strong></td>
        <td>{{ $inquiry->company_name }}</td>
    </tr>
    <tr>
        <td><strong>Monthly budget</strong></td>
        <td>{{ $inquiry->monthly_budget }}</td>
    </tr>
</table>

<p><strong>Message</strong></p>
<p>{{ $inquiry->message }}</p>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=SponsorshipInquiryReceivedTest`
Expected: PASS

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Mail/SponsorshipInquiryReceived.php \
        resources/views/mail/sponsorship-inquiry-received.blade.php \
        tests/Feature/Sponsorship/SponsorshipInquiryReceivedTest.php
git commit -m "Add SponsorshipInquiryReceived notification mail"
```

---

### Task 4: `LatestSearchResults` term filter and pagination

**Files:**
- Modify: `app/Search/Queries/LatestSearchResults.php`
- Modify: `tests/Feature/Search/LatestSearchResultsTest.php`

**Interfaces:**
- Produces: `LatestSearchResults::for(SearchVertical $vertical, int $limit, ?string $term = null): Collection<int, SearchResult>`
  (existing signature, now with an optional trailing `$term`), and
  `LatestSearchResults::paginate(SearchVertical $vertical, int $perPage, ?string $term = null): \Illuminate\Pagination\LengthAwarePaginator`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Search/LatestSearchResultsTest.php` (before the final closing of the
file — it already ends with an `it(...)` block, so just add these after it):

```php
it('filters results by a search term matching the title', function () {
    snapshotWith('brave', SearchVertical::Web, Carbon::parse('2026-08-09 09:00'), 0);

    $snapshot = SearchSnapshot::factory()->create([
        'provider' => 'brave',
        'vertical' => SearchVertical::Web,
        'fetched_at' => Carbon::parse('2026-08-09 09:00'),
        'result_count' => 2,
    ]);

    SearchResult::factory()->create([
        'search_snapshot_id' => $snapshot->id,
        'position' => 1,
        'title' => 'Vertucio pricing guide',
        'description' => 'Plans and pricing',
    ]);

    SearchResult::factory()->create([
        'search_snapshot_id' => $snapshot->id,
        'position' => 2,
        'title' => 'Unrelated headline',
        'description' => 'Nothing to do with pricing',
    ]);

    $results = app(LatestSearchResults::class)->for(SearchVertical::Web, 10, 'pricing');

    expect($results)->toHaveCount(2);
});

it('filters results by a search term with no matches to an empty collection', function () {
    snapshotWith('brave', SearchVertical::Web, Carbon::parse('2026-08-09 09:00'), 3);

    $results = app(LatestSearchResults::class)->for(SearchVertical::Web, 10, 'no such term anywhere');

    expect($results)->toBeEmpty();
});

it('paginates the organic results', function () {
    snapshotWith('brave', SearchVertical::Web, Carbon::parse('2026-08-09 09:00'), 10);

    $page1 = app(LatestSearchResults::class)->paginate(SearchVertical::Web, 4);

    expect($page1->total())->toBe(10)
        ->and($page1->items())->toHaveCount(4)
        ->and($page1->currentPage())->toBe(1);
});

it('returns an empty paginator when nothing has been collected', function () {
    $page = app(LatestSearchResults::class)->paginate(SearchVertical::Web, 4);

    expect($page->total())->toBe(0)
        ->and($page->items())->toBeEmpty();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=LatestSearchResultsTest`
Expected: FAIL — `paginate()` does not exist, and `for()` does not accept a third argument yet
(PHP allows extra args to be silently ignored only if using func_get_args, so the term-filter
tests fail on the assertion, not a fatal error).

- [ ] **Step 3: Implement the term filter and pagination**

Replace the full contents of `app/Search/Queries/LatestSearchResults.php`:

```php
<?php

namespace App\Search\Queries;

use App\Models\SearchResult;
use App\Models\SearchSnapshot;
use App\Search\Enums\SearchVertical;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read model over the immutable snapshot history.
 *
 * Snapshots are append-only, so "latest" is the highest id per provider. A
 * vertical may be served by more than one provider (news comes from both
 * Brave and Google News RSS), so results are merged across them.
 */
final class LatestSearchResults
{
    /**
     * @return Collection<int, SearchResult>
     */
    public function for(SearchVertical $vertical, int $limit, ?string $term = null): Collection
    {
        $snapshotIds = $this->latestSnapshotIds($vertical);

        if ($snapshotIds === []) {
            return collect();
        }

        return $this->baseQuery($snapshotIds, $term)
            ->limit($limit)
            ->get();
    }

    /**
     * The organic Results section is the only one the Figma design paginates.
     */
    public function paginate(SearchVertical $vertical, int $perPage, ?string $term = null): LengthAwarePaginator
    {
        $snapshotIds = $this->latestSnapshotIds($vertical);

        if ($snapshotIds === []) {
            return new LengthAwarePaginator([], 0, $perPage);
        }

        return $this->baseQuery($snapshotIds, $term)->paginate($perPage);
    }

    /**
     * Whether a fetch has ever landed for this vertical. Distinct from having
     * zero results: "not collected" and "collected, found nothing" are
     * different facts and the UI states for them differ.
     */
    public function wasCollected(SearchVertical $vertical): bool
    {
        return $this->latestSnapshotIds($vertical) !== [];
    }

    public function lastCollectedAt(SearchVertical $vertical): ?Carbon
    {
        $fetchedAt = SearchSnapshot::query()
            ->where('vertical', $vertical)
            ->max('fetched_at');

        return $fetchedAt === null ? null : Carbon::parse($fetchedAt);
    }

    /**
     * @param  array<int, int>  $snapshotIds
     * @return Builder<SearchResult>
     */
    private function baseQuery(array $snapshotIds, ?string $term): Builder
    {
        return SearchResult::query()
            ->whereIn('search_snapshot_id', $snapshotIds)
            ->with('snapshot')
            ->when(filled($term), fn (Builder $query): Builder => $query->where(
                fn (Builder $query) => $query
                    ->where('title', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
            ))
            ->orderBy('position')
            ->orderBy('search_snapshot_id');
    }

    /**
     * The newest snapshot id for each provider serving this vertical.
     *
     * @return array<int, int>
     */
    private function latestSnapshotIds(SearchVertical $vertical): array
    {
        /** @var array<int, int> $ids */
        $ids = SearchSnapshot::query()
            ->where('vertical', $vertical)
            ->groupBy('provider')
            ->pluck(SearchSnapshot::raw('MAX(id)'))
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        return $ids;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=LatestSearchResultsTest`
Expected: PASS — all existing and new tests green.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Search/Queries/LatestSearchResults.php tests/Feature/Search/LatestSearchResultsTest.php
git commit -m "Add search-term filtering and pagination to LatestSearchResults"
```

---

### Task 5: `SponsoredResult` term filter

**Files:**
- Modify: `app/Models/SponsoredResult.php`
- Modify: `tests/Feature/Search/SponsoredResultTest.php`

**Interfaces:**
- Produces: `SponsoredResult::matching(?string $term)` local scope, usable as
  `SponsoredResult::active()->when(filled($term), fn ($q) => $q->matching($term))`.

- [ ] **Step 1: Write the failing test**

Open `tests/Feature/Search/SponsoredResultTest.php`, and append:

```php
it('filters active sponsored results by a search term', function () {
    SponsoredResult::factory()->active()->create([
        'title' => 'Vertucio pricing guide',
        'description' => 'Plans and pricing',
    ]);

    SponsoredResult::factory()->active()->create([
        'title' => 'Unrelated promo',
        'description' => 'Nothing to do with pricing',
    ]);

    $results = SponsoredResult::active()->matching('pricing')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->title)->toBe('Vertucio pricing guide');
});

it('leaves the active set unfiltered when the term is blank', function () {
    SponsoredResult::factory()->active()->count(2)->create();

    expect(SponsoredResult::active()->matching(null)->get())->toHaveCount(2);
});
```

(If `App\Models\SponsoredResult` is not already imported at the top of the file, no import is
needed here — the existing tests in that file already use it.)

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=SponsoredResultTest`
Expected: FAIL — `matching()` is not a recognized method/scope on the query builder.

- [ ] **Step 3: Add the scope**

In `app/Models/SponsoredResult.php`, add `use Illuminate\Database\Eloquent\Builder;` import
(already imported), and add this method after `active()`:

```php
    /**
     * Optional title/description filter, applied alongside active().
     *
     * A null or blank term is a no-op — callers gate this with
     * `when(filled($term), ...)` rather than always calling it, but the
     * no-op behavior here makes that gate a convenience, not a requirement.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function matching(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $query->where(
            fn (Builder $query) => $query
                ->where('title', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
        );
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=SponsoredResultTest`
Expected: PASS

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/SponsoredResult.php tests/Feature/Search/SponsoredResultTest.php
git commit -m "Add a search-term filter scope to SponsoredResult"
```

---

### Task 6: Shared `x-site.search-bar` component

**Files:**
- Create: `resources/views/components/site/search-bar.blade.php`
- Verify (already downloaded, real Figma vector assets): `public/icons/arrow-left-right.svg`, `public/icons/search.svg`
- Test: `tests/Feature/Site/SearchBarComponentTest.php`

**Interfaces:**
- Produces: `<x-site.search-bar :label="string" :live="bool" />` — renders a form. When
  `live` is `true`, the input carries `wire:model="term"` and the form carries
  `wire:submit="search"` (both refer to the Livewire component that renders it — see Task 11).
  When `live` is `false`, the form plain-GETs to `route('search')` with the input named `q`.

- [ ] **Step 1: Confirm the icon assets exist**

Run: `ls -la public/icons/`
Expected: `arrow-left-right.svg` and `search.svg` are present (downloaded from Figma's asset
export — real vector paths, not placeholders). If missing, they are not something to hand-author;
stop and re-fetch via the Figma MCP `get_design_context` on node `14:22` in file
`rMMzAzhxu1QoqqUYaGrzf2`, which returns fresh short-lived asset URLs.

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/Site/SearchBarComponentTest.php`:

```php
<?php

it('renders a live search bar bound to the term property', function () {
    $html = Blade::render('<x-site.search-bar label="vertucio" :live="true" />');

    expect($html)
        ->toContain('wire:submit="search"')
        ->toContain('wire:model="term"')
        ->toContain('vertucio');
});

it('renders a non-live search bar that gets to the search route', function () {
    $html = Blade::render('<x-site.search-bar label="Vertucio" :live="false" />');

    expect($html)
        ->toContain('method="GET"')
        ->toContain(route('search'))
        ->toContain('name="q"');
});
```

Add `use Illuminate\Support\Facades\Blade;` at the top of the file.

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --compact --filter=SearchBarComponentTest`
Expected: FAIL — component `site.search-bar` not found.

- [ ] **Step 4: Write the component**

Create `resources/views/components/site/search-bar.blade.php`:

```blade
@props([
    'label',
    'live' => false,
])

<form
    @if ($live)
        wire:submit="search"
    @else
        method="GET"
        action="{{ route('search') }}"
    @endif
    class="flex w-full items-center gap-3"
>
    <div class="flex h-11 flex-1 items-center gap-2 rounded-full border border-(--serp-line) bg-(--serp-raised) px-3">
        <span class="shrink-0 text-sm font-bold text-(--serp-text)">{{ $label }}</span>

        <img src="{{ asset('icons/arrow-left-right.svg') }}" alt="" class="size-4 shrink-0">

        <input
            type="text"
            @if ($live)
                wire:model="term"
            @else
                name="q"
            @endif
            placeholder="{{ __('Search...') }}"
            class="min-w-0 flex-1 bg-transparent text-sm text-(--serp-text) placeholder:text-(--serp-meta) focus:outline-none"
        >
    </div>

    <button
        type="submit"
        class="flex h-11 shrink-0 items-center gap-2 rounded-full bg-zinc-900 px-3.5 text-sm font-semibold text-white"
    >
        <img src="{{ asset('icons/search.svg') }}" alt="" class="size-[18px]">
        <span>{{ __('Search') }}</span>
    </button>
</form>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=SearchBarComponentTest`
Expected: PASS

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add public/icons/arrow-left-right.svg public/icons/search.svg \
        resources/views/components/site/search-bar.blade.php \
        tests/Feature/Site/SearchBarComponentTest.php
git commit -m "Add shared search-bar component matching the Figma design"
```

---

### Task 7: Shared `x-site.footer` component

**Files:**
- Create: `resources/views/components/site/footer.blade.php`
- Test: `tests/Feature/Site/FooterComponentTest.php`

**Interfaces:**
- Produces: `<x-site.footer :active="'privacy'|'sponsor'|null" />`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Site/FooterComponentTest.php`:

```php
<?php

it('links to the privacy and sponsor routes', function () {
    $html = Blade::render('<x-site.footer />');

    expect($html)
        ->toContain(route('privacy'))
        ->toContain(route('sponsor'))
        ->toContain('Privacy Policy')
        ->toContain('Sponsor Us');
});

it('bolds the active page link', function () {
    $html = Blade::render('<x-site.footer active="sponsor" />');

    expect($html)->toContain('font-semibold');
});
```

Add `use Illuminate\Support\Facades\Blade;` at the top of the file.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=FooterComponentTest`
Expected: FAIL — component `site.footer` not found.

- [ ] **Step 3: Write the component**

Create `resources/views/components/site/footer.blade.php`:

```blade
@props(['active' => null])

<footer class="border-t border-(--serp-line) bg-(--serp-surface)">
    <div class="mx-auto flex w-full max-w-[1280px] flex-col items-start gap-2 px-4 py-6 text-sm text-(--serp-meta) sm:flex-row sm:items-center sm:justify-between md:px-8 xl:px-20">
        <p>{{ __('© :year, :name', ['year' => now()->year, 'name' => config('app.name', 'Vertucio.com')]) }}</p>

        <nav class="flex items-center gap-6">
            <a
                href="{{ route('privacy') }}"
                class="hover:text-(--serp-text) {{ $active === 'privacy' ? 'font-semibold text-(--serp-text)' : '' }}"
            >
                {{ __('Privacy Policy') }}
            </a>
            <a
                href="{{ route('sponsor') }}"
                class="hover:text-(--serp-text) {{ $active === 'sponsor' ? 'font-semibold text-(--serp-text)' : '' }}"
            >
                {{ __('Sponsor Us') }}
            </a>
        </nav>
    </div>
</footer>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=FooterComponentTest`
Expected: PASS

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/components/site/footer.blade.php tests/Feature/Site/FooterComponentTest.php
git commit -m "Add shared footer component"
```

---

### Task 8: Privacy Policy page

**Files:**
- Create: `resources/views/pages/privacy.blade.php`
- Test: `tests/Feature/Site/PrivacyPageTest.php`

**Interfaces:**
- Consumes: `x-site.footer` (Task 7), `layouts.serp` (existing).
- Produces: `GET /privacy` (route already added in Task 1) renders this view.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Site/PrivacyPageTest.php`:

```php
<?php

it('renders the privacy policy page', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertSee('Privacy Policy')
        ->assertSee('sponsorship');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=PrivacyPageTest`
Expected: FAIL — view `pages.privacy` not found (500).

- [ ] **Step 3: Write the page**

Create `resources/views/pages/privacy.blade.php`:

```blade
<x-layouts.serp>
    <x-slot:title>{{ __('Privacy Policy') }}</x-slot:title>

    <div class="flex min-h-screen flex-col">
        <div class="border-b border-(--serp-line) px-4 py-4 md:px-8 xl:px-20">
            <x-site.search-bar label="{{ config('search.query') }}" :live="false" />
        </div>

        <main class="mx-auto flex w-full max-w-[720px] flex-1 flex-col gap-6 px-4 py-10 md:px-8">
            <h1 class="text-2xl font-extrabold tracking-tight text-(--serp-text) md:text-3xl">
                {{ __('Privacy Policy') }}
            </h1>

            <p class="text-sm text-(--serp-meta)">
                {{ __('Last updated: :date', ['date' => now()->toFormattedDateString()]) }}
            </p>

            <div class="flex flex-col gap-4 text-sm leading-relaxed text-(--serp-text)">
                <p>
                    {{ __('Vertucio.com has no user accounts and does not track visitors. Browsing the search results page collects nothing beyond ordinary web server logs.') }}
                </p>

                <p>
                    {{ __('The only personal information this site collects is what you submit through the Sponsor Us form: your name, email address, company name, budget range, and message. That information is used solely to follow up about your sponsorship inquiry, and is stored so we can review it and get back to you.') }}
                </p>

                <p>
                    {{ __('To request that your sponsorship inquiry be deleted, email us using the address on the Sponsor Us page and reference the email you submitted it with.') }}
                </p>
            </div>
        </main>

        <x-site.footer active="privacy" />
    </div>
</x-layouts.serp>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=PrivacyPageTest`
Expected: PASS. If `x-layouts.serp` is not usable as a component wrapper (the existing
`layouts.serp` was written as a `#[Layout(...)]` target, not necessarily anonymous-component
compatible), open `resources/views/layouts/serp.blade.php` first — it already just renders
`{{ $slot }}` inside `<body>`, which **is** anonymous-component compatible, so
`<x-layouts.serp>...</x-layouts.serp>` works without changes to that file.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/pages/privacy.blade.php tests/Feature/Site/PrivacyPageTest.php
git commit -m "Add the Privacy Policy page"
```

---

### Task 9: Custom pagination view matching Figma

**Files:**
- Create: `resources/views/vendor/pagination/serp.blade.php`
- Verify (already downloaded): `public/icons/chevron-left.svg`, `public/icons/chevron-right.svg`
- Test: `tests/Feature/Site/PaginationViewTest.php`

**Interfaces:**
- Produces: a Blade view usable as `$paginator->links('vendor.pagination.serp')`, driving
  `wire:click="previousPage"` / `wire:click="nextPage"` / `wire:click="gotoPage(N)"` — these three
  method names are provided by Livewire's `WithPagination` trait (used in Task 11) and must not be
  renamed.

- [ ] **Step 1: Confirm the icon assets exist**

Run: `ls -la public/icons/chevron-left.svg public/icons/chevron-right.svg`
Expected: both present (downloaded from Figma node `16:19` in file `rMMzAzhxu1QoqqUYaGrzf2`).

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/Site/PaginationViewTest.php`:

```php
<?php

use Illuminate\Pagination\LengthAwarePaginator;

it('renders numbered pill buttons for each page', function () {
    $paginator = new LengthAwarePaginator(
        items: range(1, 4),
        total: 12,
        perPage: 4,
        currentPage: 2,
    );

    $html = $paginator->links('vendor.pagination.serp')->render();

    expect($html)
        ->toContain('wire:click="gotoPage(1)"')
        ->toContain('wire:click="gotoPage(3)"')
        ->toContain('wire:click="previousPage"')
        ->toContain('wire:click="nextPage"');
});

it('renders no navigation when there is only one page', function () {
    $paginator = new LengthAwarePaginator(items: [1, 2], total: 2, perPage: 4, currentPage: 1);

    expect(trim($paginator->links('vendor.pagination.serp')->render()))->toBe('');
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --compact --filter=PaginationViewTest`
Expected: FAIL — view `vendor.pagination.serp` not found.

- [ ] **Step 4: Write the pagination view**

Create `resources/views/vendor/pagination/serp.blade.php`:

```blade
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center gap-2">
        @if ($paginator->onFirstPage())
            <span class="flex size-8 items-center justify-center rounded-full border border-(--serp-line) bg-(--serp-raised) opacity-40">
                <img src="{{ asset('icons/chevron-left.svg') }}" alt="" class="size-4">
            </span>
        @else
            <button
                type="button"
                wire:click="previousPage"
                wire:loading.attr="disabled"
                class="flex size-8 items-center justify-center rounded-full border border-(--serp-line) bg-(--serp-raised) hover:bg-(--serp-line)"
            >
                <img src="{{ asset('icons/chevron-left.svg') }}" alt="{{ __('Previous') }}" class="size-4">
            </button>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="flex size-8 items-center justify-center rounded-full border border-(--serp-line) bg-(--serp-raised) text-[13px] font-medium text-(--serp-meta)">
                    {{ $element }}
                </span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="flex size-8 items-center justify-center rounded-full bg-zinc-900 text-[13px] font-semibold text-white">
                            {{ $page }}
                        </span>
                    @else
                        <button
                            type="button"
                            wire:click="gotoPage({{ $page }})"
                            class="flex size-8 items-center justify-center rounded-full border border-(--serp-line) bg-(--serp-raised) text-[13px] font-medium text-(--serp-meta) hover:bg-(--serp-line)"
                        >
                            {{ $page }}
                        </button>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <button
                type="button"
                wire:click="nextPage"
                wire:loading.attr="disabled"
                class="flex size-8 items-center justify-center rounded-full border border-(--serp-line) bg-(--serp-raised) hover:bg-(--serp-line)"
            >
                <img src="{{ asset('icons/chevron-right.svg') }}" alt="{{ __('Next') }}" class="size-4">
            </button>
        @else
            <span class="flex size-8 items-center justify-center rounded-full border border-(--serp-line) bg-(--serp-raised) opacity-40">
                <img src="{{ asset('icons/chevron-right.svg') }}" alt="" class="size-4">
            </span>
        @endif
    </nav>
@endif
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=PaginationViewTest`
Expected: PASS

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add public/icons/chevron-left.svg public/icons/chevron-right.svg \
        resources/views/vendor/pagination/serp.blade.php \
        tests/Feature/Site/PaginationViewTest.php
git commit -m "Add a pagination view matching the Figma pill-button style"
```

---

### Task 10: `x-serp.sponsored-row` component

**Files:**
- Create: `resources/views/components/serp/sponsored-row.blade.php`
- Test: `tests/Feature/Search/SponsoredRowComponentTest.php`

**Interfaces:**
- Consumes: any model using `RendersResultLinks` with a `title`/`description` (here,
  `SponsoredResult`).
- Produces: `<x-serp.sponsored-row :result="$sponsoredResult" />`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Search/SponsoredRowComponentTest.php`:

```php
<?php

use App\Models\SponsoredResult;

it('shows an inline Sponsored label beside the title, no separate heading needed', function () {
    $result = SponsoredResult::factory()->active()->create([
        'url' => 'https://vertucio.com/pricing',
        'title' => 'Vertucio Pricing',
        'description' => 'Compare plans and pricing.',
    ]);

    $html = Blade::render('<x-serp.sponsored-row :result="$result" />', ['result' => $result]);

    expect($html)
        ->toContain('Sponsored')
        ->toContain('Vertucio Pricing')
        ->toContain('Compare plans and pricing.')
        ->toContain('rel="sponsored noopener noreferrer nofollow"');
});

it('renders unlinked text when the URL is unsafe', function () {
    $result = SponsoredResult::factory()->active()->create([
        'url' => 'javascript:alert(1)',
        'title' => 'Bad link',
    ]);

    $html = Blade::render('<x-serp.sponsored-row :result="$result" />', ['result' => $result]);

    expect($html)->not->toContain('href="javascript:alert(1)"');
});
```

Add `use Illuminate\Support\Facades\Blade;` at the top of the file.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=SponsoredRowComponentTest`
Expected: FAIL — component `serp.sponsored-row` not found.

- [ ] **Step 3: Write the component**

Create `resources/views/components/serp/sponsored-row.blade.php`:

```blade
@props(['result'])

@php
    $href = $result->safeUrl();
@endphp

<article class="flex flex-col gap-1">
    <p class="truncate text-xs text-(--serp-url) md:text-[13px]">
        {{ $result->displayHost() }}
    </p>

    <div class="flex flex-wrap items-baseline gap-2">
        <span class="shrink-0 text-[11px] font-semibold uppercase tracking-wide text-(--serp-meta)">
            {{ __('Sponsored') }}
        </span>

        <h3 class="text-lg leading-snug md:text-xl">
            @if ($href)
                <a
                    href="{{ $href }}"
                    target="_blank"
                    rel="sponsored noopener noreferrer nofollow"
                    class="line-clamp-2 text-(--serp-link) underline-offset-2 hover:underline focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--serp-link)"
                >
                    {{ $result->title }}
                </a>
            @else
                <span class="line-clamp-2 text-(--serp-text)">{{ $result->title }}</span>
            @endif
        </h3>
    </div>

    @if (filled($result->description))
        <p class="line-clamp-2 text-sm leading-relaxed text-(--serp-meta)">
            {{ $result->description }}
        </p>
    @endif
</article>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=SponsoredRowComponentTest`
Expected: PASS

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/components/serp/sponsored-row.blade.php tests/Feature/Search/SponsoredRowComponentTest.php
git commit -m "Add sponsored-row component with an inline Sponsored label"
```

---

### Task 11: Refactor the search page onto Livewire

**Files:**
- Modify: `resources/views/pages/⚡search.blade.php`
- Modify: `tests/Feature/Search/SearchPageTest.php`
- Test: `tests/Feature/Search/SearchPageFilteringTest.php`

**Interfaces:**
- Consumes: `LatestSearchResults::for()/paginate()` (Task 4), `SponsoredResult::active()->matching()`
  (Task 5), `x-site.search-bar` (Task 6), `x-site.footer` (Task 7),
  `vendor.pagination.serp` (Task 9), `x-serp.sponsored-row` (Task 10).
- Produces: Livewire component `pages::search` with public property `term`, action method
  `search(): void`, lifecycle hook `updatedTerm(): void`, and computed properties `news`,
  `images`, `sponsored`, `results` (a `LengthAwarePaginator`), all consumed by the Blade half of
  the same file.

- [ ] **Step 1: Write the failing tests**

First, update the existing `it('renders the page for guests', ...)` test in
`tests/Feature/Search/SearchPageTest.php` — it currently asserts `'Verified Results'`, which this
refactor removes (the search bar replaces that header entirely). Replace that one test:

```php
it('renders the page for guests', function () {
    $this->get(route('search'))
        ->assertOk()
        ->assertSee('vertucio');
});
```

Then create `tests/Feature/Search/SearchPageFilteringTest.php`:

```php
<?php

use App\Models\SearchResult;
use App\Models\SearchSnapshot;
use App\Models\SponsoredResult;
use App\Search\Enums\SearchVertical;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

function seedWebResult(string $title, string $description = ''): void
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
        'description' => $description,
    ]);
}

it('filters the results section by the typed term', function () {
    seedWebResult('Vertucio pricing guide', 'Plans and pricing');

    Livewire::test('pages::search')
        ->set('term', 'pricing')
        ->call('search')
        ->assertSee('Vertucio pricing guide');
});

it('shows nothing when the term matches no result', function () {
    seedWebResult('Vertucio pricing guide', 'Plans and pricing');

    Livewire::test('pages::search')
        ->set('term', 'no such term anywhere')
        ->call('search')
        ->assertDontSee('Vertucio pricing guide');
});

it('resets to page one when a new search term is submitted', function () {
    foreach (range(1, 10) as $i) {
        seedWebResult("Result number {$i}");
    }

    Livewire::test('pages::search')
        ->call('nextPage')
        ->set('term', 'Result')
        ->call('search')
        ->assertSet('paginators.page', 1);
});

it('renders sponsored rows with the inline label and no section heading', function () {
    SponsoredResult::factory()->active()->create([
        'title' => 'Vertucio Pricing',
        'description' => 'Compare plans.',
    ]);

    $this->get(route('search'))
        ->assertOk()
        ->assertSee('Vertucio Pricing')
        ->assertSee('Sponsored')
        ->assertDontSee('SPONSORED RESULTS'); // no separate section heading
});

it('renders pagination controls once there is more than one page of results', function () {
    foreach (range(1, 10) as $i) {
        seedWebResult("Result number {$i}");
    }

    $this->get(route('search'))
        ->assertOk()
        ->assertSee('wire:click="nextPage"', escape: false);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=SearchPageTest`
Run: `php artisan test --compact --filter=SearchPageFilteringTest`
Expected: FAIL — the page is not yet a stateful Livewire component (`term`/`search`/pagination
don't exist), and the sponsored block still renders under a section heading.

- [ ] **Step 3: Rewrite the page**

Replace the full contents of `resources/views/pages/⚡search.blade.php`:

```php
<?php

use App\Models\SponsoredResult;
use App\Search\Enums\SearchVertical;
use App\Search\Queries\LatestSearchResults;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.serp')] #[Title('Search Results')] class extends Component
{
    use WithPagination;

    /**
     * Display caps taken from the design. The fetch layer stores
     * search.results_per_vertical (20) per snapshot; these govern how many of
     * those the page shows.
     */
    public const NEWS_LIMIT = 3;

    public const IMAGE_LIMIT = 6;

    public const RESULTS_PER_PAGE = 4;

    #[Url(as: 'q')]
    public string $term = '';

    /**
     * Any change to the bound term — whether typed live or synced on a
     * deferred submit — should not leave the user stranded on a page number
     * that no longer exists for the new result set.
     */
    public function updatedTerm(): void
    {
        $this->resetPage();
    }

    public function search(): void
    {
        $this->resetPage();
    }

    /**
     * @return Collection<int, \App\Models\SearchResult>
     */
    #[Computed]
    public function news(): Collection
    {
        return app(LatestSearchResults::class)->for(SearchVertical::News, self::NEWS_LIMIT, $this->term);
    }

    /**
     * @return Collection<int, \App\Models\SearchResult>
     */
    #[Computed]
    public function images(): Collection
    {
        return app(LatestSearchResults::class)->for(SearchVertical::Images, self::IMAGE_LIMIT, $this->term);
    }

    /**
     * @return Collection<int, SponsoredResult>
     */
    #[Computed]
    public function sponsored(): Collection
    {
        return SponsoredResult::active()
            ->when(filled($this->term), fn ($query) => $query->matching($this->term))
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function results(): LengthAwarePaginator
    {
        return app(LatestSearchResults::class)->paginate(SearchVertical::Web, self::RESULTS_PER_PAGE, $this->term);
    }

    public function newsCollected(): bool
    {
        return app(LatestSearchResults::class)->wasCollected(SearchVertical::News);
    }

    public function imagesCollected(): bool
    {
        return app(LatestSearchResults::class)->wasCollected(SearchVertical::Images);
    }

    public function resultsCollected(): bool
    {
        return app(LatestSearchResults::class)->wasCollected(SearchVertical::Web);
    }

    public function lastCollectedAt(): ?Carbon
    {
        return app(LatestSearchResults::class)->lastCollectedAt(SearchVertical::Web);
    }
}; ?>

<div class="flex min-h-screen flex-col">
    <div class="border-b border-(--serp-line) px-4 py-4 md:px-8 xl:px-20">
        <x-site.search-bar label="{{ config('search.query') }}" :live="true" />
    </div>

    <main class="mx-auto flex w-full max-w-[1280px] flex-1 flex-col gap-8 px-4 py-6 md:gap-10 md:px-8 md:py-8 xl:gap-12 xl:px-20 xl:py-10">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <p class="text-sm text-(--serp-meta)">
                {{ __('Showing results for') }}
                <span class="font-medium text-(--serp-text)">{{ filled($term) ? $term : config('search.query') }}</span>
            </p>

            @if ($this->lastCollectedAt())
                <p class="text-xs text-(--serp-meta)">
                    {{ __('Updated') }}
                    <time datetime="{{ $this->lastCollectedAt()->toIso8601String() }}">
                        {{ $this->lastCollectedAt()->diffForHumans() }}
                    </time>
                </p>
            @endif
        </div>

        <x-serp.section :heading="__('News')" :collected="$this->newsCollected()" :is-empty="$this->news->isEmpty()">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->news as $result)
                    <x-serp.news-card :result="$result" :wire:key="'news-'.$result->id" />
                @endforeach
            </div>
        </x-serp.section>

        <x-serp.section :heading="__('Images')" :collected="$this->imagesCollected()" :is-empty="$this->images->isEmpty()">
            <div class="grid grid-cols-3 gap-4 xl:grid-cols-6">
                @foreach ($this->images as $result)
                    <x-serp.image-card :result="$result" :wire:key="'image-'.$result->id" />
                @endforeach
            </div>
        </x-serp.section>

        @if ($this->sponsored->isNotEmpty())
            <div class="flex flex-col gap-6 md:gap-7 xl:gap-8">
                @foreach ($this->sponsored as $result)
                    <x-serp.sponsored-row :result="$result" :wire:key="'sponsored-'.$result->id" />
                @endforeach
            </div>
        @endif

        <x-serp.section :heading="__('Results')" :collected="$this->resultsCollected()" :is-empty="$this->results->isEmpty()">
            <div class="flex flex-col gap-6 md:gap-7 xl:gap-8">
                @foreach ($this->results as $result)
                    <x-serp.result-row :result="$result" :wire:key="'result-'.$result->id" />
                @endforeach
            </div>

            <div class="pt-4">
                {{ $this->results->links('vendor.pagination.serp') }}
            </div>
        </x-serp.section>
    </main>

    <x-site.footer />
</div>
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=SearchPageTest`
Run: `php artisan test --compact --filter=SearchPageFilteringTest`
Expected: PASS. If `assertSet('paginators.page', 1)` fails because Livewire's pagination trait
namespaces the page key differently (e.g. `paginators.default` vs a keyed array), inspect the
actual property with `dd($component->get('paginators'))` in a scratch test run and adjust the
assertion key to match — the behavior under test is "page resets to 1", not the exact internal
key name.

- [ ] **Step 5: Run the full existing Search test suite for regressions**

Run: `php artisan test --compact --filter=Search`
Expected: PASS. Fix any test in `SearchResultDisplayTest.php` or `SearchResultUrlSafetyTest.php`
that still does a plain `$this->get(route('search'))` and asserted on now-removed markup (e.g.
"Verified Results") — update assertions to match current output, not behavior.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/pages/⚡search.blade.php \
        tests/Feature/Search/SearchPageTest.php \
        tests/Feature/Search/SearchPageFilteringTest.php
git commit -m "Refactor the search page onto Livewire with a live search bar and pagination"
```

---

### Task 12: Sponsor Us page

**Files:**
- Create: `resources/views/pages/⚡sponsor.blade.php`
- Test: `tests/Feature/Sponsorship/SponsorPageTest.php`

**Interfaces:**
- Consumes: `SponsorshipInquiry` (Task 2), `SponsorshipInquiryReceived` (Task 3), `x-site.search-bar`
  (Task 6), `x-site.footer` (Task 7), `config('sponsorship.*')` (Task 1).
- Produces: Livewire component `pages::sponsor` with public properties `fullName`, `email`,
  `companyName`, `monthlyBudget`, `message`, `submitted`, and action method `submit(): void`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Sponsorship/SponsorPageTest.php`:

```php
<?php

use App\Mail\SponsorshipInquiryReceived;
use App\Models\SponsorshipInquiry;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    config(['sponsorship.notify_email' => 'owner@vertucio.com']);
});

it('renders the sponsor us form for guests', function () {
    $this->get(route('sponsor'))
        ->assertOk()
        ->assertSee('Sponsor Us')
        ->assertSee('Submit Sponsorship Request');
});

it('creates an inquiry and emails a notification on valid submission', function () {
    Mail::fake();

    Livewire::test('pages::sponsor')
        ->set('fullName', 'Jane Doe')
        ->set('email', 'jane@company.com')
        ->set('companyName', 'Acme Corp')
        ->set('monthlyBudget', '1000_5000')
        ->set('message', 'Interested in the top slot.')
        ->call('submit')
        ->assertSet('submitted', true);

    expect(SponsorshipInquiry::query()->count())->toBe(1);

    $inquiry = SponsorshipInquiry::sole();

    expect($inquiry->full_name)->toBe('Jane Doe')
        ->and($inquiry->monthly_budget)->toBe('1000_5000');

    Mail::assertSent(
        SponsorshipInquiryReceived::class,
        fn (SponsorshipInquiryReceived $mail): bool => $mail->hasTo('owner@vertucio.com')
            && $mail->inquiry->is($inquiry),
    );
});

it('rejects an invalid submission and creates nothing', function () {
    Mail::fake();

    Livewire::test('pages::sponsor')
        ->set('fullName', '')
        ->set('email', 'not-an-email')
        ->set('companyName', 'Acme Corp')
        ->set('monthlyBudget', 'not_a_real_tier')
        ->set('message', 'Interested.')
        ->call('submit')
        ->assertHasErrors(['fullName', 'email', 'monthlyBudget']);

    expect(SponsorshipInquiry::query()->count())->toBe(0);
    Mail::assertNothingSent();
});

it('links the express sponsorship button to the configured PayPal URL', function () {
    config(['sponsorship.paypal_url' => 'https://paypal.me/vertucio-test']);

    $this->get(route('sponsor'))
        ->assertOk()
        ->assertSee('https://paypal.me/vertucio-test');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=SponsorPageTest`
Expected: FAIL — `pages::sponsor` component / view does not exist.

- [ ] **Step 3: Write the page**

Create `resources/views/pages/⚡sponsor.blade.php`:

```php
<?php

use App\Mail\SponsorshipInquiryReceived;
use App\Models\SponsorshipInquiry;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('layouts.serp')] #[Title('Sponsor Us')] class extends Component
{
    /**
     * Keys must stay in sync with the `in:` rule on $monthlyBudget below —
     * there is no single source of truth because #[Validate] arguments must
     * be compile-time string literals.
     *
     * @var array<string, string>
     */
    public const BUDGET_TIERS = [
        'under_1000' => 'Under $1,000 / month',
        '1000_5000' => '$1,000 - $5,000 / month',
        '5000_10000' => '$5,000 - $10,000 / month',
        '10000_plus' => '$10,000+ / month',
    ];

    #[Validate('required|string|max:255')]
    public string $fullName = '';

    #[Validate('required|email|max:255')]
    public string $email = '';

    #[Validate('required|string|max:255')]
    public string $companyName = '';

    #[Validate('required|string|in:under_1000,1000_5000,5000_10000,10000_plus')]
    public string $monthlyBudget = '';

    #[Validate('required|string|max:2000')]
    public string $message = '';

    public bool $submitted = false;

    public function submit(): void
    {
        $this->validate();

        $inquiry = SponsorshipInquiry::create([
            'full_name' => $this->fullName,
            'email' => $this->email,
            'company_name' => $this->companyName,
            'monthly_budget' => $this->monthlyBudget,
            'message' => $this->message,
        ]);

        if (filled(config('sponsorship.notify_email'))) {
            Mail::to((string) config('sponsorship.notify_email'))->send(new SponsorshipInquiryReceived($inquiry));
        }

        $this->submitted = true;
    }
}; ?>

<div class="flex min-h-screen flex-col">
    <div class="border-b border-(--serp-line) px-4 py-4 md:px-8 xl:px-20">
        <x-site.search-bar label="Vertucio" :live="false" />
    </div>

    <main class="mx-auto flex w-full max-w-[680px] flex-1 flex-col gap-10 px-4 py-10 md:px-8 md:py-16">
        <div class="flex flex-col gap-3">
            <h1 class="text-2xl font-extrabold tracking-tight text-(--serp-text) md:text-3xl">
                {{ __('Sponsor Us') }}
            </h1>
            <p class="text-sm leading-relaxed text-(--serp-meta) md:text-[15px]">
                {{ __('Interested in sponsoring search results on Vertucio? Fill out the form below and our team will get back to you with rates and placement options.') }}
            </p>
        </div>

        @if ($submitted)
            <div class="rounded-lg border border-(--serp-line) bg-(--serp-raised) px-4 py-6 text-sm text-(--serp-text)">
                {{ __('Thanks — your sponsorship request has been received. We will get back to you soon.') }}
            </div>
        @else
            <form wire:submit="submit" class="flex flex-col gap-6">
                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div class="flex flex-col gap-2">
                        <label for="fullName" class="text-[13px] font-semibold text-(--serp-text)">{{ __('Full Name') }}</label>
                        <input
                            id="fullName"
                            type="text"
                            wire:model="fullName"
                            placeholder="{{ __('e.g. John Doe') }}"
                            class="h-11 rounded-lg border border-(--serp-line) bg-(--serp-surface) px-3.5 text-sm text-(--serp-text) placeholder:text-(--serp-meta) focus:outline-none"
                        >
                        @error('fullName') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="email" class="text-[13px] font-semibold text-(--serp-text)">{{ __('Email Address') }}</label>
                        <input
                            id="email"
                            type="email"
                            wire:model="email"
                            placeholder="{{ __('e.g. john@company.com') }}"
                            class="h-11 rounded-lg border border-(--serp-line) bg-(--serp-surface) px-3.5 text-sm text-(--serp-text) placeholder:text-(--serp-meta) focus:outline-none"
                        >
                        @error('email') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="companyName" class="text-[13px] font-semibold text-(--serp-text)">{{ __('Company Name') }}</label>
                        <input
                            id="companyName"
                            type="text"
                            wire:model="companyName"
                            placeholder="{{ __('e.g. Acme Corp') }}"
                            class="h-11 rounded-lg border border-(--serp-line) bg-(--serp-surface) px-3.5 text-sm text-(--serp-text) placeholder:text-(--serp-meta) focus:outline-none"
                        >
                        @error('companyName') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="monthlyBudget" class="text-[13px] font-semibold text-(--serp-text)">{{ __('Monthly Budget') }}</label>
                        <select
                            id="monthlyBudget"
                            wire:model="monthlyBudget"
                            class="h-11 rounded-lg border border-(--serp-line) bg-(--serp-surface) px-3.5 text-sm text-(--serp-text) focus:outline-none"
                        >
                            <option value="">{{ __('Select your budget scale') }}</option>
                            @foreach (self::BUDGET_TIERS as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('monthlyBudget') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <label for="message" class="text-[13px] font-semibold text-(--serp-text)">{{ __('Your Message / Placement Goals') }}</label>
                    <textarea
                        id="message"
                        wire:model="message"
                        rows="4"
                        placeholder="{{ __('Tell us about your brand and what search terms you are interested in sponsoring...') }}"
                        class="rounded-lg border border-(--serp-line) bg-(--serp-surface) px-3.5 py-3 text-sm text-(--serp-text) placeholder:text-(--serp-meta) focus:outline-none"
                    ></textarea>
                    @error('message') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>

                <button
                    type="submit"
                    class="flex h-12 items-center justify-center rounded-lg bg-blue-600 text-[15px] font-semibold text-white"
                >
                    {{ __('Submit Sponsorship Request') }}
                </button>
            </form>

            <div class="flex items-center gap-4">
                <div class="h-px flex-1 bg-(--serp-line)"></div>
                <span class="shrink-0 text-xs font-semibold uppercase tracking-wide text-(--serp-meta)">{{ __('Or Fast Track') }}</span>
                <div class="h-px flex-1 bg-(--serp-line)"></div>
            </div>

            <div class="flex flex-col items-center gap-4">
                <p class="text-[16px] font-bold text-(--serp-text)">{{ __('Pay via PayPal') }}</p>
                <p class="text-center text-[13px] text-(--serp-meta)">
                    {{ __('Already have a custom quote or booking ID? Secure your slot instantly below.') }}
                </p>
                <a
                    href="{{ config('sponsorship.paypal_url') }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="flex h-12 w-full items-center justify-center gap-2 rounded-lg border border-[#e2b21c] bg-[#ffc439] text-[15px] font-bold italic text-[#111]"
                >
                    {{ __('Pay with PayPal') }}
                </a>
            </div>
        @endif
    </main>

    <x-site.footer active="sponsor" />
</div>
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=SponsorPageTest`
Expected: PASS

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/pages/⚡sponsor.blade.php tests/Feature/Sponsorship/SponsorPageTest.php
git commit -m "Add the Sponsor Us page with lead-gen form and PayPal link"
```

---

### Task 13: Full regression pass

**Files:** none created — verification only.

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test --compact`
Expected: PASS, zero failures. Fix any regression before proceeding — do not skip or comment out
a failing test.

- [ ] **Step 2: Run static analysis**

Run: `vendor/bin/phpstan analyse` (or the project's configured Larastan command/baseline — check
`composer.json` `scripts` for the exact invocation used elsewhere in this codebase if `phpstan`
alone is not on the `PATH`).
Expected: no new errors beyond the existing baseline referenced in
`docs/superpowers/plans/2026-08-10-sponsored-results.md`.

- [ ] **Step 3: Run Pint across the whole diff**

Run: `vendor/bin/pint --dirty --format agent`
Expected: no further changes reported (everything was already formatted per-task).

- [ ] **Step 4: Manual smoke check**

Run: `composer run dev` (or `php artisan serve` + `pnpm run dev` per the project's existing
dev workflow), then in a browser:
- Visit `/` — confirm the search bar renders, typing a term and clicking Search filters the
  News/Images/Sponsored/Results sections without a full page reload, and pagination buttons under
  Results work.
- Visit `/sponsor` — confirm the form validates, a successful submission shows the confirmation
  message, and the PayPal button links out correctly.
- Visit `/privacy` — confirm it renders with the shared header/footer.
- Confirm the footer's "Sponsor Us" / "Privacy Policy" links work from every page, and the active
  link is bolded on its own page.

- [ ] **Step 5: Final commit if the smoke check turned up fixes**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "Fix regressions found in full-suite and manual smoke testing"
```

(Skip this step entirely if Steps 1–4 required no changes.)
