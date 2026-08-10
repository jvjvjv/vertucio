@props(['result'])

@php
    $href = $result->safeUrl();
@endphp

<article class="flex flex-col gap-1">
    <p class="truncate text-xs text-(--serp-url) md:text-[13px]">
        {{ $result->displayHost() }}
    </p>

    <div class="flex flex-wrap items-baseline gap-2">
        <span class="shrink-0 text-[11px] font-semibold uppercase tracking-wide text-(--serp-meta)">
            {{ __('Sponsored') }}
        </span>

        <h3 class="text-lg leading-snug md:text-xl">
            @if ($href)
                <a
                    href="{{ $href }}"
                    target="_blank"
                    rel="sponsored noopener noreferrer nofollow"
                    class="line-clamp-2 text-(--serp-link) underline-offset-2 hover:underline focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--serp-link)"
                >
                    {{ $result->title }}
                </a>
            @else
                <span class="line-clamp-2 text-(--serp-text)">{{ $result->title }}</span>
            @endif
        </h3>
    </div>

    @if (filled($result->description))
        <p class="line-clamp-2 text-sm leading-relaxed text-(--serp-meta)">
            {{ $result->description }}
        </p>
    @endif
</article>
