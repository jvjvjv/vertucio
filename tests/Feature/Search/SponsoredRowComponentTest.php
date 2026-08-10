<?php

use App\Models\SponsoredResult;
use Illuminate\Support\Facades\Blade;

it('shows an inline Sponsored label beside the title, no separate heading needed', function () {
    $result = SponsoredResult::factory()->active()->create([
        'url' => 'https://vertucio.com/pricing',
        'title' => 'Vertucio Pricing',
        'description' => 'Compare plans and pricing.',
    ]);

    $html = Blade::render('<x-serp.sponsored-row :result="$result" />', ['result' => $result]);

    expect($html)
        ->toContain('Sponsored')
        ->toContain('Vertucio Pricing')
        ->toContain('Compare plans and pricing.')
        ->toContain('rel="sponsored noopener noreferrer nofollow"');
});

it('renders unlinked text when the URL is unsafe', function () {
    $result = SponsoredResult::factory()->active()->create([
        'url' => 'javascript:alert(1)',
        'title' => 'Bad link',
    ]);

    $html = Blade::render('<x-serp.sponsored-row :result="$result" />', ['result' => $result]);

    expect($html)->not->toContain('href="javascript:alert(1)"');
});
