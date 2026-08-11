<?php

use App\Models\SearchResult;
use App\Models\SearchSnapshot;
use App\Search\Enums\SearchVertical;
use Illuminate\Support\Carbon;

it('accepts http and https urls', function (string $url) {
    expect(SearchResult::factory()->make(['url' => $url])->safeUrl())->toBe($url);
})->with([
    'https' => 'https://example.com/a',
    'http' => 'http://example.com/a',
    'uppercase scheme' => 'HTTPS://example.com/a',
]);

it('rejects dangerous url schemes', function (string $url) {
    expect(SearchResult::factory()->make(['url' => $url])->safeUrl())->toBeNull();
})->with([
    'javascript' => 'javascript:alert(document.cookie)',
    'javascript mixed case' => 'JaVaScRiPt:alert(1)',
    'data' => 'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
    'vbscript' => 'vbscript:msgbox(1)',
    'file' => 'file:///etc/passwd',
    'scheme relative' => '//evil.example.com/a',
    'schemeless' => 'evil.example.com/a',
    'empty' => '',
]);

it('rejects dangerous thumbnail schemes', function () {
    $result = SearchResult::factory()->make([
        'image_url' => 'javascript:alert(1)',
        'meta' => [],
    ]);

    expect($result->safeImageUrl())->toBeNull();
});

it('falls back to the meta thumbnail when there is no image url', function () {
    $result = SearchResult::factory()->make([
        'image_url' => null,
        'meta' => ['thumbnail' => 'https://imgs.example.com/a.jpg'],
    ]);

    expect($result->safeImageUrl())->toBe('https://imgs.example.com/a.jpg');
});

it('never renders a javascript url into an href', function () {
    $snapshot = SearchSnapshot::factory()->create([
        'provider' => 'brave',
        'vertical' => SearchVertical::Web,
        'fetched_at' => Carbon::parse('2026-08-09 09:00:00'),
        'result_count' => 1,
    ]);

    SearchResult::factory()->create([
        'search_snapshot_id' => $snapshot->id,
        'position' => 1,
        'title' => 'Totally normal result',
        'url' => 'javascript:alert(document.cookie)',
        'source' => 'evil.example.com',
        'meta' => [],
    ]);

    $response = $this->get(route('search'))->assertOk();

    // The title still shows, so the result is not silently dropped...
    $response->assertSee('Totally normal result');

    // ...but nothing navigable was emitted for it.
    $response->assertDontSee('href="javascript:', escape: false)
        ->assertDontSee('javascript:alert', escape: false);
});
