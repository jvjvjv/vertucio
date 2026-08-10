<?php

use App\Models\SearchResult;
use App\Models\SearchSnapshot;
use App\Search\Enums\SearchVertical;
use Illuminate\Support\Carbon;

function seedSnapshot(string $provider, SearchVertical $vertical, int $count, array $overrides = []): SearchSnapshot
{
    $snapshot = SearchSnapshot::factory()->create([
        'provider' => $provider,
        'vertical' => $vertical,
        'fetched_at' => Carbon::parse('2026-08-09 09:00:00'),
        'result_count' => $count,
    ]);

    for ($position = 1; $position <= $count; $position++) {
        SearchResult::factory()->create(array_merge([
            'search_snapshot_id' => $snapshot->id,
            'position' => $position,
            'title' => "{$vertical->value} result {$position}",
            'url' => "https://example.com/{$vertical->value}/{$position}",
            'source' => 'example.com',
            'meta' => [],
        ], $overrides));
    }

    return $snapshot;
}

it('renders the page for guests', function () {
    $this->get(route('search'))
        ->assertOk()
        ->assertSee('Verified Results')
        ->assertSee('vertucio');
});

it('shows results from each vertical', function () {
    seedSnapshot('brave', SearchVertical::Web, 2);
    seedSnapshot('brave', SearchVertical::News, 2);
    seedSnapshot('brave', SearchVertical::Images, 2);

    $this->get(route('search'))
        ->assertOk()
        ->assertSee('web result 1')
        ->assertSee('news result 1')
        ->assertSee('News')
        ->assertSee('Images')
        ->assertSee('Results');
});

it('caps each section at the display limit from the design', function () {
    seedSnapshot('brave', SearchVertical::Web, 20);
    seedSnapshot('brave', SearchVertical::News, 20);
    seedSnapshot('brave', SearchVertical::Images, 20);

    $response = $this->get(route('search'))->assertOk();

    $response->assertSee('web result 4')->assertDontSee('web result 5');
    $response->assertSee('news result 3')->assertDontSee('news result 4');
    $response->assertSee('images result 6')->assertDontSee('images result 7');
});

it('tells the visitor when a vertical has never been collected', function () {
    $this->get(route('search'))
        ->assertOk()
        ->assertSee('Not collected yet.');
});

it('distinguishes a collected but empty vertical from an uncollected one', function () {
    seedSnapshot('brave', SearchVertical::Web, 0);

    $this->get(route('search'))
        ->assertOk()
        ->assertSee('No results found for this search.')
        ->assertSee('Not collected yet.');
});

it('renders the publisher host for a google news redirect link', function () {
    $snapshot = SearchSnapshot::factory()->create([
        'provider' => 'google_news_rss',
        'vertical' => SearchVertical::News,
        'fetched_at' => Carbon::parse('2026-08-09 09:00:00'),
        'result_count' => 1,
    ]);

    SearchResult::factory()->create([
        'search_snapshot_id' => $snapshot->id,
        'position' => 1,
        'title' => 'A headline',
        'url' => 'https://news.google.com/rss/articles/CBMibkFV',
        'source' => 'Kearney Funeral Services',
        'meta' => ['source_url' => 'https://www.kearneyfs.com'],
    ]);

    $response = $this->get(route('search'))->assertOk();

    // The publisher host is what the visitor reads...
    $response->assertSee('kearneyfs.com');

    // ...while the link still goes through Google's redirect, which is the
    // only URL the RSS feed gives us.
    $response->assertSee('href="https://news.google.com/rss/articles/CBMibkFV"', escape: false);
});

it('links results out safely', function () {
    seedSnapshot('brave', SearchVertical::Web, 1);

    $this->get(route('search'))
        ->assertOk()
        ->assertSee('rel="noopener noreferrer nofollow"', escape: false);
});
