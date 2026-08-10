<x-layouts.serp>
    <x-slot:title>{{ __('Privacy Policy') }}</x-slot:title>

    <div class="flex min-h-screen flex-col">
        <div class="border-b border-(--serp-line) px-4 py-4 md:px-8 xl:px-20">
            <x-site.search-bar label="{{ config('search.query') }}" :live="false" :logo="true" />
        </div>

        <main class="mx-auto flex w-full max-w-[720px] flex-1 flex-col gap-6 px-4 py-10 md:px-8">
            <h1 class="text-2xl font-extrabold tracking-tight text-(--serp-text) md:text-3xl">
                {{ __('Privacy Policy') }}
            </h1>

            <p class="text-sm text-(--serp-meta)">
                {{ __('Last updated: :date', ['date' => now()->toFormattedDateString()]) }}
            </p>

            <div class="flex flex-col gap-4 text-sm leading-relaxed text-(--serp-text)">
                <p>
                    {{ __('Vertucio.com has no user accounts and does not track visitors. Browsing the search results page collects nothing beyond ordinary web server logs.') }}
                </p>

                <p>
                    {{ __('The only personal information this site collects is what you submit through the Sponsor Us form: your name, email address, company name, budget range, and message. That information is used solely to follow up about your sponsorship inquiry, and is stored so we can review it and get back to you.') }}
                </p>

                <p>
                    {{ __('To request that your sponsorship inquiry be deleted, email us using the address on the Sponsor Us page and reference the email you submitted it with.') }}
                </p>
            </div>
        </main>

        <x-site.footer active="privacy" />
    </div>
</x-layouts.serp>
