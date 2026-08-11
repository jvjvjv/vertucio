@props([
    'heading',
    'collected' => false,
    'isEmpty' => true,
])

<section {{ $attributes->class('flex flex-col gap-3 md:gap-4') }}>
    <div class="flex items-center justify-between gap-2">
        <h2 class="text-[13px] md:text-sm font-medium tracking-wide uppercase text-(--serp-meta)">
            {{ $heading }}
        </h2>

        @unless ($isEmpty)
            {{ $nav ?? '' }}
        @endunless
    </div>

    @if ($isEmpty)
        <p class="rounded-lg border border-dashed border-(--serp-line) bg-(--serp-raised) px-4 py-6 text-sm text-(--serp-meta)">
            @if ($collected)
                {{-- The fetch ran and the engine genuinely returned nothing. --}}
                {{ __('No results found for this search.') }}
            @else
                {{-- No snapshot exists: the fetch never ran or the provider failed. --}}
                {{ __('Not collected yet. Results appear after the next scheduled fetch.') }}
            @endif
        </p>
    @else
        {{ $slot }}
    @endif
</section>
