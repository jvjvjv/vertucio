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

function googleNewsResponse(): string
{
    return '<?xml version="1.0"?><rss><channel><item>'
        .'<title>A headline</title><link>https://news.google.com/rss/articles/X</link>'
        .'</item></channel></rss>';
}

function fakeAllProviders(): void
{
    Http::fake([
        'api.search.brave.com/res/v1/web/*' => Http::response(braveResponse('web')),
        'api.search.brave.com/res/v1/news/*' => Http::response(braveResponse('news')),
        'api.search.brave.com/res/v1/images/*' => Http::response(braveResponse('images')),
        'news.google.com/*' => Http::response(googleNewsResponse()),
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
        'news.google.com/*' => Http::response(googleNewsResponse()),
    ]);

    $this->artisan('search:fetch')->assertExitCode(1);

    expect(SearchSnapshot::count())->toBe(3)
        ->and(SearchSnapshot::where('provider', 'brave')->where('vertical', 'web')->exists())->toBeFalse();
});

it('persists the results attached to each snapshot', function () {
    fakeAllProviders();

    $this->artisan('search:fetch')->assertExitCode(0);

    $webSnapshot = SearchSnapshot::where('provider', 'brave')->where('vertical', 'web')->sole();

    expect($webSnapshot->results)->toHaveCount(1)
        ->and($webSnapshot->results->first()->url)->toBe('https://example.com/web')
        ->and($webSnapshot->results->first()->position)->toBe(1);
});
