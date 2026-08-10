<?php

namespace App\Search\Contracts;

use App\Search\Data\SearchQuery;
use App\Search\Data\SearchResultSet;
use App\Search\Enums\SearchVertical;

interface SearchProvider
{
    /**
     * The stable identifier persisted on every snapshot this provider produces.
     */
    public function name(): string;

    public function supports(SearchVertical $vertical): bool;

    public function search(SearchQuery $query): SearchResultSet;
}
