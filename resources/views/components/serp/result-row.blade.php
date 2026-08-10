@props(['result'])

@php
    $href = $result->safeUrl();
    $label = $result->title ?? $result->displayHost();
@endphp

<article class="flex flex-col gap-1">
    <p class="truncate text-xs md:text-[13px] text-(--serp-url)">
        {{ $result->displayHost() }}
    </p>

    <h3 class="text-lg md:text-xl leading-snug">
        @if ($href)
            <a
                href="{{ $href }}"
                target="_blank"
                rel="noopener noreferrer nofollow"
                class="line-clamp-2 text-(--serp-link) underline-offset-2 hover:underline focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--serp-link)"
            >
                {{ $label }}
            </a>
        @else
            <span class="line-clamp-2 text-(--serp-text)">{{ $label }}</span>
        @endif
    </h3>

    @if (filled($result->description))
        <p class="line-clamp-2 text-sm leading-relaxed text-(--serp-meta)">
            {{ $result->description }}
        </p>
    @endif
</article>
