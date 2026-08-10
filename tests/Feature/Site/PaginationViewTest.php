<?php

use Illuminate\Pagination\LengthAwarePaginator;

it('renders numbered pill buttons for each page, using the default page name', function () {
    $paginator = new LengthAwarePaginator(
        items: range(1, 4),
        total: 12,
        perPage: 4,
        currentPage: 2,
    );

    $html = $paginator->links('vendor.pagination.serp')->render();

    expect($html)
        ->toContain("setPage(1, 'page')")
        ->toContain("setPage(3, 'page')")
        ->toContain("previousPage('page')")
        ->toContain("nextPage('page')");
});

it('renders wire:click calls using a custom page name', function () {
    $paginator = new LengthAwarePaginator(
        items: range(1, 4),
        total: 12,
        perPage: 4,
        currentPage: 1,
        options: ['pageName' => 'news'],
    );

    $html = $paginator->links('vendor.pagination.serp', ['pageName' => 'news'])->render();

    expect($html)
        ->toContain("setPage(2, 'news')")
        ->toContain("nextPage('news')");
});

it('renders no navigation when there is only one page', function () {
    $paginator = new LengthAwarePaginator(items: [1, 2], total: 2, perPage: 4, currentPage: 1);

    expect(trim($paginator->links('vendor.pagination.serp')->render()))->toBe('');
});
