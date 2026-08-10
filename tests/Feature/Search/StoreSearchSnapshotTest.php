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
