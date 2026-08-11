<?php

it('has a search route at the home page', function () {
    expect(route('search'))->toBe(url('/'));
});

it('has named routes for sponsor and privacy pages', function () {
    expect(route('sponsor'))->toBe(url('/sponsor'))
        ->and(route('privacy'))->toBe(url('/privacy'));
});
