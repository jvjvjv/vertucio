<?php

use Illuminate\Support\Facades\Blade;

it('links to the privacy and sponsor routes', function () {
    $html = Blade::render('<x-site.footer />');

    expect($html)
        ->toContain(route('privacy'))
        ->toContain(route('sponsor'))
        ->toContain('Privacy Policy')
        ->toContain('Sponsor Us');
});

it('bolds the active page link', function () {
    $html = Blade::render('<x-site.footer active="sponsor" />');

    expect($html)->toContain('font-semibold');
});
