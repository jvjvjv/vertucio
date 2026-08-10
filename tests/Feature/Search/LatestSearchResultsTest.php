<?php

use App\Models\SearchResult;
use App\Models\SearchSnapshot;
use App\Search\Enums\SearchVertical;
use App\Search\Queries\LatestSearchResults;
use Illuminate\Support\Carbon;

function snapshotWith(string $provider, SearchVertical $vertical, Carbon $fetchedAt, int $results): SearchSnapshot
{
    $snapshot = SearchSnapshot::factory()->create([
        'provider' => $provider,
        'vertical' => $vertical,
        'fetched_at' => $fetchedAt,
        'result_count' => $results,
    ]);

    foreach (range(1, max($results, 0)) as $position) {
        if ($results === 0) {
            break;
        }

        SearchResult::factory()->create([
            'search_snapshot_id' => $snapshot->id,
            'position' => $position,
            'title' => "{$provider} #{$position}",
        ]);
    }

    return $snapshot;
}

it('returns results from the newest snapshot only', function () {
    snapshotWith('brave', SearchVertical::Web, Carbon::parse('2026-08-01 09:00'), 2);
    snapshotWith('brave', SearchVertical::Web, Carbon::parse('2026-08-09 09:00'), 3);

    $results = app(LatestSearchResults::class)->for(SearchVertical::Web, 10);

    expect($results)->toHaveCount(3);
});

it('merges the newest snapshot from each provider', function () {
    snapshotWith('brave', SearchVertical::News, Carbon::parse('2026-08-09 09:00'), 2);
    snapshotWith('google_news_rss', SearchVertical::News, Carbon::parse('2026-08-09 09:00'), 2);

    $results = app(LatestSearchResults::class)->for(SearchVertical::News, 10);

    expect($results)->toHaveCount(4)
        ->and($results->pluck('snapshot.provider')->unique()->sort()->values()->all())
        ->toBe(['brave', 'google_news_rss']);
});

it('caps the number of results returned', function () {
    snapshotWith('brave', SearchVertical::Web, Carbon::parse('2026-08-09 09:00'), 20);

    expect(app(LatestSearchResults::class)->for(SearchVertical::Web, 4))->toHaveCount(4);
});

it('orders results by rank', function () {
    snapshotWith('brave', SearchVertical::Web, Carbon::parse('2026-08-09 09:00'), 3);

    expect(app(LatestSearchResults::class)->for(SearchVertical::Web, 10)->pluck('position')->all())
        ->toBe([1, 2, 3]);
});

it('reports nothing collected when no snapshot exists', function () {
    $query = app(LatestSearchResults::class);

    expect($query->wasCollected(SearchVertical::Images))->toBeFalse()
        ->and($query->for(SearchVertical::Images, 6))->toBeEmpty();
});

it('distinguishes a collected empty snapshot from no snapshot at all', function () {
    snapshotWith('brave', SearchVertical::Images, Carbon::parse('2026-08-09 09:00'), 0);

    $query = app(LatestSearchResults::class);

    expect($query->wasCollected(SearchVertical::Images))->toBeTrue()
        ->and($query->for(SearchVertical::Images, 6))->toBeEmpty();
});

it('reports when each vertical was last collected', function () {
    snapshotWith('brave', SearchVertical::Web, Carbon::parse('2026-08-09 09:00'), 1);

    expect(app(LatestSearchResults::class)->lastCollectedAt(SearchVertical::Web)->toDateTimeString())
        ->toBe('2026-08-09 09:00:00');
});
