<?php

namespace App\Search\Data;

use App\Search\Enums\SearchVertical;
use Illuminate\Support\Carbon;

final readonly class SearchResultSet
{
    /**
     * @param  array<int, SearchResultItem>  $items
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $provider,
        public SearchVertical $vertical,
        public string $query,
        public Carbon $fetchedAt,
        public array $items,
        public array $meta = [],
    ) {}

    public function count(): int
    {
        return count($this->items);
    }
}
