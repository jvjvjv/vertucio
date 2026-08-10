<?php

use App\Models\SearchResult;

it('prefers the publisher host from meta source url', function () {
    $result = SearchResult::factory()->make([
        'url' => 'https://news.google.com/rss/articles/CBMibkFV',
        'source' => 'Kearney Funeral Services',
        'meta' => ['source_url' => 'https://www.kearneyfs.com'],
    ]);

    expect($result->displayHost())->toBe('kearneyfs.com');
});

it('falls back to the source when there is no source url', function () {
    $result = SearchResult::factory()->make([
        'url' => 'https://example.com/a',
        'source' => 'www.ancestry.com',
        'meta' => [],
    ]);

    expect($result->displayHost())->toBe('ancestry.com');
});

it('falls back to the host of the link itself', function () {
    $result = SearchResult::factory()->make([
        'url' => 'https://www.example.com/some/path',
        'source' => null,
        'meta' => [],
    ]);

    expect($result->displayHost())->toBe('example.com');
});

it('returns the raw url when it has no host', function () {
    $result = SearchResult::factory()->make([
        'url' => 'not-a-url',
        'source' => null,
        'meta' => [],
    ]);

    expect($result->displayHost())->toBe('not-a-url');
});
