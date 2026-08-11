<?php

use App\Models\SponsoredResult;
use App\Search\Enums\SponsorshipStatus;
use Carbon\CarbonImmutable;

it('treats a promo with no window as active', function () {
    $promo = SponsoredResult::factory()->create();

    expect($promo->status())->toBe(SponsorshipStatus::Active)
        ->and(SponsoredResult::active()->pluck('id')->all())->toBe([$promo->id]);
});

it('opens the window inclusively at starts_at', function () {
    $at = CarbonImmutable::parse('2026-08-10 12:00:00');

    $promo = SponsoredResult::factory()->create([
        'starts_at' => $at,
        'ends_at' => null,
    ]);

    expect($promo->status($at))->toBe(SponsorshipStatus::Active)
        ->and($promo->status($at->subSecond()))->toBe(SponsorshipStatus::Scheduled)
        ->and(SponsoredResult::active($at)->pluck('id')->all())->toBe([$promo->id])
        ->and(SponsoredResult::active($at->subSecond())->pluck('id')->all())->toBe([]);
});

it('closes the window exclusively at ends_at', function () {
    $at = CarbonImmutable::parse('2026-08-10 12:00:00');

    $promo = SponsoredResult::factory()->create([
        'starts_at' => null,
        'ends_at' => $at,
    ]);

    expect($promo->status($at))->toBe(SponsorshipStatus::Expired)
        ->and($promo->status($at->subSecond()))->toBe(SponsorshipStatus::Active)
        ->and(SponsoredResult::active($at)->pluck('id')->all())->toBe([])
        ->and(SponsoredResult::active($at->subSecond())->pluck('id')->all())->toBe([$promo->id]);
});

it('reports expired even when the window never opened', function () {
    $promo = SponsoredResult::factory()->make([
        'starts_at' => CarbonImmutable::parse('2026-09-01 00:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-08-01 00:00:00'),
    ]);

    expect($promo->status(CarbonImmutable::parse('2026-08-10 12:00:00')))
        ->toBe(SponsorshipStatus::Expired);
});

it('provides factory states for each status', function () {
    expect(SponsoredResult::factory()->active()->create()->status())->toBe(SponsorshipStatus::Active)
        ->and(SponsoredResult::factory()->scheduled()->create()->status())->toBe(SponsorshipStatus::Scheduled)
        ->and(SponsoredResult::factory()->expired()->create()->status())->toBe(SponsorshipStatus::Expired);
});

it('filters active sponsored results by a search term', function () {
    SponsoredResult::factory()->active()->create([
        'title' => 'Vertucio pricing guide',
        'description' => 'Plans and pricing',
    ]);

    SponsoredResult::factory()->active()->create([
        'title' => 'Unrelated promo',
        'description' => 'General information about our company',
    ]);

    $results = SponsoredResult::active()->matching('pricing')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->title)->toBe('Vertucio pricing guide');
});

it('leaves the active set unfiltered when the term is blank', function () {
    SponsoredResult::factory()->active()->count(2)->create();

    expect(SponsoredResult::active()->matching(null)->get())->toHaveCount(2);
});
