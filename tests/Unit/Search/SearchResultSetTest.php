<?php

use App\Search\Data\SearchResultItem;
use App\Search\Data\SearchResultSet;
use App\Search\Enums\SearchVertical;
use Illuminate\Support\Carbon;

it('exposes the vertical as a string backed value', function () {
    expect(SearchVertical::Web->value)->toBe('web')
        ->and(SearchVertical::News->value)->toBe('news')
        ->and(SearchVertical::Images->value)->toBe('images');
});

it('counts its items', function () {
    $set = new SearchResultSet(
        provider: 'brave',
        vertical: SearchVertical::Web,
        query: 'vertucio',
        fetchedAt: Carbon::parse('2026-08-10 09:00:00'),
        items: [
            new SearchResultItem(position: 1, url: 'https://example.com/a'),
            new SearchResultItem(position: 2, url: 'https://example.com/b'),
        ],
    );

    expect($set->count())->toBe(2);
});

it('defaults optional result item fields to null', function () {
    $item = new SearchResultItem(position: 1, url: 'https://example.com');

    expect($item->title)->toBeNull()
        ->and($item->description)->toBeNull()
        ->and($item->source)->toBeNull()
        ->and($item->publishedAt)->toBeNull()
        ->and($item->imageUrl)->toBeNull()
        ->and($item->meta)->toBe([]);
});
