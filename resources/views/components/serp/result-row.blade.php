@props(['result'])

<article class="flex flex-col gap-1">
    <p class="truncate text-xs md:text-[13px] text-(--serp-url)">
        {{ $result->displayHost() }}
    </p>

    <h3 class="text-lg md:text-xl leading-snug">
        <a
            href="{{ $result->url }}"
            target="_blank"
            rel="noopener noreferrer nofollow"
            class="line-clamp-2 text-(--serp-link) underline-offset-2 hover:underline focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--serp-link)"
        >
            {{ $result->title ?? $result->displayHost() }}
        </a>
    </h3>

    @if (filled($result->description))
        <p class="line-clamp-2 text-sm leading-relaxed text-(--serp-meta)">
            {{ $result->description }}
        </p>
    @endif
</article>
