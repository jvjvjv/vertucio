<?php

use App\Models\SponsoredResult;
use App\Search\Enums\SponsorshipStatus;

it('creates a sponsored result from options', function () {
    $this->artisan('sponsored:add', [
        '--url' => 'https://vertucio.com/work',
        '--title' => 'Hire Jason Vertucio',
        '--description' => 'Senior Laravel engineer.',
        '--position' => '2',
        '--no-interaction' => true,
    ])->assertSuccessful();

    $promo = SponsoredResult::sole();

    expect($promo->url)->toBe('https://vertucio.com/work')
        ->and($promo->title)->toBe('Hire Jason Vertucio')
        ->and($promo->description)->toBe('Senior Laravel engineer.')
        ->and($promo->position)->toBe(2)
        ->and($promo->starts_at)->toBeNull()
        ->and($promo->ends_at)->toBeNull()
        ->and($promo->status())->toBe(SponsorshipStatus::Active);
});

it('stores a date window when given one', function () {
    $this->artisan('sponsored:add', [
        '--url' => 'https://vertucio.com/work',
        '--title' => 'Autumn promo',
        '--starts-at' => '2026-09-01 09:00',
        '--ends-at' => '2026-10-01 09:00',
        '--no-interaction' => true,
    ])->assertSuccessful();

    $promo = SponsoredResult::sole();

    expect($promo->starts_at?->toDateTimeString())->toBe('2026-09-01 09:00:00')
        ->and($promo->ends_at?->toDateTimeString())->toBe('2026-10-01 09:00:00');
});

it('rejects a url that is not http or https', function () {
    $this->artisan('sponsored:add', [
        '--url' => 'javascript:alert(1)',
        '--title' => 'Sketchy',
        '--no-interaction' => true,
    ])->assertFailed();

    expect(SponsoredResult::count())->toBe(0);
});

it('rejects an image url that is not http or https', function () {
    $this->artisan('sponsored:add', [
        '--url' => 'https://vertucio.com/work',
        '--title' => 'Sketchy image',
        '--image-url' => 'javascript:alert(1)',
        '--no-interaction' => true,
    ])->assertFailed();

    expect(SponsoredResult::count())->toBe(0);
});

it('rejects an end date that does not fall after the start date', function () {
    $this->artisan('sponsored:add', [
        '--url' => 'https://vertucio.com/work',
        '--title' => 'Backwards',
        '--starts-at' => '2026-09-01 09:00',
        '--ends-at' => '2026-08-01 09:00',
        '--no-interaction' => true,
    ])->assertFailed();

    expect(SponsoredResult::count())->toBe(0);
});

it('rejects a date it cannot parse', function () {
    $this->artisan('sponsored:add', [
        '--url' => 'https://vertucio.com/work',
        '--title' => 'Whenever',
        '--starts-at' => 'sometime next quarter',
        '--no-interaction' => true,
    ])->assertFailed();

    expect(SponsoredResult::count())->toBe(0);
});

it('requires a url', function () {
    $this->artisan('sponsored:add', [
        '--title' => 'No link',
        '--no-interaction' => true,
    ])->assertFailed();

    expect(SponsoredResult::count())->toBe(0);
});
