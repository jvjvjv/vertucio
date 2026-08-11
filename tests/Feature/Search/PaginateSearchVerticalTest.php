<?php

use App\Models\SearchResult;
use App\Models\SearchSnapshot;
use App\Search\Actions\PaginateSearchVertical;
use App\Search\Contracts\SearchProvider;
use App\Search\Data\SearchQuery;
use App\Search\Data\SearchResultItem;
use App\Search\Data\SearchResultSet;
use App\Search\Enums\SearchVertical;
use App\Search\SearchManager;
use Illuminate\Support\Carbon;

/**
 * @param  array<int, SearchResultItem>  $items
 */
function fakeSearchManagerFor(SearchVertical $vertical, array $items): SearchManager
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

it('paginates the daily-collected snapshot when the term is blank', function () {
    $snapshot = SearchSnapshot::factory()->create([
        'provider' => 'brave',
        'vertical' => SearchVertical::Web,
        'fetched_at' => Carbon::parse('2026-08-09 09:00'),
        'result_count' => 6,
    ]);

    foreach (range(1, 6) as $position) {
        SearchResult::factory()->create([
            'search_snapshot_id' => $snapshot->id,
            'position' => $position,
            'title' => "stored result {$position}",
        ]);
    }

    $page = app(PaginateSearchVertical::class)->for(SearchVertical::Web, 4, 'results', null);

    expect($page->total())->toBe(6)
        ->and($page->items())->toHaveCount(4)
        ->and($page->items()[0]->title)->toBe('stored result 1');
});

it('performs a live search when a term is given, without persisting anything', function () {
    $item = new SearchResultItem(position: 1, url: 'https://live.example.com', title: 'Live result');
    app()->instance(SearchManager::class, fakeSearchManagerFor(SearchVertical::Web, [$item]));

    $page = app(PaginateSearchVertical::class)->for(SearchVertical::Web, 4, 'results', 'anything');

    expect($page->total())->toBe(1)
        ->and($page->items()[0]->title)->toBe('Live result')
        ->and($page->items()[0]->exists)->toBeFalse();

    expect(SearchSnapshot::query()->count())->toBe(0)
        ->and(SearchResult::query()->count())->toBe(0);
});

it('resolves the current page for a live search from the named page parameter', function () {
    $items = collect(range(1, 5))
        ->map(fn (int $i) => new SearchResultItem(position: $i, url: "https://live.example.com/{$i}", title: "Live result {$i}"))
        ->all();

    app()->instance(SearchManager::class, fakeSearchManagerFor(SearchVertical::Web, $items));

    request()->merge(['results' => 2]);

    $page = app(PaginateSearchVertical::class)->for(SearchVertical::Web, 4, 'results', 'anything');

    expect($page->currentPage())->toBe(2)
        ->and($page->items())->toHaveCount(1)
        ->and($page->items()[0]->title)->toBe('Live result 5');
});
