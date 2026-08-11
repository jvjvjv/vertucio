<?php

use App\Mail\SponsorshipInquiryReceived;
use App\Models\SponsorshipInquiry;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('layouts.serp')] #[Title('Sponsor Us')] class extends Component
{
    /**
     * Keys must stay in sync with the `in:` rule on $monthlyBudget below —
     * there is no single source of truth because #[Validate] arguments must
     * be compile-time string literals.
     *
     * @var array<string, string>
     */
    public const BUDGET_TIERS = [
        'under_1000' => 'Under $1,000 / month',
        '1000_5000' => '$1,000 - $5,000 / month',
        '5000_10000' => '$5,000 - $10,000 / month',
        '10000_plus' => '$10,000+ / month',
    ];

    #[Validate('required|string|max:255')]
    public string $fullName = '';

    #[Validate('required|email|max:255')]
    public string $email = '';

    #[Validate('required|string|max:255')]
    public string $companyName = '';

    #[Validate('required|string|in:under_1000,1000_5000,5000_10000,10000_plus')]
    public string $monthlyBudget = '';

    #[Validate('required|string|max:2000')]
    public string $message = '';

    public bool $submitted = false;

    public function submit(): void
    {
        $this->validate();

        $inquiry = SponsorshipInquiry::create([
            'full_name' => $this->fullName,
            'email' => $this->email,
            'company_name' => $this->companyName,
            'monthly_budget' => $this->monthlyBudget,
            'message' => $this->message,
        ]);

        if (filled(config('sponsorship.notify_email'))) {
            Mail::to((string) config('sponsorship.notify_email'))->send(new SponsorshipInquiryReceived($inquiry));
        }

        $this->submitted = true;
    }
}; ?>

<div class="flex min-h-screen flex-col">
    <div class="border-b border-(--serp-line) px-4 py-4 md:px-8 xl:px-20">
        <x-site.search-bar label="Vertucio" :live="false" :logo="true" />
    </div>

    <main class="mx-auto flex w-full max-w-[680px] flex-1 flex-col gap-10 px-4 py-10 md:px-8 md:py-16">
        <div class="flex flex-col gap-3">
            <h1 class="text-2xl font-extrabold tracking-tight text-(--serp-text) md:text-3xl">
                {{ __('Sponsor Us') }}
            </h1>
            <p class="text-sm leading-relaxed text-(--serp-meta) md:text-[15px]">
                {{ __('Interested in sponsoring search results on Vertucio? Fill out the form below and our team will get back to you with rates and placement options.') }}
            </p>
        </div>

        @if ($submitted)
            <div class="rounded-lg border border-(--serp-line) bg-(--serp-raised) px-4 py-6 text-sm text-(--serp-text)">
                {{ __('Thanks — your sponsorship request has been received. We will get back to you soon.') }}
            </div>
        @else
            <form wire:submit="submit" class="flex flex-col gap-6">
                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div class="flex flex-col gap-2">
                        <label for="fullName" class="text-[13px] font-semibold text-(--serp-text)">{{ __('Full Name') }}</label>
                        <input
                            id="fullName"
                            type="text"
                            wire:model="fullName"
                            placeholder="{{ __('e.g. John Doe') }}"
                            class="h-11 rounded-lg border border-(--serp-line) bg-(--serp-surface) px-3.5 text-sm text-(--serp-text) placeholder:text-(--serp-meta) focus:outline-none"
                        >
                        @error('fullName') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="email" class="text-[13px] font-semibold text-(--serp-text)">{{ __('Email Address') }}</label>
                        <input
                            id="email"
                            type="email"
                            wire:model="email"
                            placeholder="{{ __('e.g. john@company.com') }}"
                            class="h-11 rounded-lg border border-(--serp-line) bg-(--serp-surface) px-3.5 text-sm text-(--serp-text) placeholder:text-(--serp-meta) focus:outline-none"
                        >
                        @error('email') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="companyName" class="text-[13px] font-semibold text-(--serp-text)">{{ __('Company Name') }}</label>
                        <input
                            id="companyName"
                            type="text"
                            wire:model="companyName"
                            placeholder="{{ __('e.g. Acme Corp') }}"
                            class="h-11 rounded-lg border border-(--serp-line) bg-(--serp-surface) px-3.5 text-sm text-(--serp-text) placeholder:text-(--serp-meta) focus:outline-none"
                        >
                        @error('companyName') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="monthlyBudget" class="text-[13px] font-semibold text-(--serp-text)">{{ __('Monthly Budget') }}</label>
                        <select
                            id="monthlyBudget"
                            wire:model="monthlyBudget"
                            class="h-11 rounded-lg border border-(--serp-line) bg-(--serp-surface) px-3.5 text-sm text-(--serp-text) focus:outline-none"
                        >
                            <option value="">{{ __('Select your budget scale') }}</option>
                            @foreach (self::BUDGET_TIERS as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('monthlyBudget') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <label for="message" class="text-[13px] font-semibold text-(--serp-text)">{{ __('Your Message / Placement Goals') }}</label>
                    <textarea
                        id="message"
                        wire:model="message"
                        rows="4"
                        placeholder="{{ __('Tell us about your brand and what search terms you are interested in sponsoring...') }}"
                        class="rounded-lg border border-(--serp-line) bg-(--serp-surface) px-3.5 py-3 text-sm text-(--serp-text) placeholder:text-(--serp-meta) focus:outline-none"
                    ></textarea>
                    @error('message') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>

                <button
                    type="submit"
                    class="flex h-12 items-center justify-center rounded-lg bg-blue-600 text-[15px] font-semibold text-white"
                >
                    {{ __('Submit Sponsorship Request') }}
                </button>
            </form>

            <div class="flex items-center gap-4">
                <div class="h-px flex-1 bg-(--serp-line)"></div>
                <span class="shrink-0 text-xs font-semibold uppercase tracking-wide text-(--serp-meta)">{{ __('Or Fast Track') }}</span>
                <div class="h-px flex-1 bg-(--serp-line)"></div>
            </div>

            <div class="flex flex-col items-center gap-4">
                <p class="text-[16px] font-bold text-(--serp-text)">{{ __('Pay via PayPal') }}</p>
                <p class="text-center text-[13px] text-(--serp-meta)">
                    {{ __('Already have a custom quote or booking ID? Secure your slot instantly below.') }}
                </p>
                <a
                    href="{{ config('sponsorship.paypal_url') }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="flex h-12 w-full items-center justify-center gap-2 rounded-lg border border-[#e2b21c] bg-[#ffc439] text-[15px] font-bold italic text-[#111]"
                >
                    {{ __('Pay with PayPal') }}
                </a>
            </div>
        @endif
    </main>

    <x-site.footer active="sponsor" />
</div>
