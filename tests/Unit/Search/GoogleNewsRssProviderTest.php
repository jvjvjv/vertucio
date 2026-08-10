<?php

use App\Search\Data\SearchQuery;
use App\Search\Enums\SearchVertical;
use App\Search\Exceptions\SearchProviderException;
use App\Search\Providers\GoogleNewsRssProvider;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

function googleNewsFixture(): string
{
    return file_get_contents(base_path('tests/Fixtures/search/google-news.xml'));
}

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
    Http::fake(['news.google.com/*' => Http::response(googleNewsFixture())]);

    (new GoogleNewsRssProvider)->search(new SearchQuery('vertucio', SearchVertical::News));

    Http::assertSent(function ($request) {
        return $request['q'] === 'vertucio'
            && $request['hl'] === 'en-US'
            && $request['gl'] === 'US'
            && $request['ceid'] === 'US:en';
    });
});

it('maps rss items into a result set', function () {
    Http::fake(['news.google.com/*' => Http::response(googleNewsFixture())]);

    $set = (new GoogleNewsRssProvider)->search(new SearchQuery('vertucio', SearchVertical::News));

    expect($set->provider)->toBe('google_news_rss')
        ->and($set->vertical)->toBe(SearchVertical::News)
        ->and($set->count())->toBeGreaterThan(0);

    $first = $set->items[0];

    expect($first->position)->toBe(1)
        ->and($first->url)->toStartWith('http')
        ->and($first->title)->not->toBeEmpty()
        ->and($first->imageUrl)->toBeNull();

    expect(array_map(fn ($item) => $item->position, $set->items))
        ->toBe(range(1, $set->count()));
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

it('skips items with no link', function () {
    Http::fake([
        'news.google.com/*' => Http::response(
            '<?xml version="1.0"?><rss><channel>'
            .'<item><title>No link</title></item>'
            .'<item><title>Has link</title><link>https://example.com/a</link></item>'
            .'</channel></rss>'
        ),
    ]);

    $set = (new GoogleNewsRssProvider)->search(new SearchQuery('vertucio', SearchVertical::News));

    expect($set->count())->toBe(1)
        ->and($set->items[0]->position)->toBe(1)
        ->and($set->items[0]->title)->toBe('Has link');
});

it('throws when the request fails', function () {
    Http::fake(['news.google.com/*' => Http::response('nope', 503)]);

    (new GoogleNewsRssProvider)->search(new SearchQuery('vertucio', SearchVertical::News));
})->throws(SearchProviderException::class, 'request failed with HTTP 503');

it('throws when the body is not valid xml', function () {
    Http::fake(['news.google.com/*' => Http::response('not xml at all')]);

    (new GoogleNewsRssProvider)->search(new SearchQuery('vertucio', SearchVertical::News));
})->throws(SearchProviderException::class, 'unexpected payload');
