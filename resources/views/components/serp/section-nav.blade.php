@props(['paginator', 'pageName'])

@if ($paginator->hasPages())
    <div class="flex items-center gap-2">
        <button
            type="button"
            wire:click="previousPage('{{ $pageName }}')"
            wire:loading.attr="disabled"
            @disabled($paginator->onFirstPage())
            aria-label="{{ __('Previous') }}"
            class="flex size-8 items-center justify-center rounded-full border border-(--serp-line) bg-(--serp-raised) hover:bg-(--serp-line) disabled:opacity-40 disabled:hover:bg-(--serp-raised)"
        >
            <img src="{{ asset('icons/chevron-left.svg') }}" alt="" class="size-4">
        </button>

        <button
            type="button"
            wire:click="nextPage('{{ $pageName }}')"
            wire:loading.attr="disabled"
            @disabled(! $paginator->hasMorePages())
            aria-label="{{ __('Next') }}"
            class="flex size-8 items-center justify-center rounded-full border border-(--serp-line) bg-(--serp-raised) hover:bg-(--serp-line) disabled:opacity-40 disabled:hover:bg-(--serp-raised)"
        >
            <img src="{{ asset('icons/chevron-right.svg') }}" alt="" class="size-4">
        </button>
    </div>
@endif
