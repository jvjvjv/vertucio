<?php

use App\Models\SearchResult;
use App\Models\SearchSnapshot;
use App\Models\SponsoredResult;
use App\Search\Contracts\SearchProvider;
use App\Search\Data\SearchQuery;
use App\Search\Data\SearchResultItem;
use App\Search\Data\SearchResultSet;
use App\Search\Enums\SearchVertical;
use App\Search\SearchManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

/**
 * Swaps the SearchManager singleton for one built from a single fake
 * provider, so tests exercise the live-search path through LiveSearch's
 * real container plumbing instead of hardcoding its private cache-key
 * format via Cache::put(...).
 *
 * @param  array<int, SearchResultItem>  $items
 */
function fakeSearchManager(SearchVertical $vertical, array $items): SearchManager
{
    $provider = new class($vertical, $items) implements SearchProvider
    {
        /**
         * @param  array<int, SearchResultItem>  $items
         */
        public function __construct(private SearchVertical $vertical, private array $items) {}

        public function name(): string
        {
            return 'fake';
        }

        public function supports(SearchVertical $vertical): bool
        {
            return $vertical === $this->vertical;
        }

        public function search(SearchQuery $query): SearchResultSet
        {
            return new SearchResultSet(
                provider: 'fake',
                vertical: $query->vertical,
                query: $query->query,
                fetchedAt: Carbon::now(),
                items: $this->items,
            );
        }
    };

    return new SearchManager([$provider]);
}

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

it('shows the default monitored-query results when no term is typed', function () {
    seedWebResult('Vertucio pricing guide', 'Plans and pricing');

    Livewire::test('pages::search')->assertSee('Vertucio pricing guide');
});

it('performs a live search when a term is submitted, without persisting anything', function () {
    seedWebResult('Vertucio pricing guide', 'Plans and pricing');

    Cache::put(
        'search:live:web:live term',
        collect([new SearchResultItem(position: 1, url: 'https://live.example.com', title: 'Live web result')]),
    );

    Livewire::test('pages::search')
        ->set('term', 'live term')
        ->call('search')
        ->assertSee('Live web result')
        ->assertDontSee('Vertucio pricing guide');

    expect(SearchSnapshot::query()->count())->toBe(1) // only the one from seedWebResult(), untouched
        ->and(SearchResult::query()->count())->toBe(1); // only the seeded one from setup, untouched
});

it('resets every section to page one when a new search term is submitted', function () {
    Livewire::test('pages::search')
        ->call('nextPage', 'results')
        ->set('term', 'anything')
        ->call('search')
        ->assertSet('paginators.results', 1);
});

it('filters active sponsored results by the typed term', function () {
    SponsoredResult::factory()->active()->create([
        'title' => 'Vertucio Pricing',
        'description' => 'Compare plans.',
    ]);

    SponsoredResult::factory()->active()->create([
        'title' => 'Unrelated promo',
    ]);

    Livewire::test('pages::search')
        ->set('term', 'Pricing')
        ->call('search')
        ->assertSee('Vertucio Pricing')
        ->assertDontSee('Unrelated promo');
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

it('paginates within a live search and shows distinct results per page', function () {
    // RESULTS_PER_PAGE is 4, so 5 unsaved live items span exactly two pages.
    $items = collect(range(1, 5))
        ->map(fn (int $i) => new SearchResultItem(
            position: $i,
            url: "https://live.example.com/{$i}",
            title: "Live web result {$i}",
        ))
        ->all();

    $this->app->instance(SearchManager::class, fakeSearchManager(SearchVertical::Web, $items));

    Livewire::test('pages::search')
        ->set('term', 'pagination term')
        ->call('search')
        ->assertSee('Live web result 1')
        ->assertDontSee('Live web result 5')
        ->call('nextPage', 'results')
        ->assertSee('Live web result 5')
        ->assertDontSee('Live web result 1');
});

it('initializes the search term from the ?q= query string on a fresh page load', function () {
    $items = [new SearchResultItem(position: 1, url: 'https://live.example.com/q', title: 'Query string result')];

    $this->app->instance(SearchManager::class, fakeSearchManager(SearchVertical::Web, $items));

    $this->get(route('search', ['q' => 'some term']))
        ->assertOk()
        ->assertSee('Showing results for')
        ->assertSee('some term')
        ->assertSee('Query string result');
});
