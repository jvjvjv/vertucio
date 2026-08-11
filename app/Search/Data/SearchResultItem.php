<?php

namespace App\Search\Data;

use Illuminate\Support\Carbon;

final readonly class SearchResultItem
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public int $position,
        public string $url,
        public ?string $title = null,
        public ?string $description = null,
        public ?string $source = null,
        public ?Carbon $publishedAt = null,
        public ?string $imageUrl = null,
        public array $meta = [],
    ) {}
}
