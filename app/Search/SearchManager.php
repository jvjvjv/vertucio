<?php

namespace App\Search;

use App\Search\Contracts\SearchProvider;
use App\Search\Data\SearchQuery;
use App\Search\Data\SearchResultSet;
use App\Search\Enums\SearchVertical;
use Illuminate\Support\Collection;

final class SearchManager
{
    /**
     * @param  array<int, SearchProvider>  $providers
     */
    public function __construct(private array $providers) {}

    /**
     * @return Collection<int, SearchProvider>
     */
    public function providersFor(SearchVertical $vertical): Collection
    {
        return collect($this->providers)
            ->filter(static fn (SearchProvider $provider): bool => $provider->supports($vertical))
            ->values();
    }

    /**
     * Every provider crossed with the verticals it supports, in registration order.
     *
     * @return Collection<int, array{provider: SearchProvider, vertical: SearchVertical}>
     */
    public function pairs(): Collection
    {
        return collect($this->providers)->flatMap(
            static fn (SearchProvider $provider): array => collect(SearchVertical::cases())
                ->filter(static fn (SearchVertical $vertical): bool => $provider->supports($vertical))
                ->map(static fn (SearchVertical $vertical): array => [
                    'provider' => $provider,
                    'vertical' => $vertical,
                ])
                ->all()
        )->values();
    }

    /**
     * Exceptions propagate — callers decide whether a failed pair is fatal.
     */
    public function fetch(SearchProvider $provider, SearchVertical $vertical, string $query): SearchResultSet
    {
        return $provider->search(new SearchQuery(
            query: $query,
            vertical: $vertical,
            limit: (int) config('search.results_per_vertical', 20),
            language: (string) config('search.locale.language', 'en'),
            country: (string) config('search.locale.country', 'US'),
        ));
    }
}
