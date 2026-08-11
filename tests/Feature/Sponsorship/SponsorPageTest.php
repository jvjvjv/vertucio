<?php

use App\Mail\SponsorshipInquiryReceived;
use App\Models\SponsorshipInquiry;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    config(['sponsorship.notify_email' => 'owner@vertucio.com']);
});

it('renders the sponsor us form for guests', function () {
    $this->get(route('sponsor'))
        ->assertOk()
        ->assertSee('Sponsor Us')
        ->assertSee('Submit Sponsorship Request');
});

it('creates an inquiry and emails a notification on valid submission', function () {
    Mail::fake();

    Livewire::test('pages::sponsor')
        ->set('fullName', 'Jane Doe')
        ->set('email', 'jane@company.com')
        ->set('companyName', 'Acme Corp')
        ->set('monthlyBudget', '1000_5000')
        ->set('message', 'Interested in the top slot.')
        ->call('submit')
        ->assertSet('submitted', true);

    expect(SponsorshipInquiry::query()->count())->toBe(1);

    $inquiry = SponsorshipInquiry::sole();

    expect($inquiry->full_name)->toBe('Jane Doe')
        ->and($inquiry->monthly_budget)->toBe('1000_5000');

    Mail::assertSent(
        SponsorshipInquiryReceived::class,
        fn (SponsorshipInquiryReceived $mail): bool => $mail->hasTo('owner@vertucio.com')
            && $mail->inquiry->is($inquiry),
    );
});

it('rejects an invalid submission and creates nothing', function () {
    Mail::fake();

    Livewire::test('pages::sponsor')
        ->set('fullName', '')
        ->set('email', 'not-an-email')
        ->set('companyName', 'Acme Corp')
        ->set('monthlyBudget', 'not_a_real_tier')
        ->set('message', 'Interested.')
        ->call('submit')
        ->assertHasErrors(['fullName', 'email', 'monthlyBudget']);

    expect(SponsorshipInquiry::query()->count())->toBe(0);
    Mail::assertNothingSent();
});

it('links the express sponsorship button to the configured PayPal URL', function () {
    config(['sponsorship.paypal_url' => 'https://paypal.me/vertucio-test']);

    $this->get(route('sponsor'))
        ->assertOk()
        ->assertSee('https://paypal.me/vertucio-test');
});
