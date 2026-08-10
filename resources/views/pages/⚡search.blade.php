<?php

use App\Search\Enums\SearchVertical;
use App\Search\Queries\LatestSearchResults;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.serp')] #[Title('Search Results')] class extends Component
{
    /**
     * Display caps taken from the design. The fetch layer stores
     * search.results_per_vertical (20) per snapshot; these govern how many of
     * those the page shows.
     */
    public const NEWS_LIMIT = 3;

    public const IMAGE_LIMIT = 6;

    public const RESULT_LIMIT = 4;

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $latest = app(LatestSearchResults::class);

        return [
            'query' => (string) config('search.query'),
            'lastCollectedAt' => $latest->lastCollectedAt(SearchVertical::Web),

            'news' => $latest->for(SearchVertical::News, self::NEWS_LIMIT),
            'images' => $latest->for(SearchVertical::Images, self::IMAGE_LIMIT),
            'results' => $latest->for(SearchVertical::Web, self::RESULT_LIMIT),

            'newsCollected' => $latest->wasCollected(SearchVertical::News),
            'imagesCollected' => $latest->wasCollected(SearchVertical::Images),
            'resultsCollected' => $latest->wasCollected(SearchVertical::Web),
        ];
    }
}; ?>

<div>
    <header class="border-b border-(--serp-line)">
        <div class="mx-auto flex w-full max-w-[1280px] flex-col gap-1 px-4 py-4 md:px-8 md:py-5 xl:px-20 xl:py-6">
            <p class="text-xl font-semibold tracking-tight md:text-2xl">
                {{ config('app.name', 'Vertucio.com') }}
            </p>

            <p class="text-sm text-(--serp-meta)">
                {{ __('Verified Results') }}
            </p>
        </div>
    </header>

    <main class="mx-auto flex w-full max-w-[1280px] flex-col gap-8 px-4 py-6 md:gap-10 md:px-8 md:py-8 xl:gap-12 xl:px-20 xl:py-10">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <p class="text-sm text-(--serp-meta)">
                {{ __('Showing results for') }}
                <span class="font-medium text-(--serp-text)">{{ $query }}</span>
            </p>

            @if ($lastCollectedAt)
                <p class="text-xs text-(--serp-meta)">
                    {{ __('Updated') }}
                    <time datetime="{{ $lastCollectedAt->toIso8601String() }}">
                        {{ $lastCollectedAt->diffForHumans() }}
                    </time>
                </p>
            @endif
        </div>

        <x-serp.section :heading="__('News')" :collected="$newsCollected" :is-empty="$news->isEmpty()">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($news as $result)
                    <x-serp.news-card :result="$result" :wire:key="'news-'.$result->id" />
                @endforeach
            </div>
        </x-serp.section>

        <x-serp.section :heading="__('Images')" :collected="$imagesCollected" :is-empty="$images->isEmpty()">
            <div class="grid grid-cols-3 gap-4 xl:grid-cols-6">
                @foreach ($images as $result)
                    <x-serp.image-card :result="$result" :wire:key="'image-'.$result->id" />
                @endforeach
            </div>
        </x-serp.section>

        <x-serp.section :heading="__('Results')" :collected="$resultsCollected" :is-empty="$results->isEmpty()">
            <div class="flex flex-col gap-6 md:gap-7 xl:gap-8">
                @foreach ($results as $result)
                    <x-serp.result-row :result="$result" :wire:key="'result-'.$result->id" />
                @endforeach
            </div>
        </x-serp.section>
    </main>
</div>
