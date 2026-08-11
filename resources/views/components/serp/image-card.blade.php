@props(['result'])

@php
    $href = $result->safeUrl();
    $thumbnail = $result->safeImageUrl();
    $label = $result->title ?? $result->displayHost();

    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($href)
        href="{{ $href }}"
        target="_blank"
        rel="noopener noreferrer nofollow"
    @endif
    class="group flex flex-col gap-2 xl:gap-2.5 focus-visible:rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--serp-link)"
>
    <div class="aspect-square overflow-hidden rounded-lg border border-(--serp-line) bg-(--serp-raised)">
        @if ($thumbnail)
            <img
                src="{{ $thumbnail }}"
                alt="{{ $label }}"
                loading="lazy"
                referrerpolicy="no-referrer"
                class="size-full object-cover transition group-hover:scale-[1.02]"
            >
        @endif
    </div>

    <p class="line-clamp-1 text-xs text-(--serp-meta)">
        {{ $label }}
    </p>
</{{ $tag }}>
