<?php

use Illuminate\Support\Facades\Blade;

it('renders a live search bar bound to the term property', function () {
    $html = Blade::render('<x-site.search-bar label="vertucio" :live="true" />');

    expect($html)
        ->toContain('wire:submit="search"')
        ->toContain('wire:model="term"')
        ->toContain('vertucio');
});

it('renders a non-live search bar that gets to the search route', function () {
    $html = Blade::render('<x-site.search-bar label="Vertucio" :live="false" />');

    expect($html)
        ->toContain('method="GET"')
        ->toContain(route('search'))
        ->toContain('name="q"');
});

it('omits the brand logo by default and shows it when requested', function () {
    $withoutLogo = Blade::render('<x-site.search-bar label="Vertucio" :live="false" />');
    $withLogo = Blade::render('<x-site.search-bar label="Vertucio" :live="false" :logo="true" />');

    expect($withoutLogo)->not->toContain('data-brand-logo')
        ->and($withLogo)->toContain('data-brand-logo');
});
