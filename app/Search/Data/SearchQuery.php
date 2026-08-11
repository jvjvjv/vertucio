<?php

namespace App\Search\Data;

use App\Search\Enums\SearchVertical;

final readonly class SearchQuery
{
    public function __construct(
        public string $query,
        public SearchVertical $vertical,
        public int $limit = 20,
        public string $language = 'en',
        public string $country = 'US',
    ) {}
}
