@props(['result'])

<article class="flex h-full flex-col justify-between gap-3 rounded-xl border border-(--serp-line) bg-(--serp-surface) p-4">
    <h3 class="text-sm md:text-[15px] font-medium leading-tight text-(--serp-text)">
        <a
            href="{{ $result->url }}"
            target="_blank"
            rel="noopener noreferrer nofollow"
            class="line-clamp-2 underline-offset-2 hover:underline focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--serp-link)"
        >
            {{ $result->title ?? $result->displayHost() }}
        </a>
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
