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

it('lets provider failures propagate to the caller', function () {
    $exploding = new class implements SearchProvider
    {
        public function name(): string
        {
            return 'exploding';
        }

        public function supports(SearchVertical $vertical): bool
        {
            return true;
        }

        public function search(SearchQuery $query): SearchResultSet
        {
            throw new RuntimeException('boom');
        }
    };

    (new SearchManager([$exploding]))->fetch($exploding, SearchVertical::Web, 'vertucio');
})->throws(RuntimeException::class, 'boom');
