<?php

use App\Models\SponsorshipInquiry;

it('is mass-assignable only for the sponsorship form fields', function () {
    $inquiry = SponsorshipInquiry::create([
        'full_name' => 'Jane Doe',
        'email' => 'jane@company.com',
        'company_name' => 'Acme Corp',
        'monthly_budget' => '1000_5000',
        'message' => 'We would like to sponsor the top result.',
    ]);

    expect($inquiry->fresh())
        ->full_name->toBe('Jane Doe')
        ->email->toBe('jane@company.com')
        ->company_name->toBe('Acme Corp')
        ->monthly_budget->toBe('1000_5000')
        ->message->toBe('We would like to sponsor the top result.');
});

it('has a working factory', function () {
    $inquiry = SponsorshipInquiry::factory()->create();

    expect($inquiry->exists)->toBeTrue()
        ->and($inquiry->email)->not->toBeEmpty();
});
