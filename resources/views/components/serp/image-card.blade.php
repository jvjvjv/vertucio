@props(['result'])

@php
    $thumbnail = $result->image_url ?: data_get($result->meta, 'thumbnail');
@endphp

<a
    href="{{ $result->url }}"
    target="_blank"
    rel="noopener noreferrer nofollow"
    class="group flex flex-col gap-2 xl:gap-2.5 focus-visible:rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--serp-link)"
>
    <div class="aspect-square overflow-hidden rounded-lg border border-(--serp-line) bg-(--serp-raised)">
        @if ($thumbnail)
            <img
                src="{{ $thumbnail }}"
                alt="{{ $result->title }}"
                loading="lazy"
                referrerpolicy="no-referrer"
                class="size-full object-cover transition group-hover:scale-[1.02]"
            >
        @endif
    </div>

    <p class="line-clamp-1 text-xs text-(--serp-meta)">
        {{ $result->title ?? $result->displayHost() }}
    </p>
</a>
