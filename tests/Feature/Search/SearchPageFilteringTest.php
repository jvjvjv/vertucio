<?php

use App\Models\SearchResult;
use App\Models\SearchSnapshot;
use App\Models\SponsoredResult;
use App\Search\Data\SearchResultItem;
use App\Search\Enums\SearchVertical;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

function seedWebResult(string $title, string $description = ''): void
{
    $snapshot = SearchSnapshot::factory()->create([
        'provider' => 'brave',
        'vertical' => SearchVertical::Web,
        'fetched_at' => Carbon::parse('2026-08-09 09:00:00'),
        'result_count' => 1,
    ]);

    SearchResult::factory()->create([
        'search_snapshot_id' => $snapshot->id,
        'position' => 1,
        'title' => $title,
        'description' => $description,
    ]);
}

it('shows the default monitored-query results when no term is typed', function () {
    seedWebResult('Vertucio pricing guide', 'Plans and pricing');

    Livewire::test('pages::search')->assertSee('Vertucio pricing guide');
});

it('performs a live search when a term is submitted, without persisting anything', function () {
    seedWebResult('Vertucio pricing guide', 'Plans and pricing');

    Cache::put(
        'search:live:web:live term',
        collect([new SearchResultItem(position: 1, url: 'https://live.example.com', title: 'Live web result')]),
    );

    Livewire::test('pages::search')
        ->set('term', 'live term')
        ->call('search')
        ->assertSee('Live web result')
        ->assertDontSee('Vertucio pricing guide');

    expect(SearchSnapshot::query()->count())->toBe(1) // only the one from seedWebResult(), untouched
        ->and(SearchResult::query()->count())->toBe(1); // only the seeded one from setup, untouched
});

it('resets every section to page one when a new search term is submitted', function () {
    Livewire::test('pages::search')
        ->call('nextPage', 'results')
        ->set('term', 'anything')
        ->call('search')
        ->assertSet('paginators.results', 1);
});

it('filters active sponsored results by the typed term', function () {
    SponsoredResult::factory()->active()->create([
        'title' => 'Vertucio Pricing',
        'description' => 'Compare plans.',
    ]);

    SponsoredResult::factory()->active()->create([
        'title' => 'Unrelated promo',
    ]);

    Livewire::test('pages::search')
        ->set('term', 'Pricing')
        ->call('search')
        ->assertSee('Vertucio Pricing')
        ->assertDontSee('Unrelated promo');
});

it('renders sponsored rows with the inline label and no section heading', function () {
    SponsoredResult::factory()->active()->create([
        'title' => 'Vertucio Pricing',
        'description' => 'Compare plans.',
    ]);

    $this->get(route('search'))
        ->assertOk()
        ->assertSee('Vertucio Pricing')
        ->assertSee('Sponsored')
        ->assertDontSee('SPONSORED RESULTS'); // no separate section heading
});
