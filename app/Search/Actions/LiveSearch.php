<?php

namespace App\Search\Actions;

use App\Search\Contracts\SearchProvider;
use App\Search\Data\SearchResultItem;
use App\Search\Enums\SearchVertical;
use App\Search\Exceptions\SearchProviderException;
use App\Search\SearchManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * An on-demand search for whatever the visitor typed into the search bar.
 *
 * Unlike the scheduled fetch, this never writes to search_snapshots or
 * search_results — those tables are exclusively the daily-collected log for
 * the one monitored query (config('search.query')). A typed term is a real,
 * live call through the same provider layer, cached so the same query
 * doesn't repeatedly hit paid APIs, but the result is never persisted.
 */
final class LiveSearch
{
    private const CACHE_MINUTES = 15;

    public function __construct(private readonly SearchManager $manager)
    {
        //
    }

    /**
     * @return Collection<int, SearchResultItem>
     */
    public function for(SearchVertical $vertical, string $query): Collection
    {
        $key = sprintf('search:live:%s:%s', $vertical->value, mb_strtolower(trim($query)));

        /** @var Collection<int, SearchResultItem> $items */
        $items = Cache::remember($key, now()->addMinutes(self::CACHE_MINUTES), function () use ($vertical, $query): Collection {
            return $this->manager->providersFor($vertical)
                ->flatMap(function (SearchProvider $provider) use ($vertical, $query): array {
                    try {
                        return $this->manager->fetch($provider, $vertical, $query)->items;
                    } catch (SearchProviderException) {
                        return [];
                    }
                })
                ->values();
        });

        return $items;
    }
}
