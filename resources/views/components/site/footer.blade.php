@props(['active' => null])

<footer class="border-t border-(--serp-line) bg-(--serp-surface)">
    <div class="mx-auto flex w-full max-w-[1280px] flex-col items-start gap-2 px-4 py-6 text-sm text-(--serp-meta) sm:flex-row sm:items-center sm:justify-between md:px-8 xl:px-20">
        <p>{{ __('© :year, :name', ['year' => now()->year, 'name' => config('app.name', 'Vertucio.com')]) }}</p>

        <nav class="flex items-center gap-6">
            <a
                href="{{ route('privacy') }}"
                class="hover:text-(--serp-text) {{ $active === 'privacy' ? 'font-semibold text-(--serp-text)' : '' }}"
            >
                {{ __('Privacy Policy') }}
            </a>
            <a
                href="{{ route('sponsor') }}"
                class="hover:text-(--serp-text) {{ $active === 'sponsor' ? 'font-semibold text-(--serp-text)' : '' }}"
            >
                {{ __('Sponsor Us') }}
            </a>
        </nav>
    </div>
</footer>
