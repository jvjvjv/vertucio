<?php

use App\Models\SearchResult;
use App\Models\SearchSnapshot;
use App\Search\Actions\LiveSearch;
use App\Search\Contracts\SearchProvider;
use App\Search\Data\SearchQuery;
use App\Search\Data\SearchResultItem;
use App\Search\Data\SearchResultSet;
use App\Search\Enums\SearchVertical;
use App\Search\Exceptions\SearchProviderException;
use App\Search\SearchManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

function liveProvider(string $name, array $verticals, array $items = [], bool $throws = false): SearchProvider
{
    return new class($name, $verticals, $items, $throws) implements SearchProvider
    {
        /**
         * @param  array<int, SearchVertical>  $verticals
         * @param  array<int, SearchResultItem>  $items
         */
        public function __construct(
            private string $providerName,
            private array $verticals,
            private array $items,
            private bool $throws,
        ) {}

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
            if ($this->throws) {
                throw SearchProviderException::requestFailed($this->providerName, $query->vertical, 500);
            }

            return new SearchResultSet(
                provider: $this->providerName,
                vertical: $query->vertical,
                query: $query->query,
                fetchedAt: Carbon::now(),
                items: $this->items,
            );
        }
    };
}

beforeEach(function () {
    Cache::flush();
});

it('merges items from every provider supporting the vertical', function () {
    $itemA = new SearchResultItem(position: 1, url: 'https://a.example.com', title: 'From A');
    $itemB = new SearchResultItem(position: 1, url: 'https://b.example.com', title: 'From B');

    $manager = new SearchManager([
        liveProvider('a', [SearchVertical::Web], [$itemA]),
        liveProvider('b', [SearchVertical::Web], [$itemB]),
    ]);

    $results = (new LiveSearch($manager))->for(SearchVertical::Web, 'vertucio');

    expect($results->pluck('title')->all())->toBe(['From A', 'From B']);
});

it('skips a provider that throws instead of failing the whole search', function () {
    $item = new SearchResultItem(position: 1, url: 'https://a.example.com', title: 'Still here');

    $manager = new SearchManager([
        liveProvider('working', [SearchVertical::Web], [$item]),
        liveProvider('broken', [SearchVertical::Web], [], throws: true),
    ]);

    $results = (new LiveSearch($manager))->for(SearchVertical::Web, 'vertucio');

    expect($results)->toHaveCount(1)
        ->and($results->first()->title)->toBe('Still here');
});

it('skips a provider that throws an exception unrelated to search providers', function () {
    $item = new SearchResultItem(position: 1, url: 'https://a.example.com', title: 'Still here');

    $working = liveProvider('working', [SearchVertical::Web], [$item]);

    $broken = new class implements SearchProvider
    {
        public function name(): string
        {
            return 'broken';
        }

        public function supports(SearchVertical $vertical): bool
        {
            return $vertical === SearchVertical::Web;
        }

        public function search(SearchQuery $query): SearchResultSet
        {
            throw new RuntimeException('DNS resolution failed');
        }
    };

    $manager = new SearchManager([$working, $broken]);

    $results = (new LiveSearch($manager))->for(SearchVertical::Web, 'vertucio');

    expect($results)->toHaveCount(1)
        ->and($results->first()->title)->toBe('Still here');
});

it('caches the result so a repeated query does not call providers again', function () {
    $item = new SearchResultItem(position: 1, url: 'https://a.example.com', title: 'Cached once');
    $calls = 0;

    $provider = new class($item, $calls) implements SearchProvider
    {
        public function __construct(private SearchResultItem $item, private int &$calls) {}

        public function name(): string
        {
            return 'counting';
        }

        public function supports(SearchVertical $vertical): bool
        {
            return true;
        }

        public function search(SearchQuery $query): SearchResultSet
        {
            $this->calls++;

            return new SearchResultSet(
                provider: 'counting',
                vertical: $query->vertical,
                query: $query->query,
                fetchedAt: Carbon::now(),
                items: [$this->item],
            );
        }
    };

    $manager = new SearchManager([$provider]);
    $liveSearch = new LiveSearch($manager);

    $liveSearch->for(SearchVertical::Web, 'vertucio');
    $liveSearch->for(SearchVertical::Web, 'vertucio');

    expect($calls)->toBe(1);
});

it('does not write anything to the search_results or search_snapshots tables', function () {
    $item = new SearchResultItem(position: 1, url: 'https://a.example.com', title: 'Never persisted');
    $manager = new SearchManager([liveProvider('a', [SearchVertical::Web], [$item])]);

    (new LiveSearch($manager))->for(SearchVertical::Web, 'vertucio');

    expect(SearchSnapshot::query()->count())->toBe(0)
        ->and(SearchResult::query()->count())->toBe(0);
});
