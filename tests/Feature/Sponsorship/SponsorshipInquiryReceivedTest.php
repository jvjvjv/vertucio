<?php

use App\Mail\SponsorshipInquiryReceived;
use App\Models\SponsorshipInquiry;

it('renders the inquiry details in the notification email', function () {
    $inquiry = SponsorshipInquiry::factory()->create([
        'full_name' => 'Jane Doe',
        'email' => 'jane@company.com',
        'company_name' => 'Acme Corp',
        'monthly_budget' => '1000_5000',
        'message' => 'Interested in the top slot.',
    ]);

    $mailable = new SponsorshipInquiryReceived($inquiry);

    $mailable->assertSeeInHtml('Jane Doe');
    $mailable->assertSeeInHtml('jane@company.com');
    $mailable->assertSeeInHtml('Acme Corp');
    $mailable->assertSeeInHtml('Interested in the top slot.');
});
