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

it('resolves the source from the result host', function () {
    Http::fake([
        'api.search.brave.com/res/v1/news/*' => Http::response(braveFixture('news')),
    ]);

    $set = (new BraveSearchProvider)->search(new SearchQuery('vertucio', SearchVertical::News));

    expect($set->items[0]->source)->not->toBeEmpty();
});

it('parses the iso publication date from news results', function () {
    Http::fake([
        'api.search.brave.com/res/v1/news/*' => Http::response([
            'results' => [[
                'title' => 'A headline',
                'url' => 'https://example.com',
                'page_age' => '2022-11-08T09:06:21',
                'age' => 'November 8, 2022',
            ]],
        ]),
    ]);

    $set = (new BraveSearchProvider)->search(new SearchQuery('vertucio', SearchVertical::News));

    expect($set->items[0]->publishedAt->toDateTimeString())->toBe('2022-11-08 09:06:21');
});

it('leaves the publication date null when brave only gives a human readable age', function () {
    Http::fake([
        'api.search.brave.com/res/v1/web/*' => Http::response([
            'web' => ['results' => [[
                'title' => 'A page',
                'url' => 'https://example.com',
                'age' => 'about 3 hours ago',
            ]]],
        ]),
    ]);

    $set = (new BraveSearchProvider)->search(new SearchQuery('vertucio', SearchVertical::Web));

    expect($set->items[0]->publishedAt)->toBeNull();
});

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

it('skips results that carry no url', function () {
    Http::fake([
        'api.search.brave.com/res/v1/web/*' => Http::response([
            'web' => ['results' => [
                ['title' => 'No url here'],
                ['title' => 'Has a url', 'url' => 'https://example.com'],
            ]],
        ]),
    ]);

    $set = (new BraveSearchProvider)->search(new SearchQuery('vertucio', SearchVertical::Web));

    expect($set->count())->toBe(1)
        ->and($set->items[0]->position)->toBe(1)
        ->and($set->items[0]->title)->toBe('Has a url');
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
