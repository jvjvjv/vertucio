<?php

use App\Models\SponsoredResult;

it('accepts http and https urls', function (string $url) {
    expect(SponsoredResult::factory()->make(['url' => $url])->safeUrl())->toBe($url);
})->with([
    'https' => 'https://example.com/a',
    'http' => 'http://example.com/a',
    'uppercase scheme' => 'HTTPS://example.com/a',
]);

it('rejects dangerous url schemes', function (string $url) {
    expect(SponsoredResult::factory()->make(['url' => $url])->safeUrl())->toBeNull();
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

it('rejects a dangerous image url', function () {
    $promo = SponsoredResult::factory()->make(['image_url' => 'javascript:alert(1)']);

    expect($promo->safeImageUrl())->toBeNull();
});

it('shows the host without a www prefix', function () {
    $promo = SponsoredResult::factory()->make(['url' => 'https://www.example.com/work']);

    expect($promo->displayHost())->toBe('example.com');
});
