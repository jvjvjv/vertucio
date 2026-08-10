<?php

namespace App\Search\Actions;

use App\Models\SearchResult;
use App\Search\Enums\SearchVertical;
use App\Search\Queries\LatestSearchResults;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

/**
 * The single decision point for how a search page section gets its page of
 * results: the daily-collected snapshot when nothing was typed, or a live,
 * cached, never-persisted search once a term is submitted.
 *
 * Pulled out of the search page's Livewire component so the branching is
 * directly unit-testable and reusable, matching this app's existing
 * Action-per-concern style (LiveSearch, StoreSearchSnapshot).
 */
final class PaginateSearchVertical
{
    public function __construct(
        private readonly LatestSearchResults $latestSearchResults,
        private readonly LiveSearch $liveSearch,
    ) {}

    /**
     * @return LengthAwarePaginator<int, SearchResult>
     */
    public function for(SearchVertical $vertical, int $perPage, string $pageName, ?string $term): LengthAwarePaginator
    {
        if (blank($term)) {
            return $this->latestSearchResults->paginate($vertical, $perPage, $pageName);
        }

        return $this->paginateLive($vertical, $perPage, $pageName, $term);
    }

    /**
     * Wraps each live SearchResultItem as an unsaved SearchResult so the
     * existing result-card components render it identically to a stored
     * row — never call save() or create() on it.
     *
     * The current page is resolved the same way Eloquent's own paginate()
     * resolves it: through Paginator::resolveCurrentPage(), which Livewire's
     * WithPagination trait binds to the component's tracked page state for
     * the current request. That keeps this action framework-consistent
     * without depending on a live Livewire component instance.
     *
     * @return LengthAwarePaginator<int, SearchResult>
     */
    private function paginateLive(SearchVertical $vertical, int $perPage, string $pageName, string $term): LengthAwarePaginator
    {
        $items = $this->liveSearch->for($vertical, $term)
            ->map(fn ($item) => new SearchResult([
                'position' => $item->position,
                'url' => $item->url,
                'title' => $item->title,
                'description' => $item->description,
                'source' => $item->source,
                'published_at' => $item->publishedAt,
                'image_url' => $item->imageUrl,
                'meta' => $item->meta,
            ]));

        $page = Paginator::resolveCurrentPage($pageName);
        $slice = $items->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            items: $slice,
            total: $items->count(),
            perPage: $perPage,
            currentPage: $page,
            options: ['pageName' => $pageName],
        );
    }
}
