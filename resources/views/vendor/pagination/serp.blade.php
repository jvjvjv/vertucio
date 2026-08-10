@php
    $pageName ??= 'page';
@endphp

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center gap-2">
        @if ($paginator->onFirstPage())
            <span class="flex size-8 items-center justify-center rounded-full border border-(--serp-line) bg-(--serp-raised) opacity-40">
                <img src="{{ asset('icons/chevron-left.svg') }}" alt="" class="size-4">
            </span>
        @else
            <button
                type="button"
                wire:click="previousPage('{{ $pageName }}')"
                wire:loading.attr="disabled"
                class="flex size-8 items-center justify-center rounded-full border border-(--serp-line) bg-(--serp-raised) hover:bg-(--serp-line)"
            >
                <img src="{{ asset('icons/chevron-left.svg') }}" alt="{{ __('Previous') }}" class="size-4">
            </button>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="flex size-8 items-center justify-center rounded-full border border-(--serp-line) bg-(--serp-raised) text-[13px] font-medium text-(--serp-meta)">
                    {{ $element }}
                </span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="flex size-8 items-center justify-center rounded-full bg-zinc-900 text-[13px] font-semibold text-white">
                            {{ $page }}
                        </span>
                    @else
                        <button
                            type="button"
                            wire:click="setPage({{ $page }}, '{{ $pageName }}')"
                            class="flex size-8 items-center justify-center rounded-full border border-(--serp-line) bg-(--serp-raised) text-[13px] font-medium text-(--serp-meta) hover:bg-(--serp-line)"
                        >
                            {{ $page }}
                        </button>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <button
                type="button"
                wire:click="nextPage('{{ $pageName }}')"
                wire:loading.attr="disabled"
                class="flex size-8 items-center justify-center rounded-full border border-(--serp-line) bg-(--serp-raised) hover:bg-(--serp-line)"
            >
                <img src="{{ asset('icons/chevron-right.svg') }}" alt="{{ __('Next') }}" class="size-4">
            </button>
        @else
            <span class="flex size-8 items-center justify-center rounded-full border border-(--serp-line) bg-(--serp-raised) opacity-40">
                <img src="{{ asset('icons/chevron-right.svg') }}" alt="" class="size-4">
            </span>
        @endif
    </nav>
@endif
