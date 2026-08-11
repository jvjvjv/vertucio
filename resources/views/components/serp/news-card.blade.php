@props(['result'])

@php
    $href = $result->safeUrl();
    $label = $result->title ?? $result->displayHost();
@endphp

<article class="flex h-full flex-col justify-between gap-3 rounded-xl border border-(--serp-line) bg-(--serp-surface) p-4">
    <h3 class="text-sm md:text-[15px] font-medium leading-tight text-(--serp-text)">
        @if ($href)
            <a
                href="{{ $href }}"
                target="_blank"
                rel="noopener noreferrer nofollow"
                class="line-clamp-2 underline-offset-2 hover:underline focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--serp-link)"
            >
                {{ $label }}
            </a>
        @else
            <span class="line-clamp-2">{{ $label }}</span>
        @endif
    </h3>

    <p class="flex items-center gap-1.5 text-xs text-(--serp-meta)">
        <span class="truncate">{{ $result->displayHost() }}</span>

        @if ($result->published_at)
            <span aria-hidden="true">&bull;</span>
            <time datetime="{{ $result->published_at->toIso8601String() }}" class="shrink-0">
                {{ $result->published_at->diffForHumans() }}
            </time>
        @endif
    </p>
</article>
