@props([
    'label',
    'live' => false,
    'logo' => false,
])

<div class="flex w-full items-center gap-4">
    @if ($logo)
        <a href="{{ route('search') }}" data-brand-logo class="flex shrink-0 items-center gap-1">
            <span class="text-[22px] font-extrabold tracking-tight text-(--serp-text)">{{ __('Vertucio') }}</span>
            <span class="size-1.5 rounded-full bg-blue-600" aria-hidden="true"></span>
        </a>
    @endif

    <form
        @if ($live)
            wire:submit="search"
        @else
            method="GET"
            action="{{ route('search') }}"
        @endif
        class="flex flex-1 items-center gap-3"
    >
        <div class="flex h-11 flex-1 items-center gap-2 rounded-full border border-(--serp-line) bg-(--serp-raised) px-3">
            <span class="shrink-0 text-sm font-bold text-(--serp-text)">{{ $label }}</span>

            <img src="{{ asset('icons/arrow-left-right.svg') }}" alt="" class="size-4 shrink-0">

            <input
                type="text"
                @if ($live)
                    wire:model="term"
                @else
                    name="q"
                @endif
                placeholder="{{ __('Search...') }}"
                class="min-w-0 flex-1 bg-transparent text-sm text-(--serp-text) placeholder:text-(--serp-meta) focus:outline-none"
            >
        </div>

        <button
            type="submit"
            class="flex h-11 shrink-0 items-center gap-2 rounded-full bg-zinc-900 px-3.5 text-sm font-semibold text-white"
        >
            <img src="{{ asset('icons/search.svg') }}" alt="" class="size-[18px]">
            <span>{{ __('Search') }}</span>
        </button>
    </form>
</div>
