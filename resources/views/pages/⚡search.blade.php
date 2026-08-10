<?php

use App\Models\SponsoredResult;
use App\Search\Actions\PaginateSearchVertical;
use App\Search\Enums\SearchVertical;
use App\Search\Queries\LatestSearchResults;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.serp')] #[Title('Search Results')] class extends Component
{
    use WithPagination;

    /**
     * Per-page sizes taken from the design's original display caps. These
     * are deliberately modest for now — raise them directly here once the
     * live/cached search path has proven itself in production.
     */
    public const NEWS_PER_PAGE = 3;

    public const IMAGES_PER_PAGE = 6;

    public const SPONSORED_PER_PAGE = 3;

    public const RESULTS_PER_PAGE = 4;

    #[Url(as: 'q')]
    public string $term = '';

    /**
     * Any change to the bound term — whether typed live or synced on a
     * deferred submit — should not leave the user stranded on a page number
     * that no longer exists for the new result set. Every section resets
     * independently since each tracks its own page.
     */
    public function updatedTerm(): void
    {
        $this->resetPage('news');
        $this->resetPage('images');
        $this->resetPage('sponsored');
        $this->resetPage('results');
    }

    public function search(): void
    {
        $this->updatedTerm();
    }

    #[Computed]
    public function news(): LengthAwarePaginator
    {
        return $this->paginatedVertical(SearchVertical::News, self::NEWS_PER_PAGE, 'news');
    }

    #[Computed]
    public function images(): LengthAwarePaginator
    {
        return $this->paginatedVertical(SearchVertical::Images, self::IMAGES_PER_PAGE, 'images');
    }

    #[Computed]
    public function results(): LengthAwarePaginator
    {
        return $this->paginatedVertical(SearchVertical::Web, self::RESULTS_PER_PAGE, 'results');
    }

    #[Computed]
    public function sponsored(): LengthAwarePaginator
    {
        return SponsoredResult::active()
            ->when(filled($this->term), fn ($query) => $query->matching($this->term))
            ->orderBy('position')
            ->orderBy('id')
            ->paginate(self::SPONSORED_PER_PAGE, ['*'], 'sponsored');
    }

    /**
     * With no term typed, every section reads the daily-collected snapshot
     * for the monitored query, exactly as before. Once a term is submitted,
     * News/Images/Results switch to a live, cached, non-persisted search —
     * see PaginateSearchVertical and the LiveSearch action it delegates to.
     */
    private function paginatedVertical(SearchVertical $vertical, int $perPage, string $pageName): LengthAwarePaginator
    {
        return app(PaginateSearchVertical::class)->for($vertical, $perPage, $pageName, $this->term);
    }

    public function newsCollected(): bool
    {
        return filled($this->term) || app(LatestSearchResults::class)->wasCollected(SearchVertical::News);
    }

    public function imagesCollected(): bool
    {
        return filled($this->term) || app(LatestSearchResults::class)->wasCollected(SearchVertical::Images);
    }

    public function resultsCollected(): bool
    {
        return filled($this->term) || app(LatestSearchResults::class)->wasCollected(SearchVertical::Web);
    }

    public function lastCollectedAt(): ?Carbon
    {
        return app(LatestSearchResults::class)->lastCollectedAt(SearchVertical::Web);
    }
}; ?>

<div class="flex min-h-screen flex-col">
    <div class="border-b border-(--serp-line) px-4 py-4 md:px-8 xl:px-20">
        <x-site.search-bar label="{{ config('search.query') }}" :live="true" :logo="true" />
    </div>

    <main class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-8 px-4 py-6 md:gap-10 md:px-8 md:py-8 xl:gap-12 xl:px-20 xl:py-10">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <p class="text-sm text-(--serp-meta)">
                {{ __('Showing results for') }}
                <span class="font-medium text-(--serp-text)">{{ filled($term) ? $term : config('search.query') }}</span>
            </p>

            @if (blank($term) && $this->lastCollectedAt())
                <p class="text-xs text-(--serp-meta)">
                    {{ __('Updated') }}
                    <time datetime="{{ $this->lastCollectedAt()->toIso8601String() }}">
                        {{ $this->lastCollectedAt()->diffForHumans() }}
                    </time>
                </p>
            @endif
        </div>

        <x-serp.section :heading="__('News')" :collected="$this->newsCollected()" :is-empty="$this->news->isEmpty()">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->news as $result)
                    <x-serp.news-card :result="$result" :wire:key="'news-'.($result->id ?? md5($result->url))" />
                @endforeach
            </div>

            <div class="pt-4">{{ $this->news->links('vendor.pagination.serp', ['pageName' => 'news']) }}</div>
        </x-serp.section>

        <x-serp.section :heading="__('Images')" :collected="$this->imagesCollected()" :is-empty="$this->images->isEmpty()">
            <div class="grid grid-cols-3 gap-4 xl:grid-cols-6">
                @foreach ($this->images as $result)
                    <x-serp.image-card :result="$result" :wire:key="'image-'.($result->id ?? md5($result->url))" />
                @endforeach
            </div>

            <div class="pt-4">{{ $this->images->links('vendor.pagination.serp', ['pageName' => 'images']) }}</div>
        </x-serp.section>

        @if ($this->sponsored->isNotEmpty())
            <div class="flex flex-col gap-6 md:gap-7 xl:gap-8">
                @foreach ($this->sponsored as $result)
                    <x-serp.sponsored-row :result="$result" :wire:key="'sponsored-'.$result->id" />
                @endforeach

                <div>{{ $this->sponsored->links('vendor.pagination.serp', ['pageName' => 'sponsored']) }}</div>
            </div>
        @endif

        <x-serp.section :heading="__('Results')" :collected="$this->resultsCollected()" :is-empty="$this->results->isEmpty()">
            <div class="flex flex-col gap-6 md:gap-7 xl:gap-8">
                @foreach ($this->results as $result)
                    <x-serp.result-row :result="$result" :wire:key="'result-'.($result->id ?? md5($result->url))" />
                @endforeach
            </div>

            <div class="pt-4">{{ $this->results->links('vendor.pagination.serp', ['pageName' => 'results']) }}</div>
        </x-serp.section>
    </main>

    <x-site.footer />
</div>
